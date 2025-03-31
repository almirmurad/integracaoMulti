<?php
namespace src\handlers;

use PDO;
use src\models\Permissions_group;
use src\models\User;
use src\models\Permissions_item;
use src\models\Permissions_link;

class PermissionHandler {

    public static function getPermissions($idPermission){
        $permissionsList = [];

        $data = Permissions_link::select('id_permission_item')->where('id_permission_group', $idPermission)->get();

        foreach($data as $dataItem){
            $permissionsItemId[] = $dataItem['id_permission_item'];
        }

        $data = Permissions_item::select('slug')->whereIn('id', $permissionsItemId)->get();

        foreach($data as $dataSlug){
            $permissionsList[] = $dataSlug['slug'];
        }

        return $permissionsList;
    }

    public static function getAllGroups(){

        $completo = [];
        $array = [];
        $permissions = Permissions_group::select()->get();

        foreach($permissions as $p){
            $completo['idPermission'] = $p['id'];
            $completo['namePermission'] = $p['name'];
            $completo['totalUserPermission'] = User::select()->where('id_permission',$p['id'])->count();
            $array[]=$completo;
        }

        return $array;
    }

    public static function delGroupPermission($idGroup){
        
        $usersPermission = User::select()->where('id_permission',$idGroup)->get();
        
        if (empty($usersPermission)){
            Permissions_link::delete()->where('id_permission_group',$idGroup)->execute();
            Permissions_group::delete()->where('id',$idGroup)->execute();

            return true;
        }
        return false;

    }
    
    public static function getAllItems(){
        $items = Permissions_item::select()->get();
        return $items;
    }

    public static function getPermissionGroupName($idGroup){

        $name = Permissions_group::select('name')->where('id', $idGroup)->one();
        
        return $name;
    }

    public static function insertNewPermissionGroup($data){
        if(!empty($data['name'])){
            $id_group = Permissions_group::insert([
                'name'=>$data['name']
            ]
            )->execute();
            
            if(isset($data['itemPermission']) && count($data['itemPermission'])>0){
                $items = $data['itemPermission'];

                foreach($items as $item){
                    Permissions_link::insert([
                        'id_permission_group'=>$id_group,
                        'id_permission_item'=>$item
                    ])->execute();
                }
            }
            return true;
        }
        return false;

    }

    public static function editPermissionGroup($data){
        $id_group = $data['idGroup'];
        
        if(!empty($data['name'])){
           Permissions_group::update()
            ->set('name',$data['name'])
            ->where('id',$data['idGroup'])->execute();

            Permissions_link::delete()->where('id_permission_group',$data['idGroup'])->execute();

            if(isset($data['itemPermission']) && count($data['itemPermission'])>0){
                $items = $data['itemPermission'];

                foreach($items as $item){
                    Permissions_link::insert([
                        'id_permission_group'=>$id_group['id'],
                        'id_permission_item'=>$item
                    ])->execute();
                }
            }
            return true;
            
        }return false;

            
        
        
    }


}
