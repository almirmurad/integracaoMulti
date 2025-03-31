<?php
namespace src\controllers;

use \core\Controller;
use src\functions\CustomFieldsFunction;
use src\handlers\LoginHandler;

class HomeController extends Controller {
    
    private $loggedUser;

    public function index($args) {

        print'aqui';
        exit;

        $flash = '';
        if (!empty($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            $_SESSION['flash'] = '';
        }
        // print_r($this->loggedUser->permission);
        // exit;

        $total = 0;
        $data = [
            'pagina' => 'Dashboard',
            'loggedUser'=>$this->loggedUser,
            'total'=>$total,
            'flash'=>$flash
        ];
        $this->render('gerenciador.pages.index', $data);
    }
    

}