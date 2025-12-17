<?php
namespace src\handlers;

use Exception;
use src\models\api_user;
use src\models\User;
use src\services\BicorpApiServices;
use src\services\DatabaseServices;

class AppsHandler {

    public static function createNewAppErp($data): array
    {
        $response=[];
        try{

            $t = TenancyHandler::getTenancyByUserId($data['user_api_id']);

            if(isset($t['error']) && !empty($t['error'])){
                http_response_code(500);
                throw new Exception($t['error'], 500);
            }

            $data['tenancy_id'] = $t['id'];
            
            $dataBaseServices = new DatabaseServices();
            $id = $dataBaseServices->createNewAppErp($data);
            $response['status'] = 200;
            $response['content'] = $id;
            
            return $response;
           
        }catch(Exception $e)
        {
            $response['status'] = 500;
            $response['content'] = $e->getMessage();

            return $response;
        }
        

    }

    
    public static function createNewAppMkt($data): array
    {
        $response=[];
        try{

            $t = TenancyHandler::getTenancyByUserId($data['user_api_id']);

            if(isset($t['error']) && !empty($t['error'])){
                http_response_code(500);
                throw new Exception($t['error'], 500);
            }

            $data['tenancy_id'] = $t['id'];
            
            $dataBaseServices = new DatabaseServices();
            $result = $dataBaseServices->createNewAppMkt($data);

            if($result['success']){
                $response['status'] = 200;
                $response['content'] = $result['content'];
                return $response;
            }else{
                $response['status'] = 500;
                $response['content'] = $result['content'];
                return $response;
            }

            
           
        }catch(Exception $e)
        {
            $response['status'] = 500;
            $response['content'] = $e->getMessage();

            return $response;
        }
        

    }

    public static function createNewAppPloomes($data): array
    {       
        $response=[];
        try{
       
            $t = TenancyHandler::getTenancyByUserId($data['user_api_id']);
            $data['tenancy_id'] = $t['id'];
            
            $dataBaseServices = new DatabaseServices();
            $id = $dataBaseServices->createNewAppPloomes($data);
            $response['status'] = 200;
            $response['content'] = $id;
            
            return $response;
           
        }catch(Exception $e)
        {
            $response['status'] = 500;
            $response['content'] = $e->getMessage();

            return $response;
        }
        

    }

    public static function createVhostRabbitMQ($data): array
    {
        $response=[];
        try{
            $t = TenancyHandler::getTenancyByUserId($data['user_api_id']);
            $data['tenancy_id'] = $t['id'];
            
            $dataBaseServices = new DatabaseServices();
            $id = $dataBaseServices->createNewVHostRabbitMQ($data);
            $response['status'] = 200;
            $response['content'] = $id;
            
            return $response;
           
        }catch(Exception $e)
        {
            $response['status'] = 500;
            $response['content'] = $e->getMessage();

            return $response;
        }
    }

        public static function createNewAppOmni($data): array
    {       
        $response=[];
        try{
       
            $t = TenancyHandler::getTenancyByUserId($data['user_api_id']);
            $data['tenancy_id'] = $t['id'];
            
            $dataBaseServices = new DatabaseServices();
            $id = $dataBaseServices->createNewAppOmni($data);
            $response['status'] = 200;
            $response['content'] = $id;
            
            return $response;
           
        }catch(Exception $e)
        {
            $response['status'] = 500;
            $response['content'] = $e->getMessage();

            return $response;
        }
        

    }

    

}



