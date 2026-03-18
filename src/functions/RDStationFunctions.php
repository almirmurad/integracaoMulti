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

    

    public static function processPloomesRDStation(array $args, PloomesServices $ploomesServices, RDStationServices $rdstationServices, array $action): array
    {
        
        $message = [];
        $current = date('d/m/Y H:i:s');
        $decoded = $args['body']['New'];

        $dealId = $decoded['Id'];

        $deal = $ploomesServices->getDealById($dealId) ?? null;

        if($deal === null){
            throw new WebhookReadErrorException('Card não foi encontrado pela API', 500);
        }

        //se for uma empresa
        if(isset($deal['Contact']) && $deal['Contact']['TypeId'] === 1){

            //busca o contato da empresa
            $empresa = $ploomesServices->getClientById($deal['Contact']['Id']);
            //pega o primeiro contato da empresa
            $contact = $empresa['Contacts'][0];
        }elseif(isset($deal['Contact']) && $deal['Contact']['TypeId'] === 2){//se for uma pessoa
            $contact = $deal['Contact'];
        }else{//se for uma empresa e ela foi excluida contact estará vazio e só estará o array person

            $contact = $deal['Person'];

        }

        $cOtherProperties = $contact['OtherProperties'];
   
        $funnel = $deal['Pipeline'];
        $stage = $deal['Stage'];
        $tags = $deal['Contact']['Tags'];
        $lossReason = null;
        $product = null;

        // print_r($tags);
        //tags do cliente
        $rdTags = [];
        foreach($tags as $tag){

            $rdTags[] = mb_strtolower($tag['Tag']['Name']) ?? null;

        }

        $dealCustom = CustomFieldsFunction::compareCustomFieldsFromOtherProperties($deal['OtherProperties'], 'Negócio', $args['Tenancy']['tenancies']['id']);
            
        if(mb_strtolower($funnel['Name']) !== 'vendas' && isset($dealCustom['bicorp_api_solucao_desejada_out']) && !empty($dealCustom['bicorp_api_solucao_desejada_out'])){
            $solucao = $ploomesServices->getOptionsFieldById($dealCustom['bicorp_api_solucao_desejada_out']);
            
            
        }else{
            if(isset($dealCustom['bicorp_api_solucao_definida_out']) && !empty($dealCustom['bicorp_api_solucao_definida_out'])){
                
                $solucao = $ploomesServices->getProductById($dealCustom['bicorp_api_solucao_definida_out']);
            
            }else{
                
                $solucao = $ploomesServices->getOptionsFieldById($dealCustom['bicorp_api_solucao_desejada_out']);
                
            }
        
        }
        
        $product = $solucao['Name'];
            
        if($action['action'] === 'lose'){
            
            //motivo de perda
            $lossReasonId = $deal['LossReasonId'];
            $lossReasons = $ploomesServices->getDealLossReasos($lossReasonId);
            $lossReason = $lossReasons['Name'];
        }
        //data da movimentação
        $moveDate = $current;

        // $PloomesCustomFields = CustomFieldsFunction::getCustomFieldsByEntity('Cliente');
        $contactCustom = CustomFieldsFunction::compareCustomFieldsFromOtherProperties($cOtherProperties, 'Cliente', $args['Tenancy']['tenancies']['id']);

        $funil = $ploomesServices->getOptionsFieldById($contactCustom['bicorp_api_funil_rd_out']);
        //pega o nome do funil para comparar com o nome do APP do RD e buscar os dados no app correto
        $args['funnel'] = $funil['Name'];
        
        $token = $rdstationServices->authenticate($args);
        if(!empty($token)){

            //busca o cliente NO RDStation
            $contactRdResponse = $rdstationServices->getContactByEmail($contact['Email']);
            if($contactRdResponse['http_code'] != 200){
                $contactRD = null;
            }else{
                $contactRD = $contactRdResponse['response'];
            }
        }
           
        //monta a request pra enviar ao RD com os dados vindos do Ploomes
        $dataCrmToMkt = [];
        $dataCrmToMkt['name'] = $contact['Name'];
        $dataCrmToMkt['email'] = $contact['Email'];
        $dataCrmToMkt['tags'] = $rdTags;
                
        $RDAllFields = $rdstationServices->getFields();

        $rdCustoms =  self::getRDCustomFieldsFromRDAllFields($RDAllFields['response']['fields']);

        foreach($rdCustoms as $rdCustom){

            switch($rdCustom['api_identifier']){

                case 'cf_motivo_de_perda': 
                    $dataCrmToMkt['cf_motivo_de_perda'] = $lossReason;
                    break;
                case 'cf_funil':
                    $dataCrmToMkt['cf_funil'] = $funil['Name'];
                    break;
                case 'cf_fase_do_funil_ploomes':
                    $dataCrmToMkt['cf_fase_do_funil_ploomes'] = "{$funnel['Name']} / {$stage['Name']}";
                    break;
                case 'cf_solucao_desejada':
                    $dataCrmToMkt['cf_solucao_desejada'] = $product;
                    break;
                case 'cf_data_da_acao_ploomes':
                    $dataCrmToMkt['cf_data_da_acao_ploomes'] = $moveDate;
                    break;

            }
           
        }

        // $fieldMap = CustomFieldsFunction::mapPloomesFieldsToRD($rdCustoms, $PloomesCustomFields);

        // $pValues = CustomFieldsFunction::extractPloomesValues(
        //     $cOtherProperties, 
        //     $PloomesCustomFields, 
        //     function ($id) use ($ploomesServices) {
        //         return $ploomesServices->getOptionsFieldById($id);
        //     }
        // );
        // print 'aqui';
        // print_r($pValues);
        // // print_r($fieldMap);
        // exit;
        

        // $rdCustomFields = [];

        // foreach ($fieldMap as $map) {
        //     $key = $map['ploomes_key'];

        //     if (!isset($pValues[$key])) {
        //         continue;
        //     }

        //     $dataCrmToMkt = [
        //         $map['rd_identifier'] => $pValues[$key]               
        //     ];
        // }


        $jsonContact = json_encode($dataCrmToMkt);

        // print_r($jsonContact);
        if($contactRD !== null){

            //Envia a request de update contact Ploomes com o UUID do $contactRD.
            $response = $rdstationServices->updateContactByUuid($contactRD['uuid'], $jsonContact);
            if($response['http_code'] != 200){
                throw new WebhookReadErrorException('Erro ao atualizar contato no RDStation!', 500);

            }

            $msgReturn = 'Cliente ' . $contact['Name'] . ' atualizado no RDSTation com sucesso! ';

            $interaction = self::sendInteractionPloomes($contact, $ploomesServices, $msgReturn, $current);


            if($interaction){
                $msgReturn .= $interaction;
            }

            $message['success'] = $msgReturn . $current;

            
        }else{

            //envia a requisição para criar o contato no RDStation
            $response = $rdstationServices->createContactRD($jsonContact);
            // print_r($response);
            if($response['http_code'] != 200){
                throw new WebhookReadErrorException('Erro ao cadastrar contato no RDStation!', 500);

            }
         
            $msgReturn = 'Cliente ' . $contact['Name'] . ' criado no RDSTation com sucesso! ';
            
            $interaction = self::sendInteractionPloomes($contact, $ploomesServices, $msgReturn, $current);


            if($interaction){
                $msgReturn .= $interaction;
            }

            $message['success'] = $msgReturn . $current;




        }

        return $message;
       
               
    }

    //auxiliadores

    public static function getRDCustomFieldsFromRDAllFields($RDAllFields)
    {
        $RDcustomFields = [];
        // print_r($RDAllFields);
        foreach($RDAllFields as $RDfields){

            if($RDfields['custom_field']){

                $RDcustomFields[] = $RDfields;

            }

        }

        return $RDcustomFields;


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
    
    public static function companyPloomesJson($contact)
    {

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

    public static function sendInteractionPloomes($contact, $ploomesServices, $content, $current=null)
    {
            if(!isset($contact['Id'])){
                throw new WebhookReadErrorException('Não foi possível encontrar o cliente no Ploomes - '.$current,500);                
            }

            $frase = 'Interação contato RDStation adicionada no cliente '. $contact['Id'] .' em: '.$current;
            //monta a mensagem para retornar ao ploomes
            $msg = [
                'ContactId'=>  $contact['Id'],
                'TypeId'=> 1,
                'Title'=> 'Contato RDStation',
                'Content'=> $content,
            ];

            $interaction = $ploomesServices->createPloomesIteraction(json_encode($msg));

            if(!$interaction){

                $frase = false;

            }

            return $frase;
        

    }

    // public static function createJsonContactReturnRd(array $contact)
    // {
    //     $json = null;
        


    //     return $json;
    // }
}
