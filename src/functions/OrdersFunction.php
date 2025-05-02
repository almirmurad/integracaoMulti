<?php
namespace src\functions;
use src\exceptions\PedidoInexistenteException;
use src\exceptions\WebhookReadErrorException;
use stdClass;


class OrdersFunction{

    public static $current;

    public static function setCurrent(){
        return self::$current = date('d/m/Y H:i:s');
    }

    //processa o contato do CRM para o ERP
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
        $bases = $args['Tenancy']['omie_bases'];
        
        //busca o Id do cliente no contact do ploomes
        $order->ids = self::getIdCustomerErpFromContactPloomes($contact['OtherProperties'], $bases, 'Cliente', $tenancyId);
        
        //busca os campos customizáveis **Obrigatório (busca com campos simples como no otherProperties do pedido do webook)
        $customFields = CustomFieldsFunction::compareCustomFields($decoded['New']['OtherProperties'],$tenancyId, $entity);
        if(empty($customFields)) {
            throw new PedidoInexistenteException('Erro ao montar pedido pra enviar ao omie: Não foram encontrados campos personalizados do Ploomes', 500);
        }
        
        //pega os campos customizáveis da entidade venda e os campos customizados da venda atual compara pra pegar as opções de base de faturamento e seu nome
        $bf = self::setBaseFaturamento($entity, $customFields);

        //Seta a base de faturamento ***Obrigatório
        ($bf) ? $order->baseFaturamento = $bf : throw new PedidoInexistenteException('Erro ao montar pedido pra enviar ao omie: não foi encontrada a empresa de faturamento do pedido', 500);
                
        //seta o id do cliente do omie para a base de faturamento de destino
        self::setIdClienteErp($order);

        //Monta Os detalhes do Omie ***Obrigatório (Monta o Objeto do ERP de destino)
        $omie = self::createErpObjectSetDetailsOrder($bases, $bf);

        //busca o código do vendedor pelo email do ploomes, se não encotrar retorna nulo
        $order->codVendedorErp = self::getIdVendedorErpFromMail($omie, $orderArray['Owner']['Email'], $formatter);  
        
        //id dos itens no omie registrados no ploomes *** Obrigatório
        $idItemOmie = self::setIdItemOmie($omie);    
        
        //seta informações adicionais(pega as informações como modalidade de frete, projeto etc de other properties pela sendExternalKey)
        self::setAdditionalOrderProperties($order, $orderArray, $customFields);
        
        //tipo da venda (is service) ***Obrigatótio
        $isService = self::isService($order);
       
        //separa os produtos dos serviços
        $contentOrder = $formatter->distinctProductsServicesFromOmieOrders($orderArray, $isService, $idItemOmie, $order);
        
        //insere o projeto e retorna o id
        // $order->codProjeto = self::insertProjectOmie($omie, $order);
        
        //se o array de produtos tiver conteúdo significa quee é uma venda de produto se não de serviço pra incluir no modulo certo do omie
        if($isService === false)
        {
            //monta estrutura Omie para a requisição de pedidos
            $order->contentOrder = $contentOrder['products'];
            $json = $formatter->createOrder($order, $omie);

            /**
             * Aqui vamos implementar o metodo que coloca o json na fila do rabbitMQ para o consumer enviar ao omie
             */
                        
            //envia o pedido ao Omie e retorna a mensagem de resposta
            $message = self::createRequestNewOrder($omie, $order, $json, $formatter, $ploomesServices);     

        }
        elseif(isset($contentOrder['services']['servicos']) && !empty($contentOrder['services']['servicos']))
        {
            //monta estrutura Omie para a requisição de OS
            $order->contentOrder = $contentOrder['services'];
            $os = self::createStructureOSOmie($omie, $order, $contentOrder['services']);

            //envia a OS ao Omie e retorna a mensagem de resposta
            $message = self::createRequestOS($omie, $order, $os);

        }
    
        return $message;

    }

    //processa o pedido do ERP para o CRM
    public static function processOrderErpToCrm($args, $ploomesServices, $formatter, $action):array
    {        
        $formatter->detectLoop($args);
        $message = [];
        $current = date('d/m/Y H:i:s');
        $contact = $formatter->createObjectCrmContactFromErpData($args, $ploomesServices);
        $json = $formatter->createPloomesContactFromErpObject($contact, $ploomesServices);      
    
        $idContact = $ploomesServices->consultaClientePloomesCnpj(DiverseFunctions::limpa_cpf_cnpj($contact->cnpjCpf));

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
                     case "bicorp_api_id_cliente_omie_{$baseName}_out":
                         $ids[$baseName] = $v ?? null;
                         break;
                     case "bicorp_api_id_cliente_omie_{$baseName}_out":
                         $ids[$baseName] = $v ?? null;
                         break;
                     case "bicorp_api_id_cliente_omie_{$baseName}_out":
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
             throw new PedidoInexistenteException('Erro ao montar pedido para enviar ao Omie: Não foi encontrado nenhum Id do omie no cliente.');
         }
         if(!isset($order->baseFaturamento) || !is_array($order->baseFaturamento) || empty($order->baseFaturamento)){
             throw new PedidoInexistenteException('Erro ao montar pedido para enviar ao Omie: Base de Faturamento Inexistente.', 500);
         }
         
         $baseFaturamentoTitle = $order->baseFaturamento['Name'];
         $order->idClienteOmie = $order->ids[strtolower($baseFaturamentoTitle)] ?? null;
 
         if (!isset($order->idClienteOmie) || $order->idClienteOmie === null) {
             throw new PedidoInexistenteException('Erro ao montar pedido para enviar ao Omie: Não foi encontrado Id do Omie para  o aplicativo de destino escolhido para faturar o pedido [ '. $baseFaturamentoTitle .' ].');
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
         $omie = new stdClass();
         foreach($bases as $base){
             if(strtolower($base['app_name']) == strtolower($bf['Name'])){
                 $omie->baseFaturamentoTitle = $base['app_name'];
                 $omie->ncc = $base['ncc'];
                 $omie->appSecret = $base['app_secret'];
                 $omie->appKey = $base['app_key'];
             }
         }
         
         if(empty($omie)){
             {
                 throw new PedidoInexistenteException('Erro ao montar pedido para enviar ao Omie: Base de faturamento inexistente, não foi possível montar dados do App Omie', 500);
             }
         }
         return $omie;
     }
 
     private static function setAdditionalOrderProperties(object $order, array $orderArray, array $customFields):void
     {
         
         $order->orderNumber = $orderArray['OrderNumber']; // numero da venda
         $order->dealId = $orderArray['DealId']; // Id do card
         $order->contactId = $orderArray['ContactId'];
         $order->createDate = $orderArray['CreateDate']; // data de criação da order
         $order->ownerId = $orderArray['OwnerId']; // Responsável
         $order->amount = $orderArray['Amount']; // Valor
 
         //previsão de faturamento
         $order->previsaoFaturamento =(isset($customFiels['Previsão de Faturamento']) && !empty($customFiels['api_bicorp_previsao_faturamento_out']))? $customFiels['api_bicorp_previsao_faturamento_out'] : date('Y-m-d');
 
         //template id (tipo de venda produtos ou serviços) **Obrigatório
         $order->templateId =(isset($customFields['bicorp_api_tipo_venda_tratado_out']) && !empty($customFields['bicorp_api_tipo_venda_tratado_out']))? $customFields['bicorp_api_tipo_venda_tratado_out'] : $m[] = 'Erro: não foi possível identificar o tipo de venda (Produtos ou serviços)';
 
         //numero do pedido do cliente (preenchido na venda) localizado em pedidos info. adicionais
         $order->numPedidoCliente = (isset($customFields['bicorp_api_numero_pedido_cliente_out']) && !empty($customFields['bicorp_api_numero_pedido_cliente_out']))?$customFields['bicorp_api_numero_pedido_cliente_out']:null;
 
         $order->descricaoServico = (isset($customFields['bicorp_api_descricao_servico_out']) && !empty($customFields['bicorp_api_descricao_servico_out']))?htmlspecialchars_decode(strip_tags($customFields['bicorp_api_descricao_servico_out'],'\n')):null;
 
         //Numero pedido de compra (id da customFieldsosta) localizado em item da venda info. adicionais
         $order->numPedidoCompra = (isset($customFields['bicorp_api_numero_pedido_compra_out']) && !empty($customFields['bicorp_api_numero_pedido_compra_out'])? $customFields['bicorp_api_numero_pedido_compra_out']: null); 
 
         //id modalidade do frete
         ((isset($customFields['bicorp_api_codigo_modalidade_frete_out'])) && (!empty($customFields['bicorp_api_codigo_modalidade_frete_out']) || $customFields['bicorp_api_codigo_modalidade_frete_out'] === "0")) ? $order->modalidadeFrete = $customFields['bicorp_api_codigo_modalidade_frete_out'] : $order->modalidadeFrete = null;
       
         //projeto ***Obrigatório
         $order->projeto = ($customFields['bicorp_api_projeto_out']) ?? $m[]='Erro ao montar pedido para enviar ao Omie ERP: Não foi informado o Projeto';
 
         //observações da nota
         $order->notes = (isset($customFields['bicorp_api_dados_adicionais_nota_fiscal_out']) ? htmlspecialchars_decode(strip_tags($customFields['bicorp_api_dados_adicionais_nota_fiscal_out'])): null);  
 
         $order->idParcelamento = $customFields['bicorp_api_codigo_condicao_pagamento_out'] ?? null;
 
         if(!empty($m)){
             throw new PedidoInexistenteException($m[0], 500);
         }
 
     }
 
    //  private static function insertProjectOmie(object $omie, object $order):string
    //  {
    //      $project = $this->omieServices->insertProject($omie,  $order->projeto);
 
    //      if(isset($project['faultstring'])){
    //          throw new PedidoInexistenteException('Erro ao cadastrar o Projeto no Omie: ' . $project['faultstring'], 500);
    //      }else{
    //          return $project['codigo'];
    //      }
 
    //  }
 
     private static function getIdVendedorErpFromMail(object $omie, string $mail, object $formatter): int | null
     {   
         if(!isset($email) || empty($email)){
             return null;
         }
         return $formatter->getIdVendedorERP($omie, $mail);
     }
 
     private static function getDetailsOrderFromPloomes(object $order, $ploomesServices):array
     {
         $arrayRequestOrder = $ploomesServices->requestOrder($order);
 
         if(!isset($arrayRequestOrder) || empty($arrayRequestOrder) )
         {
             throw new PedidoInexistenteException('Erro ao montar pedido para enviar ao Omie: Não foi encontrada a venda no Ploomes', 500);
         }
 
         return $arrayRequestOrder;
     }
 
     private static function setIdItemOmie(object $omie)
     {
         $customProducts = CustomFieldsFunction::getCustomFieldsByEntity('Produto');
         $idItemOmie ='';
         foreach($customProducts as $custom){
             if(isset($custom['SendExternalKey']) && strtolower($custom['SendExternalKey']) === strtolower("bicorp_api_idProductOmie{$omie->baseFaturamentoTitle}_out"))
             {
                 $idItemOmie = $custom['Key'];
             }
         }
 
         if(empty($idItemOmie)){
             {
                 throw new PedidoInexistenteException('Erro ao montar pedido para enviar ao Omie: Não foi encontrado o Id Omie do item da venda', 500);
             }
         }
       
         return $idItemOmie;
     }
 
     private static function isService(object $order):bool
     {
         $type = strtolower($order->templateId);       
         //verifica se é um serviço
         return ($type === 'servicos') ? true : false;
     }
 
     private static function createRequestNewOrder(object $omie, object $order, string $jsonPedido, object $formatter, object $ploomesServices):array
     {   
         $incluiPedidoErp = $formatter->createOrderErp($jsonPedido);
 
         //verifica se criou o pedido no omie
         if(isset($incluiPedidoErp['codigo_status']) && $incluiPedidoErp['codigo_status'] == "0") 
         {
             $message['winDeal']['interactionMessage'] = 'Integração concluída com sucesso!<br> Pedido Ploomes: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' gravados no Omie ERP com o numero: '.intval($incluiPedidoErp['numero_pedido']).' e mensagem enviada com sucesso em: '.self::$current;
 
             //monta a mensagem para atualizar o card do ploomes
             $msg=[
                 'DealId' => $order->dealId,
                 'Content' => 'Venda ('.intval($incluiPedidoErp['numero_pedido']).') criada no OMIE via API BICORP na base '.$omie->baseFaturamentoTitle.'.',
                 'Title' => 'Pedido Criado'
             ];
 
             //cria uma interação no card
             ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['winDeal']['interactionMessage'] = 'Integração concluída com sucesso!<br> Pedido Ploomes: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' gravados no Omie ERP com o numero: '.intval($incluiPedidoErp['numero_pedido']).' e mensagem enviada com sucesso em: '.self::$current
             :throw new PedidoInexistenteException('Integração concluída com sucesso!<br> Pedido Ploomes: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' gravados no Omie ERP com o numero: '.intval($incluiPedidoErp['numero_pedido']).'Porém houve um erro ao enviar a mensagem ao Ploomes. '.self::$current);
 
             $message['winDeal']['returnPedidoOmie'] ='Pedido criado no Omie via BICORP INTEGRAÇÃO pedido numero: '.intval($incluiPedidoErp['numero_pedido']);
 
         }else{ 
 
             //$deleteProject = $this->deleteProjectOmie($omie, $order);
             $deleteProject = 'mensagem de retorno ao deletar um projeto';
             
             //monta a mensagem para atualizar o card do ploomes
             $msg=[
                 'DealId' => $order->dealId,
                 'Content' => 'Pedido não pode ser criado no OMIE ERP. '.$incluiPedidoErp['faultstring'],
                 'Title' => 'Erro na integração'
             ];
         
             //cria uma interação no card
             ($ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['deal']['interactionMessage'] = 'Erro na integração, dados incompatíveis: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' - '.$incluiPedidoErp['faultstring']. 'Mensagem enviada com sucesso em: '.self::$current : throw new PedidoInexistenteException('Não foi possível gravar a mensagem na venda',500);
 
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
 
     private static function createStructureOSOmie(object $omie, object $os, array $contentOrder):array
     {
 
         $cabecalho = [];//cabeçalho do pedido (array)
         $cabecalho['nCodCli'] = $os->idClienteOmie;//int
         $cabecalho['cCodIntOS'] = 'VEN_SRV/'.$os->id;//string
         $cabecalho['dDtPrevisao'] = DiverseFunctions::convertDate($os->previsaoFaturamento);//string
         $cabecalho['cEtapa'] = '10';//string
         $cabecalho['cCodParc'] =  $os->idParcelamento ?? '000';//string'qtde_parcela'=>2
         $cabecalho['nQtdeParc'] = 3;//string'qtde_parcela'=>2
         $cabecalho['nCodVend'] = $os->codVendedorErp;//string'qtde_parcela'=>2
 
         $InformacoesAdicionais = []; //informações adicionais por exemplo codigo_categoria = 1.01.02 p/ serviços
         $InformacoesAdicionais['cCodCateg'] = '1.01.02';//string
         $InformacoesAdicionais['nCodCC'] = $omie->ncc;//int
         $InformacoesAdicionais['cDadosAdicNF'] = $os->notes;//string
         $InformacoesAdicionais['cNumPedido']=$os->numPedidoCliente ?? "0";
         $InformacoesAdicionais['nCodProj']= $os->codProjeto;
 
         $pu = [];
 
         $pu['cAcaoProdUtilizados'] = 'EST';
         $pu['produtoUtilizado'] = $contentOrder['produtosUtilizados'];
     
         $newOS = [];//array que engloba tudo
         $newOS['cabecalho'] = $cabecalho;
         $newOS['InformacoesAdicionais'] = $InformacoesAdicionais;
         $newOS['servicosPrestados'] = $contentOrder['servicos'];
         $newOS['produtosUtilizados'] = $pu;
 
         if(
             !empty($newPedido['cabecalho']) || !empty($newPedido['InformacoesAdicionais']) ||
             !empty($newPedido['servicosPrestados']) 
         )
         {
 
             return $newOS;       
         }else{
             throw new PedidoInexistenteException('Erro ao montar a OS para enviar ao Omie: Estrutura de pedido com preblema',500);
         }
     }
 
     private static function createRequestOS(object $omie, object $os, array $structureOS):array
     {
         //inclui a ordem de serviço
         $incluiOS = $this->omieServices->criaOS($omie, $os, $structureOS);
 
         /**
          * array de retorno da inclusão de OS
         * [cCodIntOS] => SRV/404442017
         * [nCodOS] => 6992578495
         * [cNumOS] => 000000000000018
         * [cCodStatus] => 0
         * [cDescStatus] => Ordem de Serviço adicionada com sucesso!
         */
 
         //se incluiu a OS
         if(isset($incluiOS['cCodStatus']) && $incluiOS['cCodStatus'] == "0"){
 
             $message['winDeal']['interactionMessage'] = 'Integração concluída com sucesso!<br> Pedido Ploomes: '.$os->id.' card nº: '.$os->dealId.' e client id: '.$os->contactId.' gravados no Omie ERP com o numero: '.intval($incluiOS['cNumOS']).' em: '.self::$current ;
 
             //monta mensagem pra enviar ao ploomes
             $msg=[
                 'DealId' => $os->dealId,
                 'Content' => 'Ordem de Serviço ('.intval($incluiOS['cNumOS']).') criada no OMIE via API BICORP na base '.$omie->baseFaturamentoTitle.'.',
                 'Title' => 'Ordem de Serviço Criada'
             ];
 
             $message['winDeal']['incluiOS']['Success'] = $incluiOS['cDescStatus']. 'Numero: ' . intval($incluiOS['cNumOS']);
             //cria uma interação no card
             (self::createInteractionPloomes(json_encode($msg)))?$message['winDeal']['interactionMessage'] = 'Integração concluída com sucesso!<br> Pedido Ploomes: '.$os->id.' card nº: '.$os->dealId.' e client id: '.$os->contactId.' gravados no Omie ERP com o numero: '.intval($incluiOS['cNumOS']).' e mensagem enviada com sucesso em: '.self::$current:throw new PedidoInexistenteException('Integração concluída com sucesso!<br> Pedido Ploomes: '.$os->id.' card nº: '.$os->dealId.' e client id: '.$os->contactId.' gravados no Omie ERP com o numero: '.intval($incluiOS['cNumOS']).'Porém houve um erro ao enviar a mensagem ao Ploomes. '.self::$current,500);
                 
             
             
         }else{
                         
             $deleteProject = $this->deleteProjectOmie($omie, $os);
             $message['winDeal']['error'] ='Não foi possível gravar a Ordem de Serviço no Omie! '. $deleteProject;
             
             $msg=[
                 'DealId' => $os->dealId,
                 'Content' => 'Ordem de Serviço não pode ser criado no OMIE ERP. '.$incluiOS['faultstring'],
                 'Title' => 'Erro na integração'
             ];
             
             //cria uma interação no card
 
             (self::createInteractionPloomes(json_encode($msg)))?$message['deal']['interactionMessage'] = 'Erro na integração, dados incompatíveis, pedido: '.$os->id.' card nº: '.$os->dealId.' e client id: '.$os->contactId.' - '.$incluiOS['faultstring']. 'Mensagem enviada com sucesso em: '.self::$current : throw new PedidoInexistenteException('Não foi possível gravar a mensagem na venda',500);
             
 
         }
 
         return $message;
 
     }
 
     private static function deleteProjectOmie(object $omie, object $order):string
     {
        $del = $this->omieServices->deleteProject($omie, $order);
 
        if($del['codigo'] === "0"){
           return $del['descricao'];
 
         }else{
             return $del['faultstring'];
         }
     }

    

}