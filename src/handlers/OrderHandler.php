<?php

namespace src\handlers;

use src\contracts\ErpFormattersInterface;
use src\exceptions\PedidoInexistenteException;

use src\exceptions\WebhookReadErrorException;
use src\functions\CustomFieldsFunction;
use src\functions\DiverseFunctions;
use src\models\Omie;
use src\models\Order;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;
use stdClass;

class OrderHandler
{
    private ErpFormattersInterface $formatter;
    private PloomesServices $ploomesServices;
    private OmieServices $omieServices;
    private DatabaseServices $databaseServices;
    private $current;

    public function __construct(
        PloomesServices $ploomesServices, OmieServices $omieServices, DatabaseServices $databaseServices, ErpFormattersInterface $formatter
    )
    {
        $this->formatter = $formatter;
        $this->ploomesServices = $ploomesServices;
        $this->omieServices = $omieServices;
        $this->databaseServices = $databaseServices;
        $this->current = date('d/m/Y H:i:s');

    }

    //SALVA O WEBHOOK NO BANCO DE DADOS
    public function saveDealHook($json){

        $decoded = json_decode($json, true);
        $origem = (!isset($decoded['Entity']))?'Omie':'Ploomes';

        //infos do webhook
        $webhook = new stdClass();
        $webhook->json = $json; //webhook 
        $webhook->status = 1; // recebido
        $webhook->result = 'Rececibo';
        $webhook->entity = $decoded['Entity']??'Deals';
        $webhook->origem = $origem;

        if($this->databaseServices->saveWebhook($webhook))
        {
            $m= [ 'msg' =>'Webhook Salvo com sucesso id = às '.date('d/m/Y H:i:s')];
            return $m;
        } 
    }

    //PROCESSA E CRIA O PEDIDO.
    public function startProcess($json)
    {   
        //inicia o processo de crição de pedido, caso de certo retorna mensagem de success, e caso de erro retorna error
        $newOrder=[];
        
        try{
            //resposta do processo de inclusão 
            $res = Self::newOrder($json); 

            if(!isset($res['newOrder']['error'])){
                
                $newOrder['success'] = $res;
                
                //card processado pedido criado no Omie retorna mensagem newOrder para salvr no log
                return $newOrder; 
            }
            else{
                // se der erro finaliza o processo e lança excessão ao controller                        
                throw new WebhookReadErrorException('Erro ao gravar pedido: ' . $res['newOrder']['error'] . ' - ' . date('d/m/Y H:i:s'), 500);                    
            }

        }
        catch(PedidoInexistenteException $e)
        {
            //Caso de erro na inclusão do peddido monta a mensagem para atualizar o card do ploomes    
            $decoded = json_decode($json,true);
        
            $msg=[
                'DealId' => $decoded['New']['DealId'],
                'Content' => 'Pedido não pode ser criado no OMIE ERP. '.$e->getMessage(),
                'Title' => 'Erro na integração'
            ];
            // print($e->getMessage());
            // exit;

            // cria uma interação no card
            ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['deal']['interactionMessage'] = 'Erro na integração, dados incompatíveis: ' . $decoded['New']['Id'] . ' card nº: ' .$decoded['New']['DealId'] .' e client id: ' . $decoded['New']['ContactId'] . ' - '. $e->getMessage() . 'Mensagem enviada com sucesso em: '.date('d/m/Y : H:i:s') : throw new WebhookReadErrorException('Não foi possível gravar a mensagem na venda do ploomes',500);
            throw new WebhookReadErrorException($e->getMessage());
        }                    
    }

    public function newOrder($json)
    {
      
        $m = [];
        $message = [];
        $decoded = json_decode($json, true);
          
        //order_24F97378-4AFA-446C-834C-FD5EC4A0453E = Campo Para Condicionais
        //order_6B1470FA-DAAF-4F88-AF61-F2FD99EDEE80 = Condição de Pagamento
        //order_943040FD-4DEF-4AEB-B21A-2190529FE4B9 = Número de Revisão da Proposta
        //order_DF2C63CB-3893-4211-91C9-6A2C4FE1D0CA = Em branco ?(AParentemente informações adicioanis da nota fiscal)
        //order_ACB7C14D-2E32-4FB1-BC18-181EC06593A0 = Atualizar Informações
        //order_5ABC5118-2AA4-493A-B016-67E26C723DD1 //previsão de faturamento
        //order_94FD0EA3-9219-4E27-8BBC-87D7EB505CD9 // Empresa que irá faturar 
        //order_2FBCDD5C-2985-464A-B465-6F3AB3E7BC0D //id modalidade frete
        //order_BFDB31A9-C17C-4BC0-AB27-3ADF7582EE4E // modalidade (string)
        //order_BBBEB889-6888-4451-81A7-29AB821B1402 //projeto
        //order_C59D726E-A2A5-42B7-A18E-9898E12F203A //Descrição do serviço
        //order_94E64B44-63C4-4068-A992-F197E40DF8C8 //Razão social da empresa que irá faturar
        //order_4AAD4C79-F3EF-4798-B83E-72466B37DB79 // CNPJ da empresa que irá faturar
        //order_2E8E6008-5AFF-4D89-9F41-4FDA003D7703 // I>E> da empresa que irá faturar
        //order_E31797C6-2BC7-4AE5-8A38-1388DD8FD84A // numero pedido do cliente
        //order_4AF9E1C1-6DB9-45B2-89E4-9062B2E07B87 // numero pedido de compra
        //order_F438939E-F11E-4024-8F3D-6496F2B11778 // dados adicionais NF
        //order_1268DD4B-1E32-4CCA-A208-DCA5693613E8 // cod da proposta
        //order_943040FD-4DEF-4AEB-B21A-2190529FE4B9 // num de revisão da proposta
        //order_E768DCD5-D0B0-4417-9F58-6A4333C1846C // valor anterior da venda
        //order_F90FC615-C3B1-4E9A-9061-88C0AF944CC5 // Tipo de Venda Tratado (Jéssica)
        //order_B14B38B7-FB43-4E8E-A57A-61EFC97725A6 // codigo do parcelamento
        
        //Cria o objeto de order e seta o id
        $order = $this->createNewOrder($decoded);
        
        //Array de detalhes do item da venda ***Obrigatório
        $orderArray = $this->getDetailsOrderFromPloomes($order);

        //busca o contact do ploomes **obrigatório
        $contact = $orderArray['Contact'];
        
        //busca o Id do cliente no contact do ploomes
        $order->ids = $this->getIdCustomerOmieFromContactPloomes($contact['OtherProperties']);
      
        //busca os campos customizáveis **Obrigatório
        $customFields = CustomFieldsFunction::compareCustomFields($decoded['New']['OtherProperties']);
        if(empty($customFields)) {
            throw new PedidoInexistenteException('Erro ao montar pedido pra enviar ao omie: Não foram encontrados campos personalizados do Ploomes', 500);
        }

        //Seta a base de faturamento ***Obrigatório
        $order->baseFaturamento = $customFields['Empresa que irá Faturar'] ?? throw new PedidoInexistenteException('Erro ao montar pedido pra enviar ao omie: não foi encontrada a empresa de faturamento do pedido', 500);

        //seta o id do cliente do omie para a base de faturamento de destino
        $this->setIdClienteOmie($order);

        //Monta Os detalhes do Omie ***Obrigatório
        $omie = $this->createOmieObjectSetDetailsOrder($order);
        
        //busca o código do vendedor pelo email do ploomes, se não encotrar retorna nulo
        $order->codVendedorOmie = $this->getIdVendedorOmieFromMail($omie, $orderArray['Owner']['Email']);       
        
        //id dos itens no omie registrados no ploomes *** Obrigatório (lembrar de verificar retorno e retornar excessão)
        $idItemOmie = $this->setIdItemOmie($omie);
        
        //seta informações adicionais
        $this->setAdditionalOrderProperties($order, $orderArray, $customFields);
        
        //tipo da venda (is service) ***Obrigatótio
        $isService = $this->isService($order);    
        //separa os produtos dos serviços
        $contentOrder = $this->distinctProductsServicesFromOmieOrders($orderArray, $isService, $idItemOmie, $order);

        //insere o projeto e retorna o id
        //$order->codProjeto = $this->insertProjectOmie($omie, $order);
        
        //se o array de produtos tiver conteúdo significa quee é uma venda de produto se não de serviço pra incluir no modulo certo do omie
        if($isService === false)
        {
            //monta estrutura Omie para a requisição de pedidos
            $order->contentOrder = $contentOrder['products'];
            $json = $this->formatter->createOrder($order, $omie);
        
            // print_r($json);
            // exit;

            /**
             * Aqui vamos implementar o metodo que coloca o json na fila do rabbitMQ para o consumer enviar ao omie
             */
                        
            //envia o pedido ao Omie e retorna a mensagem de resposta
            $message = $this->createRequestNewOrder($omie, $order, $json);     

        }
        elseif(isset($contentOrder['services']['servicos']) && !empty($contentOrder['services']['servicos']))
        {
            //monta estrutura Omie para a requisição de OS
            $order->contentOrder = $contentOrder['services'];
            $os = $this->createStructureOSOmie($omie, $order, $contentOrder['services']);

            //envia a OS ao Omie e retorna a mensagem de resposta
            $message = $this->createRequestOS($omie, $order, $os);

        }
 
        return $message;
    }

    /* REPARTINDO NEW ORDER EM FUNCTIONS */

    private function createNewOrder(array $decoded):object
    {

        $order = new Order();
        $order->id = $decoded['New']['Id']; //Id da order

        return $order;
    }

    private function getIdCustomerOmieFromContactPloomes(array $otherProperties):array
    {

        $ids = [];

        $contacOp = CustomFieldsFunction::compareCustomFieldsFromOtherProperties($otherProperties);

        foreach($contacOp as $k => $v){
            
            switch ($k) {
                case "Gamatermic":
                    $ids['IdGTC'] = $v ?? null;
                    break;
                case "Engepartes":
                    $ids['IdEPT'] = $v ?? null;
                    break;
                case "Semin":
                    $ids['IdSMN'] = $v ?? null;
                    break;
                case "GSU":
                    $ids['IdGSU'] = $v ?? null;
                    break;
                
            }
        }

        return $ids;
    }

    private function setIdClienteOmie(object $order):void{

        $baseFaturamentoTitle = null;

        if (!isset($order->ids) || !is_array($order->ids) || empty($order->ids)) {
            throw new PedidoInexistenteException('Erro ao montar pedido para enviar ao Omie: Não foi encontrado nenhum Id do omie no cliente.');
        }

        switch ($order->baseFaturamento) {
            case '420197140':
                $baseFaturamentoTitle = 'Engeparts';
                $order->idClienteOmie = $order->ids['IdEPT'] ?? null;
                break;
            case '420197141':
                $baseFaturamentoTitle = 'Gamatermic';
                $order->idClienteOmie = $order->ids['IdGTC'] ?? null;
                break;
            case '420197143':
                $baseFaturamentoTitle = 'Semin';
                $order->idClienteOmie = $order->ids['IdSMN'] ?? null;
                break;
            case '420197142':
                $baseFaturamentoTitle = 'GSU';
                $order->idClienteOmie = $order->ids['IdGSU'] ?? null;
                break;
            default:
                throw new PedidoInexistenteException('Erro ao montar pedido para enviar ao Omie: Base de Faturamento Inexistente.', 500);
        }

        if (!isset($order->idClienteOmie) || $order->idClienteOmie === null) {
            throw new PedidoInexistenteException('Erro ao montar pedido para enviar ao Omie: Não foi encontrado Id do Omie para  o aplicativo de destino escolhido para faturar o pedido [ '. $baseFaturamentoTitle .' ].');
        }   

    }

    private function createOmieObjectSetDetailsOrder(object $order):object
    {
        
        $omie = new Omie();
        $baseFaturamentoTitle = null;

        switch ($order->baseFaturamento) {
            case '420197140':
                $baseFaturamentoTitle = 'ENGEPARTS';
                $omie->target = 'EPT'; 
                break;
            case '420197141':
                $baseFaturamentoTitle = 'GAMATERMIC';
                $omie->target = 'GTC'; 
                break;
            case '420197143':
                $baseFaturamentoTitle = 'SEMIN';
                $omie->target = 'SMN'; 
                break;
            case '420197142':
                $baseFaturamentoTitle = 'GSU';
                $omie->target = 'GSU'; 
                break;
            default:
                throw new PedidoInexistenteException('Erro ao montar pedido para enviar ao Omie: Base de faturamento inexistente, não foi possível montar dados do App Omie', 500);
        }
   
        $omie->baseFaturamentoTitle = $baseFaturamentoTitle;
        $omie->ncc = $_ENV["NCC_{$omie->target}"];
        $omie->appSecret = $_ENV["SECRETS_{$omie->target}"];
        $omie->appKey = $_ENV["APPK_{$omie->target}"];

        return $omie;
    }

    private function setAdditionalOrderProperties(object $order, array $orderArray, array $customFields):void
    {
        
        $order->orderNumber = $orderArray['OrderNumber']; // numero da venda
        $order->dealId = $orderArray['DealId']; // Id do card
        $order->contactId = $orderArray['ContactId'];
        $order->createDate = $orderArray['CreateDate']; // data de criação da order
        $order->ownerId = $orderArray['OwnerId']; // Responsável
        $order->amount = $orderArray['Amount']; // Valor

        //previsão de faturamento
        $order->previsaoFaturamento =(isset($customFiels['Previsão de Faturamento']) && !empty($customFiels['Previsao de Faturamento']))? $customFiels['Previsao de Faturamento'] : date('Y-m-d');

        //template id (tipo de venda produtos ou serviços) **Obrigatório
        $order->templateId =(isset($customFields['Tipo de Venda Tratado']) && !empty($customFields['Tipo de Venda Tratado']))? $customFields['Tipo de Venda Tratado'] : $m[] = 'Erro: não foi possível identificar o tipo de venda (Produtos ou serviços)';

        //numero do pedido do cliente (preenchido na venda) localizado em pedidos info. adicionais
        $order->numPedidoCliente = (isset($customFields['N° do Pedido do Cliente']) && !empty($customFields['N° do Pedido do Cliente']))?$customFields['N° do Pedido do Cliente']:null;

        $order->descricaoServico = (isset($customFields['Descrição do Serviço']) && !empty($customFields['Descrição do Serviço']))?htmlspecialchars_decode(strip_tags($customFields['Descrição do Serviço'],'\n')):null;

        //Numero pedido de compra (id da customFieldsosta) localizado em item da venda info. adicionais
        $order->numPedidoCompra = (isset($customFields['Nº do Pedido de Compra']) && !empty($customFields['Nº do Pedido de Compra'])? $customFields['Nº do Pedido de Compra']: null); 

        //id modalidade do frete
        ((isset($customFields['Código Modalidade de Frete'])) && (!empty($customFields['Código Modalidade de Frete']) || $customFields['Código Modalidade de Frete'] === "0")) ? $order->modalidadeFrete = $customFields['Código Modalidade de Frete'] : $order->modalidadeFrete = null;
      
        //projeto ***Obrigatório
        $order->projeto = ($customFields['Projeto']) ?? $m[]='Erro ao montar pedido para enviar ao Omie ERP: Não foi informado o Projeto';

        //observações da nota
        $order->notes = (isset($customFields['Dados Adicionais para a Nota Fiscal']) ? htmlspecialchars_decode(strip_tags($customFields['Dados Adicionais para a Nota Fiscal'])): null);  

        $order->idParcelamento = $customFields['Código condição de Pagamento'] ?? null;

        if(!empty($m)){
            throw new PedidoInexistenteException($m[0], 500);
        }

    }

    private function insertProjectOmie(object $omie, object $order):string
    {
        $project = $this->omieServices->insertProject($omie,  $order->projeto);

        if(isset($project['faultstring'])){
            throw new PedidoInexistenteException('Erro ao cadastrar o Projeto no Omie: ' . $project['faultstring'], 500);
        }else{
            return $project['codigo'];
        }

    }

    private function getIdVendedorOmieFromMail(object $omie, string $mail): int | null
    {
        return $this->omieServices->vendedorIdOmie($omie, $mail);
    }

    private function getDetailsOrderFromPloomes(object $order):array
    {
        $arrayRequestOrder = $this->ploomesServices->requestOrder($order);

        if(!isset($arrayRequestOrder) || empty($arrayRequestOrder) )
        {
            throw new PedidoInexistenteException('Erro ao montar pedido para enviar ao Omie: Não foi encontrada a venda no Ploomes', 500);
        }

        return $arrayRequestOrder;
    }

    private function setIdItemOmie(object $omie)
    {
        
        $idItemOmie =  match(strtolower($omie->baseFaturamentoTitle)){
            'gamatermic'=> 'product_E241BF1D-7622-45DF-9658-825331BD1C2D',
            'semin'=> 'product_429C894A-708E-4125-A434-2A70EDCAFED6',
            'engeparts'=> 'product_0A53B875-0974-440F-B4CE-240E8F400B0F',
            'gsu'=> 'product_08A41D8E-F593-4B74-8CF8-20A924209A09',
        };
        
        return $idItemOmie;
    }

    private function isService(object $order):bool
    {
        $type = match($order->templateId){
            '40130624' => "servicos",
            '40124278' => "produtos"
        };
        //verifica se é um serviço
        return ($type === 'servicos') ? true : false;
    }

    private function distinctProductsServicesFromOmieOrders(array $orderArray, bool $isService, mixed $idItemOmie, object $order):array
    {
        //separa e monta os arrays de produtos e serviços
        $productsOrder = []; 
        $det = [];  
        $det['ide'] = [];
        $det['produto'] = [];
        $opServices = [];
        $serviceOrder = [];
        $pu = [];
        $service = [];
        $produtosUtilizados = [];
        $contentServices = [];
        
        foreach($orderArray['Products'] as $prdItem)
        {   
            foreach($prdItem['Product']['OtherProperties'] as $otherp){
                $opServices[$otherp['FieldKey']] = $otherp['ObjectValueName'] ?? 
                $otherp['BigStringValue'] ?? $otherp['StringValue'] ??  $otherp['IntegerValue'] ?? $otherp['DateTimeValue'];
            }

            if(!array_key_exists($idItemOmie, $opServices )){
                throw new PedidoInexistenteException('Erro ao montar pedido para enviar ao Omie: Não foi encontrado o id do produto  Omie para o aplicativo de faturamento escolhido no pedido.', 500);
            }

            //verifica se é venda de serviço 
            if($isService){
                //verifica se tem serviço com produto junto
                if($prdItem['Product']['Group']['Name'] !== 'Serviços'){
                    
                    //monts o produtos utilizados (pu)
                    $pu['nCodProdutoPU'] = $opServices[$idItemOmie];
                    $pu['nQtdePU'] = $prdItem['Quantity'];
                    
                    $produtosUtilizados[] = $pu;
                    
                }else{
                    
                    //monta o serviço
                    $service['nCodServico'] = $opServices[$idItemOmie];
                    $service['nQtde'] = $prdItem['Quantity'];
                    $service['nValUnit'] = $prdItem['UnitPrice'];
                    $service['cDescServ'] = $order->descricaoServico;
                    
                    $serviceOrder[] = $service;
                }

                $contentServices['servicos'] = $serviceOrder;
                $contentServices['produtosServicos'] = $produtosUtilizados;
            }else{

                $det['ide']['codigo_item_integracao'] = $prdItem['Id'];
                $det['produto']['quantidade'] = $prdItem['Quantity'];
                $det['produto']['valor_unitario'] = $prdItem['UnitPrice'];
                $det['produto']['codigo_produto'] = $opServices[$idItemOmie];

                $det['inf_adic'] = [];
                $det['inf_adic']['numero_pedido_compra'] = $order->numPedidoCompra;
                $det['inf_adic']['item_pedido_compra'] =$prdItem['Ordination']+1;

                $productsOrder[] = $det;
            }
        }

        return ['products'=>$productsOrder, 'services'=>$contentServices];
    }

    private function createRequestNewOrder(object $omie, object $order, string $jsonPedido):array
    {
        
        $incluiPedidoOmie = $this->omieServices->criaPedidoOmie($jsonPedido);

        //verifica se criou o pedido no omie
        if(isset($incluiPedidoOmie['codigo_status']) && $incluiPedidoOmie['codigo_status'] == "0") 
        {
            $message['winDeal']['interactionMessage'] = 'Integração concluída com sucesso!<br> Pedido Ploomes: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' gravados no Omie ERP com o numero: '.intval($incluiPedidoOmie['numero_pedido']).' e mensagem enviada com sucesso em: '.$this->current;

            //monta a mensagem para atualizar o card do ploomes
            $msg=[
                'DealId' => $order->dealId,
                'Content' => 'Venda ('.intval($incluiPedidoOmie['numero_pedido']).') criada no OMIE via API BICORP na base '.$omie->baseFaturamentoTitle.'.',
                'Title' => 'Pedido Criado'
            ];

            //cria uma interação no card
            ($this->createInteractionPloomes(json_encode($msg)))?$message['winDeal']['interactionMessage'] = 'Integração concluída com sucesso!<br> Pedido Ploomes: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' gravados no Omie ERP com o numero: '.intval($incluiPedidoOmie['numero_pedido']).' e mensagem enviada com sucesso em: '.$this->current
            :throw new PedidoInexistenteException('Integração concluída com sucesso!<br> Pedido Ploomes: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' gravados no Omie ERP com o numero: '.intval($incluiPedidoOmie['numero_pedido']).'Porém houve um erro ao enviar a mensagem ao Ploomes. '.$this->current);

            $message['winDeal']['returnPedidoOmie'] ='Pedido criado no Omie via BICORP INTEGRAÇÃO pedido numero: '.intval($incluiPedidoOmie['numero_pedido']);

        }else{ 

            $deleteProject = $this->deleteProjectOmie($omie, $order);
            
            //monta a mensagem para atualizar o card do ploomes
            $msg=[
                'DealId' => $order->dealId,
                'Content' => 'Pedido não pode ser criado no OMIE ERP. '.$incluiPedidoOmie['faultstring'],
                'Title' => 'Erro na integração'
            ];
        
            //cria uma interação no card
            ($this->ploomesServices->createPloomesIteraction(json_encode($msg)))?$message['deal']['interactionMessage'] = 'Erro na integração, dados incompatíveis: '.$order->id.' card nº: '.$order->dealId.' e client id: '.$order->contactId.' - '.$incluiPedidoOmie['faultstring']. 'Mensagem enviada com sucesso em: '.$this->current : throw new PedidoInexistenteException('Não foi possível gravar a mensagem na venda',500);

            $message['winDeal']['error'] ='Não foi possível gravar o peddido no Omie! '. $incluiPedidoOmie['faultstring'] . $deleteProject;
           
        }  
        return $message;
    }

    private function createInteractionPloomes(string $msg):bool
    {
        if($this->ploomesServices->createPloomesIteraction($msg)){
            return true;
        }else{
           return false;
        }
    }

    private function createStructureOSOmie(object $omie, object $os, array $contentOrder):array
    {

        $cabecalho = [];//cabeçalho do pedido (array)
        $cabecalho['nCodCli'] = $os->idClienteOmie;//int
        $cabecalho['cCodIntOS'] = 'VEN_SRV/'.$os->id;//string
        $cabecalho['dDtPrevisao'] = DiverseFunctions::convertDate($os->previsaoFaturamento);//string
        $cabecalho['cEtapa'] = '10';//string
        $cabecalho['cCodParc'] =  $os->idParcelamento ?? '000';//string'qtde_parcela'=>2
        $cabecalho['nQtdeParc'] = 3;//string'qtde_parcela'=>2
        $cabecalho['nCodVend'] = $os->codVendedorOmie;//string'qtde_parcela'=>2

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

    private function createRequestOS(object $omie, object $os, array $structureOS):array
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

            $message['winDeal']['interactionMessage'] = 'Integração concluída com sucesso!<br> Pedido Ploomes: '.$os->id.' card nº: '.$os->dealId.' e client id: '.$os->contactId.' gravados no Omie ERP com o numero: '.intval($incluiOS['cNumOS']).' em: '.$this->current ;

            //monta mensagem pra enviar ao ploomes
            $msg=[
                'DealId' => $os->dealId,
                'Content' => 'Ordem de Serviço ('.intval($incluiOS['cNumOS']).') criada no OMIE via API BICORP na base '.$omie->baseFaturamentoTitle.'.',
                'Title' => 'Ordem de Serviço Criada'
            ];

            $message['winDeal']['incluiOS']['Success'] = $incluiOS['cDescStatus']. 'Numero: ' . intval($incluiOS['cNumOS']);
            //cria uma interação no card
            ($this->createInteractionPloomes(json_encode($msg)))?$message['winDeal']['interactionMessage'] = 'Integração concluída com sucesso!<br> Pedido Ploomes: '.$os->id.' card nº: '.$os->dealId.' e client id: '.$os->contactId.' gravados no Omie ERP com o numero: '.intval($incluiOS['cNumOS']).' e mensagem enviada com sucesso em: '.$this->current:throw new PedidoInexistenteException('Integração concluída com sucesso!<br> Pedido Ploomes: '.$os->id.' card nº: '.$os->dealId.' e client id: '.$os->contactId.' gravados no Omie ERP com o numero: '.intval($incluiOS['cNumOS']).'Porém houve um erro ao enviar a mensagem ao Ploomes. '.$this->current,500);
                
            
            
        }else{
                        
            $deleteProject = $this->deleteProjectOmie($omie, $os);
            $message['winDeal']['error'] ='Não foi possível gravar a Ordem de Serviço no Omie! '. $deleteProject;
            
            $msg=[
                'DealId' => $os->dealId,
                'Content' => 'Ordem de Serviço não pode ser criado no OMIE ERP. '.$incluiOS['faultstring'],
                'Title' => 'Erro na integração'
            ];
            
            //cria uma interação no card

            ($this->createInteractionPloomes(json_encode($msg)))?$message['deal']['interactionMessage'] = 'Erro na integração, dados incompatíveis, pedido: '.$os->id.' card nº: '.$os->dealId.' e client id: '.$os->contactId.' - '.$incluiOS['faultstring']. 'Mensagem enviada com sucesso em: '.$this->current : throw new PedidoInexistenteException('Não foi possível gravar a mensagem na venda',500);
            

        }

        return $message;

    }

    private function deleteProjectOmie(object $omie, object $order):string
    {
       $del = $this->omieServices->deleteProject($omie, $order);

       if($del['codigo'] === "0"){
          return $del['descricao'];

        }else{
            return $del['faultstring'];
        }
    }

    
 
    
}