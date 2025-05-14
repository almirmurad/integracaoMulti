<?php

namespace src\handlers;

use src\contracts\ErpFormattersInterface;
use src\exceptions\WebhookReadErrorException;
use src\functions\DiverseFunctions;
use src\functions\ServicesFunctions;
use src\models\Webhook;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;
use src\services\ProductServices;
use src\services\ServiceServices;

class ServiceHandler
{
    private ErpFormattersInterface $formatter;
    private $current;
    private $ploomesServices;
    private $databaseServices;

    public function __construct(PloomesServices $ploomesServices, DatabaseServices $databaseServices, ErpFormattersInterface $formatter)
    {
        $this->formatter = $formatter;
        $this->ploomesServices = $ploomesServices;
        $this->databaseServices = $databaseServices;
        $date = date('d/m/Y H:i:s');
        $this->current = $date;
    }
    

    //SALVA O WEBHOOK NO BANCO DE DADOS
    public function saveServiceHook($json, $idUser)
    { 
        $decoded = json_decode($json,true);
      
        $origem = (!isset($decoded['Entity']))?'Omie':'Ploomes';
        //infos do webhook
        $webhook = new Webhook();
        $webhook->json = $json; //webhook 
        $webhook->status = 1; // recebido
        $webhook->user_id = $idUser; 
        $webhook->result = 'Rececibo';
        $webhook->entity = $decoded['Entity']??'Services';
        $webhook->origem = $origem;
        //salva o hook no banco
        return ($id = $this->databaseServices->saveWebhook($webhook)) ? ['id'=>$id, 'msg' =>'Webhook Salvo com sucesso id = '.$id .'às '.$this->current] : 0;

    }

    //PROCESSA E CRIA O cliente. CHAMA O REPROCESS CASO DE ERRO
    public function startProcess($args)
    {   
        //$action = $this->formatter->findAction($args);
        $action = DiverseFunctions::findAction($args);
                    
        return ServicesFunctions::processServiceErpToCrm($args, $this->ploomesServices, $this->formatter, $action);

        // if($action){
        //     //se tiver action cria o objeto de contacs
        //     switch($action){
        //         case 'createERPToCRM':
        //             $service  = ServicesFunctions::createOmieObj($json);
        //             $process = ServiceServices::createServiceFromERPToCRM($service);
        //             break;
        //         case 'updateERPToCRM':
        //             $service  = ServicesFunctions::createOmieObj($json);
        //             $serviceJson = ServicesFunctions::createPloomesServiceFromOmieObject($service, $this->ploomesServices, $this->omieServices);
        //             $process = ServiceServices::updateServiceFromERPToCRM($serviceJson, $service, $this->ploomesServices);
        //             break;
        //         case 'deleteERPToCRM':
        //             $service = ServicesFunctions::createOmieObj($json);
        //             $process = ServiceServices::deleteServiceFromERPToCRM($service, $this->ploomesServices);
        //             break;
        //     } 
        // }
        
        // return $process;

        
                 
    }

    //Trata a respostas para devolver ao controller
    // public function response($webhook, $service, $process)
    // {
    //     if($webhook['origem'] === 'Omie'){
            
    //         if(!empty($process['error'])){
    //             $status = 4; //falhou
    //             $alterStatus = $this->databaseServices->alterStatusWebhook($webhook['id'], $status);
            
    //             //$reprocess = Self::reprocessWebhook($webhook);
    //             $this->databaseServices->registerLog($webhook['id'], $process['error'], $webhook['entity']); 
              
    //             $decoded = json_decode($webhook['json'],true);
            
    //             if($decoded['topic'] === 'Servico.Incluido'){
    //                 print 'entrou aqui';
    //                 throw new WebhookReadErrorException($process['error']. 'Verifique em logs do sistema. Webhook id:'. $webhook['id']. ' em: '.$this->current, 500);
    //             }elseif($decoded['topic'] === 'Servico.Excluido'){
    //                 throw new WebhookReadErrorException($process['error']. 'Verifique em logs do sistema. Webhook id: '.$webhook['id']. ' em: '.$this->current, 500);
    //             }elseif($decoded['topic'] === 'Servico.Alterado'){
    //                 throw new WebhookReadErrorException($process['error']. 'Verifique em logs do sistema. Webhook id: '.$webhook['id']. ' em: '.$this->current, 500);
    //             }
    //         }

    //         $status = 3; //Success
    //         $alterStatus = $this->databaseServices->alterStatusWebhook($webhook['id'], $status);
            
    //         if($alterStatus){
    //             $this->databaseServices->registerLog($webhook['id'], $process['success'], $webhook['entity']);
                
    //             return $process;//card processado cliente criado no Omie retorna mensagem winDeal para salvar no log
    //         }
    //     }

    //     //verifica quantas bases haviam para integrar
    //     $totalBasesIntegrar = 0;
    //     foreach($service->basesFaturamento as $bf){
    //         if($bf['integrar']>0){
    //             $totalBasesIntegrar++;
    //         }
    //     }
    //     //sucesso absoluto contato cadastrado em todas as bases
    //     if(count($process['success']) == $totalBasesIntegrar){
    //         $status = 3; //Success
    //         $alterStatus = $this->databaseServices->alterStatusWebhook($webhook['id'], $status);
    //         foreach($process['success'] as $success){

    //             $this->databaseServices->registerLog($webhook['id'], $success, $webhook['entity']);
    //         }

    //         if($alterStatus){
                
    //             return $process;//card processado cliente criado no Omie retorna mensagem winDeal para salvar no log
    //         }
    //         //falha absoluta erro no cadastramento do contato em todas as bases
    //     }elseif(count($process['error']) == $totalBasesIntegrar){
    //         $status = 4; //falhou
    //         $alterStatus = $this->databaseServices->alterStatusWebhook($webhook['id'], $status);
            
    //         //$reprocess = Self::reprocessWebhook($webhook);

    //         //if($reprocess['error']){
    //             foreach($process['error'] as $error){
                    
    //                 $this->databaseServices->registerLog($webhook['id'], $error, $webhook['entity']); 

    //             }
    //             throw new WebhookReadErrorException('Erro ao gravar cliente(s) verifique em logs do sistema. Webhook id: '.$webhook['id']. ' em: '.$this->current, 500);
                
    //             //return $reprocess['error'];

    //         //}
            
    //     }else{

    //         $status = 5; //parcial cadastrou eum alguma(s) bases e em outara(s) não
    //         $alterStatus = $this->databaseServices->alterStatusWebhook($webhook['id'], $status);
            
    //         //$reprocess = Self::reprocessWebhook($webhook);

    //         //if($reprocess['error']){
    //             foreach($process['success'] as $success){

    //                 $this->databaseServices->registerLog($webhook['id'], $success, $webhook['entity']);
    //             }
    //             foreach($process['error'] as $error){

    //                 $this->databaseServices->registerLog($webhook['id'], $error, $webhook['entity']);
    //             }
                
    //             throw new WebhookReadErrorException('Nem todos os clientes foram cadastrados, houveram falhas as gravar clientes, verifique os logs do sistema. '. $this->current, 500);
                
    //             // return $process;

    //         //}

    //     }
    // }
    

}