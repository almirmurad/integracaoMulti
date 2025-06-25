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

        $users = UserHandler::listAllUsers();

        if($users > 0){

            $response = [];
            $response['status'] = 200;
            $response['content'] = $users;
            
            return print_r(json_encode($response));
        }else{

            $response = [];
            $response['status'] = 500;
            $response['content'] = 'Erro ao buscar usuários cadastrados!';

        }

        
    }

    // public function listTenacys(){

    //     $tenancys = TenancyHandler::listTenancys();

    // }

    public function createNewTenancyAction()
    {
       

        $json = file_get_contents('php://input');
       
        $data = json_decode($json, true); 
        
        $response = [];     
                   
        $id = TenancyHandler::createNewTenancy($data);
        if($id){
            $response['status'] = 200;
            $response['content'] = $id;
            return print_r(json_encode($response));        
        }else{
            $response['status'] = 500;
            $response['content'] = 'Erro ao gravar o tenancy na base de dados da API integração';
            return print_r(json_encode($response));
        }
       
    }

    public function allInfoUserApi($idTenancy)
    {   
        
        $response =[];
        //recebe todas as informações do usuário de api pelo código
        $t = TenancyHandler::getAllInfoUserAPi($idTenancy['id']);
        
        if(isset($t['error']) && !empty($t['error'])){
            $response['status'] = 500;
            $response['content'] = $t['error'];
            
            return print json_encode($response);
        }
        
        $response['status'] = 200;
        $response['content'] = $t;

        return print json_encode($response);

    }

    //na evolução do sitema teremos um create new ERP
    public function createNewAppErp()
    {
        $this->loggedUser  = LoginHandler::checkLogin();

        $json = file_get_contents('php://input');
        
        $data = json_decode($json, true);        
        
                
        if($this->loggedUser)
        {                
            $response = AppsHandler::createNewAppErp($data);

            return print_r(json_encode($response));
        }else{
            $response['status'] = 200;
            $response['content'] = 'Não foi possível logar encontrar o usuário logado na API';
            return print_r(json_encode($response));     
        }
    }


    //na evolução do sitema teremos um create new CRM
    public function createNewAppPloomes()
    {
        $this->loggedUser  = LoginHandler::checkLogin();

        $json = file_get_contents('php://input');
        
        $data = json_decode($json, true);
        
        if($this->loggedUser)
        {                
            $response = AppsHandler::createNewAppPloomes($data);

            return print_r(json_encode($response));
        }else{
            $response['status'] = 200;
            $response['content'] = 'Não foi possível logar encontrar o usuário logado na API';
            return print_r(json_encode($response));     
        }
    }

        //na evolução do sitema teremos um create new CRM
    public function createNewAppOmni()
    {
        $this->loggedUser  = LoginHandler::checkLogin();

        $json = file_get_contents('php://input');
        
        $data = json_decode($json, true);
        
        if($this->loggedUser)
        {                
            $response = AppsHandler::createNewAppOmni($data);

            return print_r(json_encode($response));
        }else{
            $response['status'] = 200;
            $response['content'] = 'Não foi possível logar encontrar o usuário logado na API';
            return print_r(json_encode($response));     
        }
    }

    public function createVhostRabbitMQ(){
        $this->loggedUser  = LoginHandler::checkLogin();

        $json = file_get_contents('php://input');
        
        $data = json_decode($json, true);            
                
        if($this->loggedUser)
        {                
            $response = AppsHandler::createVhostRabbitMQ($data);

            return print_r(json_encode($response));
        }else{
            $response['status'] = 200;
            $response['content'] = 'Não foi possível logar encontrar o usuário logado na API';
            return print_r(json_encode($response));     
        }
    }

    
}
