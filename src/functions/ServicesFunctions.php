<?php
namespace src\functions;

use src\exceptions\WebhookReadErrorException;
use src\models\Contact;
use stdClass;


class ServicesFunctions{

    // encontra o processo a ser executado caso haja cadastro, exclusão ou alteração no webhook
    public static function findAction($json)
    {
        //decodifica o json de clientes vindos do json
        //$json = $json['json'];
        $decoded = json_decode($json, true);
        $current = date('d/m/Y H:i:s');
        //identifica qual action do json
        if(isset($decoded['Action'])){

            $action = match($decoded['Action']){
                'Create' => 'createCRMToERP',
                'Update' => 'updateCRMToERP',
                'Delete' => 'deleteCRMToERP'
            };
        }elseif(isset($decoded['topic'])){
            $action = match($decoded['topic']){
                'Servico.Incluido' => 'createERPToCRM',
                'Servico.Alterado' => 'updateERPToCRM',
                'Servico.Excluido' => 'deleteERPToCRM'
            };
        }else{
            throw new WebhookReadErrorException('Não foi encontrda nenhuma ação no webhook '.$current, 500);
        }

        return $action;

    }

    //cria um objeto do webhook vindo do omie para enviar ao ploomes
    public static function createOmieObj($json)
    {
        //decodifica o json de produto vindos do webhook
        //$json = $webhook['json'];
        $decoded = json_decode($json, true);
        //achata o array multidimensional decoded em um array simples
        $array = DiverseFunctions::achatarArray($decoded);
        //cria o objeto de produtos
        $service = new stdClass();

        switch($decoded['appKey']){
            case '1120581879417': 
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_EPT'];
                $omie->appSecret = $_ENV['SECRETS_EPT'];
                $service->baseFaturamentoTitle = 'Engeparts';
                // $cOmie = [
                //     'FieldKey'=>'contact_4F0C36B9-5990-42FB-AEBC-5DCFD7A837C3',
                //     'StringValue'=>$service->codigoClienteOmie,
                // ];
                break;
            case '146532853467': 
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_GTC'];
                $omie->appSecret = $_ENV['SECRETS_GTC'];
                $service->baseFaturamentoTitle = 'Gamatermic';
                // $cOmie = [
                //     'FieldKey'=>'contact_6DB7009F-1E58-4871-B1E6-65534737C1D0',
                //     'StringValue'=>$service->codigoClienteOmie,

                // ];
                break;
            case '146571186762':
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_SMN'];
                $omie->appSecret = $_ENV['SECRETS_SMN']; 
                $service->baseFaturamentoTitle = 'Semin';
                // $cOmie = [
                //     'FieldKey'=>'contact_AE3D1F66-44A8-4F88-AAA5-F10F05E662C2',
                //     'StringValue'=>$service->codigoClienteOmie,
                // ];
                break;
            case '171250162083': 
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_GSU'];
                $omie->appSecret = $_ENV['SECRETS_GSU']; 
                $service->baseFaturamentoTitle = 'GSU';
                // $cOmie = [
                //     'FieldKey'=>'contact_07784D81-18E1-42DC-9937-AB37434176FB',
                //     'StringValue'=>$service->codigoClienteOmie,

                // ];
                break;
        }

        $service->grupo = 'Serviços';
        $service->idGrupo = 400345487;
        $service->messageId = $array['messageId'];
        $service->topic = $array['topic'];
        $service->codLC116 = $array['event_cabecalho_cCodLC116'];
        $service->codServMun = $array['event_cabecalho_cCodServMun'];
        $service->codigo = $array['event_cabecalho_cCodigo'];
        $service->descricao = $array['event_cabecalho_cDescricao'];
        $service->idTrib = $array['event_cabecalho_cIdTrib'];
        $service->idNBS = $array['event_cabecalho_nIdNBS'];
        $service->precoUnit = $array['event_cabecalho_nPrecoUnit'];
        $service->descrCompleta = $array['event_descricao_cDescrCompleta'];
        $service->retCOFINS = $array['event_impostos_cRetCOFINS'];
        $service->retCSLL = $array['event_impostos_cRetCSLL'];
        $service->retINSS = $array['event_impostos_cRetINSS'];
        $service->retIR = $array['event_impostos_cRetIR'];
        $service->retISS = $array['event_impostos_cRetISS'];
        $service->retPIS = $array['event_impostos_cRetPIS'];
        $service->aliqCOFINS = $array['event_impostos_nAliqCOFINS'];
        $service->aliqCSLL = $array['event_impostos_nAliqCSLL'];
        $service->aliqINSS = $array['event_impostos_nAliqINSS'];
        $service->aliqIR = $array['event_impostos_nAliqIR'];
        $service->aliqISS = $array['event_impostos_nAliqISS'];
        $service->aliqPIS = $array['event_impostos_nAliqPIS'];
        $service->redBaseINSS = $array['event_impostos_nRedBaseINSS'];
        $service->impAPI = $array['event_info_cImpAPI'];
        $service->inativo = $array['event_info_cInativo'];
        $service->dAlt = $array['event_info_dAlt'];
        $service->dInc = $array['event_info_dInc'];
        $service->hAlt = $array['event_info_hAlt'];
        $service->hInc = $array['event_info_hInc'];
        $service->uAlt = $array['event_info_uAlt'];
        $service->uInc = $array['event_info_uInc'];
        $service->codIntServ = $array['event_intListar_cCodIntServ'];
        $service->codServ = $array['event_intListar_nCodServ'];
        $service->author_email = $array['author_email'];
        $service->author_name = $array['author_name'];
        $service->author_userId = $array['author_userId'];
        $service->appKey = $array['appKey'];
        $service->appHash = $array['appHash'];
        $service->origin = $array['origin'];      
 
        return $service;
    }

 
    // cria o objet e a requisição a ser enviada ao ploomes com o objeto do omie
    public static function createPloomesServiceFromOmieObject($service, $ploomesServices, $omieServices)
    {
       
        switch($service->appKey){
            case '1120581879417': 
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_EPT'];
                $omie->appSecret = $_ENV['SECRETS_EPT'];
                $service->baseFaturamentoTitle = 'Engeparts';
                $cOmie = [
                    'FieldKey'=>'product_0A53B875-0974-440F-B4CE-240E8F400B0F',
                    'StringValue'=>$service->codServ,
                ];
                break;
            case '146532853467': 
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_GTC'];
                $omie->appSecret = $_ENV['SECRETS_GTC'];
                $service->baseFaturamentoTitle = 'Gamatermic';
                $cOmie = [
                    'FieldKey'=>'product_E241BF1D-7622-45DF-9658-825331BD1C2D',
                    'StringValue'=>$service->codServ,

                ];
                break;
            case '146571186762':
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_SMN'];
                $omie->appSecret = $_ENV['SECRETS_SMN']; 
                $service->baseFaturamentoTitle = 'Semin';
                $cOmie = [
                    'FieldKey'=>'product_429C894A-708E-4125-A434-2A70EDCAFED6',
                    'StringValue'=>$service->codServ,
                ];
                break;
            case '171250162083': 
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_GSU'];
                $omie->appSecret = $_ENV['SECRETS_GSU']; 
                $service->baseFaturamentoTitle = 'GSU';
                $cOmie = [
                    'FieldKey'=>'product_816E5031-2843-4E71-8721-E97185A98E77',
                    'StringValue'=>$service->codServ,

                ];
                break;
        }
        //cria o produto formato ploomes 
        $data = [];

        $data['Name'] = $service->descricao;
        $data['GroupId'] = $service->idGrupo;
        // $data['FamilyId'] = $service->codigo_familia;
        $data['Code'] = $service->codigo;
        //$data['ImageUrl'] = $service->endereco ?? null;
        $data['UnitPrice'] = $service->precoUnit ?? null;
        // $data['CreateImportId'] = $city['Id'];//pegar na api do ploomes
        // $data['UpdateImportId'] = $service->segmento ?? null;//Id do Tipo de atividade(não veio no webhook de cadastro do omie)
        // $data['Editable'] = $service->nFuncionarios ?? null;//Id do número de funcionários(não veio no webhook de cadastro do omie)
        // $data['Deletable'] = $service->cVendedorPloomes ?? null;//Id do vendedor padrão(comparar api ploomes)
        // $data['Suspended'] = $service->observacao ?? null;
        // $data['CreatorId'] = $service->email ?? null;
        // $data['UpdaterId'] = $service->homepage ?? null;
        // $data['CreateDate'] = $service->cnae ?? null;
        // $data['LastUpdateDate'] = $service->codigoClienteOmie ?? null;//chave externa do cliente(código Omie)
        //$data['ImportationIdCreate'] = $service->latitude ?? null;(inexistente no omie)
        //$data['ImportationIdUpdate'] = $service->longitude ?? null;(inexistente no omie)
        
        $op = [];
        // $ncm = [
        //     'FieldKey'=> 'product_15405B03-AA47-4921-BC83-E358501C3227',
        //     'StringValue'=>$service->ncm ?? null,
        // ];
        // $marca = [
        //     'FieldKey'=>'product_4C2CCB79-448F-49CF-B27A-822DA762BE5E',
        //     'StringValue'=>$service->marca ?? null,
        // ];

        // $modelo = [
        //     'FieldKey'=>'product_A92259E5-1E19-44AC-B781-CB908F5602EC',
        //     'StringValue'=>$service->modelo ?? null,
        // ];
        $descDetalhada = [
            'FieldKey'=>'product_F48280B4-688C-4346-833C-03E28991564C',
            'BigStringValue'=>$service->descrCompleta ?? null,
        ];
        $obsInternas = [
            'FieldKey'=>'product_5FB6D80C-CB90-4A46-95BD-1A18141FBC46',
            'BigStringValue'=>$service->nCodServ ?? null,
        ];
        // $categoria = [
        //     'FieldKey'=>'product_44CCBB11-CD81-439A-8304-921C2E39C25D',
        //     'StringValue'=>$service->codigo_familia ?? null,
        // ];

        $op[] = $descDetalhada;
        $op[] = $obsInternas;
        $op[] = $cOmie;
        // $op[] = $categoria;
   
        $data['OtherProperties'] = $op;

        // print_r($data);
        // exit;
        $json = json_encode($data);

        return $json;

    }

}