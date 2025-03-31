<?php
namespace src\controllers;

use \core\Controller;
use src\handlers\LoginHandler;
use src\handlers\PermissionHandler;

use src\models\Permissions_group;

class PermissionController extends Controller {
    
    private $loggedUser;

    public function __construct(){
        $this->loggedUser = LoginHandler::checkLogin();
        if($this->loggedUser === false){
            $this->redirect('/login');
        }
        if( !in_array('permissions_view', $this->loggedUser->permission )){
            $this->redirect('/',['flash'=>$_SESSION['flash'] = "Usuário sem permissão para acessar esta area!"]);
        }
    }

    public function index() {

        $p = PermissionHandler::getAllGroups();
      
        $flash = '';
        if (!empty($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            $_SESSION['flash'] = '';
        }
    
        $data = [
            'pagina' => 'Permissões',
            'loggedUser'=>$this->loggedUser,
            'flash'=>$flash,
            'list'=> $p
        ];
        $this->render('gerenciador.pages.permissions', $data);
    }

    public function delGroupPermission($idGroup){


        if(PermissionHandler::delGroupPermission($idGroup['id'])){
            $this->redirect('/permissions',['flash'=>$_SESSION['flash'] = "Grupo de permissões deletado com suceso!"]);
        }else{
            $this->redirect('/permissions',['flash'=>$_SESSION['flash'] = "Não foi possível excluir o grupo de permissões, haviam usuários incluidos nele!"]);
        }

    }

    public function addPermissionGroup(){

        $p = PermissionHandler::getAllGroups();
        $items = PermissionHandler::getAllItems();
      
        $flash = '';
        if (!empty($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            $_SESSION['flash'] = '';
        }
    
        $data = [
            'pagina' => 'Adicionar Grupos de Permissões',
            'loggedUser'=>$this->loggedUser,
            'flash'=>$flash,
            'list'=> $p,
            'items'=> $items,
        ];
        $this->render('gerenciador.pages.addPermissionGroup', $data);
    }

    public function addPermissionGroupAction(){

        $args = array(
            'name'=>FILTER_SANITIZE_SPECIAL_CHARS,
            'itemPermission'=>array('filter'=>FILTER_VALIDATE_INT,
                                    'flags'  => FILTER_REQUIRE_ARRAY,
                                     ));
       
        $data  = filter_input_array(INPUT_POST, $args);

        if(PermissionHandler::insertNewPermissionGroup($data)){
            $this->redirect('/permissions',['flash'=>$_SESSION['flash'] = "Grupo de permissões inserido com suceso!"]);
        }
        $this->redirect('/addPermissionGroup',['flash'=>$_SESSION['flash'] = "Erro ao adicionar grupo de permissões!"]);

    }

    public function editPermissionGroup($idGroup){

        $name = PermissionHandler::getPermissionGroupName($idGroup);
        $items = PermissionHandler::getAllItems();
        $permissionLinks = PermissionHandler::getPermissions($idGroup);
      
        $flash = '';
        if (!empty($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            $_SESSION['flash'] = '';
        }
    
        $data = [
            'pagina' => 'Editar Grupos de Permissões',
            'loggedUser'=>$this->loggedUser,
            'flash'=>$flash,
            'items'=>$items,
            'name'=>$name,
            'permissionLinks'=> $permissionLinks,
            'idGroup'=>$idGroup['id']
        ];
        $this->render('gerenciador.pages.editPermissionGroup', $data);
        
    }

    public function editPermissionGroupAction($idGroup){

        $args = array(
            'name'=>FILTER_SANITIZE_SPECIAL_CHARS,
            'itemPermission'=>array('filter'=>FILTER_VALIDATE_INT,
            'flags'  => FILTER_REQUIRE_ARRAY,
        ),
        );
        
        $data  = filter_input_array(INPUT_POST, $args);
        $data['idGroup'] = $idGroup;


        if(PermissionHandler::editPermissionGroup($data)){
            $this->redirect('/permissions',['flash'=>$_SESSION['flash'] = "Grupo de permissões alterado com suceso!"]);
        }
        $this->redirect('/permissions',['flash'=>$_SESSION['flash'] = "Erro ao alterar grupo de Permissões!"]);

    }

}