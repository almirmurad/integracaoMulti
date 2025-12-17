<?php
namespace src\controllers;

use core\Controller;
use src\exceptions\WebhookReadErrorException;
use src\factories\ErpFormatterFactory;
use src\handlers\LoginHandler;
use src\handlers\ProductHandler;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;
use src\services\RabbitMQServices;


class ProductController extends Controller {
    
    private $loggedUser;
    private $ploomesServices;
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
        $this->rabbitMQServices = new RabbitMQServices($vhost);
    }

    private function getProductHandler($args): ProductHandler
    {
        $this->formatter = ErpFormatterFactory::create($args);
        $productHandler = new ProductHandler($this->ploomesServices, $this->databaseServices, $this->formatter);
        
        return $productHandler;
    }
    
    //recebe webhook do erp
    public function erpProducts($args)
    {
        $idUser = $args['Tenancy']['tenancies']['user_id'];
        
        $json = json_encode($args['body']);
        $message = [];
    
        try{
            
            $productHandler = $this->getProductHandler($args);
            
            $response = $productHandler->saveProductHook($json, $idUser);
         
             // $rk = origem.entidade.ação
             $rk = array('Erp','Products');
             $this->rabbitMQServices->publicarMensagem('products_exc', $rk, 'erp_products',  $json);
            
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
                file_put_contents('./assets/logERPProduct.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
                
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
    //processa webhook de produtos
    public function processNewProduct($args)
    {
        $message = [];
        try{
            $productHandler = $this->getProductHandler($args);
            $response = $productHandler->startProcess($args);

            $message =[
                'status_code' => 200,
                'status_message' => $response['success'],
            ];
             
        }catch(WebhookReadErrorException $e){
        }
        finally{
            if(isset($e)){
                ob_start();
                var_dump($e->getMessage());
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/logProcessProduct.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
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

    // public function ploomesProducts()
    // {
    //     $message = [];
    //     $json = file_get_contents('php://input');

    //     try{
    //         $productHandler = new ProductHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
    //         $response = $productHandler->saveProductHook($json);
    //         // $rk = origem.entidade.ação
    //         $rk = array('Ploomes','Products');
    //         //$this->rabbitMQServices->publicarMensagem('products_exc', $rk, 'ploomes_products',  $json);
            
    //         if ($response > 0) {
                
    //             $message =[
    //                 'status_code' => 200,
    //                 'status_message' => 'Success: '. $response['msg'],
    //             ];    
    //         }

    //     }catch(WebhookReadErrorException $e){        
    //     }
    //     finally{
    //         if(isset($e)){
    //             ob_start();
    //             var_dump($e->getMessage());
    //             $input = ob_get_contents();
    //             ob_end_clean();
    //             file_put_contents('./assets/log.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
                
    //             return print $e->getMessage();
    //         }
                         
    //          return print $message['status_message'];
    //     }     
    // }

    public function nasajonProducts(){

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