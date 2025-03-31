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
            $customFields = self::divideCustomForEntity($custom);
            self::$customFields = $customFields;
        }
    }

    private static function divideCustomForEntity($customFields)
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
                    $cf[$custom['Entity']['Name']][] = $contacts;
                    break;
                case 2:
                    $deals['Name'] = $custom['Name'];
                    $deals['Key'] = $custom['Key'];
                    $deals['SendExternalKey'] = $custom['SendExternalKey'];
                    $deals['Type'] = $custom['Type']['NativeType'];
                    $deals['Entity'] = $custom['Entity']['Name'];
                    $cf[$custom['Entity']['Name']][] = $deals;
                    break;
                case 7:
                    $quotes['Name'] = $custom['Name'];
                    $quotes['Key'] = $custom['Key'];
                    $quotes['SendExternalKey'] = $custom['SendExternalKey'];
                    $quotes['Type'] = $custom['Type']['NativeType'];
                    $quotes['Entity'] = $custom['Entity']['Name'];
                    $cf[$custom['Entity']['Name']][] = $quotes;
                    break;
                case 8:
                    $quoteSection['Name'] = $custom['Name'];
                    $quoteSection['Key'] = $custom['Key'];
                    $quoteSection['SendExternalKey'] = $custom['SendExternalKey'];
                    $quoteSection['Type'] = $custom['Type']['NativeType'];
                    $quoteSection['Entity'] = $custom['Entity']['Name'];
                    $cf[$custom['Entity']['Name']][] = $quoteSection;
                    break;
                case 10:
                    $products['Name'] = $custom['Name'];
                    $products['Key'] = $custom['Key'];
                    $products['SendExternalKey'] = $custom['SendExternalKey'];
                    $products['Type'] = $custom['Type']['NativeType'];
                    $products['Entity'] = $custom['Entity']['Name'];
                    $cf[$custom['Entity']['Name']][] = $products;
                break;
            }
            
        }
    
        return $cf;

    }



    public static function getCustomFields() {
        
        return self::$customFields;
    }

    public static function compareCustomFields(array $otherProperties, int $id, $entity):array{

        if(!$_SESSION['contact_custom_fields'][$id]){
            $customFields = self::getCustomFields();
        }else{
            $customFields = $_SESSION['contact_custom_fields'][$id];
        }
       
        $customFields = self::getCustomFields();
        
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


    public static function compareCustomFieldsFromOtherProperties(array $otherProperties, $id):array{

      
        $array = [];
        if(!$_SESSION['contact_custom_fields'][$id]){

            $customFields = self::getCustomFields();
        }else{
            $customFields = $_SESSION['contact_custom_fields'][$id];
        }


        // Criar um índice associativo para facilitar a busca
        $customFieldMap = [];
        foreach ($customFields['Cliente'] as $field) {
            $customFieldMap[$field['Key']] = $field['Name'];
        }


       // Mapear os valores de OtherProperties com os nomes dos campos
        foreach ($otherProperties as $property) {
           
            $fieldKey = $property['FieldKey'];
            
            if (isset($customFieldMap[$fieldKey])) {
                $array[$customFieldMap[$fieldKey]] =  $property['StringValue'] ?? $property['BigStringValue'] ?? $property['IntegerValue'] ?? $property['DecimalValue'] ?? $property['DateTimeValue'];
            }
        }
        
        return $array;

    }

    public static function createOtherPropertiesByEntity(array $custom, array|object $data)
    {
        //para cada ERP teremos campos personalizados com valores diferentes. Talvez seja preciso criar um modelo desta função para cada erp ou fazer com que esta função receba os campos diferentes de cada erp;
    
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