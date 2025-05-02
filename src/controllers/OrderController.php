<?php
namespace src\controllers;

use core\Controller;
use src\contracts\ErpFormattersInterface;
use src\contracts\OmieManagerInterface;
use src\exceptions\InteracaoNaoAdicionadaException;

use src\exceptions\WebhookReadErrorException;
use src\factories\ErpFormatterFactory;
use src\handlers\LoginHandler;
use src\handlers\OrderHandler;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;
use src\services\RabbitMQServices;

class OrderController extends Controller {
    
    private $loggedUser;
    private $ploomesServices;
    private $databaseServices;
    // private $rabbitMQServices;
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

    private function getOrderHandler($args): OrderHandler
    {
        $this->formatter = ErpFormatterFactory::create($args);
        $orderHandler = new OrderHandler($this->ploomesServices, $this->databaseServices, $this->formatter);

        return $orderHandler;
    }

    public function ploomesOrder($args)
    {
        $idUser = $args['Tenancy']['tenancies']['user_id'];
        
        $json = json_encode($args['body']);
        $message = [];

        try{

            $orderHandler = $this->getOrderHandler($args);

            $response = $orderHandler->saveDealHook($json, $idUser);
                        
            // $rk = origem.entidade.ação
            $rk = array('Ploomes','Orders');
            // $this->rabbitMQServices->publicarMensagem('orders_exc', $rk, 'ploomes_orders',  $json);

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
                file_put_contents('./assets/orders.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);

                return print 'ERROR:'. $message['status_code'].'. MESSAGE: ' .$message['status_message'];
            }
             
            return print $message['status_message'];
        }        
    }

    public function processNewOrder($args)
    {        
        $message = [];
      
        try{
        
            $orderHandler = $this->getOrderHandler($args);

            $response = $orderHandler->startProcess($args);

            $message =[
                'status_code' => 200,
                'status_message' => $response,
            ];
                
            //grava log
            ob_start();
            print_r($message);
            $input = ob_get_contents();
            ob_end_clean();
            file_put_contents('./assets/orders.log', $input . PHP_EOL, FILE_APPEND);
            //return $message['status_message'];
        
        }catch(WebhookReadErrorException $e){                      
        }
        finally{
            if(isset($e)){
                ob_start();
                var_dump($e->getMessage());
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/orders.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
                //print $e->getMessage();
              
                $message =[
                    'status_code' => 500,
                    'status_message' => $e->getMessage(),
                ];
                $m = json_encode($message);
                 return print_r($m);
                //return print 'ERROR: '.$message['status_code'].' MESSAGE: '.$message['status_message'];
               }
               $m = json_encode($message);
               return print_r($m);
            //return print $message['status_message']['winDeal']['success'];
        }

    }


}