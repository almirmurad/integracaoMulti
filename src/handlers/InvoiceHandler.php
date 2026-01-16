<?php

namespace src\handlers;

use src\exceptions\WebhookReadErrorException;
use src\functions\DiverseFunctions;
use src\models\Webhook;
use src\services\DatabaseServices;
use src\services\PloomesServices;
use src\contracts\ErpFormattersInterface;
use src\functions\InvoicesFunctions;
class InvoiceHandler
{
    private ErpFormattersInterface $formatter;
    private PloomesServices $ploomesServices;
    private DatabaseServices $databaseServices;
    private $current;
    
    public function __construct(PloomesServices $ploomesServices, DatabaseServices $databaseServices, ErpFormattersInterface $formatter)
    {       
        $this->formatter = $formatter;
        $this->ploomesServices = $ploomesServices;
        $this->databaseServices = $databaseServices;
        $date = date('d/m/Y H:i:s');
        $this->current = $date;
    }

    public function saveInvoiceHook($json, $idUser){

        $decoded = json_decode($json, true);
        
        $origem = (!isset($decoded['Entity']))?'Erp':'Ploomes';  
        //infos do webhook
        $webhook = new Webhook();
        $webhook->json = $json; //webhook 
        $webhook->status = 1; // recebido
        $webhook->user_id = $idUser;
        $webhook->result = 'Rececibo';
        $webhook->entity = $decoded['Entity'] ?? 'Invoices';
        $webhook->origem = $origem;
        //salva o hook no banco
        return ($id = $this->databaseServices->saveWebhook($webhook)) ? ['id'=>$id, 'msg' =>'Webhook Salvo com sucesso id = '.$id .'às '.$this->current] : 0;
    }

    //LÊ O WEBHOOK E COM A NOTA FATURADA
    public function startProcess($args)
    {   
        $action = DiverseFunctions::findAction($args);
        $current = $this->current;
        // Array de retorno
        $message = [];      
        
        if(empty($action['action'])){
            throw new WebhookReadErrorException('Não foi possível ler o Webhook ou não existe nota fiscal emitida! - '. $current,1020);
        }

        if($action['action'] === 'csFaturado'){
            throw new WebhookReadErrorException('Não tratamos dados de contrato de serviço no momento - '. $current,1020);
        }
        
        if($action['action'] === 'osFaturada' || $action['action'] === 'venFaturada')
        {
            
            $message['success'] = InvoicesFunctions::processOrderInvoicedErpToCrm($args, $this->ploomesServices, $this->formatter, $action);


        }else{

            $message['success'] = InvoicesFunctions::processInvoiceErpToCrm($args, $this->ploomesServices, $this->formatter, $action);
        }




        return $message;
        
    }

}