<?php

namespace src\handlers;

use Exception;
use PDOException;
use src\contracts\ErpFormattersInterface;
use src\exceptions\WebhookReadErrorException;
use src\functions\ClientsFunctions;
use src\functions\DiverseFunctions;
use src\functions\OmnismartFunctions;
use src\models\Omnichannel_webhook;
use src\models\User;
use src\services\DatabaseServices;
use src\services\OmnismartServices;
use src\services\PloomesServices;


class OmnismartHandler
{
    private OmnismartServices $omnismartServices;
    private PloomesServices $ploomesServices;
    private DatabaseServices $databaseServices;
    private $current;
    
    public function __construct(PloomesServices $ploomesServices, DatabaseServices $databaseServices, OmnismartServices $omnismartServices)
    {
        $this->omnismartServices = $omnismartServices;
        $this->ploomesServices = $ploomesServices;
        $this->databaseServices = $databaseServices;
        $date = date('d/m/Y H:i:s');
        $this->current = $date;
    }

    //SALVA O WEBHOOK NO BANCO DE DADOS
    public function saveClientHook($json, $idUser)
    { 
        $decoded = json_decode($json, true);
        
        $origem = (!isset($decoded['Entity']))?'Omnismart':'Ploomes';
        //infos do webhook
        $webhook = new Omnichannel_webhook();
        $webhook->json = $json; //webhook 
        $webhook->status = 1; // recebido
        $webhook->user_id = $idUser; 
        $webhook->result = 'Rececibo';
        $webhook->entity = $decoded['Entity'] ?? $decoded['type'];
        $webhook->origem = $origem;
        //salva o hook no banco
        return ($id = $this->databaseServices->saveOmnichannelWebhook($webhook)) ? ['id'=>$id, 'msg' =>'Webhook Salvo com sucesso id = '.$id .'às '.$this->current] : 0;

    }

    //PROCESSA E CRIA O cliente. CHAMA O REPROCESS CASO DE ERRO
    public function startProcess($args)
    {   
        $action = DiverseFunctions::findAction($args);
        
        if(isset($action['origem']) && $action['origem'] === 'OMNIToCRM'){
           
            return OmnismartFunctions::processOminsmartPloomes($args, $this->ploomesServices, $this->omnismartServices, $action);                                                   
        }
        
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