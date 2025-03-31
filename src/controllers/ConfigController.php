<?php
namespace src\controllers;

use \core\Controller;
use Exception;
use src\handlers\ConfigHandler;
use src\handlers\LoginHandler;
use src\models\Deal;

class ConfigController extends Controller {
    
    private $loggedUser;

    public function __construct(){
        $this->loggedUser = LoginHandler::checkLogin();
        if($this->loggedUser === false){
            $this->redirect('/login');
        }   
    }

    public function index() {
        $total = Deal::select('id')->count();        
        $data = [
            'pagina' => 'Configurações',
            'loggedUser'=>$this->loggedUser,
            'total'=>$total
        ];
        $this->render('gerenciador.pages.configs', $data);
    }

    public function defineConfig(){
      
        $flash = '';
        if (!empty($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            $_SESSION['flash'] = '';
        }

        $this->render('gerenciador.pages.configs', [
            'pagina' => 'Integração',
            'loggedUser' => $this->loggedUser,
            'flash' => $flash
        ]);

        try{
        if(isset($_POST['submitMpr'])){

            $MPR_SK = filter_input(INPUT_POST, 'secretKeyMpr',FILTER_SANITIZE_SPECIAL_CHARS);
            $MPR_APPK = filter_input(INPUT_POST, 'appKeyMpr',FILTER_SANITIZE_SPECIAL_CHARS);
            $MPR_NCC = filter_input(INPUT_POST, 'nccMpr', FILTER_VALIDATE_INT);
            $define = ConfigHandler::configOmieMpr($MPR_SK,$MPR_APPK,$MPR_NCC);
            
        }elseif(isset($_POST['submitMsc'])){

            $MSC_SK = filter_input(INPUT_POST, 'secretKeyMsc',FILTER_SANITIZE_SPECIAL_CHARS);
            $MSC_APPK = filter_input(INPUT_POST, 'appKeyMsc',FILTER_SANITIZE_SPECIAL_CHARS);
            $MSC_NCC = filter_input(INPUT_POST, 'nccMsc', FILTER_VALIDATE_INT);
            $define = ConfigHandler::configOmieMsc($MSC_SK,$MSC_APPK,$MSC_NCC);
            
       }elseif(isset($_POST['submitMhl'])){

            $MHL_SK = filter_input(INPUT_POST, 'secretKeyMhl',FILTER_SANITIZE_SPECIAL_CHARS);
            $MHL_APPK = filter_input(INPUT_POST, 'appKeyMhl',FILTER_SANITIZE_SPECIAL_CHARS);
            $MHL_NCC = filter_input(INPUT_POST, 'nccMhl', FILTER_VALIDATE_INT);
            $define = ConfigHandler::configOmieMhl($MHL_SK,$MHL_APPK,$MHL_NCC);
            

        }elseif(isset($_POST['submitPlm'])){
            $PLM_APK = filter_input(INPUT_POST, 'apiKeyPlm',FILTER_SANITIZE_SPECIAL_CHARS);
            $define = ConfigHandler::configPloomesApk($PLM_APK);
            
        }
           
        ($define)? $_SESSION['flash'] = 'Configurado': $_SESSION['flash'] = 'Erro ao configurar o sistema';
        $this->render('/define');
        
                
          
        }catch(Exception $e){
            print $e->getMessage();
        }


        

        

    }

}