<?php
namespace src\controllers;

use \core\Controller;
use src\handlers\DashboardHandler;
use src\handlers\LoginHandler;


class DashboardController extends Controller {
    
    private $loggedUser;

    public function __construct(){
        $this->loggedUser = LoginHandler::checkLogin();
        if($this->loggedUser === false || !in_array('dashboard_view', $this->loggedUser->permission )){
            $this->redirect('/login');
        }   
    }

    public function index() {

        $totals = DashboardHandler::getAllTotals();

        echo $totals;
        
    }



}