<?php

namespace src\handlers;

use Exception;
use PDOException;
use src\contracts\ErpFormattersInterface;
use src\exceptions\WebhookReadErrorException;
use src\functions\ClientsFunctions;
use src\models\User;
use src\models\Webhook;
use src\services\DatabaseServices;
use src\services\PloomesServices;


class ClientHandler
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
    public function saveClientHook($json, $idUser)
    { 

        $decoded = json_decode($json, true);
      
        $origem = (!isset($decoded['Entity']))?'Omie':'Ploomes';
        //infos do webhook
        $webhook = new Webhook();
        $webhook->json = $json; //webhook 
        $webhook->status = 1; // recebido
        $webhook->user_id = $idUser; 
        $webhook->result = 'Rececibo';
        $webhook->entity = $decoded['Entity']??'Contacts';
        $webhook->origem = $origem;
        //salva o hook no banco
        return ($id = $this->databaseServices->saveWebhook($webhook)) ? ['id'=>$id, 'msg' =>'Webhook Salvo com sucesso id = '.$id .'às '.$this->current] : 0;

    }

    //PROCESSA E CRIA O cliente. CHAMA O REPROCESS CASO DE ERRO
    public function startProcess($args)
    {   
        $action = ClientsFunctions::findAction($args);
       
        if($action){
            //se tiver action cria o objeto de contacs
            switch($action){
                case 'createCRMToERP':
                    $contact = $this->formatter->createContactObjFromPloomesCrm($args, $this->ploomesServices);
                    $process = $this->formatter->createContact($contact, $this->ploomesServices);
                    break;
                case 'updateCRMToERP':
                    $contact = $this->formatter->createContactObjFromPloomesCrm($args, $this->ploomesServices);
                    $process = $this->formatter->updateContactCRMToERP($contact, $this->ploomesServices);
                    break;
                case 'createERPToCRM':
                    $contact  = $this->formatter->createClientErpToCrmObj($args);
                    $contactJson = $this->formatter->createPloomesContactFromErpObject($contact, $this->ploomesServices);
                    $process = $this->formatter->createContactERP($contactJson, $this->ploomesServices);                   
                    break;
                case 'updateERPToCRM':
                    $contact  = $this->formatter->createClientErpToCrmObj($args);
                    $contactJson = $this->formatter->createPloomesContactFromErpObject($contact, $this->ploomesServices);
                    $process = $this->formatter->updateContactERP($contactJson, $contact, $this->ploomesServices);                 
                    break;
            } 
        }
        return self::response($args, $contact, $process);
    }

    //Trata a respostas para devolver ao controller
    public function response($args, $contact, $process)
    {
        $decoded=$args['body'];
        $origem = (!isset($decoded['Entity']))?'Omie':'Ploomes';
        //Quando a origem é omie x ploomes então apenas uma base para uma base
        if($origem === 'Omie'){

            if(!empty($process['error'])){
               
                if($decoded['topic'] === 'ClienteFornecedor.Incluido'){
                    throw new WebhookReadErrorException($process['error']. ' em: '.$this->current, 500);
                }elseif($decoded['topic'] === 'ClienteFornecedor.Excluido'){
                    throw new WebhookReadErrorException($process['error']. ' em: '.$this->current, 500);
                }elseif($decoded['topic'] === 'ClienteFornecedor.Alterado'){
                    throw new WebhookReadErrorException($process['error']. ' em: '.$this->current, 500);
                }
            }
            return $process;//card processado cliente criado no Omie retorna mensagem winDeal para salvar no log
            
        }

        //quando a origem é ploomes x omie verifica quantas bases haviam para integrar
        $totalBasesIntegrar = 0;
        foreach($contact->basesFaturamento as $bf){
            if(isset($bf['integrar']) && $bf['integrar']>0){
                $totalBasesIntegrar++;
            }
        }
  
        //sucesso absoluto contato cadastrado em todas as bases que estavam marcadas para integrar
        if(count($process['success']) == $totalBasesIntegrar){
            
            $process['success'] = 'Sucesso: ação executada em todos os clientes';
                
            return $process;//card processado cliente criado no Omie retorna mensagem winDeal para salvar no log
        //falha absoluta erro no cadastramento do contato em todas as bases
        }elseif(count($process['error']) == $totalBasesIntegrar){
            
            $m = '';
            foreach($process['error'] as $error){
                $m .= $error .  "\r\n";
            }
            throw new WebhookReadErrorException('Erro ao gravar cliente(s): '.$m.' em: ' .$this->current, 500);
        
        //parcial cadastrou eum alguma(s) bases e em outara(s) não
        }else{
            $m = '';
            foreach($process['error'] as $error){
                $m .= $error .  "\r\n";
            }
            throw new WebhookReadErrorException('Nem todos os clientes foram cadastrados, houveram falhas as gravar clientes: '.$m . 'em: '. $this->current, 500);
        }
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
            throw new Exception('Erro ao buscar dados do Tenancy no banco de dados: ' .$e->getMessage());
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
            throw new Exception('Erro ao buscar dados do Tenancy no banco de dados: ' .$e->getMessage());
        }
    }

}