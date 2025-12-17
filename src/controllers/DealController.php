<?php
namespace src\controllers;

use \core\Controller;

use src\handlers\DealHandler;
use src\handlers\LoginHandler;
use src\exceptions\WebhookReadErrorException;
use src\functions\DiverseFunctions;
use src\models\Deal;
use src\services\DatabaseServices;
use src\services\PloomesServices;
use src\services\RabbitMQServices;

class DealController extends Controller {
    
    private $loggedUser;
    private $ploomesServices;
    private $databaseServices;
    private $rabbitMQServices;

    public function __construct($args)
    {
        $ploomesBase = $args['Tenancy']['ploomes_bases'][0];
        $args['Tenancy']['vhost'][0]['key'] = $args['Tenancy']['tenancies']['cpf_cnpj'];
        $vhost = $args['Tenancy']['vhost'][0];
        $this->ploomesServices = new PloomesServices($ploomesBase);
        $this->databaseServices = new DatabaseServices();
        $this->rabbitMQServices = new RabbitMQServices($vhost);
    }

    private function getDealHandler(): DealHandler
    {   
        $dealHandler = new DealHandler($this->ploomesServices, $this->databaseServices);
        return $dealHandler;
    }

    public function ploomesDeal($args)
    {
        
        $message = [];
        $idUser = $args['Tenancy']['tenancies']['user_id'];
        $json = json_encode($args['body']);

        try{

            $action = DiverseFunctions::findAction($args);
            
            if($action['action'] !== 'update'){
                throw new WebhookReadErrorException('não era um Negócio alterado');
            }          
            
            $dealHandler = $this->getDealHandler($args);
            $confirmAction = $dealHandler->confirmActionDealFromSaveWebhook($args);
            if($confirmAction){
       
                 // $rk = origem.entidade.ação
                $rk = array('Ploomes', 'Deals');
                $this->rabbitMQServices->publicarMensagem('deals_exc', $rk, 'ploomes_deals',  $json);
                $response = $dealHandler->saveWebhook($json, $idUser);

            }else{
                throw new WebhookReadErrorException('Não era um Negócio com lead qualificado');
            }            

            if ($response > 0) {

                $message =[
                    'status_code' => 200,
                    'status_message' => 'SUCCESS: '. $response['msg'],
                ];
                
            }

        }catch(WebhookReadErrorException $e){        
        }
        finally{
            if(isset($e)){
                $message =[
                    'status_code' => 500,
                    'status_message' => $e->getMessage(),
                ];

                ob_start();
                var_dump($e->getMessage());
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/log.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);

                return print 'ERROR:'. $message['status_code'].'. MESSAGE: ' .$message['status_message'];
            }
             //grava log
             ob_start();
             print_r($message);
             $input = ob_get_contents();
             ob_end_clean();
             file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
             
             return print $message['status_message'];
           
        }        
    }
    
    public function processPloomesDeal($args){
        
        header('Content-Type: application/json');
        $message = [];
        // processa o webhook 
        try {

            $dealHandler = $this->getDealHandler($args);
            $response = $dealHandler->startProcess($args);
            
            $message = [
                'status_code' => 200,
                'status_message' => $response,
            ];
             
            //grava log
            ob_start();
            print_r($message);
            $input = ob_get_contents();
            ob_end_clean();
            file_put_contents('./assets/deals.log', $input . PHP_EOL, FILE_APPEND);
            //return $message['status_message'];
        
        }catch(WebhookReadErrorException $e){                       
        }
        finally{
            if(isset($e))
            {
                ob_start();
                var_dump($e->getMessage());
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/log.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);

                $message =[
                    'status_code' => 500,
                    'status_message' => $e->getMessage(),
                ];
            }
    
            print json_encode($message);
        }

    } 

}