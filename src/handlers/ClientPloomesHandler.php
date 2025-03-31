<?php

namespace src\handlers;

use PDOException;
use src\exceptions\ContactIdInexistentePloomesCRM;
use src\exceptions\InteracaoNaoAdicionadaException;
use src\exceptions\OrderControllerException;
use src\exceptions\PedidoCanceladoException;
use src\exceptions\PedidoDuplicadoException;
use src\exceptions\PedidoInexistenteException;
use src\exceptions\PedidoNaoExcluidoException;
use src\exceptions\WebhookReadErrorException;
use src\models\Deal;
use src\models\Homologacao_invoicing;
use src\models\Homologacao_order;
use src\models\Manospr_order;
use src\models\Manossc_order;
use src\models\Omieorder;
use src\models\User;

class ClientPloomesHandler
{
    private $current;

    public function __construct() {
        $date = date('d/m/Y H:i:s');
        $this->current = $date;
    }
   

    public static function newClient($json, $apiKey, $baseApi, ClientPloomesHandler $instance){

        
        $current = $instance->current;
        $message = [];
        
        //decodifica o json de pedidos vindos do webhook
        $decoded = json_decode($json,true);


        // {
        //     "codigo_cliente_integracao": "CodigoInterno0001",
        //     "email": "primeiro@ccliente.com.br",
        //     "razao_social": "Primeiro Cliente  Ltda Me",
        //     "nome_fantasia": "Primeiro Cliente"
        // }
        if($decoded['topic'] === "VendaProduto.Incluida" && $decoded['event']['etapa'] == "10" ){



            switch($decoded['appKey']){
                case 2337978328686:               
                    $appSecret = $_ENV['SECRETS_MHL'];
                    // Monta o objeto de Order Homologação com os dados do webhook
                    $order = new Homologacao_order();
                    $order->idOmie = $decoded['event']['idPedido'];
                    $order->codCliente = $decoded['event']['idCliente'];
                    //$order->codPedidoIntegracao = $decoded['event']['idPedido']; (não vem no webhook)
                    $order->dataPrevisao = $decoded['event']['dataPrevisao']; 
                    $order->numPedidoOmie = $decoded['event']['numeroPedido'];
                    //$order->codClienteIntegracao = $decoded['event']['idPedido']; (não vem no webhook)
                    $order->numContaCorrente = $decoded['event']['idContaCorrente'];
                    $order->codVendedorOmie = $decoded['author']['userId'];
                    //$order->idVendedorPloomes = $decoded['event']['idPedido']; (não vem no webhook)       
                    $order->appKey = $decoded['appKey'];  
   
                    try{

                        $id = HomologacaoOrderHandler::saveHomologacaoOrder($order);
                        $message['order']['newOrder'] = 'Novo pedido salvo na base de dados de pedidos de Homologação, id '.$id.'em: '.$current;
                                                
                    }catch(PDOException $e){
                        echo $e->getMessage();
                        throw new PedidoDuplicadoException('<br> Pedido Nº: '.$order->numPedidoOmie.' já cadastrado no omie em: '. $current, 1500);
                    }
                    
                    break;
                    
                    case 2335095664902:

                        $appSecret = $_ENV['SECRETS_MPR'];
                        // Monta o objeto de Order Homologação com os dados do webhook
                        $order = new Manospr_order();
                        $order->idOmie = $decoded['event']['idPedido'];
                        $order->codCliente = $decoded['event']['idCliente'];
                        //$order->codPedidoIntegracao = $decoded['event']['idPedido']; (não vem no webhook)
                        $order->dataPrevisao = $decoded['event']['dataPrevisao']; 
                        $order->numPedidoOmie = $decoded['event']['numeroPedido'];
                        //$order->codClienteIntegracao = $decoded['event']['idPedido']; (não vem no webhook)
                        $order->numContaCorrente = $decoded['event']['idContaCorrente'];
                        $order->codVendedorOmie = $decoded['author']['userId'];
                        //$order->idVendedorPloomes = $decoded['event']['idPedido']; (não vem no webhook)       
                        $order->appKey = $decoded['appKey'];

                        

                        
                        try{
                            
                            $id = ManosPrOrderHandler::saveManosPrOrder($order);
                            $message['order']['newOrder'] = 'Novo pedido salvo na base de dados de pedidos de Manos-PR id '.$id.'em: '.$current;
                           
        
                    }catch(PDOException $e){
                        echo $e->getMessage();
                        throw new PedidoDuplicadoException('<br> Pedido Nº: '.$order->numPedidoOmie.' já cadastrado no omie em: '. $current, 1500);
                    }

                    break;
                    
                case 2597402735928:
                    $appSecret = $_ENV['SECRETS_MSC'];
                    // Monta o objeto de Order Homologação com os dados do webhook
                    $order = new Manossc_order();
                    $order->idOmie = $decoded['event']['idPedido'];
                    $order->codCliente = $decoded['event']['idCliente'];
                    //$order->codPedidoIntegracao = $decoded['event']['idPedido']; (não vem no webhook)
                    $order->dataPrevisao = $decoded['event']['dataPrevisao']; 
                    $order->numPedidoOmie = $decoded['event']['numeroPedido'];
                    //$order->codClienteIntegracao = $decoded['event']['idPedido']; (não vem no webhook)
                    $order->numContaCorrente = $decoded['event']['idContaCorrente'];
                    $order->codVendedorOmie = $decoded['author']['userId'];
                    //$order->idVendedorPloomes = $decoded['event']['idPedido']; (não vem no webhook)       
                    $order->appKey = $decoded['appKey'];

                    try{

                        $id = ManosScOrderHandler::saveManosScOrder($order);
                        $message['order']['newOrder'] = 'Novo pedido salvo na base de dados de pedidos de Manos-SC id '.$id.'em: '.$current;
                       
        
                    }catch(PDOException $e){
                        echo $e->getMessage();
                        throw new PedidoDuplicadoException('<br> Pedido Nº: '.$order->numPedidoOmie.' já cadastrado no omie em: '. $current, 1500);
                    }

                    break;
                }

            
            //busca o cnpj do cliente através do id do omie
            $cnpjClient = (InvoiceHandler::clienteIdOmie($order->codCliente, $order->appKey, $appSecret));
            //busca o contactId do cliente no ploomes pelo cnpj
            (!empty($contactId = InvoiceHandler::consultaClientePloomesCnpj($cnpjClient, $baseApi, $method='get', $apiKey)))?$contactId : throw new ContactIdInexistentePloomesCRM('Não foi Localizado no Ploomes CRM cliente cadastrado com o CNPJ: '.$cnpjClient.'',1505);
            //monta a mensadem para atualizar o ploomes       
            $msg=[
                'ContactId' => $contactId,
                'Content' => 'Venda ('.intval($order->numPedidoOmie).') criado manualmente no Omie ERP.',
                'Title' => 'Pedido Criado Manualmente no Omie ERP'
            ];

            //cria uma interação no Ploomes
            (InteractionHandler::createPloomesIteraction(json_encode($msg), $baseApi, $apiKey))?$message['order']['orderInteraction'] = 'Venda ('.intval($order->numPedidoOmie).') criado manualmente no Omie ERP.' : throw new InteracaoNaoAdicionadaException('Não foi possível enviar a mensagem de pedido criado manualmente no Omie ERP ao Ploomes CRM.',1010);

        }else{
            throw new OrderControllerException('<br> Havia um orçamento e não um pedido no webhook em '. $current, 1500);
        }

        $message['order']['orderCreate'] = 'Pedido ('.intval($order->numPedidoOmie).'), criado manualmente no Omie ERP e Interação enviada ao ploomes em: '.$current;

        return $message;
    }

    public static function deletedOrder($json, $apiKey, $baseApi, OmieOrderHandler $instance)
    {   
        
        $current = $instance->current;
        $message = [];
        $decoded = json_decode($json,true);


        if(($decoded['topic'] !== "VendaProduto.Cancelada" && isset($decoded['event']['cancelada']) && $decoded['event']['cancelada'] ="S") || $decoded['topic'] !== "VendaProduto.Excluida" && !isset($decoded['event']['cancelada'])  ){
            throw new OrderControllerException('Não havia um pedido cancelado ou excluido no webhook em '.$current);
        }

        switch($decoded['appKey'])
            {
                case 2337978328686: //MHL
                    $appSecret = $_ENV['SECRETS_MHL'];
                  
                    try{
                        $id = HomologacaoOrderHandler::isIssetOrder($decoded['event']['idPedido']);
                        if(is_string($id)){
                            throw new PedidoInexistenteException('Erro ao consultar a base de dados de pedidos de Manos Homologação. Erro: '.$id. ' - '.$current, 1030);
                            }elseif(empty($id)){
                            throw new PedidoInexistenteException('Pedido não cadastrada na base de dados de pedidos de Manos Homologação. - '.$current, 1030);
                        }else{$message['order']['issetOrder'] = 'Pedido '. $decoded['event']['idPedido'] .'encontrada na base. - '.$current;
                        }

                    }catch(PedidoInexistenteException $e){
                        throw new PedidoInexistenteException($e->getMessage());
                    }
                    
                    //exclui pedido da base de dados caso seja uma venda excluída
                    if($decoded['topic'] === "VendaProduto.Excluida"){
                        try{                           
                            $message['order']['isdeleted'] = HomologacaoOrderHandler::excluiHomologacaoOrder($decoded['event']['idPedido']);

                            if(is_string($message['order']['isdeleted'])){
                                throw new PedidoNaoExcluidoException($message['order']['isdeleted']);
                            }
                        }
                        catch(PedidoNaoExcluidoException $e)
                        {
                            throw new PedidoNaoExcluidoException($e->getMessage());
                        }
                    }

                    //altera o pedido no banco para cancelado
                    try{
                        //Altera o pedido para cancelado no banco MHL
                        $altera = HomologacaoOrderHandler::alterHomologacaoOrder($decoded['event']['idPedido']);

                        if(is_string($altera)){
                            throw new PedidoCanceladoException('Erro ao consultar a base de dados de Manos Homologação. Erro: '.$altera. ' - '.$current, 1030);                     
                        }

                        $message['invoice']['iscanceled'] = 'Pedido '. $decoded['event']['idPedido'] .'cancelado com sucesso! - '.$current;
                        
                    }catch(PedidoCanceladoException $e){          
                        throw new PedidoCanceladoException($e->getMessage(), 1031);
                    }

                 break;
                    
                case 2335095664902: // MPR
                    $appSecret = $_ENV['SECRETS_MPR'];
                    
                    try{
                        $id = ManosPrOrderHandler::isIssetOrder($decoded['event']['idPedido']);
                        if(is_string($id)){
                            throw new PedidoInexistenteException('Erro ao consultar a base de dados de pedidos de Manos-PR. Erro: '.$id. ' - '.$current, 1030);
                            }elseif(empty($id)){
                            throw new PedidoInexistenteException('Pedido não cadastrada na base de dados de pedidos de Manos-PR , ou já foi cancelado. - '.$current, 1030);
                        }else{$message['order']['issetOrder'] = 'Pedido '. $decoded['event']['idPedido'] .'encontrada na base. - '.$current;
                        }

                    }catch(PedidoInexistenteException $e){
                        throw new PedidoInexistenteException($e->getMessage());
                    }

                    //exclui pedido da base de dados caso seja uma venda excluída
                    if($decoded['topic'] === "VendaProduto.Excluida"){
                        try{                           
                            $message['order']['isdeleted'] = ManosPrOrderHandler::excluiManosPrOrder($decoded['event']['idPedido']);

                            if(is_string($message['order']['isdeleted'])){
                                throw new PedidoNaoExcluidoException($message['order']['isdeleted']);
                            }
                        }
                        catch(PedidoNaoExcluidoException $e)
                        {
                            throw new PedidoNaoExcluidoException($e->getMessage());
                        }
                    }
                            
                    //altera o pedido no banco para cancelado
                    try{
                        //Altera o pedido para cancelado no banco MPR
                        $altera = ManosPrOrderHandler::alterManosPrOrder($decoded['event']['idPedido']);

                        if(is_string($altera)){
                            throw new PedidoCanceladoException('Erro ao consultar a base de dados de Manos-PR. Erro: '.$altera. 'em '.$current, 1030);                     
                        }

                        $message['invoice']['iscanceled'] = 'Pedido '. $decoded['event']['idPedido'] .'cancelado com sucesso em '.$current;
                        
                    }catch(PedidoCanceladoException $e){          
                        throw new PedidoCanceladoException($e->getMessage(), 1031);
                    }

                break;
                    
                case 2597402735928: // MSC
                    $appSecret = $_ENV['SECRETS_MSC'];
                    
                    try{
                        $id = ManosScOrderHandler::isIssetOrder($decoded['event']['idPedido']);
                        if(is_string($id)){
                            throw new PedidoInexistenteException('Erro ao consultar a base de dados de pedidos de Manos-SC. Erro: '.$id. ' - '.$current, 1030);
                            }elseif(empty($id)){
                            throw new PedidoInexistenteException('Pedido não cadastrada na base de dados de pedidos de Manos-SC , ou já foi cancelado. - '.$current, 1030);
                        }else{$message['order']['issetOrder'] = 'Pedido '. $decoded['event']['idPedido'] .'encontrada na base. - '.$current;
                        }

                    }catch(PedidoInexistenteException $e){
                        throw new PedidoInexistenteException($e->getMessage());
                    }

                    //exclui pedido da base de dados caso seja uma venda excluída
                    if($decoded['topic'] === "VendaProduto.Excluida"){
                        try{                           
                            $message['order']['isdeleted'] = ManosScOrderHandler::excluiManosScOrder($decoded['event']['idPedido']);

                            if(is_string($message['order']['isdeleted'])){
                                throw new PedidoNaoExcluidoException($message['order']['isdeleted']);
                            }
                        }
                        catch(PedidoNaoExcluidoException $e)
                        {
                            throw new PedidoNaoExcluidoException($e->getMessage());
                        }
                    }
                            
                    //altera o pedido no banco para cancelado
                    try{
                        //Altera o pedido para cancelado no banco MPR
                        $altera = ManosScOrderHandler::alterManosScOrder($decoded['event']['idPedido']);

                        if(is_string($altera)){
                            throw new PedidoCanceladoException('Erro ao consultar a base de dados de Manos-SC. Erro: '.$altera. 'em '.$current, 1030);                     
                        }

                        $message['order']['iscanceled'] = 'Pedido '. $decoded['event']['idPedido'] .'cancelado com sucesso em '.$current;
                        
                    }catch(PedidoCanceladoException $e){          
                        throw new PedidoCanceladoException($e->getMessage(), 1031);
                    }
                    
                break;
            
            }

            //busca o cnpj do cliente através do id do omie
            $cnpjClient = (InvoiceHandler::clienteIdOmie($decoded['event']['idCliente'], $decoded['appKey'], $appSecret));
            //busca o contact id do cliente no P`loomes CRM através do cnpj do cliente no Omie ERP
            $contactId = InvoiceHandler::consultaClientePloomesCnpj($cnpjClient,$baseApi, $method='GET', $apiKey);
            //monta a mensadem para atualizar o card do ploomes
            if($message['order']['isdeleted']){
                $msg=[
                    'ContactId' => $contactId,
                    'Content' => 'Pedido ('.$decoded['event']['numeroPedido'].') EXCLUÍDO no Omie ERP em: '.$current,
                    'Title' => 'Pedido EXCLUIDO no Omie ERP'
                ];

                $message['order']['deleted'] = "Pedido excluído no Omie ERP e na base de dados do sistema!";
    
            }else{

                $msg=[
                    'ContactId' => $contactId,
                    'Content' => 'Pedido ('.$decoded['event']['numeroPedido'].') cancelado no Omie ERP em: '.$current,
                    'Title' => 'Pedido Cancelado no Omie ERP'
                ];
                
                $message['order']['deleted'] = "Pedido excluído no Omie ERP e na base de dados do sistema!";

            }
            //cria uma interação no card
            (InteractionHandler::createPloomesIteraction(json_encode($msg), $baseApi, $apiKey))?$message['interactionMessage'] = 'Integração de cancelamento/exclusão de Pedido concluída com sucesso!<br> Pedido ('.$decoded['event']['numeroPedido'].') foi cancelado/excluído no Omie ERP, no sistema de integração e interação criada no cliente id: '.$contactId.' - '.$current : throw new InteracaoNaoAdicionadaException('Não foi possível gravar a mensagem de nota cancelada no Ploomes CRM',1032);




        return $message;
    }

    public static function alterOrderStage($json, $apiKey, $baseApi, OmieOrderHandler $instance){
      
        $current = $instance->current;
        $message = [];

        $decoded =json_decode($json, true);
        
        if($decoded['topic'] !== 'VendaProduto.EtapaAlterada'){
            throw new WebhookReadErrorException('Não havia mudança de etapa no webhook - '.$current, 1040);
        }

        switch($decoded['appKey']){
            case 2337978328686: //MHL
                $appSecret = $_ENV['SECRETS_MHL'];
                break;

            case 2335095664902: // MPR
                $appSecret = $_ENV['SECRETS_MPR']; 
                break;

            case 2597402735928: // MSC
                $appSecret = $_ENV['SECRETS_MSC'];
                break;
        }

        //busca o cnpj do cliente através do id do omie
        $cnpjClient = (InvoiceHandler::clienteIdOmie($decoded['event']['idCliente'], $decoded['appKey'], $appSecret));
        //busca o contact id do cliente no P`loomes CRM através do cnpj do cliente no Omie ERP
        $contactId = InvoiceHandler::consultaClientePloomesCnpj($cnpjClient,$baseApi, $method='GET', $apiKey);
        //monta a mensadem para atualizar o card do ploomes
        $msg=[
            'ContactId' => $contactId,
            'Content' => 'Etapa dp pedido ('.$decoded['event']['numeroPedido'].') ALTERADA no Omie ERP para '.$decoded['event']['etapaDescr'].' em: '.$current,
            'Title' => 'Etapa do pedido ALTERADA no Omie ERP'
        ];
        //cria uma interação no card
        (InteractionHandler::createPloomesIteraction(json_encode($msg), $baseApi, $apiKey))?$message['order']['interactionMessage'] = 'Etapa do pedido alterada com sucesso!<br> Etapa do pedido ('.$decoded['event']['numeroPedido'].') foi alterada no Omie ERP para '.$decoded['event']['etapaDescr'].'! - '.$current : throw new InteracaoNaoAdicionadaException('Não foi possível criar interação no Ploomes CRM ',1042);

        if ($decoded['event']['etapa'] === '60' && !empty($decoded['event']['codIntPedido'])){
            

            $orderId = $decoded['event']['codIntPedido'];
            $method = 'get';

            $orderPloomes = DealHandler::requestOrder($orderId, $baseApi, $method, $apiKey);
            if($orderPloomes[0]->Id == $orderId){
                
                $method = 'patch';
                $stageId= ['StageId'=>40011765];
                $stage = json_encode($stageId);
                (InvoiceHandler::alterStageOrder($stage, $orderId, $baseApi, $method, $apiKey))?$message['order']['alterStagePloomes'] = 'Estágio do pedido de venda do Ploomes CRM alterado com sucesso! \n Id Pedido Ploomes: '.$orderPloomes[0]->Id.' \n Card Id: '.$orderPloomes[0]->DealId.' \n omieOrderHandler - '.$current : $message['order']['alterStagePloomes'] = 'Não foi possível mudar o estágio do pedido no Ploomes CRM. Pedido não foi encontrado no Ploomes CRM. - omieOrderHandler - '.$current;
            }

            $message['order']['alterStagePloomes'] = 'Não foi possível mudar o estágio da venda no Ploomes CRM, possívelmente o pedido foi criado direto no Omie ERP. - omieOrderHandler - '.$current;

        }

        $message['order']['alterStage'] = 'Integração de mudança de estágio de pedido de venda no omie ERP concluída com sucesso!  - '.$current;

        return $message;

    }
    

}