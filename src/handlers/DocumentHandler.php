<?php

namespace src\handlers;

use src\contracts\ErpFormattersInterface;
use src\exceptions\PedidoInexistenteException;

use src\exceptions\WebhookReadErrorException;
use src\functions\CustomFieldsFunction;
use src\functions\DiverseFunctions;
use src\functions\DocumentsFunction;
use src\functions\OrdersFunction;
use src\services\DatabaseServices;
use src\services\PloomesServices;
use stdClass;

class DocumentHandler
{
    private ErpFormattersInterface $formatter;
    private PloomesServices $ploomesServices;
    private DatabaseServices $databaseServices;
    private $current;

    public function __construct(
        PloomesServices $ploomesServices, DatabaseServices $databaseServices, ErpFormattersInterface $formatter
    )
    {
        $this->formatter = $formatter;
        $this->ploomesServices = $ploomesServices;
        $this->databaseServices = $databaseServices;
        $this->current = date('d/m/Y H:i:s');

    }

    //SALVA O WEBHOOK NO BANCO DE DADOS
    public function saveDealHook($json, $idUser)
    {
        $decoded = json_decode($json, true);
        $origem = (!isset($decoded['Entity']))?'ERP':'CRM';

        //infos do webhook
        $webhook = new stdClass();
        $webhook->json = $json; //webhook 
        $webhook->user_id = $idUser;
        $webhook->status = 1; // recebido
        $webhook->result = 'Rececibo';
        $webhook->entity = $decoded['Entity']??'Orders';
        $webhook->origem = $origem;

        if($this->databaseServices->saveWebhook($webhook))
        {
            $m= [ 'msg' =>'Webhook Salvo com sucesso id = às '.$this->current];
            return $m;
        } 
    }

    //PROCESSA E CRIA O PEDIDO.
    public function startProcess($args)
    {   
        $action = DiverseFunctions::findAction($args);
        
        if(isset($action['origem']) && $action['origem'] === 'CRMToERP'){
            
            return DocumentsFunction::processDocumentsCrmToErp($args, $this->ploomesServices, $this->formatter, $action);                                                   
        }
        
        return OrdersFunction::processOrderErpToCrm($args, $this->ploomesServices, $this->formatter, $action);
        
    } 

    public function detectLoop($args)
    {
       return $this->formatter->detectLoop($args);
    }
 
    
}