<?php

namespace src\controllers;

use \core\Controller;
use src\handlers\AppsHandler;
use src\handlers\IntegraHandler;
use src\handlers\LoginHandler;
use src\handlers\OmieAppHandler;
use src\handlers\TenancyHandler;
use src\handlers\UserHandler;
use src\models\User;

class TenancyController extends Controller
{

    private $loggedUser; 

    // public function __construct(){

    //     if($_SERVER['REQUEST_METHOD'] !== "POST"){
    //         $this->loggedUser = LoginHandler::checkLogin();
    //         if ($this->loggedUser === false) {
    //             $this->redirect('/login');
    //         }elseif(!in_array('users_view', $this->loggedUser->permission)){
    //             $this->redirect('/',['flash'=>$_SESSION['flash'] = "Usuário sem permissão para acessar esta area!"]);
    //         }
    //     }
    // }

    // public function setLoggedUser($args)
    // {
    //     if(isset($args['loggedUser']) && !empty($args['loggedUser'])){
    //         $this->loggedUser = $args['loggedUser'];
    //     }
    // }

    public function listApiUsers()
    {
        header('Content-Type: application/json');
        $users = UserHandler::listAllUsers();

        if($users > 0){

            $response = [];
            $response['status'] = 200;
            $response['content'] = $users;
            
            print json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }else{

            $response = [];
            $response['status'] = 500;
            $response['content'] = 'Erro ao buscar usuários cadastrados!';

            print json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;

        }

        
    }

    // public function listTenacys(){

    //     $tenancys = TenancyHandler::listTenancys();

    // }

    public function createNewTenancyAction()
    {
       
        header('Content-Type: application/json');
        $json = file_get_contents('php://input');
       
        $data = json_decode($json, true); 
        
        $response = [];     
                   
        $id = TenancyHandler::createNewTenancy($data);
        if($id){
            $response['status'] = 200;
            $response['content'] = $id;

            print json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;      
        }else{
            $response['status'] = 500;
            $response['content'] = 'Erro ao gravar o tenancy na base de dados da API integração';

            print json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }
       
    }

    public function allInfoUserApi($idTenancy)
    {   
        header('Content-Type: application/json');
        $response =[];
        //recebe todas as informações do usuário de api pelo código
        $t = TenancyHandler::getAllInfoUserAPi($idTenancy['id']);
        
        if(isset($t['error']) && !empty($t['error'])){
            $response['status'] = 500;
            $response['content'] = $t['error'];
            
            print json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $response['status'] = 200;
        $response['content'] = $t;

        print json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;

    }

    //na evolução do sitema teremos um create new ERP
    public function createNewAppErp()
    {
        header('Content-Type: application/json');
        $this->loggedUser  = LoginHandler::checkLogin();

        $json = file_get_contents('php://input');
        
        $data = json_decode($json, true);        
                
        if($this->loggedUser)
        {                
            $response = AppsHandler::createNewAppErp($data);

            print json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }else{

            $response['status'] = 200;
            $response['content'] = 'Não foi possível logar encontrar o usuário logado na API';

            print json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public function createNewAppMkt()
    {
        header('Content-Type: application/json');
        $this->loggedUser  = LoginHandler::checkLogin();

        $json = file_get_contents('php://input');
        
        $data = json_decode($json, true);        
                        
        
        if($this->loggedUser)
        {                
            $response = AppsHandler::createNewAppMkt($data);
            print json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }else{
            $response['status'] = 200;
            $response['content'] = 'Não foi possível logar encontrar o usuário logado na API';
            print json_encode($response, JSON_UNESCAPED_UNICODE);    
            exit; 
        }
    }


    //na evolução do sitema teremos um create new CRM
    public function createNewAppPloomes()
    {
        header('Content-Type: application/json');
        $this->loggedUser  = LoginHandler::checkLogin();

        $json = file_get_contents('php://input');
        
        $data = json_decode($json, true);
        
        if($this->loggedUser)
        {                
            $response = AppsHandler::createNewAppPloomes($data);

            print json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;
        }else{
            $response['status'] = 200;
            $response['content'] = 'Não foi possível logar encontrar o usuário logado na API';
            print json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;     
        }
    }

        //na evolução do sitema teremos um create new CRM
    public function createNewAppOmni()
    {
        header('Content-Type: application/json');
        $this->loggedUser  = LoginHandler::checkLogin();

        $json = file_get_contents('php://input');
        
        $data = json_decode($json, true);
        
        if($this->loggedUser)
        {                
            $response = AppsHandler::createNewAppOmni($data);

            print json_encode($response, JSON_UNESCAPED_UNICODE);
            exit; 
        }else{
            $response['status'] = 200;
            $response['content'] = 'Não foi possível logar encontrar o usuário logado na API';
            print json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;      
        }
    }

    public function createVhostRabbitMQ(){
        header('Content-Type: application/json');
        $this->loggedUser  = LoginHandler::checkLogin();

        $json = file_get_contents('php://input');
        
        $data = json_decode($json, true);            
                
        if($this->loggedUser)
        {                
            $response = AppsHandler::createVhostRabbitMQ($data);

            print json_encode($response, JSON_UNESCAPED_UNICODE);
            exit; 
        }else{
            $response['status'] = 200;
            $response['content'] = 'Não foi possível logar encontrar o usuário logado na API';
            print json_encode($response, JSON_UNESCAPED_UNICODE);
            exit;   
        }
    }


    
}
