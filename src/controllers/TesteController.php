<?php
namespace src\controllers;

use \core\Controller;

class TesteController extends Controller {

    public function teste() {
        // $this->render('404');
        echo json_encode(['status' => 'ok', 'time' => microtime(true)]);
        exit;
    }

}