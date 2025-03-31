<?php

namespace src\handlers;

use src\exceptions\WebhookReadErrorException;
use src\functions\ProductsFunctions;
use src\models\Webhook;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;
use src\services\ProductServices;

class ProductHandler
{
    private $current;
    private $ploomesServices;
    private $omieServices;
    private $databaseServices;

    public function __construct(PloomesServices $ploomesServices, OmieServices $omieServices, DatabaseServices $databaseServices)
    {
        $this->ploomesServices = $ploomesServices;
        $this->omieServices = $omieServices;
        $this->databaseServices = $databaseServices;
        $date = date('d/m/Y H:i:s');
        $this->current = $date;
    }

    //SALVA O WEBHOOK NO BANCO DE DADOS
    public function saveProductHook($json)
    { 
        $decoded = json_decode($json, true);
      
        $origem = (!isset($decoded['Entity']))?'Omie':'Ploomes';
        //infos do webhook
        $webhook = new Webhook();
        $webhook->json = $json; //webhook 
        $webhook->status = 1; // recebido
        $webhook->result = 'Rececibo';
        $webhook->entity = $decoded['Entity']??'Products';
        $webhook->origem = $origem;
        //salva o hook no banco
        return ($id = $this->databaseServices->saveWebhook($webhook)) ? ['id'=>$id, 'msg' =>'Webhook Salvo com sucesso id = '.$id .'às '.$this->current] : 0;

    }

    //PROCESSA E CRIA O cliente. CHAMA O REPROCESS CASO DE ERRO
    public function startProcess($json)
    {   
        //$webhook = $this->databaseServices->getWebhook($status, $entity);
        
        //$status = 2; //processando
        //$alterStatus = $this->databaseServices->alterStatusWebhook($webhook['id'], $status);
        
        //talvez o ideal fosse devolver ao controller o ok de que o processo foi iniciado e um novo processo deve ser inciado 
       // if($alterStatus){
      
            $action = ProductsFunctions::findAction($json);

            if($action){
                //se tiver action cria o objeto de contacs
                switch($action){
                    case 'createERPToCRM':
                        $product  = ProductsFunctions::createOmieObj($json, $this->omieServices);
                        $process = ProductServices::createProductFromERPToCRM($product);
                        break;
                    case 'updateERPToCRM':
                        $product  = ProductsFunctions::createOmieObj($json, $this->omieServices);
                        $productJson = ProductsFunctions::createPloomesProductFromOmieObject($product, $this->ploomesServices, $this->omieServices);
                        $process = ProductServices::updateProductFromERPToCRM($productJson, $product, $this->ploomesServices);
                        break;
                    case 'deleteERPToCRM':
                        $product = ProductsFunctions::createOmieObj($json, $this->omieServices);
                        $process = ProductServices::deleteProductFromERPToCRM($product, $this->ploomesServices);
                        break;
                    case 'stockMovementERPtoCRM':
                        $process = ProductsFunctions::moveStock($json, $this->ploomesServices);
                        //$process = ProductServices::attStockTablePloomes($stockTable, $this->ploomesServices);
                        break;
                    case 'createCRMToERP' || 'updateCRMToERP':
                        //quando o produto é criado no crm ele apenas atualiza o código de integração na base correta
                        $product = ProductsFunctions::createProductFromPloomesWebhook($json);
                        $process = ProductServices::setProductIntegrationCode($product, $this->omieServices);
                        break;
                } 
            }

           // return self::response($webhook, $process);

            return $process;

       // }
                 
    }

    //Trata a respostas para devolver ao controller
    // public function response($webhook, $process)
    // {


    //         if(!empty($process['error'])){

    //             $status = 4; //falhou
    //             $alterStatus = $this->databaseServices->alterStatusWebhook($webhook['id'], $status);
                
    //             //$reprocess = Self::reprocessWebhook($webhook);
    //             $this->databaseServices->registerLog($webhook['id'], $process['error'], $webhook['entity']); 
    //             throw new WebhookReadErrorException($process['error']. 'Verifique em logs do sistema. Webhook id: '.$webhook['id']. ' em: '.$this->current, 500);

    //             //$decoded = json_decode($webhook['json'],true);
            
    //             // if($decoded['topic'] === 'Produto.Incluido'){
    //             //     throw new WebhookReadErrorException($process['error']. 'Verifique em logs do sistema. Webhook id:'. $webhook['id']. ' em: '.$this->current, 500);
    //             // }elseif($decoded['topic'] === 'Produto.Excluido'){
    //             //     throw new WebhookReadErrorException($process['error']. 'Verifique em logs do sistema. Webhook id: '.$webhook['id']. ' em: '.$this->current, 500);
    //             // }elseif($decoded['topic'] === 'Produto.Alterado'){
    //             //     throw new WebhookReadErrorException($process['error']. 'Verifique em logs do sistema. Webhook id: '.$webhook['id']. ' em: '.$this->current, 500);
    //             // }elseif($decoded['topic'] === 'Produto.MovimentacaoEstoque'){
    //             //     throw new WebhookReadErrorException($process['error']. 'Verifique em logs do sistema. Webhook id: '.$webhook['id']. ' em: '.$this->current, 500);
    //             // }
    //         }

    //         $status = 3; //Success
    //         $alterStatus = $this->databaseServices->alterStatusWebhook($webhook['id'], $status);
            
    //         if($alterStatus){
    //             $this->databaseServices->registerLog($webhook['id'], $process['success'], $webhook['entity']);
                
    //             return $process;//card processado cliente criado no Omie retorna mensagem winDeal para salvar no log
    //         }


         
        

    // }
    

}