<?php
namespace src\functions;

use DateTime;
use phpseclib3\Crypt\EC\Curves\prime192v1;
use src\exceptions\WebhookReadErrorException;
use stdClass;


class OrdersFunction{

    public static $current;

    public static function setCurrent(){
        return self::$current = date('d/m/Y H:i:s');
    }

    //processa o pedido do CRM para o ERP
    public static function processOrdersCrmToErp($args, $ploomesServices, $formatter, $action):array
    {

        self::setCurrent();
        $entity = 'Venda';
        $tenancyId = $args['Tenancy']['tenancies']['id'];
        $message = [];
        $decoded = $args['body'];
        
        
       
        //Cria o objeto de order e seta o id da Venda no plooomes
        $order = self::createNewOrder($decoded);
        
        //Array de detalhes do item da venda ***Obrigatório (Busca a venda original no Ploomes com campos expandidos)
        $orderArray = self::getDetailsOrderFromPloomes($order, $ploomesServices);
        
        //busca o contact do ploomes **obrigatório (Cliente da venda) 
        $contact = $orderArray['Contact'];

        //pega as informações dos aplicativos
        $bases = $args['Tenancy']['erp_bases'];
                
        //busca o Id do cliente no contact do ploomes
        $order->ids = self::getIdCustomerErpFromContactPloomes($contact['OtherProperties'], $bases, 'Cliente', $tenancyId);
        
        //busca os campos customizáveis **Obrigatório (busca com campos simples como no otherProperties do pedido do webook)
        $customFields = CustomFieldsFunction::compareCustomFields($decoded['New']['OtherProperties'],$tenancyId, $entity);
        if(empty($customFields)) {
            throw new WebhookReadErrorException('Erro ao montar pedido pra enviar ao omie: Não foram encontrados campos personalizados do Ploomes', 500);
        }
      
        //pega os campos customizáveis da entidade venda e os campos customizados da venda atual compara pra pegar as opções de base de faturamento e seu nome
        $bf = self::setBaseFaturamento($entity, $customFields);
        $bf['erp_name'] = $args['Tenancy']['tenancies']['erp_name'];
  
        //Seta a base de faturamento ***Obrigatório
        ($bf) ? $order->baseFaturamento = $bf : throw new WebhookReadErrorException('Erro ao montar pedido pra enviar ao omie: não foi encontrada a empresa de faturamento do pedido', 500);
        
        // print_r($order);
        // exit;
        //seta o id do cliente do omie para a base de faturamento de destino
        self::setIdClienteErp($order);
 
        //Monta Os detalhes do Omie ***Obrigatório (Monta o Objeto do ERP de destino)
        $erp = self::createErpObjectSetDetailsOrder($bases, $bf);
        //busca o código do vendedor pelo email do ploomes, se não encotrar retorna nulo
        $order->codVendedorErp = self::getIdVendedorErpFromMail($erp, $orderArray['Owner']['Email'], $formatter); 
        
        //observações da nota
        $order->description = (isset($orderArray['Description']) ? htmlspecialchars_decode(strip_tags($orderArray['Description'])): null);  
        
        
        
        

        //SendExternalKey do id dos itens no omie registrados no ploomes *** Obrigatório
        $idItemErp = self::setIdItemErp($erp);    
        
        // print_r($idItemErp);
        // exit;
        
        //seta informações adicionais(pega as informações como modalidade de frete, projeto etc de other properties pela sendExternalKey)
        self::setAdditionalOrderProperties($order, $orderArray, $customFields, $ploomesServices);
        // print_r($order);
        // exit;
        

        $arrayIsServices =[];
        //tipo da venda (is service) ***Obrigatótio
        $arrayIsServices['isService'] = self::isService($order);
        //é um contrato de recorrência
        $arrayIsServices['isRecurrence'] = $order->recurrence;

        //separa os produtos dos serviços
        $contentOrder = $formatter->distinctProductsServicesFromOmieOrders($orderArray, $arrayIsServices, $idItemErp, $order);

        //insere o projeto e retorna o id
        (isset($order->projeto) || $order->projeto != null) ? $order->codProjeto = $formatter->insertProjectOmie($erp, $order) : $order->codProjeto = null;
        
        //se o array de produtos tiver conteúdo significa quee é uma venda de produto se não de serviço pra incluir no modulo certo do omie        
        if($arrayIsServices['isService'] === false)
        {
            //monta estrutura Omie para a requisição de pedidos
            $order->contentOrder = $contentOrder['products'];
            $json = $formatter->createOrder($order, $erp);

            /**
             * Aqui vamos implementar o metodo que coloca o json na fila do rabbitMQ para o consumer enviar ao omie
             */
                        
            //envia o pedido ao Omie e retorna a mensagem de resposta
            $message = self::createRequestNewOrder($erp, $order, $json, $formatter, $ploomesServices, $arrayIsServices);     

        }
        elseif(isset($contentOrder['services']) && !empty($contentOrder['services']))
        {
            $order->contentOrder = $contentOrder['services'];
            // print_r($order->contentOrder);
            // exit;
            if($arrayIsServices['isRecurrence']){
                //monta estrutura de venda recorrente para a requisição de OS
                $json = $formatter->createContract($order, $erp);
            }else{
                //monta estrutura Omie para a requisição de OS
                $json = $formatter->createOS($order, $erp);
            }
            //envia a OS ao Omie e retorna a mensagem de resposta
            $message = self::createRequestNewOrder($erp, $order, $json, $formatter, $ploomesServices, $arrayIsServices);

        }
   
        return $message;

    }

    //processa o pedido do ERP para o CRM
    public static function processOrderErpToCrm($args, $ploomesServices, $formatter, $action):array
    {        
        $message = [];
        $current = date('d/m/Y H:i:s');
        

        if($action['action'] === 'venEtapaAlterada'){
           
            $decoded = $args['body'];
            $message[] = self::alterOrderStage($ploomesServices, $decoded);
            

            return $message;

        }

        if($idContact !== null || $action['action'] !== 'create')
        {
            if($ploomesServices->updatePloomesContact($json, $idContact)){

                $message['success'] = 'Integração concluída com sucesso! Cliente Ploomes id: '.$idContact.' alterado no Ploomes CRM com sucesso em: '.$current;

                return $message;
            
                // $msg=[
                //     'ContactId' => $idContact,
                //     'Content' => 'Cliente '.$contact->nomeFantasia.' alterado no Omie ERP na base: '.$contact->baseFaturamentoTitle.' via Bicorp Integração',
                //     'Title' => 'Cliente Alterado'
                // ];
                
                // //cria uma interação no card
                // ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$idContact.' alterado no Ploomes CRM ('.$contact->baseFaturamentoTitle.') e mensagem enviada com sucesso em: '.$current : $message = 'Integração concluída com sucesso! Cliente Ploomes id: '.$idContact.' alterado no PLoomes CRM, porém não foi possível gravar a mensagem no card do cliente do Ploomes: '.$current;
            }

            throw new WebhookReadErrorException('Erro ao alterar o cliente Ploomes id: '.$idContact.' em: '.$current, 500);    
        }
        else
        {

            if($ploomesServices->createPloomesContact($json)){
                $message['success'] = 'Cliente '.$contact->nomeFantasia.' Cadastrado no Ploomes CRM com sucesso! Data: '.$current;
                return $message;
            }
            
            throw new WebhookReadErrorException('Erro ao cadastrar o cliente Ploomes id: '.$idContact.' em: '.$current, 500);
        }
    }
 

     /* REPARTINDO NEW ORDER EM FUNCTIONS */
     private static function createNewOrder(array $decoded):object
     {
 
         $order = new stdClass();
         $order->id = $decoded['New']['Id']; //Id da order
 
         return $order;
     }
 
     private static function getIdCustomerErpFromContactPloomes(array $otherProperties, $bases, $entity, $tenancyId):array
     {      
         $ids = [];
         $contacOp = CustomFieldsFunction::compareCustomFieldsFromOtherProperties($otherProperties, $entity, $tenancyId);
      
         foreach($contacOp as $k => $v){
             foreach($bases as $base){
                 $baseName = strtolower($base['app_name']);
                 switch ($k) {
                     case "bicorp_api_id_cliente_erp_{$baseName}_out":
                         $ids[$baseName] = $v ?? null;
                         break;
                     case "bicorp_api_id_cliente_erp_{$baseName}_out":
                         $ids[$baseName] = $v ?? null;
                         break;
                     case "bicorp_api_id_cliente_erp_{$baseName}_out":
                         $ids[$baseName] = $v ?? null;
                         break;          
                 }
             }
         }
         
         return $ids;
     }
 
     private static function setIdClienteErp(object $order):void{
 
         $baseFaturamentoTitle = null;
 
         if (!isset($order->ids) || !is_array($order->ids) || empty($order->ids)) {
             throw new WebhookReadErrorException('Erro ao montar pedido para enviar ao Omie: Não foi encontrado nenhum Id do omie no cliente.');
         }
         if(!isset($order->baseFaturamento) || !is_array($order->baseFaturamento) || empty($order->baseFaturamento)){
             throw new WebhookReadErrorException('Erro ao montar pedido para enviar ao Omie: Base de Faturamento Inexistente.', 500);
         }
        //  print_r($order);
        //  exit;
         $baseFaturamentoTitle = mb_strtolower($order->baseFaturamento['Name']);
         $order->idClienteOmie = $order->ids[mb_strtolower($baseFaturamentoTitle)] ?? null;
         
 
         if (!isset($order->idClienteOmie) || $order->idClienteOmie === null) {
             throw new WebhookReadErrorException('Erro ao montar pedido para enviar ao Omie: Não foi encontrado Id do Omie para  o aplicativo de destino escolhido para faturar o pedido [ '. $baseFaturamentoTitle .' ].');
         }   
 
     }
 
     private static function setBaseFaturamento(string $entity, array $customFields):array
     {
         $bf =[];
         
         $orderCustomFields = CustomFieldsFunction::getCustomFieldsByEntity($entity);
         foreach($orderCustomFields as $ocf){
             if($ocf['SendExternalKey'] == 'bicorp_api_base_faturamento_out'){
                 foreach($ocf['Options'] as $opt){
                     if($opt['Id'] == $customFields['bicorp_api_base_faturamento_out']){
                         $bf['Name'] = $opt['Name'];
                         $bf['Id'] = $opt['Id'];
                     }  
                 }
             }
         }
 
         return $bf;
 
     }
 
     private static function createErpObjectSetDetailsOrder(array $bases, array $bf):object
     {
         $erp = new stdClass();
         switch(strtolower($bf['erp_name'])){
            case 'omie':
                foreach($bases as $base){
                    if(strtolower($base['app_name']) == strtolower($bf['Name'])){
                        $erp->baseFaturamentoTitle = $base['app_name'];
                        $erp->ncc = $base['ncc'];
                        $erp->appSecret = $base['app_secret'];
                        $erp->appKey = $base['app_key'];
                    }
                }
            break;
            case 'nasajon':
                foreach($bases as $base){
                    if(strtolower($base['app_name']) == strtolower($bf['Name'])){
                        $erp->baseFaturamentoTitle = $base['app_name'];
                        $erp->client_id = $base['client_id'];
                        $erp->client_secret = $base['client_secret'];
                        $erp->access_token = $base['access_token'];
                        $erp->refresh_token = $base['refresh_token'];
                        $erp->email = $base['email'];
                        $erp->password = $base['password'];
                    }
                }
            break;
         }
         
         
         if(empty($erp)){
             {
                 throw new WebhookReadErrorException('Erro ao montar pedido para enviar ao Omie: Base de faturamento inexistente, não foi possível montar dados do App Omie', 500);
             }
         }
         return $erp;
     }
 
    private static function setAdditionalOrderProperties(object $order, array $orderArray, array $customFields, object $ploomesServices):void
     {
         
        $order->orderNumber = $orderArray['OrderNumber']; // numero da venda
        $order->dealId = $orderArray['DealId']; // Id do card
        $order->contactId = $orderArray['ContactId'];
        $order->createDate = $orderArray['CreateDate']; // data de criação da order
        $order->ownerId = $orderArray['OwnerId']; // Responsável
        $order->amount = $orderArray['Amount']; // Valor
         //previsão de faturamento
        $order->previsaoFaturamento = (isset($customFields['bicorp_api_previsao_faturamento_out']) && !empty($customFields['bicorp_api_previsao_faturamento_out']))? $customFields['bicorp_api_previsao_faturamento_out'] : date('Y-m-d');
        //código da categoria de vendas
        $codigoCategoria = (isset($customFields['bicorp_api_codigo_categoria_venda_out']) && !empty($customFields['bicorp_api_codigo_categoria_venda_out'])) ? $customFields['bicorp_api_codigo_categoria_venda_out'] : false;
        $categoria = (isset($customFields['bicorp_api_lista_categoria_venda_out']) && !empty($customFields['bicorp_api_lista_categoria_venda_out'])) ? $customFields['bicorp_api_lista_categoria_venda_out'] : false;
        
        if($categoria){
            $option = $ploomesServices->getOptionsFieldById($categoria);
            $catParts = explode(' - ', $option['Name']);
            $order->codigoCategoriaVenda = $catParts[0];
        }elseif($codigoCategoria){
            print 'codCategoria';
            $order->codigoCategoriaVenda = $codigoCategoria;
        }else{
            $order->codigoCategoriaVenda = "1.01.03";
        }
   

        //previsão de entrega
        $order->previsaoEntrega = (isset($customFields['bicorp_api_previsao_entrega_out']) && !empty($customFields['bicorp_api_previsao_entrega_out'])) ? $customFields['bicorp_api_previsao_entrega_out'] : null;

        //template id (tipo de venda produtos ou serviços) **Obrigatório
        $order->templateId = (isset($customFields['bicorp_api_tipo_venda_tratado_out']) && !empty($customFields['bicorp_api_tipo_venda_tratado_out']))? $customFields['bicorp_api_tipo_venda_tratado_out'] : 'produtos';

        //recurrence (tipo de venda de serviço é recorrente)
        $order->recurrence = (isset($customFields['bicorp_api_order_recorrencia_out']) && !empty($customFields['bicorp_api_order_recorrencia_out']))? $order->recurrence = true : $order->recurrence = false;

        //numContrato 
        $order->numContrato = (isset($customFields['bicorp_api_order_numero_contrato_out']) && !empty($customFields['bicorp_api_order_numero_contrato_out']))? $customFields['bicorp_api_order_numero_contrato_out'] : null;

        //sitContrato (tipo de venda de serviço é recorrente)
        $sitContrato = (isset($customFields['bicorp_api_order_situacao_contrato_out']) && !empty($customFields['bicorp_api_order_situacao_contrato_out']))? $customFields['bicorp_api_order_situacao_contrato_out'] : null;
        
        if($sitContrato){
        $sit =  explode(' - ',$sitContrato);
        $order->sitContrato = $sit[0];
        }else{
        $order->sitContrato = null;
        }
        
        $order->cidadeServico = (isset($customFields['bicorp_api_order_cidade_servico_out']) && !empty($customFields['bicorp_api_order_cidade_servico_out']))? $customFields['bicorp_api_order_cidade_servico_out'] : null;

        //tipo faturamento (tipo de faturamento Mensal/trimestral/semestral)
        $tipoFaturamento = (isset($customFields['bicorp_api_order_tipo_faturamento_out']) && !empty($customFields['bicorp_api_order_tipo_faturamento_out']))? $customFields['bicorp_api_order_tipo_faturamento_out'] : null;

        if($tipoFaturamento){
        $tFat =  explode(' - ',$tipoFaturamento);
        $order->tipoFaturamento = $tFat[0];
        }else{
        $order->tipoFaturamento = null;
        }     
                
        //inicio vigência do contrato
        $dStart =(isset($customFields['bicorp_api_order_inicio_vigencia_out']) && !empty($customFields['bicorp_api_order_inicio_vigencia_out']))? new DateTime($customFields['bicorp_api_order_inicio_vigencia_out']) : new DateTime();//$m[] = 'Erro: não foi possível identificar o tipo de venda (Produtos ou serviços)';
        
        //fim vigência do contrato
        $dEnd = (isset($customFields['bicorp_api_order_fim_vigencia_out']) && !empty($customFields['bicorp_api_order_fim_vigencia_out']))? new DateTime($customFields['bicorp_api_order_fim_vigencia_out']) : new DateTime();
        
        $order->inicioVigencia = $dStart->format('d/m/Y');
        $order->fimVigencia = $dEnd->format('d/m/Y');
        //dia do faturamento de inteiro de 1 a 31
        $order->diaFaturamento = (isset($customFields['bicorp_api_order_dia_faturamento_out']) && !empty($customFields['bicorp_api_order_dia_faturamento_out']))? $customFields['bicorp_api_order_dia_faturamento_out'] : null;

        //numero do pedido do cliente (preenchido na venda) localizado em pedidos info. adicionais
        $order->numPedidoCliente = (isset($customFields['bicorp_api_numero_pedido_cliente_out']) && !empty($customFields['bicorp_api_numero_pedido_cliente_out']))?$customFields['bicorp_api_numero_pedido_cliente_out']:null;

        $order->descricaoServico = (isset($customFields['bicorp_api_descricao_servico_out']) && !empty($customFields['bicorp_api_descricao_servico_out']))?htmlspecialchars_decode(strip_tags($customFields['bicorp_api_descricao_servico_out'],'\n')) : null;

        //Numero pedido de compra (id da customFieldsosta) localizado em item da venda info. adicionais
        $order->numPedidoCompra = (isset($customFields['bicorp_api_numero_pedido_compra_out']) && !empty($customFields['bicorp_api_numero_pedido_compra_out'])? $customFields['bicorp_api_numero_pedido_compra_out']: null); 

        //id modalidade do frete
        ((isset($customFields['bicorp_api_codigo_modalidade_frete_out'])) && (!empty($customFields['bicorp_api_codigo_modalidade_frete_out']) || $customFields['bicorp_api_codigo_modalidade_frete_out'] === "0")) ? $order->modalidadeFrete = $customFields['bicorp_api_codigo_modalidade_frete_out'] : $order->modalidadeFrete = null;
    
        //projeto
        $order->projeto = $customFields['bicorp_api_projeto_out'] ?? null;
        
        if(isset($customFields['bicorp_api_venda_workshop_out'])){
            
            $dataWorkshop = $customFields['bicorp_api_data_workshop_out'];
            $speaker = $customFields['bicorp_api_speaker_workshop_out'];
            $vendedorAdicional = $customFields['bicorp_api_vendedor_adicional_out'];
            
            $order->notes = "{$order->description}\r\n Data do Workshop: {$dataWorkshop}\r\n Speaker do Workshop: {$speaker}\r\n Vendedor Adicional: $vendedorAdicional"; 
            
        }else{
        //observações da nota
        $order->notes = (isset($customFields['bicorp_api_dados_adicionais_nota_fiscal_out']) ? htmlspecialchars_decode(strip_tags($customFields['bicorp_api_dados_adicionais_nota_fiscal_out'])): null); 
        }
        
        $codigoParcelamento = (isset($customFields['bicorp_api_codigo_condicao_pagamento_out']) && !empty($customFields['bicorp_api_codigo_condicao_pagamento_out'])) ? $customFields['bicorp_api_codigo_condicao_pagamento_out'] : false;
        
        $listaParcela = (isset($customFields['bicorp_api_lista_parcelas_omie_out']) && !empty($customFields['bicorp_api_lista_parcelas_omie_out'])) ? $customFields['bicorp_api_lista_parcelas_omie_out'] : false;
        
        
        if($listaParcela){
            $option = $ploomesServices->getOptionsFieldById($listaParcela);
            $parts = explode(' - ', $option['Name']);
            $order->idParcelamento = $parts[0];
        }elseif($codigoParcelamento){
          
            $order->idParcelamento = $codigoParcelamento;
        }else{
            $order->idParcelamento = "000";
        }
        
        $codigoMeioPagamento = (isset($customFields['bicorp_api_codigo_meio_pagamento_out']) && !empty($customFields['bicorp_api_codigo_meio_pagamento_out'])) ? $customFields['bicorp_api_codigo_meio_pagamento_out'] : false;
        
        $listaMeiosPagamento= (isset($customFields['bicorp_api_lista_meio_pagamento_out']) && !empty($customFields['bicorp_api_lista_meio_pagamento_out'])) ? $customFields['bicorp_api_lista_meio_pagamento_out'] : false;
        
        if($listaMeiosPagamento){
            $option = $ploomesServices->getOptionsFieldById($listaMeiosPagamento);
            $parts = explode(' - ', $option['Name']);
            $order->idMeioPagamento = $parts[0];
        }elseif($codigoParcelamento){
            $order->idMeioPagamento = $codigoParcelamento;
        }else{
            $order->idMeioPagamento = "15";
        }
        
        // Endereço de entrega
        // CNPJ/CPF do recebedor
        $order->docRecebedorEnderecoEntrega = $customFields['bicorp_api_entrega_doc_recebedor_venda_out'] ?? null;
        // Nome / Razão Social
        $order->nomeEnderecoEntrega = $customFields['bicorp_api_entrega_razao_social_venda_out'] ?? null;
        // Inscrição Estadual
        $order->ieEnderecoEntrega = $customFields['bicorp_api_entrega_inscircao_estadual_venda_out'] ?? null;
        // CEP Endereco Entrega
        $order->cepEnderecoEntrega = $customFields['bicorp_api_entrega_cep_venda_out'] ?? null;
        // Endereco Entrega
        $order->enderecoEnderecoEntrega = $customFields['bicorp_api_entrega_endereco_venda_out'] ?? null;
        // Número
        $order->numeroEnderecoEntrega = $customFields['bicorp_api_entrega_numero_venda_out'] ?? null;
        // Complemento
        $order->complementoEnderecoEntrega = $customFields['bicorp_api_entrega_complemento_venda_out'] ?? null;
        // Bairro
        $order->bairroEnderecoEntrega = $customFields['bicorp_api_entrega_bairro_venda_out'] ?? null;
        // Estado UF
        $order->ufEnderecoEntrega = $customFields['bicorp_api_entrega_uf_venda_out'] ?? null;
        // Cidade
        $order->cidadeEnderecoEntrega = $customFields['bicorp_api_entrega_cidade_venda_out'] ?? null;
        // Telefone
        $order->telefoneEnderecoEntrega = $customFields['bicorp_api_entrega_telefone_venda_out'] ?? null;

        if(!empty($m)){
            throw new WebhookReadErrorException($m[0], 500);
        }
 
     }
 
    //  private static function insertProjectOmie(object $erp, object $order):string
    //  {
    //      $project = $omieServices->insertProject($erp,  $order->projeto);
 
    //      if(isset($project['faultstring'])){
    //          throw new WebhookReadErrorException('Erro ao cadastrar o Projeto no Omie: ' . $project['faultstring'], 500);
    //      }else{
    //          return $project['codigo'];
    //      }
 
    //  }
 
     private static function getIdVendedorErpFromMail(object $erp, string $email, object $formatter): int | null | string
     {   
         if(!isset($email) || empty($email)){
             return null;
         }
         return $formatter->getIdVendedorERP($erp, $email);
     }
 
     private static function getDetailsOrderFromPloomes(object $order, $ploomesServices):array
     {
         $arrayRequestOrder = $ploomesServices->requestOrder($order);
 
         if(!isset($arrayRequestOrder) || empty($arrayRequestOrder) )
         {
             throw new WebhookReadErrorException('Erro ao montar pedido para enviar ao Omie: Não foi encontrada a venda no Ploomes', 500);
         }
 
         return $arrayRequestOrder;
     }
 
     private static function setIdItemErp(object $erp)
     {
         $customProducts = CustomFieldsFunction::getCustomFieldsByEntity('Produto');
         $idItemErp ='';
         foreach($customProducts as $custom){
             if(isset($custom['SendExternalKey']) && strtolower($custom['SendExternalKey']) === strtolower("bicorp_api_idProductOmie{$erp->baseFaturamentoTitle}_out"))
             {
                 $idItemErp = $custom['Key'];
             }
         }
 
         if(empty($idItemErp)){
             {
                 throw new WebhookReadErrorException('Erro ao montar pedido para enviar ao Omie: Não foi encontrado o Id Omie do item da venda', 500);
             }
         }
       
         return $idItemErp;
     }
 
     private static function isService(object $order):bool
     {
         $type = strtolower($order->templateId);       
         //verifica se é um serviço
         return ($type === 'servicos') ? true : false;
     }
 
     private static function createRequestNewOrder(object $erp, object $order, string $jsonPedido, object $formatter, object $ploomesServices, array $arrayIsServices):array
     {  
        $incluiPedidoErp = $formatter->createOrderErp($jsonPedido, $arrayIsServices);
        
        if($arrayIsServices['isService'] && $arrayIsServices['isRecurrence'] ){
            $venda = 'Contrato de Serviço';
        }elseif($arrayIsServices['isService'] && !$arrayIsServices['isRecurrence'] ){
            $venda = 'Ordem de Serviço';
        }else{
            $venda = 'Venda de Produto';
        }
 
         //verifica se criou o pedido no ERP
         if($incluiPedidoErp['create']) 
         {
             $message['winDeal']['interactionMessage'] = 'Integração concluída com sucesso!<br> Pedido Ploomes: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' gravados no Omie ERP com o numero: '.intval($incluiPedidoErp['num_pedido']).' e mensagem enviada com sucesso em: '.self::$current;
 
             //monta a mensagem para atualizar o card do ploomes
             $msg=[
                 'DealId' => $order->dealId,
                 'Content' => $venda . ' ('.intval($incluiPedidoErp['num_pedido']).') criada no OMIE via API BICORP na base '.$erp->baseFaturamentoTitle.'.',
                 'Title' => 'Pedido Criado'
             ];
 
             //cria uma interação no card
             ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['winDeal']['interactionMessage'] = 'Integração concluída com sucesso!<br> Pedido Ploomes: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' gravados no Omie ERP com o numero: '.intval($incluiPedidoErp['num_pedido']).' e mensagem enviada com sucesso em: '.self::$current
             :throw new WebhookReadErrorException('Integração concluída com sucesso!<br> Pedido Ploomes: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' gravados no Omie ERP com o numero: '.intval($incluiPedidoErp['num_pedido']).'Porém houve um erro ao enviar a mensagem ao Ploomes. '.self::$current);
 
             $message['winDeal']['returnPedidoOmie'] ='Pedido criado no Omie via BICORP INTEGRAÇÃO pedido numero: '.intval($incluiPedidoErp['num_pedido']);
 
         }else{ 
 
             $deleteProject = self::deleteProjectErp($formatter, $erp, $order);
             
             //monta a mensagem para atualizar o card do ploomes
             $msg=[
                 'DealId' => $order->dealId,
                 'Content' => 'Pedido não pode ser criado no OMIE ERP. '.$incluiPedidoErp['faultstring'],
                 'Title' => 'Erro na integração'
             ];
         
             //cria uma interação no card
             ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['deal']['interactionMessage'] = 'Erro na integração: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' - '.$incluiPedidoErp['faultstring']. 'Mensagem enviada com sucesso em: '.self::$current : throw new WebhookReadErrorException('Não foi possível gravar a mensagem na venda',500);
 
             $message['winDeal']['error'] ='Não foi possível gravar o peddido no Omie! '. $incluiPedidoErp['faultstring'] . $deleteProject;
            
         }  
         return $message;
     }
 
     private static function createInteractionPloomes(string $msg, object $ploomesServices):bool
     {
         if($ploomesServices->createPloomesIteraction($msg)){
             return true;
         }else{
            return false;
         }
     }
 
     
 
     private static function deleteProjectErp(object $formatter, object $erp, object $order):string
     {
        $del = $formatter->deleteProject($erp, $order);
 
        return $del;
     }
     
      public static function alterOrderStage(object $ploomesServices, array $alterOrder){
        //muda a etapa da venda específica para NF-Emitida stage Id 40042597
        $allStages = $ploomesServices->getOrderStages();

        foreach($allStages as $stage){
            if(mb_strtolower($stage['Name']) === mb_strtolower($alterOrder['event']['etapaDescr'])){
                $dataStage = [
                    'Id'=> $stage['Id'],
                    'Name' => $stage['Name']
                ];
            }
        }

        $array = [
            'StageId'=>$dataStage['Id']
        ];

        $json = json_encode($array);

        $id = explode('/',$alterOrder['event']['codIntPedido']);
        $idPedidoPloomes = $id[1];    
        $alterStageOrderPloomes = $ploomesServices->alterStageOrder($json, $idPedidoPloomes);
        if(!$alterStageOrderPloomes){
            throw new WebhookReadErrorException("Erro ao alterar o estágio da venda numero do Omie {$alterOrder['event']['numeroPedido']} e Id do Ploomes {$idPedidoPloomes}");
        }

        $message = "Etapa da venda número do Omie {$alterOrder['event']['numeroPedido']} e Id do Ploomes {$idPedidoPloomes}, alterado para {$alterOrder['event']['etapaDescr']}, com sucesso!";

        return $message;
     }

    

}