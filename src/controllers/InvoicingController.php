<?php
namespace src\controllers;

use \core\Controller;
use PDOException;
use src\exceptions\WebhookReadErrorException;
use src\exceptions\DealNaoEncontradoBDException;
use src\exceptions\EstagiodavendaNaoAlteradoException;
use src\exceptions\FaturamentoNaoCadastradoException;
use src\exceptions\InteracaoNaoAdicionadaException;
use src\exceptions\NotaFiscalNaoCadastradaException;
use src\exceptions\NotaFiscalNaoCanceladaException;
use src\exceptions\NotaFiscalNaoEncontradaException;
use src\exceptions\PedidoNaoEncontradoOmieException;
use src\handlers\LoginHandler;
use src\handlers\InvoiceHandler;
use src\models\Deal;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;
use src\services\RabbitMQServices;

class InvoicingController extends Controller {
    
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

    public function index() {
        $total = Deal::select('id')->count();        
        $data = [
            'pagina' => 'Deals',
            'loggedUser'=>$this->loggedUser,
            'total'=>$total
        ];
        $this->render('gerenciador.pages.index', $data);
    }

    public function invoiceIssue()
    {
        $json = file_get_contents('php://input');
        
        try{
            $invoiceHandler = new InvoiceHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);

            $response = $invoiceHandler->saveInvoiceHook($json);

            // $rk = origem.entidade.ação
            $rk = array('Omie','Invoices');
            $this->rabbitMQServices->publicarMensagem('invoices_exc', $rk, 'omie_invoices',  $json);

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
        public function processNewInvoice()
        {
            $json = file_get_contents('php://input');
    
            $message = [];
            // processa o webhook 
            try{
                
                $invoiceHandler = new InvoiceHandler($this->ploomesServices, $this->omieServices, $this->databaseServices);
                
                $response = $invoiceHandler->startProcess($json);
    
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