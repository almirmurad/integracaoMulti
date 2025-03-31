<?php
namespace src\controllers;

use core\Controller;
use src\exceptions\ContactIdInexistentePloomesCRM;
use src\exceptions\InteracaoNaoAdicionadaException;
use src\exceptions\OrderControllerException;
use src\exceptions\PedidoCanceladoException;
use src\exceptions\PedidoDuplicadoException;
use src\exceptions\PedidoInexistenteException;
use src\exceptions\PedidoNaoExcluidoException;
use src\exceptions\WebhookReadErrorException;
use src\handlers\ClientPloomesHandler;
use src\handlers\LoginHandler;
use src\handlers\OmieOrderHandler;

class ClientPloomesController extends Controller {
    
    private $loggedUser;
    private $apiKey;
    private $baseApi;
    private $clientPloomesHandler;


    public function __construct()
    {
        
        
        // if($_SERVER['REQUEST_METHOD'] == "POST"){

        //     print_r($_SERVER);
        //     print_r($_SERVER['HTTP_TOKEN']);
        //     exit;
        // }
          if($_SERVER['REQUEST_METHOD'] !== "POST"){
            $this->loggedUser = LoginHandler::checkLogin();
            if ($this->loggedUser === false) {
                $this->redirect('/login');
            }
          }
        $this->apiKey = $_ENV['API_KEY'];
        $this->baseApi = $_ENV['BASE_API'];
        $this->clientPloomesHandler = new ClientPloomesHandler;
   
    }

    public function index() {
        //$total = Deal::select('id')->count();        
        $data = [
            'pagina' => 'Pedidos',
            'loggedUser'=>$this->loggedUser,
            //'total'=>$total
        ];
        $this->render('gerenciador.pages.index', $data);
    }

    public function newClientPloomes(){

        $json = file_get_contents('php://input');

        ob_start();
        var_dump($json);
        $input = ob_get_contents();
        ob_end_clean();
        file_put_contents('./assets/all.log', $input . PHP_EOL . date('d/m/Y H:i:s') . PHP_EOL, FILE_APPEND);

        print_r($json);exit;

        try{

            $response = json_encode(ClientPloomesHandler::newClient($json, $this->apiKey, $this->baseApi, $this->clientPloomesHandler ));
            if ($response) {
                echo"<pre>";
                json_encode($response);
                //grava log
                //$decoded = json_decode($response, true);
                ob_start();
                var_dump($response);
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);  
            }

        }catch(PedidoDuplicadoException $e){
            echo $e->getMessage();
        }catch(OrderControllerException $e){
            echo $e->getMessage();
        }catch(ContactIdInexistentePloomesCRM $e){
            echo $e->getMessage();
        }catch(InteracaoNaoAdicionadaException $e){
            echo $e->getMessage();
        }finally{
            if (isset($e)){
                ob_start();
                echo $e->getMessage();
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
                exit; 
            }
            exit;
            //return print_r($response);
        }
            
    }

    public function deletedOrder(){
        $json = file_get_contents('php://input');
            //$decoded = json_decode($json, true);

            ob_start();
            var_dump($json);
            $input = ob_get_contents();
            ob_end_clean();

            file_put_contents('./assets/whkDelOrder.log', $input . PHP_EOL, FILE_APPEND);
            // $pong = array("pong"=>true);
            // $json = json_encode($pong);
            // return print_r($json);

        try{

            $response = json_encode(OmieOrderHandler::deletedOrder($json, $this->apiKey, $this->baseApi));
            if ($response) {
                echo"<pre>";
                json_encode($response);
                //grava log
                //$decoded = json_decode($response, true);
                ob_start();
                var_dump($response);
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);  
            }

        }catch(PedidoInexistenteException $e){
            echo $e->getMessage();
        }catch(PedidoCanceladoException $e){
            echo $e->getMessage();
        }catch(PedidoNaoExcluidoException $e){
            echo $e->getMessage();
        }
        catch(PedidoDuplicadoException $e){
            echo $e->getMessage();
        }catch(OrderControllerException $e){
            echo $e->getMessage();
        }catch(ContactIdInexistentePloomesCRM $e){
            echo $e->getMessage();
        }catch(InteracaoNaoAdicionadaException $e){
            echo $e->getMessage();
        }finally{
            if (isset($e)){
                ob_start();
                echo $e->getMessage();
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
                exit; 
            }
            return print_r($response);
            exit;
        }


    }

    public function alterOrderStage(){
        $json = file_get_contents('php://input');
            //$decoded = json_decode($json, true);

            ob_start();
            var_dump($json);
            $input = ob_get_contents();
            ob_end_clean();

            file_put_contents('./assets/whkAlterStageOrder.log', $input . PHP_EOL . date('d/m/Y H:i:s') . PHP_EOL, FILE_APPEND);
            // $pong = array("pong"=>true);
            // $json = json_encode($pong);
            // return print_r($json);

        try{
            $response = json_encode(OmieOrderHandler::alterOrderStage($json, $this->apiKey, $this->baseApi, $this->omieOrderHandler));
            if ($response) {
                echo"<pre>";
                json_encode($response);
                //grava log
                //$decoded = json_decode($response, true);
                ob_start();
                var_dump($response);
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);  
            }

        }catch(WebhookReadErrorException $e){
            echo $e->getMessage();
        }catch(InteracaoNaoAdicionadaException $e){
            echo $e->getMessage();
        }finally{
            if (isset($e)){
                ob_start();
                echo $e->getMessage();
                $input = ob_get_contents();
                ob_end_clean();
                file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
            }
        }
        return print_r($response);
        exit;

    }


}