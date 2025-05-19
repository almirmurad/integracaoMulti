<?php
namespace src\functions;

use src\services\PloomesServices;

Class CustomFieldsFunction{

    private static $customFields = [];

    public static function loadCustomField($ploomesBase){
        $ploomesServices = new PloomesServices($ploomesBase);
        if(empty(self::$customFields)){
            $customFields =[];
            //pega todos os campos personalizados
            $custom = $ploomesServices->getContactCustomFields();
            //cria um array separado por entidade
            $customFields = self::divideCustomForEntity($custom, $ploomesServices);
            self::$customFields = $customFields;
        }
    }

    private static function divideCustomForEntity($customFields, $ploomesServices)
    {
        $cf = [];
        $contacts = []; // id = 1 name =Cliente
        $deals = []; //id = 2 name =Negócio
        $quotes = []; //id = 7 name = Proposta
        $quoteSection = []; //id = 8 name = Bloco 
        $products = []; //id = 10 name = produto

        foreach($customFields as $custom){
        
            switch($custom['Entity']['Id']){

                case 1:
                    $contacts['Name'] = $custom['Name'];
                    $contacts['Key'] = $custom['Key'];
                    $contacts['SendExternalKey'] = $custom['SendExternalKey'];
                    $contacts['Type'] = $custom['Type']['NativeType'];
                    $contacts['Entity'] = $custom['Entity']['Name']; 
                    $contacts['TypeId'] = $custom['TypeId']; 
                    $contacts['Options'] = null;
                    if($contacts['TypeId'] == 7 && !empty($custom['OptionsTableId']) ){
                        $opt = $ploomesServices->getOptionsTableById($custom['OptionsTableId']);
                        $contacts['Options'] = $opt['Options'];
                    }
                    $cf[$custom['Entity']['Name']][] = $contacts;
                    break;
                case 2:
                    $deals['Name'] = $custom['Name'];
                    $deals['Key'] = $custom['Key'];
                    $deals['SendExternalKey'] = $custom['SendExternalKey'];
                    $deals['Type'] = $custom['Type']['NativeType'];
                    $deals['Entity'] = $custom['Entity']['Name'];
                    $deals['TypeId'] = $custom['TypeId'];
                    $deals['Options'] = null;
                    if($deals['TypeId'] == 7 && !empty($custom['OptionsTableId']) ){
                        $opt = $ploomesServices->getOptionsTableById($custom['OptionsTableId']);
                        $contacts['Options'] = $opt['Options'];
                    }
                    $cf[$custom['Entity']['Name']][] = $deals;
                    break;
                case 4:
                    $orders['Name'] = $custom['Name'];
                    $orders['Key'] = $custom['Key'];
                    $orders['SendExternalKey'] = $custom['SendExternalKey'];
                    $orders['Type'] = $custom['Type']['NativeType'];
                    $orders['Entity'] = $custom['Entity']['Name'];
                    $orders['TypeId'] = $custom['TypeId'];
                    $orders['Options'] = null;
                    if($custom['TypeId'] == 7 && !empty($custom['OptionsTableId'])){
                        
                        $opt = $ploomesServices->getOptionsTableById($custom['OptionsTableId']);                        
                        $orders['Options'] = $opt['Options'];
                      
                    }
                    $cf[$custom['Entity']['Name']][] = $orders;
                    break;
                case 7:
                    $quotes['Name'] = $custom['Name'];
                    $quotes['Key'] = $custom['Key'];
                    $quotes['SendExternalKey'] = $custom['SendExternalKey'];
                    $quotes['Type'] = $custom['Type']['NativeType'];
                    $quotes['Entity'] = $custom['Entity']['Name'];
                    $quotes['TypeId'] = $custom['TypeId'];
                    $quotes['Options'] = null;
                    if($quotes['TypeId'] == 7 && !empty($custom['OptionsTableId']) ){
                        $opt = $ploomesServices->getOptionsTableById($custom['OptionsTableId']);
                        $contacts['Options'] = $opt['Options'];
                    }
                    $cf[$custom['Entity']['Name']][] = $quotes;

                    break;
                case 8:
                    $quoteSection['Name'] = $custom['Name'];
                    $quoteSection['Key'] = $custom['Key'];
                    $quoteSection['SendExternalKey'] = $custom['SendExternalKey'];
                    $quoteSection['Type'] = $custom['Type']['NativeType'];
                    $quoteSection['Entity'] = $custom['Entity']['Name'];
                    $quoteSection['TypeId'] = $custom['TypeId'];
                    $quoteSection['Options'] = null;
                    if($quoteSection['TypeId'] == 7 && !empty($custom['OptionsTableId']) ){
                        $opt = $ploomesServices->getOptionsTableById($custom['OptionsTableId']);
                        $contacts['Options'] = $opt['Options'];
                    }
                    $cf[$custom['Entity']['Name']][] = $quoteSection;
                    break;
                case 10:
                    $products['Name'] = $custom['Name'];
                    $products['Key'] = $custom['Key'];
                    $products['SendExternalKey'] = $custom['SendExternalKey'];
                    $products['Type'] = $custom['Type']['NativeType'];
                    $products['Entity'] = $custom['Entity']['Name'];
                    $products['TypeId'] = $custom['TypeId'];
                    $products['Options'] = null;
                    if($products['TypeId'] == 7 && !empty($custom['OptionsTableId']) ){
                        $opt = $ploomesServices->getOptionsTableById($custom['OptionsTableId']);
                        $contacts['Options'] = $opt['Options'];
                    }
                    $cf[$custom['Entity']['Name']][] = $products;
                break;
            }
            
        }

        return $cf;

    }



    public static function getCustomFields() {
        
        return self::$customFields;
    }

    public static function getCustomFieldsByEntity($entity) {
        
        return self::$customFields[$entity];
    }

    public static function compareCustomFields(array $otherProperties, int $id, $entity):array{

        if(!isset($_SESSION['contact_custom_fields'][$id])){
            $customFields = self::getCustomFields();
        }else{
            $customFields = $_SESSION['contact_custom_fields'][$id];
        }
        
        $custom =[]; 
        //pega array OtherProperties separa chave e valor
        foreach($otherProperties as $op=>$val){
            //pega a chave do array de campos customizados                
            foreach($customFields[$entity] as $k){
                //se a chave do array de campos customizáveis for igual a chave do array OtherProperties
                if($k['Key'] == $op){
                 
                    //monta array custom como o nome do campo customizado e o valor do array de OtherProperties               
                    $custom[$k['SendExternalKey']] = $val;
                }     
            }
        }
        //retorna o array montado
        return $custom;
    }        


    public static function compareCustomFieldsFromOtherProperties(array $otherProperties, string $entity, $id = null):array
    {
        //traz os campos otherProperties com suas chaves convertidas para SendExternalKey
        $array = [];
        if(!isset($_SESSION['contact_custom_fields'][$id])){
          
            $customFields = self::getCustomFields();
      
        }else{
          
            $customFields = $_SESSION['contact_custom_fields'][$id];
            
        }
        //  print_r($customFields);
        // Criar um índice associativo para facilitar a busca
        $customFieldMap = [];
        foreach ($customFields[$entity] as $field) {
            
            $customFieldMap[$field['Key']] = $field['SendExternalKey'];
        }
        // print_r($customFieldMap);
        // Mapear os valores de OtherProperties com os nomes dos campos
        foreach ($otherProperties as $property) {
            $fieldKey = $property['FieldKey'];
            
            if (isset($customFieldMap[$fieldKey])) {
                $array[$customFieldMap[$fieldKey]] =  $property['StringValue'] ?? $property['BigStringValue'] ?? $property['IntegerValue'] ?? $property['DecimalValue'] ?? $property['DateTimeValue'] ?? $property['BoolValue'] ;
            }
        }
        
        return $array;

    }

    public static function createOtherPropertiesByEntity(array $custom, array|object $data)
    {
        //cria um array OtherProperties com os campos customizados vindo de $customFIelds porém a chave $k faz referencia a chave do array $data. ou seja a SendExternaKey bicorp_api_$k_out onde $k = $data['ncm'] por exemplo.
        // print_r($data);
        // exit;
        $otherProperties = [];
        $b =[];
        $type='';
        foreach($data as $k => $value){
            foreach($custom as $field){
                if($field['SendExternalKey'] == 'bicorp_api_'.$k.'_out'){
                    switch($field['Type']){
                        case 'Integer':
                            $type = 'IntegerValue';
                            break;
                        case 'String':
                            $type = 'StringValue';
                            break;
                        case 'Bool':
                            $type = 'BoolValue';
                            break;
                        case 'BigString':
                            $type = 'BigStringValue';
                            break;
                        case 'Decimal':
                            $type = 'DecimalValue';
                            break;
                        case 'DateTime':
                            $type = 'DateTimeValue';
                            break;
                    }
                    $b['FieldKey'] = $field['Key'];
                    $b['Name'] = $field['Name'];
                    $b['Type'] = $type;
                    $body = [
                        'FieldKey'=> $b['FieldKey'],
                        $b['Type'] => $value

                    ];

                    $otherProperties[] = $body;
                }

            }
        }
       
        return $otherProperties;

    }
       

 


}