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
    private $omieServices;
    private $databaseServices;
    private $rabbitMQServices;
    private $formatter;

    public function __construct()
    {
        if($_SERVER['REQUEST_METHOD'] !== "POST"){
            $this->loggedUser = LoginHandler::checkLogin();
            if ($this->loggedUser === false) {
                $this->redirect('/login');
            }
        }

        $this->ploomesServices = new ploomesServices();
        $this->omieServices = new omieServices();
        $this->databaseServices = new DatabaseServices();
        $this->rabbitMQServices = new RabbitMQServices();

    }

    private function getOrderHandler(): OrderHandler
    {
        $erp = $_ENV['ERP']; // Pode vir de um parâmetro, sessão, banco, etc.
        $formatter = ErpFormatterFactory::create($erp);

        $orderHandler = new OrderHandler($this->ploomesServices, $this->omieServices, $this->databaseServices, $formatter);

        return $orderHandler;
    }

    public function ploomesOrder()
    {
        /*
        *Recebe o webhook de card ganho, salva na base e retorna 200
        */
        $json = file_get_contents('php://input');

        try{
            $orderHandler = $this->getOrderHandler();
            $response = $orderHandler->saveDealHook($json);
                        
            // $rk = origem.entidade.ação
            $rk = array('Ploomes','Orders');
            $this->rabbitMQServices->publicarMensagem('orders_exc', $rk, 'ploomes_orders',  $json);

            if ($response > 0) {

                $message = [];
                $message =[
                    'status_code' => 200,
                    'status_message' => 'SUCCESS: '. $response['msg'],
                ];  
            }

        }catch(WebhookReadErrorException $e){        
        }
        finally{
            if(isset($e)){
                $message = [];
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
             
            return print $message['status_message'];
        }        
    }

    public function processNewOrder()
    {
        $json = file_get_contents('php://input');
        
        $message = [];
      
        try{
        
            $orderHandler = $this->getOrderHandler();

            $response = $orderHandler->startProcess($json);

            $message =[
                'status_code' => 200,
                'status_message' => $response,
            ];
                
            //grava log
            ob_start();
            print_r($message);
            $input = ob_get_contents();
            ob_end_clean();
            file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
            //return $message['status_message'];
        
        }catch(WebhookReadErrorException $e){                      
        }
        finally{
            if(isset($e)){
                ob_start();
                var_dump($e->getMessage());
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/log.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
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