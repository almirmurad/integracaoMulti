<?php
namespace src\controllers;

use core\Controller;
use src\exceptions\WebhookReadErrorException;
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

    public function __construct()
    {
        if($_SERVER['REQUEST_METHOD'] !== "POST"){
            $this->loggedUser = LoginHandler::checkLogin();
            if ($this->loggedUser === false) {
                $this->redirect('/login');
            }
        }

        $this->ploomesServices = new PloomesServices();
        $this->omieServices = new OmieServices();
        $this->databaseServices = new DatabaseServices();
        $this->rabbitMQServices = new RabbitMQServices();

    }
    //recebe webhook do omie
    public function omieServices()
    {
        $message = [];
        $json = file_get_contents('php://input');

        ob_start();
        var_dump($json);
        $input = ob_get_contents();
        ob_end_clean();
        file_put_contents('./assets/services.log', $input . PHP_EOL . date('d/m/Y H:i:s') . PHP_EOL, FILE_APPEND);

        try{
            $serviceHandler = new ServiceHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
            $response = $serviceHandler->saveServiceHook($json);
            $rk = array('Omie','Services');
            $this->rabbitMQServices->publicarMensagem('services_exc', $rk, 'omie_services',  $json);
            
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
                file_put_contents('./assets/log.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
                
                return print $e->getMessage();
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
    //processa webhook de produtos
    public function processNewService()
    {
        $json = file_get_contents('php://input');
        //$decoded = json_decode($json,true);
        // $status = $decoded['status'];
        // $entity = $decoded['entity'];
        $message = [];

        // processa o webhook 
        try{
            
            $ServiceHandler = new ServiceHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
            $response = $ServiceHandler->startProcess($json);

            $message =[
                'status_code' => 200,
                'status_message' => $response['success'],
            ];
             
            //grava log
            ob_start();
            print_r($message);
            $input = ob_get_contents();
            ob_end_clean();
            file_put_contents('./assets/logClient.log', $input . PHP_EOL, FILE_APPEND);
        
        }catch(WebhookReadErrorException $e){
                
        }
        finally{
            if(isset($e)){
                ob_start();
                var_dump($e->getMessage());
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/logClient.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
                //print $e->getMessage();
                
                $message =[
                    'status_code' => 500,
                    'status_message' => $e->getMessage(),
                ];
               
                // return print 'ERROR: '.$message['status_code'].' MENSAGEM: '.$message['status_message'];
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