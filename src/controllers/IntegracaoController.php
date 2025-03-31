<?php

namespace src\controllers;

use \core\Controller;
use src\handlers\IntegraHandler;
use src\handlers\LoginHandler;

class IntegracaoController extends Controller
{

    private $loggedUser;
    private $apiKey;
    private $baseApi;

    public function __construct()
    {
        
        if($_SERVER['REQUEST_METHOD'] !== "POST"){
            $this->loggedUser = LoginHandler::checkLogin();
            if ($this->loggedUser === false) {
                $this->redirect('/login');
            }elseif(!in_array('register_integration', $this->loggedUser->permission)){
                $this->redirect('/',['flash'=>$_SESSION['flash'] = "Usuário sem permissão para acessar esta area!"]);
            }

        }
        $this->apiKey = $_ENV['API_KEY'];
        $this->baseApi = $_ENV['BASE_API'];

    }

    public function index()
    {
        $flash = '';
        if (!empty($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            $_SESSION['flash'] = '';
        }

        $this->render('gerenciador.pages.integrar', [
            'pagina' => 'Integração',
            'loggedUser' => $this->loggedUser,
            'flash' => $flash
        ]);
    }

    public function integraAction()
    {
        $apiKey = $this->apiKey;
        $baseApi = $this->baseApi;
        $entityId = filter_input(INPUT_POST, 'entityId', FILTER_VALIDATE_INT);
        $actionId = filter_input(INPUT_POST, 'actionId', FILTER_VALIDATE_INT);
        $callbackUrl = filter_input(INPUT_POST, 'cbUrl', FILTER_SANITIZE_URL);
        $validationKey = random_int(1000, 10000);

        $webhook = IntegraHandler::createWebhook($entityId, $actionId, $callbackUrl, $validationKey, $apiKey, $baseApi);

        if (!$webhook) {
            $_SESSION['flash'] = "Erro ao cadastrar webhook!";
            $this->redirect('/integrar');
        }
            $_SESSION['flash'] = "Webhook criado com sucesso!";
            $this->redirect('/integrar');
    }

    public function getAll()
    {
        $flash = '';
        if (!empty($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            $_SESSION['flash'] = '';
        }

        $apiKey = $this->apiKey;
        $baseApi = $this->baseApi;
        $method = 'GET';

        $response = IntegraHandler::getAll($baseApi, $method, $apiKey);

        if ($response) {

            $this->render('gerenciador.pages.list', [
                'pagina' => 'Listar Todas',
                'integracoes' => $response,
                'loggedUser' => $this->loggedUser,
                'flash' => $flash,
            ]);
        } else {
            $error = "Erro ao buscar integrações";
            echo $error;
            exit;
        }
    }

    public function delHook($id)
    {
        $apiKey = $this->apiKey;
        $baseApi = $this->baseApi;
        $id = $id['id'];

        $method = 'delete';

        $response = IntegraHandler::delHook($id, $baseApi, $method, $apiKey);

        if ($response) {
                $_SESSION['flash'] = "Erro ao excluir webhook!";
                $this->redirect('/getAll');
            }
                $_SESSION['flash'] = "Webhook excluido com sucesso!";
                $this->redirect('/getAll');
    }
}