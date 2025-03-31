<?php
namespace src\controllers;

use core\Controller;
use src\handlers\LoginHandler;

class LoginController extends Controller {

    private $loggedUser;

    public function __construct(){
        $this->loggedUser = LoginHandler::checkLogin();          
    }

    public function signin(){
       
        $flash ='';
        if(!empty($_SESSION['flash'])){
            $flash = $_SESSION['flash'];
            $_SESSION['flash'] = '';
        }
        $this->render('gerenciador.pages.signin',[
            'flash' => $flash,
        ]);
    }
    
    public function signinAction(){

        $json = file_get_contents('php://input');

        // $mail   = filter_input(INPUT_POST, 'mail', FILTER_VALIDATE_EMAIL);
        // $pass   = filter_input(INPUT_POST, 'pass');
        $decoded = json_decode($json, true);

        $mail = $decoded['mail'];
        $pass = $decoded['pass'];

        if($mail && $pass){

            $token = LoginHandler::verifyLogin($mail, $pass);
           
            if($token){
                $response = [];
                $response['status'] = 200;
                $response['content'] = $token;

                return print json_encode($response);
                // $_SESSION['token'] = $token;
                // $this->redirect('/');
            }else{
                $response = [];
                $response['status'] = 500;
                $response['content'] = "E-Mail e ou senha não conferem";
                return print json_encode($response);
                // $_SESSION['flash'] = "E-Mail e ou senha não conferem";
                // $this->redirect('/login');
               
            }

        }else{
            $this->redirect('/login');
        }
    }

    public function signout(){
        $_SESSION['token'] = '';
        $this->redirect('/login');
    }

}