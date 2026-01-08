<?php
namespace src\functions;

use src\services\PloomesServices;

Class CustomFieldsFunction{
    
    private static $customFields = [];

    public static function loadCustomField($ploomesBase, $cacheTtl = 86400){
        // $cacheTtl = 86400 (1 dia) 3600 (1hora)
        // cada tenancy/ploomesBase terá seu próprio cache
        
        $cacheDir = __DIR__ . '/../../cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }
        
        // $tenancyId = md5(json_encode($ploomesBase['tenancy_id'])); // ou use $ploomesBase['id'] se existir
        $tenancyId = json_encode($ploomesBase['tenancy_id']);
        $cacheFile = $cacheDir . "/custom_fields_{$tenancyId}.json";
        
        // se existe cache e ainda está válido
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if (is_array($data)) {
                self::$customFields = $data;
                return;
            }
        }

        // se não tem cache, ou cache expirou → buscar na API do Ploomes
        $ploomesServices = new PloomesServices($ploomesBase);
        $custom = $ploomesServices->getContactCustomFields();
        $customFields = self::divideCustomForEntity($custom, $ploomesServices);


        self::$customFields = $customFields;

        // salvar no cache
        file_put_contents($cacheFile, json_encode($customFields));
    }

    private static function divideCustomForEntity($customFields, $ploomesServices)
    {
        $cf = [];
        $contacts = []; // id = 1 name =Cliente
        $deals = []; //id = 2 name =Negócio
        $quotes = []; //id = 7 name = Proposta
        $quoteSection = []; //id = 8 name = Bloco 
        $products = []; //id = 10 name = produto
        $documents = []; //id = 66 name = documento

        // print_r($customFields);
        // exit;

        foreach($customFields as $custom){
            switch($custom['Entity']['Id']){

                case 1:
                    $contacts['Id'] = $custom['Entity']['Id'];
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
                    $deals['Id'] = $custom['Entity']['Id'];
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
                    $orders['Id'] = $custom['Entity']['Id'];
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
                    $quotes['Id'] = $custom['Entity']['Id'];
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
                    $quoteSection['Id'] = $custom['Entity']['Id'];
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
                    $products['Id'] = $custom['Entity']['Id'];
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
                case 66:
                          
                    $documents['Id'] = $custom['Entity']['Id'];
                    $documents['Name'] = $custom['Name'];
                    $documents['Key'] = $custom['Key'];
                    $documents['SendExternalKey'] = $custom['SendExternalKey'];
                    $documents['Type'] = $custom['Type']['NativeType'];
                    $documents['Entity'] = $custom['Entity']['Name'];
                    $documents['TypeId'] = $custom['TypeId'];
                    $documents['Options'] = null;
                    $documents['OptionsTableId'] = $custom['OptionsTableId'];
                    
                    if($documents['TypeId'] == 7 && !empty($custom['OptionsTableId']) ){
                        
                        $opt = $ploomesServices->getOptionsTableById($custom['OptionsTableId']);
                        $documents['Options'] = $opt['Options'];
                    }
                    $cf[$custom['Entity']['Name']][] = $documents;
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

    public static function compareCustomFields(array $otherProperties, $id, $entity):array{
        
        
        if($_SESSION['contact_custom_fields'][$id] === null){
            
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
        //  print_r($customFields);exit;
        //  print_r($customFields);
        // Criar um índice associativo para facilitar a busca
        $customFieldMap = [];
        foreach ($customFields[$entity] as $field) {           
            $customFieldMap[$field['Key']] = $field['SendExternalKey'];
        }
        // print_r($customFieldMap);exit;
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

    public static function createOtherPropertiesByEntity(array $custom, array|object $data, $ploomesServices = null)
    {
        //cria um array OtherProperties com os campos customizados vindo de $customFIelds porém a chave $k faz referencia a chave do array $data. ou seja a SendExternaKey bicorp_api_$k_out onde $k = $data['ncm'] por exemplo.
        //  print_r($custom);
        // print_r($data);
        // $data->especialidade = 'TRICOLOGIA';
        // exit;
        $otherProperties = [];
        $b =[];
        $type='';
        foreach($data as $k => $value){
            
            foreach($custom as $field){
                if($field['SendExternalKey'] == 'bicorp_api_'.mb_strtolower($k).'_out'){
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
        // foreach($data as $k => $value){
        //     foreach($custom as $field){
        //         // print_r($k);
        //         // print_r($field);
        //         // exit;
                
        //         if($field['TypeId'] === 7){
        //                 print $k;
        //                 exit;
        //             if(mb_strpos($field['SendExternalKey'], $k) > 0){
                        
                        
        //                 if(!empty($field['Options'])){
                            
        //                     foreach ($field['Options'] as $fieldOpt){
                                
        //                             switch($field['Type']){
        //                             case 'Integer':
        //                                 $type = 'IntegerValue';
        //                                 break;
        //                             case 'String':
        //                                 $type = 'StringValue';
        //                                 break;
        //                             case 'Bool':
        //                                 $type = 'BoolValue';
        //                                 break;
        //                             case 'BigString':
        //                                 $type = 'BigStringValue';
        //                                 break;
        //                             case 'Decimal':
        //                                 $type = 'DecimalValue';
        //                                 break;
        //                             case 'DateTime':
        //                                 $type = 'DateTimeValue';
        //                                 break;
        //                             }
                                    
        //                             $b['FieldKey'] = $field['Key'];
        //                             $b['Name'] = $field['Name'];
        //                             $b['Type'] = $type;
                                   
                                   
        //                         if($fieldOpt['Name'] === $data->$k){
        //                             print 'entrou';
        //                             print_r($data->$k);
        //                             print $fieldOpt['Name'];
                                    
        //                             $body = [
        //                                 'FieldKey'=> $b['FieldKey'],
        //                                 $b['Type'] => $fieldOpt['Id']
                
        //                             ];
                
        //                             $otherProperties[] = $body;
                                    
        //                             break;
                                    
        //                         }else{
                                    
                                    
        //                             $tableId = $fieldOpt['TableId'];
        //                             $strOption = $data->$k;
                                    
        //                             $arrayOpt = [
        //                                 "TableId" => $tableId,
        //                                 "Name" => $strOption
        //                             ];
                                    
        //                             $json = json_encode($arrayOpt);
                                    
        //                             $optTable = $ploomesServices->getOptionsTableById($tableId);
                                    
        //                             $names = array_column($optTable['Options'], 'Name');
                         
        //                             if (!in_array($strOption, $names, true)) {
                                        
        //                                 $insert =$ploomesServices->insertOptionsByTableId($json);
        //                             }
                                    
        //                             $body = [
        //                                 'FieldKey'=> $b['FieldKey'],
        //                                 $b['Type'] => $insert['Id']
                            
        //                             ];
                                    
        //                             $otherProperties[] = $body;
        //                             break;
                                    
        //                         }
                               
        //                     }
                            
        //                 }
                        
        //             }
                
                    
        //         }else{
                    
        //         if($field['SendExternalKey'] == 'bicorp_api_'.$k.'_out'){
        //             switch($field['Type']){
        //                 case 'Integer':
        //                     $type = 'IntegerValue';
        //                     break;
        //                 case 'String':
        //                     $type = 'StringValue';
        //                     break;
        //                 case 'Bool':
        //                     $type = 'BoolValue';
        //                     break;
        //                 case 'BigString':
        //                     $type = 'BigStringValue';
        //                     break;
        //                 case 'Decimal':
        //                     $type = 'DecimalValue';
        //                     break;
        //                 case 'DateTime':
        //                     $type = 'DateTimeValue';
        //                     break;
        //             }
        //             $b['FieldKey'] = $field['Key'];
        //             $b['Name'] = $field['Name'];
        //             $b['Type'] = $type;
        //             $body = [
        //                 'FieldKey'=> $b['FieldKey'],
        //                 $b['Type'] => $value

        //             ];

        //             $otherProperties[] = $body;
        //         }
        //         }
                

        //     }
        // }
       
    //   print_r($otherProperties);
    //   exit;

        return $otherProperties;

    }
    
    
    public static function getCustomFieldsKeys($customFields){

        foreach($customFields as $custom){
            
            if($custom['SendExternalKey'] !== null){

                $k = self::removePrefixSuffix($custom['SendExternalKey'], "bicorp_api_", "_out");

                if(mb_strpos($custom['SendExternalKey'], $k)){

                    $arrayKeys[] = [
                        'name' => $k,
                        'key'  => $custom['Key'],
                        'type' => $custom['TypeId']
                    ];
                }
            }
                 
             
        }

        return $arrayKeys;

    }

    public static function removePrefixSuffix(string $string, string $prefix, string $suffix): string {
    // Monta a regex escapando os caracteres especiais do prefixo e sufixo
    $pattern = '/^' . preg_quote($prefix, '/') . '(.*)' . preg_quote($suffix, '/') . '$/';

    // Substitui mantendo apenas o conteúdo do meio (capturado pelo grupo (.*))
    return preg_replace($pattern, '$1', $string);
    }


    //** chat gpt*/


    /**
     * Mapeia os campos entre Ploomes e RD
     *
     * @param array $ploomesFields Lista completa de campos do Ploomes (API retorna array de objetos)
     * @param array $rdData Dados vindos do RD (array associativo)
     * @return array Campos prontos para serem enviados ao Ploomes
     */
    public static function mapFields(array $ploomesFields, array $rdData): array {
        $result = [];
    
        // print_r($ploomesFields);
        // exit;
        foreach ($ploomesFields as $field) {
            if (empty($field['SendExternalKey'])) {
                continue; // campo no Ploomes não está mapeado com nada do RD
            }
    
            $rdKey = $field['SendExternalKey']; // já vem no formato cf_nome_do_campo
    
            print_r($rdKey);
    
            if (!array_key_exists($rdKey, $rdData)) {
                continue; // campo não existe no payload do RD
            }
    
            $value = $rdData[$rdKey];
    
            print_r($value);
           
    
            if ($field['TypeId'] === 7) {
                // Lista de opções -> IntegerValue
                if (is_array($value)) {
                    // Se o RD mandar múltiplos valores (ex: checkboxes)
                    foreach ($value as $v) {
                        $optionId = self::getOptionIdFromValue($field, $v);
                        if ($optionId !== null) {
                            $result[] = [
                                "FieldKey" => $field['Key'],
                                "IntegerValue" => $optionId
                            ];
                        }
                    }
                } else {
                    $optionId = self::getOptionIdFromValue($field, $value);
                    if ($optionId !== null) {
                        $result[] = [
                            "FieldKey" => $field['Key'],
                            "IntegerValue" => $optionId
                        ];
                    }
                }
            } else {
                // Campo normal
                $result[] = [
                    "FieldKey" => $field['Key'],
                    "StringValue" => is_array($value) ? implode(', ', $value) : $value
                ];
            }
        }
    
        // exit;
    
        return $result;
    }
    
    /**
     * Busca o ID da opção correta de um campo lista (type 7)
     *
     * @param array $field Campo do Ploomes que contém as opções
     * @param string $value Valor vindo do RD
     * @return int|null ID da opção ou null se não encontrar
     */
    public static function getOptionIdFromValue(array $field, string $value): ?int {
        if (!isset($field['Options']) || !is_array($field['Options'])) {
            return null;
        }
    
        foreach ($field['Options'] as $option) {
            if (strcasecmp($option['Label'], $value) === 0) {
                return $option['Id'];
            }
        }
    
        return null;
    }
    public static function mapRdToPloomes(array $rdData, array $ploomesFields): array {
        $mapped = [];
    
        foreach ($ploomesFields as $field) {
            if (empty($field['SendExternalKey'])) {
                continue; // ignora campos sem SendExternalKey
            }
    
            // remove prefixo bicorp_api_ e sufixo _out para tentar casar com RD
            $ploomesKey = $field['SendExternalKey'];
    
            $cleanKey = self::removePrefixSuffix($ploomesKey, 'bicorp_api_', '_rd_out');
            //$cleanKey = preg_replace('/^(bicorp_api_)?(.*?)(_rd_out)?$/', '$2', $ploomesKey);
    
            // procura na array do RD Station
            $rdKey = 'cf_' . $cleanKey;  
                
            if (!array_key_exists($rdKey, $rdData)) {
                continue; // campo não existe no RD
            }
            
            $rdValue = $rdData[$rdKey];
                // print_r($field);
            if ($field['TypeId'] == 7 && !empty($field['Options'])) {
                // campo lista: procurar Id na Options
                foreach ($field['Options'] as $option) {
                    if ((string)mb_strtolower($option['Name']) === (string)mb_strtolower($rdValue)) {
                        $mapped[] = [
                            'FieldKey' => $field['Key'],
                            'IntegerValue' => $option['Id']
                        ];
                        break;
                    }
                }
            } else {
                
    
                $typeField = match($field['TypeId']){
                        1 => 'StringValue',
                        2 => 'BigStringValue',
                        5 => 'DecimalValue',
                        8 => 'DateTimeValue',
                        10 => 'BoolValue',
                     
                    };
                // campos normais
                $mapped[] = [
                    'FieldKey' => $field['Key'],
                    $typeField => $rdValue
                ];
            }
        }
    
        return $mapped;
    }


    public static function createPloomesCustomFields($fields, $ploomesServices)
     {
        $op =[];
         foreach($fields as $field){
            $array = [];
            $pCustom = $ploomesServices->getCustomFieldBySendExternalKey($field['SendExternalKey']);
            if(!$pCustom){
                continue;
            }
            $array['FieldKey'] = $pCustom['Key'];
            
            switch($pCustom['Type']['NativeType']){
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

            $array[$type] = $field['Value'];
            $op[] = $array;

        }

        return $op;
     }
    

 


}