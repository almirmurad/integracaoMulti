<?php
namespace src\handlers;
use Dotenv\Dotenv;


class ConfigHandler{

    public static function configOmieEpt($SK='',$APPK='',$NCC=''){
        
        $file = parse_ini_file('../.env');
        switch($file){
            case isset($file['SECRETS_EPT']) && !empty($file['SECRETS_EPT']) && $file['SECRETS_EPT'] != $SK && $SK != '':
                $file['SECRETS_EPT'] = $SK;
                break;
            case isset($file['APPK_EPT']) && !empty($file['APPK_EPT']) && $file['APPK_EPT'] != $APPK && $APPK !='':
                $file['APPK_EPT'] = $APPK;
                break;
            case isset($file['NCC_EPT']) && !empty($file['NCC_EPT']) && $file['NCC_EPT'] != $NCC && $NCC != $NCC:
                $file['NCC_EPT'] = $NCC;
                break;  
        }
        $keys = array('SECRETS_EPT'=>$SK,'APPK_EPT'=>$APPK,'NCC_EPT'=>$NCC);
        return Self::montaArray($file, $keys); 


    }
    public static function configOmieGtc($SK='',$APPK='',$NCC=''){
        $file = parse_ini_file('../.env');   
        switch($file){
            case isset($file['SECRETS_GTC']) && !empty($file['SECRETS_GTC']) && $file['SECRETS_GTC'] != $SK && $SK != '':
                $file['SECRETS_GTC'] = $SK;
                break;
            case isset($file['APPK_GTC']) && !empty($file['APPK_GTC']) && $file['APPK_GTC'] != $APPK && $APPK != '':
                $file['APPK_GTC'] = $APPK;
                break;
            case isset($file['NCC_GTC']) && !empty($file['NCC_GTC']) && $file['NCC_GTC'] != $NCC && $NCC != '':
                $file['NCC_GTC'] = $NCC;
                break;  
        }
        $keys = array('SECRETS_GTC'=>$SK,'APPK_GTC'=>$APPK,'NCC_GTC'=>$NCC);
        return Self::montaArray($file, $keys);
    }

    public static function configOmieSmn($SK='',$APPK='',$NCC=''){  
        $file = parse_ini_file('../.env');
        switch($file){  
            case isset($file['SECRETS_SMN']) && !empty($file['SECRETS_SMN']) && $file['SECRETS_SMN'] != $SK && $SK !='':
                $file['SECRETS_SMN'] = $SK;
                break;
            case isset($file['APPK_SMN']) && !empty($file['APPK_SMN']) && $file['APPK_SMN'] != $APPK && $APPK !='':
                $file['APPK_SMN'] = $APPK;
                break;
            case isset($file['NCC_SMN']) && !empty($file['NCC_SMN']) && $file['NCC_SMN'] != $NCC && $NCC != '':
                $file['NCC_SMN'] = $NCC;
                break;  
            
        } 
        $keys = array('SECRETS_SMN'=>$SK,'APPK_SMN'=>$APPK,'NCC_SMN'=>$NCC);
        return Self::montaArray($file, $keys);
    }

    public static function configOmieGsu($SK='',$APPK='',$NCC=''){  
        $file = parse_ini_file('../.env');
        switch($file){  
            case isset($file['SECRETS_GSU']) && !empty($file['SECRETS_GSU']) && $file['SECRETS_GSU'] != $SK && $SK !='':
                $file['SECRETS_GSU'] = $SK;
                break;
            case isset($file['APPK_GSU']) && !empty($file['APPK_GSU']) && $file['APPK_GSU'] != $APPK && $APPK !='':
                $file['APPK_GSU'] = $APPK;
                break;
            case isset($file['NCC_GSU']) && !empty($file['NCC_GSU']) && $file['NCC_GSU'] != $NCC && $NCC != '':
                $file['NCC_GSU'] = $NCC;
                break;  
            
        } 
        $keys = array('SECRETS_GSU'=>$SK,'APPK_GSU'=>$APPK,'NCC_GSU'=>$NCC);
        return Self::montaArray($file, $keys);
    }

    public static function configPloomesApk($PLM_APK=''){  
        $file = parse_ini_file('../.env');
        switch($file){  
            case isset($file['API_KEY']) && !empty($file['API_KEY']) && $file['API_KEY'] != $PLM_APK && $PLM_APK !='':
                $file['API_KEY'] = $PLM_APK;
                break;
            
        } 
        $keys = array('API_KEY'=>$PLM_APK);
        return Self::montaArray($file, $keys);
    }

    public static function montaArray($file, $keys){

        $c = array_merge($file,$keys);
        $str="";
        foreach($c as $k=>$value){
            $str .= $k.'='.$value."\n";

        }

        file_put_contents('../.env',$str);

        return true;
    }

}
