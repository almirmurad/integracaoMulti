<?php
namespace src\controllers;

use \core\Controller;
use src\handlers\InteractionHandler;
use src\handlers\LoginHandler;
use src\services\DatabaseServices;
use src\services\OmieServices;
use src\services\PloomesServices;

class InteractionController extends Controller{
    private $loggedUser;
    private $ploomesServices;
    private $omieServices;
    private $databaseServices;

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

    }

    public function index() {
        $data = [
            'pagina' => 'Interactions',
            'loggedUser'=>$this->loggedUser,

        ];
        $this->render('gerenciador.pages.index', $data);
    }

    public function createInteraction(){
        $json = file_get_contents('php://input');

        ob_start();
        var_dump($json);
        $input = ob_get_contents();
        ob_end_clean();
        file_put_contents('./assets/all.log', $input . PHP_EOL, FILE_APPEND);

        $response = $this->ploomesServices->createPloomesIteraction($json);

        if ($response) {
            echo"<pre>";
            //json_encode($response);
            print_r($response);
            //grava log
            //$decoded = json_decode($response, true);
            ob_start();
            var_dump($response);
            $input = ob_get_contents();
            ob_end_clean();
            file_put_contents('./assets/log.log', $input . PHP_EOL, FILE_APPEND);
            exit;            
        } else {
            $error = "Erro ao ler dados do webhook";
            echo $error;
            exit;
        }


    }



}