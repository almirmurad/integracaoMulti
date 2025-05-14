<?php
namespace src\handlers;

use Exception;

use src\services\DatabaseServices;

class TenancyHandler {

    // public static function listTenancys()
    // {
    //     $data = User::select()->where('type',2)->get();
    //     // print_r($data);
    //     // exit;
    //     if($data>0){
    //         $all = [];
    //         foreach($data as $dt){
    //             $users = new User();
    //             $users->id = $dt['id'];
    //             $users->name = $dt['name'];
    //             $users->mail = $dt['email'];
    //             $users->avatar = $dt['avatar'];
    //             $users->type = $dt['type'];
    //             $users->id_permission = $dt['id_permission'];
    //             $users->active = $dt['active'];

    //             switch($users->type){
    //                 case 1: 
    //                     $users->type = "Administrador";
    //                     break;

    //                 case 2: 
    //                     $users->type = "Tenancy";
    //                     break;
    //                 }

    //             switch($users->active){
    //                 case 1: 
    //                     $users->active = "Ativo";
    //                     break;

    //                 case 2: 
    //                     $users->active = "Inativo";
    //                     break;
    //                 }
    //             $all[]=$users;
    //         }return $all;
    //     }return false;

    // }

    public static function getAllInfoUserAPi($idTenancy): array
    {
        $array = [];  
        try{

            $dataBaseServices = new DatabaseServices();
            $user = $dataBaseServices->getUserById($idTenancy);
            $array = $dataBaseServices->getTenancyByUserId($idTenancy);
            $tenancy = $dataBaseServices->getAllDataTenancyById($array['id'], $user['erp_name']);
            $tenancy['owner'] = $user; 
            return $tenancy;
        }
        catch(Exception $e){
            // print_r($e->getMessage());
            // exit;
             $tenancy['error'] = $e->getMessage();

             return $tenancy;

        }

     

    }

    public static function createNewTenancy($data): string | bool
    {
        
        
        try{
            
            $dataBaseServices = new DatabaseServices();
            $id = $dataBaseServices->createNewTenancy($data);
            
            return $id;
           
        }catch(Exception $e)
        {
            return false;
        }
        

    }

    public static function getTenancyByUserId($idUser): array
    {
        $array = [];  
        try{

            $dataBaseServices = new DatabaseServices();
            $array = $dataBaseServices->getTenancyByUserId($idUser);
                
            return $array;
        }
        catch(Exception $e){
            // print_r($e->getMessage());
            // exit;
            $array['error'] = $e->getMessage();

            return $array;
        }
    }

}



