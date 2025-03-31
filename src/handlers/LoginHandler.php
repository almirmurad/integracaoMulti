<?php
namespace src\handlers;

use src\models\User;
use src\handlers\PermissionHandler;

class LoginHandler {
    private $loggedUser;
    
    public static function checkLogin(){

        $headers = getallheaders();  
        if(isset($headers['Authorization'] ) || !empty($headers['Authorization'])){
            
            $authParts = explode(' ',$headers['Authorization']);
            $token = $authParts[1] ?? null;
            
            if($token){
                
                $data = User::select()->where('token', $token)->one();
            
                if($data > 0){
                    
                    $loggedUser = new User();
                    $loggedUser->id = $data['id'];
                    $loggedUser->name = $data['name'];
                    $loggedUser->mail = $data['email'];
                    $loggedUser->active = $data['active'];
                    $loggedUser->avatar = $data['avatar'];
                    $loggedUser->id_permission = $data['id_permission'];
                    $loggedUser->level = $data['type'];
                    $loggedUser->token = $data['token'];
                    //buscar lista de permissões
                    $loggedUser->permission = PermissionHandler::getPermissions($data['id_permission']);

                    switch($loggedUser->level){
                    case 1: 
                        $loggedUser->level = "Administrador";
                        break;

                    case 2: 
                        $loggedUser->level = "Normal";
                        break;
                    }
                    
                    return $loggedUser;
                }
            }
        }   

        return false;
    }


    public static function verifyLogin($mail, $pass){
        
        $user = User::select()->where ('email', $mail)->one();
              
        if($user){
            if(password_verify($pass, $user['password'])){
                $token = md5(time().rand(0,9999).time());
                User::update()
                    ->set('token',$token)
                    ->where('email', $mail)
                ->execute();     
                return $token;
            }
        }
        return false;
    }

    public static function emailExists($mail){
        $user = User::select()->where ('email', $mail)->one();
        return $user ? true : false;
    }

    public static function addUser($name, $mail, $pass, $newNameAvatar, $active, $type, $id_permission){
        // echo "<pre>";
        // var_dump($avatar);
        // exit;
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $token = md5(time().rand(0,9999).time());
        User::insert([
            'name'      => $name,
            'email'     => $mail,
            'password'  => $hash,
            'avatar'  => $newNameAvatar,
            'type' => $type,
            'id_permission' => $id_permission,
            'active' => $active,
            'token'     => $token,
            'created_at'=> date('Y-m-d H:i:s')
        ])->execute();

 
            
    }

    public static function editUser($name, $mail, $pass, $phone, $type, $id_permission, $avatar, $id){
        $hash = password_hash($pass, PASSWORD_DEFAULT);

        User::update()
                ->set('name', $name)
                ->set('email', $mail)
                ->set('password', $hash)
                ->set('phone', $phone)
                ->set('avatar', $avatar)
                ->set('type_user', $type)
                ->set('id_permission', $id_permission)
                // ->set('created_at', date('Y-m-d H:i:s'))
                ->where('id', $id)
                ->execute();

                return true;
            
                
    }

   
}
