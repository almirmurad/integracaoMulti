<?php
namespace src\controllers;

use core\Controller;
use src\exceptions\WebhookReadErrorException;
use src\factories\ErpFormatterFactory;
use src\handlers\ClientHandler;
use src\handlers\LoginHandler;
use src\handlers\ProductHandler;
use src\handlers\ServiceHandler;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;
use src\services\RabbitMQServices;

class ServiceController extends Controller {
    
    private $loggedUser;
    private $ploomesServices;
    private $omieServices;
    private $databaseServices;
    private $rabbitMQServices;
    private $formatter;

    public function __construct($args)
    {
        
        $ploomesBase = $args['Tenancy']['ploomes_bases'][0];

        $args['Tenancy']['vhost'][0]['key'] = $args['Tenancy']['tenancies']['cpf_cnpj'];
        $vhost = $args['Tenancy']['vhost'][0];
        $this->ploomesServices = new PloomesServices($ploomesBase);
        $this->databaseServices = new DatabaseServices();
        // $this->rabbitMQServices = new RabbitMQServices($vhost);
    }

    private function getServiceHandler($args): ServiceHandler
    {
        $this->formatter = ErpFormatterFactory::create($args);
        $serviceHandler = new ServiceHandler($this->ploomesServices, $this->databaseServices, $this->formatter);
        
        return $serviceHandler;
    }

    //recebe webhook do erp
    public function erpServices($args)
    {
        $idUser = $args['Tenancy']['tenancies']['user_id'];
        
        $json = json_encode($args['body']);
        $message = [];

        try{

            $serviceHandler = $this->getServiceHandler($args);
            $response = $serviceHandler->saveServiceHook($json, $idUser);
            $rk = array('Erp','Services');
            // $this->rabbitMQServices->publicarMensagem('services_exc', $rk, 'erp_services',  $json);
            
            if ($response > 0) {
                
                $message =[
                    'status_code' => 200,
                    'status_message' => 'Success: '. $response['msg'],
                ];
                
            }

        }catch(WebhookReadErrorException $e){        
        }
        finally{
            if(isset($e)){
                ob_start();
                var_dump($e->getMessage());
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/service.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
                
                return print $e->getMessage();
            }
             //grava log
             ob_start();
             print_r($message);
             $input = ob_get_contents();
             ob_end_clean();
             file_put_contents('./assets/service.log', $input . PHP_EOL, FILE_APPEND);
             
             return print $message['status_message'];
           
        }
            
    }
    //processa webhook de produtos
    public function processNewService($args)
    {
        $message = [];
        // processa o webhook 
        try{
            
            $serviceHandler = $this->getServiceHandler($args);
            $response = $serviceHandler->startProcess($args);

            $message =[
                'status_code' => 200,
                'status_message' => $response['success'],
            ];
             
            //grava log
            ob_start();
            print_r($message);
            $input = ob_get_contents();
            ob_end_clean();
            file_put_contents('./assets/service.log', $input . PHP_EOL, FILE_APPEND);
        
        }catch(WebhookReadErrorException $e){
        }
        finally{
            if(isset($e)){
                ob_start();
                var_dump($e->getMessage());
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/service.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
                //print $e->getMessage();
                
                $message =[
                    'status_code' => 500,
                    'status_message' => $e->getMessage(),
                ];

                $m = json_encode($message);
                 return print_r($m);
            }
             
                $m = json_encode($message);
                return print_r($m);
        }

    } 

    public function nasajonService(){

        // Definir cabeçalhos CORS
       header("Access-Control-Allow-Origin: *");
       header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
       header("Access-Control-Allow-Headers: Content-Type, Authorization");
       header("Content-Type: application/json");

       // Se for uma requisição OPTIONS (preflight), apenas retornar status 200 e encerrar
       if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
           http_response_code(200);
           exit();
       }

       // Receber JSON normalmente
       $json = file_get_contents('php://input');

           ob_start();
           print_r($json);
           $input = ob_get_contents();
           ob_end_clean();
           file_put_contents('./assets/nasajon.log', $input . PHP_EOL, FILE_APPEND);

           $r = [
               'pong'=>true
           ];
           $jsonR = json_encode($r);

       return print $jsonR;
    }
}