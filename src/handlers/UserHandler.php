<?php
namespace src\handlers;

use Exception;
use PDOException;
use src\models\User;
use src\handlers\PermissionsHandler;

class UserHandler {

    public static function listAllUsers(){

        $data = User::select()->get();
    
        if($data>0){
            $all = [];
            foreach($data as $dt){
                $users = new User();
                $users->id = $dt['id'];
                $users->name = $dt['name'];
                $users->mail = $dt['email'];
                $users->avatar = $dt['avatar'];
                $users->erp_name = $dt['erp_name'];
                $users->type = $dt['type'];
                $users->id_permission = $dt['id_permission'];
                $users->active = $dt['active'];
                
                $all[]=$users;
            }return $all;
        }return false;

    }

    public static function delUser($id){
       
        User::delete()
        ->where('id', $id)
        ->execute();

        return true;
    }

    public static function addUser($data){
       
        $response = [];
        $hash = password_hash($data['pass'], PASSWORD_DEFAULT);
        $token = md5(time().rand(0,9999).time());
       
        try{

            $id = User::insert([
                'name'      => $data['name'],
                'email'     => $data['mail'],
                'password'  => $hash,
                'type' => 1,
                'mkt_platform_name' => $data['mkt-platform_name'],
                'erp_name' => $data['erpName'],
                'subdomain' => $data['subdomain'],
                'id_permission' => 1,
                'active' => $data['active'],
                'token'     => $token,
                'user_code'     => $data['user_code'],
                'created_at'=> date('Y-m-d H:i:s')
            ])->execute();

            $response['token']= $token;
            $response['id']= $id;

            return $response;

        }catch(PDOException $e){
            throw new Exception($e->getMessage(), 500);
        }

    }

    public static function editUser($name, $mail, $pass, $type, $id_permission, $avatar, $active, $id){
        $hash = password_hash($pass, PASSWORD_DEFAULT);

        User::update()
                ->set('name', $name)
                ->set('email', $mail)
                ->set('password', $hash)
                ->set('type', $type)
                ->set('id_permission', $id_permission)
                ->set('avatar', $avatar)
                ->set('active', $active)
                // ->set('created_at', date('Y-m-d H:i:s'))
                ->where('id', $id)
                ->execute();

                return true;
            
                
    }



   
}
