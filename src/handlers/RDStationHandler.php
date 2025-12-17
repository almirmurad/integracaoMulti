<?php

namespace src\handlers;

use Exception;
use PDOException;

use src\exceptions\WebhookReadErrorException;
use src\functions\DiverseFunctions;
use src\functions\OmnismartFunctions;
use src\models\Omnichannel_webhook;
use src\models\RDStation_webhook;
use src\models\User;
use src\services\DatabaseServices;
use src\functions\RDStationFunctions;
use src\services\PloomesServices;
use src\services\RDStationServices;

class RDStationHandler
{
    private RDStationServices $rdstationServices;
    private PloomesServices $ploomesServices;
    private DatabaseServices $databaseServices;
    private $current;
    
    public function __construct(PloomesServices $ploomesServices, DatabaseServices $databaseServices, RDStationServices $rdstationServices)
    {
        $this->rdstationServices = $rdstationServices;
        $this->ploomesServices = $ploomesServices;
        $this->databaseServices = $databaseServices;
        $date = date('d/m/Y H:i:s');
        $this->current = $date;
    }

    //SALVA O WEBHOOK NO BANCO DE DADOS
    public function saveWebhook($json, $idUser)
    { 
        $decoded = json_decode($json, true);

            $origem = (!isset($decoded['Entity']))?'RDStation':'Ploomes';
            //infos do webhook
            $webhook = new RDStation_webhook();
            $webhook->json = $json; //webhook 
            $webhook->status = 1; // recebido
            $webhook->user_id = $idUser; 
            $webhook->result = 'Rececibo';
            $webhook->entity = $decoded['Entity'] ?? $decoded['type'] ?? 'entidade';
            $webhook->origem = $origem;
            
            $id = $this->databaseServices->saveRDStationWebhook($webhook);
            return ['id'=>$id, 'msg' =>'Webhook Salvo com sucesso id = '.$id .'às '.$this->current];
            //    return ($id = $this->databaseServices->saveOmnichannelWebhook($webhook)) ? ['id'=>$id, 'msg' =>'Webhook Salvo com sucesso id = '.$id .'às '.$this->current] : 0;

        {
            
        }

        //salva o hook no banco
        // return ($id = $this->databaseServices->saveOmnichannelWebhook($webhook)) ?  : ;

    }

    //PROCESSA E CRIA O cliente. CHAMA O REPROCESS CASO DE ERRO
    public function startProcess($args)
    {   
        $action = DiverseFunctions::findAction($args);
        
        switch($action['origem'])
        {
            case 'RDToCRM':
                return RDStationFunctions::processRDStationPloomes($args, $this->ploomesServices, $this->rdstationServices, $action);
                break;
            // case 'CRMToOMNI':
            //     return RDStationFunctions::processPloomesOmnismart($args, $this->ploomesServices, $this->rdstationServices, $action);
            //     break;
                
        }
        
        // if(isset($action['origem']) && $action['origem'] === 'OMNIToCRM'){
           
        //     return OmnismartFunctions::processOminsmartPloomes($args, $this->ploomesServices, $this->omnismartServices, $action);                                                   
        // }
        
       // return OmnismartFunctions::processContactErpToCrm($args, $this->ploomesServices, $this->formatter, $action);
    }
    
    public static function getClientBySubdomain($subdomain){
        try{
            $user = User::select('*')
            ->where('subdomain', $subdomain)
            ->first();

            if (!$user) {
                throw new Exception('usuário não encontrado! ', 500);
            }

            return $user;

        }catch(PDOException $e){
            throw new WebhookReadErrorException('Erro ao buscar dados do Tenancy no banco de dados: ' .$e->getMessage());
        }
    }

    public static function getClientById($id){
        try{
            $user = User::select('*')
            ->where('id', $id)
            ->first();

            if (!$user) {
                throw new Exception('usuário não encontrado! ', 500);
            }

            return $user;

        }catch(PDOException $e){
            throw new WebhookReadErrorException('Erro ao buscar dados do Tenancy no banco de dados: ' .$e->getMessage());
        }
    }

}