<?php
namespace src\controllers;

use \core\Controller;
use src\exceptions\WebhookReadErrorException;
use src\handlers\InvoiceHandler;
use src\services\DatabaseServices;
use src\services\PloomesServices;
use src\services\RabbitMQServices;
use src\factories\ErpFormatterFactory;


class InvoicingController extends Controller {
    
    private $loggedUser;
    private $ploomesServices;
    private $omieServices;
    private $databaseServices;
    private $rabbitMQServices;

    public function __construct($args)
    {
        $ploomesBase = $args['Tenancy']['ploomes_bases'][0];

        $args['Tenancy']['vhost'][0]['key'] = $args['Tenancy']['tenancies']['cpf_cnpj'];
        $vhost = $args['Tenancy']['vhost'][0];
        $this->ploomesServices = new PloomesServices($ploomesBase);
        $this->databaseServices = new DatabaseServices();
        // $this->rabbitMQServices = new RabbitMQServices($vhost);
    }

    private function getInvoiceHandler($args): InvoiceHandler
    {   
        $formatter = ErpFormatterFactory::create($args);
        $invoiceHandler = new InvoiceHandler($this->ploomesServices, $this->databaseServices, $formatter);

        return $invoiceHandler;
    }

    public function invoiceIssue($args)
    {
        $idUser = $args['Tenancy']['tenancies']['user_id'];
        $json = json_encode($args['body']);

        $message = [];
        
        try{
            
            $invoiceHandler = $this->getInvoiceHandler($args);
            $response = $invoiceHandler->saveInvoiceHook($json, $idUser);

            // $rk = origem.entidade.ação
            $rk = array('Erp','Invoices');
            // $this->rabbitMQServices->publicarMensagem('invoices_exc', $rk, 'erp_invoices',  $json);

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
            return print $message['status_message'];
        }
        
    }

        //processa contatos e clientes do ploomes ou do Omie
        public function processNewInvoice($args)
        {
                
            $message = [];
            // processa o webhook 
            try{
                
                $invoiceHandler = $this->getInvoiceHandler($args);
                
                $response = $invoiceHandler->startProcess($args);
    
                $message =[
                    'status_code' => 200,
                    'status_message' => $response['success'],
                ];
            
                //grava log
                ob_start();
                print_r($message);
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/logInvoice.log', $input . PHP_EOL, FILE_APPEND);
            
            }catch(WebhookReadErrorException $e){   
            }
            finally{
                if(isset($e)){
                    ob_start();
                    var_dump($e->getMessage());
                    $input = ob_get_contents();
                    ob_end_clean();
                    file_put_contents('./assets/logInvoice.log', $input . PHP_EOL . date('d/m/Y H:i:s'), FILE_APPEND);
                    //print $e->getMessage();
                    
                    $message =[
                        'status_code' => 500,
                        'status_message' => $e->getMessage(),
                    ];
                   
                    // return print 'ERROR: '.$message['status_code'].' MENSAGEM: '.$message['status_message'];
                     $m = json_encode($message);
                     return print_r($m);
                }
                
                //return print 'SUCCESS: '.$message['status_code'].' MENSAGEM: '.$message['status_message'];
                $m = json_encode($message);
                return print_r($m);
                   
            }
    
        } 

    // public function deletedInvoice()
    // {

    //     $json = file_get_contents('php://input');
           
    //     try{
    //         $invoiceHandler = new InvoiceHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
    //         $response = $invoiceHandler->isDeletedInvoice($json);
            
    //         // if ($response) {
    //         //     echo"<pre>";
    //         //     json_encode($response);
    //         //     //print_r($response);
    //         //     //grava log
    //         //     //$decoded = json_decode($response, true);
    //         //     ob_start();
    //         //     var_dump($response);
    //         //     $input = ob_get_contents();
    //         //     ob_end_clean();
    //         //     file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
    //         //     exit;        
    //         //     // return print_r($response);    
    //         // }
    //     }catch(WebhookReadErrorException $e){
    //             // echo '<pre>';
    //             // print $e->getMessage();
    //         }
    //     catch(NotaFiscalNaoCadastradaException $e){
    //         // echo '<pre>';
    //         // print $e->getMessage();       
    //     }
    //     catch(NotaFiscalNaoCanceladaException $e){
    //         // echo '<pre>';
    //         // print $e->getMessage();
    //     }catch(PDOException $e){
    //         // echo '<pre>';
    //         // print $e->getMessage();
    //     }
    //     finally{
    //         if(isset($e)){
    //             ob_start();
    //             var_dump($e->getMessage());
    //             $input = ob_get_contents();
    //             ob_end_clean();
    //             file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
                
    //             return print $e->getMessage();
    //         }    
    //         return print_r($response);       
    //     }

    // }

}