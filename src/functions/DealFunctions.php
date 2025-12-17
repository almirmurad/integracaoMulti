<?php

namespace src\functions;

use src\contracts\ErpFormattersInterface;
use src\exceptions\WebhookReadErrorException;
use src\services\PloomesServices;
use src\services\RDStationServices;
use stdClass;


class DealFunctions
{
    public static function confirmActionDealFromSaveWebhook(array $args, PloomesServices $ploomesServices): bool
    {
        $decoded = $args['body']['New'];

        $pipeline = $ploomesServices->getPipelineById($decoded['PipelineId']);
        $pipelineName = mb_strtolower($pipeline['Name']);

        if(mb_strpos($pipelineName, 'pré') === false){
            throw new WebhookReadErrorException('não é o funil de Pré-Vendas',500);
        }

        $pipelineStages = $pipeline['Stages'];
        $stage =[];
        foreach($pipelineStages as $oneStage){
            if($oneStage['Id'] === $decoded['StageId']){
                $stage = $oneStage;
            }
        }

        if($stage['Ordination'] !== 1){
            throw new WebhookReadErrorException('Não estava na etapa de ABORDAGEM', 500);
        }       

        $customFields = CustomFieldsFunction::getCustomFields();
        $custom = CustomFieldsFunction::compareCustomFields($decoded['OtherProperties'],$args['user']['id'],'Negócio');
        if(!isset($custom['bicorp_api_qualificacao_lead_out']) || $custom['bicorp_api_qualificacao_lead_out'] === 0)
        {
            throw new WebhookReadErrorException('Lead não estava marcado como Qualificado', 500);            
        }

        return true;

    }
    //processa o contato do OmniChannel para o CRM
    public static function processPloomesDeal(array $args, PloomesServices $ploomesServices, array $action): array
    {
        $message = [];
        $current = date('d/m/Y H:i:s');
        $decoded = $args['body']['New'];

        if($action['action'] === 'update')
        {
            // $contactId = $decoded['ContactId'];  
            $dealId = $decoded['Id'];  
            // $ownerId = $decoded['OwnerId'];

            $tasks = $ploomesServices->getTasks($dealId);
            if(is_array($tasks) && empty($tasks)){
                throw new WebhookReadErrorException('Todas as Tarefas já estão finalizadas');
            }

            $totalTasks = count($tasks);
            $contadorSuccess = 0;
            $contadorError = 0;
            foreach($tasks as $task){
                //Manda a tarefa para ploomesServices cancelar
                $finish = $ploomesServices->finishTask($task['Id']);
                if($finish){
                    ++ $contadorSuccess; 
                    $finishMessageSuccess[] = 'Tarefa '.$task['Title'].' Finalizada com sucesso';
                }else{
                    ++ $contadorError;
                    $finishMessageError[] = 'Erro ao finalizar '.$task['Title'];
                }
            }
            if($contadorError > 0 && $contadorError === $totalTasks){
                // $eMessage = json_encode($finishMessageError);
                throw new WebhookReadErrorException('Erro ao finalizar todas as tarefas');
            }elseif($contadorError === 0 && $contadorSuccess === $totalTasks){  
                $sMessage = json_encode($finishMessageSuccess);
                $message['success'] = $sMessage;
            }else{
                $array = [
                    'success' => $finishMessageSuccess,
                    'error' => $finishMessageError
                ];

                $message['success'] = json_encode($array);
            }

            return $message;
        }else{
            throw new WebhookReadErrorException('Chegou no function mas não era atualização');
        }
            
    }


    public static function contactPloomesJson($contact)
    {
        //precisamos que as keys do ploomes sejam iguais aos campos do rd
        $otherProperties = [];
        $customFields = CustomFieldsFunction::getCustomFieldsByEntity('Cliente');
        $mappedFields = CustomFieldsFunction::mapRdToPloomes($contact, $customFields);

        $keys = self::getFieldKey($customFields);

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
