<?php

namespace src\functions;

use src\contracts\ErpFormattersInterface;
use src\exceptions\WebhookReadErrorException;
use src\services\PloomesServices;
use src\services\RDStationServices;
use stdClass;


class RDStationFunctions
{
    //processa o contato do OmniChannel para o CRM
    public static function processRDStationPloomes(array $args, PloomesServices $ploomesServices, RDStationServices $rdstationServices, array $action): array
    {
        
        $message = [];
        $current = date('d/m/Y H:i:s');
        $decoded = $args['body'];

        $contact = $decoded['contact'];  
        
        $nome = trim($contact['name']) ?? null;
        $sobrenome = trim($contact['cf_sobrenome']) ?? null;
        $nomeCompleto = "{$nome} {$sobrenome}";
        $contact['nomeCompleto'] = $nomeCompleto;
        
        $owner = new stdClass();
        $owner->mailVendedor = $contact['funnel']['contact_owner_email'] ?? null;
        // $owner->mailVendedor = 'bicorp2@rhopen.com';
        ($owner->mailVendedor) ? $owner->ploomesOwnerId = $ploomesServices->ownerId($owner) : $owner->ploomesOwnerId = null;
        $contact['ownerId'] = $owner->ploomesOwnerId;
        //encontra o pipeline pelo nome
        $pipeline['pipelineId'] = $ploomesServices->getPipelineByName('Pré-Vendas');
    
        if(!isset($pipeline['pipelineId']) || $pipeline['pipelineId'] === null){
            throw new WebhookReadErrorException('Funil de Destino não foi encontrado no Ploomes', 500);
        }

        $pipeline['stageId'] = $ploomesServices->getPipelineStagesByPipelineId($pipeline['pipelineId']);
        
        $nomeCompletoMaiusculo = mb_strtoupper($nomeCompleto);
        $title = "{$nomeCompletoMaiusculo}/{$contact['cf_funil']}";

        //verifica se o cliente já existe no ploomes
        $pContact = $ploomesServices->getClientByEmail($contact['email']);
       
        //busca todos os cargos no Ploomes
        $contact['roleId'] = null;
        $roles = $ploomesServices->getRoles();
        
        foreach($roles as $role){
            if(mb_strtolower($contact['cf_cargo_sf']) === mb_strtolower($role['Name'])){
                $contact['roleId'] = $role['Id'];
            }
        }
        
        //verifica se tem empresa cadastrada no RD
        if(isset($contact['company']) && !empty($contact['company']['name'])){
            
            //verifica se a empresa do cliente já existe no ploomes
            $pCompany = $ploomesServices->getClientByName($contact['company']['name']);
            if(!$pCompany){
                $companyJson = self::companyPloomesJson($contact);
                $createCompanyId = $ploomesServices->createPloomesPerson($companyJson);
                if ($createCompanyId <= 0) {
                    throw new WebhookReadErrorException('Não foi possível cadastrar contato no Ploomes', 500);
                }
            }else{
                $createCompanyId = $pCompany['Id'];
            }
        }
        
        $contact['companyId'] = $createCompanyId;
        
        $tagsRd = $contact['tags'] ?? [];
            $tagDefault = 'OPPORTUNITY';
            $entityId = 1;

            // Tags já cadastradas no Ploomes
            $tags = $ploomesServices->getTagsByEntityId($entityId);

            // Cria um mapa: ['nome_em_lowercase' => Id]
            $ploomesMap = [];
            foreach ($tags as $t) {
                $ploomesMap[mb_strtolower($t['Name'])] = $t['Id'];
            }

            $contact['tagIds'] = [];

            // percorre as tags vindas do RD
            foreach ($tagsRd as $tag) {
                $tagName = trim($tag);
                $key = mb_strtolower($tagName);

                if (isset($ploomesMap[$key])) {
                    // já existe → usa o Id
                    $contact['tagIds'][] = ['TagId' => $ploomesMap[$key]];
                } else {
                    // não existe → cria
                    $hexa = '#' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                    $newId = $ploomesServices->insertTag($tagName, $entityId, $hexa);

                    if ($newId) {
                        $contact['tagIds'][] = ['TagId' => $newId];
                        // atualiza o mapa para futuras comparações
                        $ploomesMap[$key] = $newId;
                    }
                }
            }

            // se não encontrou nenhuma tag, usa a default
            if (empty($contact['tagIds'])) {
                $key = mb_strtolower($tagDefault);

                if (isset($ploomesMap[$key])) {
                    $contact['tagIds'][] = ['TagId' => $ploomesMap[$key]];
                } else {
                    $hexa = '#' . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                    $newId = $ploomesServices->insertTag($tagDefault, $entityId, $hexa);

                    if ($newId) {
                        $contact['tagIds'][] = ['TagId' => $newId];
                        $ploomesMap[$key] = $newId;
                    }
                }
            }
            
            $contactJson = self::contactPloomesJson($contact);
            
       
            if (!$contactJson) {
                throw new WebhookReadErrorException('Não foi possível montar os dados do contato', 500);
            }
        
        
     
        if(!$pContact){
            // Tags do RDStation
            $createContactPloomes = $ploomesServices->createPloomesPerson($contactJson);
    
            if ($createContactPloomes <= 0) {
                throw new WebhookReadErrorException('Não foi possível cadastrar contato no Ploomes', 500);
            }

            $cardJson = self::dealsPloomesJson($createContactPloomes, $owner, $title, $pipeline);

            if (!$cardJson) {
                throw new WebhookReadErrorException('Não foi possível montar os dados do card no Ploomes', 500);
            }

            $card = $ploomesServices->createPloomesDeal($cardJson);

            if ($card) {
                $message['success'] = 'Cliente ' . $contact['name'] . ' criado(a) no Ploomes CRM com sucesso! Foi criado uma oportunidade de negócio para ele(a) no funil: ' . $card['Pipeline']['Name'] . '. Card Id: ' . $card['Id'] . ' Título: ' . $card['Title'] . ' Estágio: ' . $card['Stage']['Name'] . ' Status: ' . $card['Status']['Name'] . ' em: ' . $current;
            }

            return $message;

        }else{
            
            // Tags do RDStation
            $updateContactPloomes = $ploomesServices->updatePloomesContact($contactJson, $pContact['Id']);
    
            if ($updateContactPloomes <= 0) {
                throw new WebhookReadErrorException('Não foi possível cadastrar contato no Ploomes', 500);
            }

            $cardJson = self::dealsPloomesJson($pContact['Id'], $owner, $title, $pipeline);

            if (!$cardJson) {
                throw new WebhookReadErrorException('Não foi possível montar os dados do card no Ploomes', 500);
            }

            $card = $ploomesServices->createPloomesDeal($cardJson);

            if ($card) {
                $message['success'] = 'Cliente ' . $pContact['Name'] . ' já existia no Ploomes! Foi atualizado e criado uma nova oportunidade de negócio para ele(a) no funil: ' . $card['Pipeline']['Name'] . '. Card Id: ' . $card['Id'] . ' Título: ' . $card['Title'] . ' Estágio: ' . $card['Stage']['Name'] . ' Status: ' . $card['Status']['Name'] . ' em: ' . $current;
            } 
            
            return $message;

        }
    

               
    }

    public static function contactPloomesJson($contact)
    {
        //precisamos que as keys do ploomes sejam iguais aos campos do rd
        $otherProperties = [];
        $customFields = CustomFieldsFunction::getCustomFieldsByEntity('Cliente');
        $mappedFields = CustomFieldsFunction::mapRdToPloomes($contact, $customFields);
        
        $keys = self::getFieldKey($customFields);
        
        // print_r($mappedFields);
        //      exit;

        // print_r($mappedFields);
        

        //insere os campos customizados do rd nos campos customizados do Ploomes
        foreach($mappedFields as $map){
           array_push($otherProperties, $map);
        }

        //isere o campo origem do rd no campo customizavel origem rd do ploomes 
        if(isset($contact['funnel']['origin']) && !empty($contact['funnel']['origin'])){

            foreach($keys as $key){
              
                if($key['name'] === 'origem_lead_rd'){
                    $a = [
                        'FieldKey'=>$key['key'],
                        'StringValue'=>$contact['funnel']['origin']
                    ];
    
                    array_push($otherProperties, $a);
                }
            }
        }
 
        $phonesRd = [];
        $arrayPhones = [];
        $phonesRd[]['phone_number'] = (isset($contact['personal_phone'])) ? DiverseFunctions::formatarTelefone($contact['personal_phone']) : null;
        $phonesRd[]['phone_number']= (isset($contact['mobile_phone'])) ? DiverseFunctions::formatarTelefone($contact['mobile_phone']) : null;

        foreach($phonesRd as $phone){
            if(isset($phone['phone_number']) && $phone['phone_number'] !== null){
                $arrayPhones[]['PhoneNumber'] = $phone['phone_number'];  
            }
        }
        
        $array = [
            'Name' => $contact['nomeCompleto'],
            'TypeId' => 2,
            'Email' => $contact['email'],
            'CompanyId' => $contact['companyId'] ?? null,
            'Phones' => $arrayPhones,
            'RoleId'=>$contact['roleId'],
            'OtherProperties' => $otherProperties,
            'Tags' => $contact['tagIds'],
            'OwnerId' => $contact['ownerId']
        ];

        // print_r($array);
        // exit;

        return json_encode($array);

    }
    
        public static function companyPloomesJson($contact){

        $array = [
            'Name' => $contact['company']['name'],
            'TypeId' => 1,
            'OwnerId' => $contact['ownerId'],

            // 'Email' => $contact['email'],
            // 'Phones' => [
            //     [
            //         'PhoneNumber' => (isset($contact['personal_phone'])) ? DiverseFunctions::formatarTelefone($contact['personal_phone']) : null,
            //         'TypeId' => 1,
            //     ],
            // ],
            // 'RoleId'=>$contact['roleId'],
            // 'OtherProperties' => $otherProperties,
            // 'Tags' => $contact['tagIds'],
        ];

        return json_encode($array);


    }

    public static function dealsPloomesJson($pContact, $owner, $title, $pipeline = null, $dealOrigin = null)
    {
        $customFields = CustomFieldsFunction::getCustomFieldsByEntity('Negócio');
        
        $keys = self::getFieldKey($customFields);
        
   
        $otherProperties = [
                // [
                //     'FieldKey' => $keys['id_chat'],
                //     'StringValue' => null,
                // ],
                // [
                //     'FieldKey' => $keys['owner_sdr'],
                //     'StringValue' => $owner->owner_sdr ?? null,
                // ],
                // [
                //     'FieldKey' => $keys['notes_sdr'],
                //     'BigStringValue' => $dealOrigin['notes'] ?? null,
                // ]

            ];

            

         
        if(!isset($dealOrigin['dealOrigemId'])){

            $array = [
            'Title' => $title,
            'ContactId' => $pContact,
            'PipelineId' => $pipeline['pipelineId'],
            'StageId' => $pipeline['stageId'],
            'OwnerId' => $owner->ploomesOwnerId,
            'OtherProperties' => $otherProperties,
        ];

        }else{

            $array = [
                'Title' => $title,
                'ContactId' => $pContact,
                'PipelineId' => $pipeline['pipelineId'],
                'StageId' => $pipeline['stageId'],
                'OwnerId' => $owner->ploomesOwnerId,
                'OriginDealId' => $dealOrigin['dealOrigemId'],
                'OtherProperties' => $otherProperties,
            ];
        }
        return json_encode($array);
    }

    public static function getCardByIdChat($idChat, $ploomesServices, $pipeline = null )
    {
        $keys = self::getFieldKey('Negócio');
        return $ploomesServices->getCardByIdChat($idChat, $keys, $pipeline);
    }

    public static function getFieldKey($customFields)
    {

        $keys = CustomFieldsFunction::getCustomFieldsKeys($customFields);
        
        return $keys;
    }
}
