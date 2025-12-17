<?php

namespace src\handlers;

use src\contracts\ErpFormattersInterface;
use src\exceptions\WebhookReadErrorException;
use src\functions\DiverseFunctions;
use src\functions\ProductsFunctions;
use src\models\Webhook;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;
use src\services\ProductServices;

class ProductHandler
{
    private ErpFormattersInterface $formatter;
    private $current;
    private $ploomesServices;
    private $databaseServices;

    public function __construct(PloomesServices $ploomesServices, DatabaseServices $databaseServices,ErpFormattersInterface $formatter)
    {
        $this->formatter = $formatter;
        $this->ploomesServices = $ploomesServices;
        $this->databaseServices = $databaseServices;
        $date = date('d/m/Y H:i:s');
        $this->current = $date;
    }

    //SALVA O WEBHOOK NO BANCO DE DADOS
    public function saveProductHook($json,$idUser)
    { 
        $decoded = json_decode($json, true);
       
        $origem = (!isset($decoded['Entity']))?'Omie':'Ploomes';
        
        //infos do webhook
        $webhook = new Webhook();
        $webhook->json = $json; //webhook 
        $webhook->status = 1; // recebido
        $webhook->user_id = $idUser; 
        $webhook->result = 'Rececibo';
        $webhook->entity = $decoded['Entity'] ?? 'Products';
        $webhook->origem = $origem;
        //salva o hook no banco
        return ($id = $this->databaseServices->saveWebhook($webhook)) ? ['id'=>$id, 'msg' =>'Webhook Salvo com sucesso id = '.$id .'às '.$this->current] : 0;

    }

    //PROCESSA E CRIA O cliente. CHAMA O REPROCESS CASO DE ERRO
    public function startProcess($args)
    {   
        //$action = $this->formatter->findAction($args);
        $action = DiverseFunctions::findAction($args);
        if(isset($action['origem']) && $action['origem'] === 'CRMToERP'){
            
            // return ProductsFunctions::processProductCrmToErp($args, $this->ploomesServices, $this->formatter, $action);                                                   
        }
        
        return ProductsFunctions::processProductErpToCrm($args, $this->ploomesServices, $this->formatter, $action);
                 
    }

   
    

}