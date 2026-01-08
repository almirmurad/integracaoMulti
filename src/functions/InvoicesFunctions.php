<?php
namespace src\functions;

use src\contracts\ErpFormattersInterface;
use src\exceptions\WebhookReadErrorException;
use src\services\PloomesServices;
use stdClass;


class InvoicesFunctions{

    //processa o contato do CRM para o ERP
    // public static function processContactCrmToErp($args, PloomesServices $ploomesServices, ErpFormattersInterface $formatter, $action):array
    // {
    //     $contact = $formatter->createObjectErpClientFromCrmData($args, $ploomesServices);

    //     $total = 0;
    //     foreach($contact->basesFaturamento as $k => $tnt)
    //     {
    //         $tenant[$k] = new stdClass();
  
    //         if(isset($tnt['integrar']) && $tnt['integrar'] > 0){
                
    //             switch (strtolower($args['user']['erp_name'])){
    //                 case 'omie':
    //                     $tenant[$k]->tenant = $tnt['app_name'];
    //                     $tenant[$k]->appSecret = $tnt['app_secret'];
    //                     $tenant[$k]->appKey = $tnt['app_key'];
    //                     $tenant[$k]->ncc = $tnt['ncc'];
    //                     $tenant[$k]->integrar = $tnt['integrar'];
    //                     $tenant[$k]->sendExternalKeyIdErp = $tnt['sendExternalKeyIdErp'];
    //                     $contact->tenant = $tenant[$k];
    //                     break;
    //                 case 'nasajon':
    //                     $tenant[$k]->tenant = $tnt['app_name'];
    //                     $tenant[$k]->client_id = $tnt['client_id'];
    //                     $tenant[$k]->client_secret = $tnt['client_secret'];
    //                     $tenant[$k]->access_token = $tnt['access_token'];
    //                     $tenant[$k]->refresh_token = $tnt['refresh_token'];
    //                     $tenant[$k]->email = $tnt['email'];
    //                     $tenant[$k]->password = $tnt['password'];
    //                     $tenant[$k]->integrar = $tnt['integrar'];
    //                     $tenant[$k]->sendExternalKeyIdErp = $tnt['sendExternalKeyIdErp'];
    //                     $contact->tenant = $tenant[$k]->tenant;
    //                     break;
    //                 default:
    //                     $tenant[$k]->tenant = $tnt['app_name'];
    //                     $tenant[$k]->appSecret = $tnt['app_secret'];
    //                     $tenant[$k]->appKey = $tnt['app_key'];
    //                     $tenant[$k]->ncc = $tnt['ncc'];
    //                     $tenant[$k]->integrar = $tnt['integrar'];
    //                     $tenant[$k]->sendExternalKeyIdErp = $tnt['sendExternalKeyIdErp'];
    //                     $contact->tenant = $tenant[$k];
    //                     break;
    //             }
                
    //             $contact->totalTenanties = ++$total;
                
    //             if($action['action'] === 'create' && $action['type'] === 'empresa'){
    //                 //aqui manda pro formatter para criar o cliente no ERP e Retorna mensagem de sucesso ou erro
    //                 $responseMessages = $formatter->createContactCRMToERP($contact, $ploomesServices, $tenant[$k]); 
    //             }
    //             else{
    //                 //aqui manda pro formatter que altera o cliente nbo ERP e retorna mensagem de erro ou sucesso   
    //                 $responseMessages = $formatter->updateContactCRMToERP($contact, $ploomesServices, $tenant[$k]);
    //             }  
    //             // Agrupa mensagens no array principal
    //             if (!empty($responseMessages['success'])) {
    //                 $messages['success'][] = $responseMessages['success'];
    //             }
    //             if (!empty($responseMessages['error'])) {
    //                 $messages['error'][] = $responseMessages['error'];
    //             }
    //         }
    //     } 

    //     return self::response($action, $contact, $messages); 
    // }

    //processa o contato do ERP para o CRM
    public static function processInvoiceErpToCrm($args, $ploomesServices, $formatter, $action):string
    {    
        $message = [];
        $current = date('d/m/Y H:i:s');
        $invoicing = $formatter->createInvoiceObject($args);
        $alter = false;
        $alterDocumentPloomes = [];
        
        //identificar se é OS ou Contrato
        if(!isset($invoicing->idPedidoInt) || empty($invoicing->idPedidoInt)){
            
            $html = '';
            $table = '<table border="1px" style= "width=100%; border-collapse: collapse;">';
            $table .= '<tr>';
            $table .= '<th style="width: 15%; border-collapse: collapse; padding:2px; text-align:center"> Data Emissão';
            $table .= '</th>';
            $table .= '<th style="width: 15%; border-collapse: collapse; padding:2px; text-align:center"> Núm. Contrato / Num. O.S.';
            $table .= '</th>';
            $table .= '<th style="width: 15%; border-collapse: collapse; padding:2px; text-align:center"> Num. NFSe / Valor';
            $table .= '</th>';
            $table .= '<th style="width: 55%; border-collapse: collapse; padding:2px; text-align:center"> Link XML';
            $table .= '</th>';
            $table .= '</tr>';
            foreach($invoicing->todas as $nfeContrato){
               
                $table .= '<tr>';
                if($nfeContrato['OrdemServico']['cNumeroContrato'] === $invoicing->nContrato){
                    
                    $table .= '<td style="width: 15%; border-collapse: collapse; padding:2px; text-align:center">'
                        .$nfeContrato['Emissao']['cDataEmissao'].' '.$nfeContrato['Emissao']['cHoraEmissao'];
                    $table .= '</td>';

                    $table .= '<td style="width: 15%; border-collapse: collapse; padding:2px; text-align:center">'
                        .$nfeContrato['OrdemServico']['cNumeroContrato'].' / '.$nfeContrato['OrdemServico']['nNumeroOS'];
                    $table .= '</td>';

                    $table .= '<td style="width: 15%; border-collapse: collapse; padding:2px; text-align:center">'
                        .$nfeContrato['Cabecalho']['nNumeroNFSe'].' / '.$nfeContrato['Cabecalho']['nValorNFSe'];
                    $table .= '</td>';

                    $table .= 
                        "<td style='width: 55%; border-collapse: collapse; padding:2px; text-align:center'> <a href='{$invoicing->nfseXml}'>{$invoicing->nfseXml} </a>";
                    $table .= '</td>';

                }
                $table .= '</tr>';
                
            }
            
            
            //se contrato montar tabela de notas emitidas durante a vigência do contrato
            $table .= '</table>';

            $html = $table;

            //identificar a referencia no Ploomes
            $idPloomes = $formatter->getIdPloomesBysContractNumber($invoicing->nContrato);

             //criar o objeto do ploomes com os dados do documento para atualizar no Ploomes
            $alterDocumentPloomes = [];
        
            $fields = [
                [
                    'SendExternalKey'=>'bicorp_api_tabela_faturamento_contrato_out',
                    'Value' => $html  ?? null,
                ],
                [
                    'SendExternalKey'=>'bicorp_api_num_os_externo_out',
                    'Value' => $nfeContrato['OrdemServico']['nNumeroOS'] ?? null,
                ],
                [
                    'SendExternalKey'=>'bicorp_api_num_nf_out',
                    'Value' => $nfeContrato['Cabecalho']['nNumeroNFSe'] ?? null,
                ],
                [
                    'SendExternalKey'=>'bicorp_api_data_nf_out',
                    'Value' => $nfeContrato['Emissao']['cDataEmissao'] ?? null,
                ],
                [
                    'SendExternalKey'=>'bicorp_api_valor_nf_out',
                    'Value' => $nfeContrato['Cabecalho']['nValorNFSe'] ?? null,
                ],
                
                
            ];

            $op = CustomFieldsFunction::createPloomesCustomFields($fields,$ploomesServices);
        
            if($op){
                $alterDocumentPloomes['OtherProperties'] = $op;
                $documentJson = json_encode($alterDocumentPloomes); 
                //atualizar o documento no Ploomes
                $alter = $ploomesServices->alterPloomesDocument($documentJson, $idPloomes);

                if($alter){
                    $number = intval($invoicing->numNfse);
                    //retorno sucesso
                    $message['success'] = "NFS-e ({$number}) emitida no ERP na base: {$invoicing->baseFaturamento} em: {$current}";
                }else{
                    throw new WebhookReadErrorException('Erro ao montar os dados da NF para retornar ao Ploomes', 500);
                }
            }
            
        }else{
       
            //identificar a referencia no Ploomes
            $partsIdPloomes = explode('/',$invoicing->idPedidoInt);
            $idPloomes = $partsIdPloomes[1];

            switch($action['action']){

                case 'nfeAutorizada':
                    return 'NF-e ('. intval($invoicing->numNfe) .') emitida no ERP na base: '.$invoicing->baseFaturamento; 
                     
                break;

                case 'nfseAutorizada':
                    
                    //criar o objeto com os dados do documento para atualizar no Ploomes
                    $fields = [
                        [
                            'SendExternalKey'=>'bicorp_api_num_os_externo_out',
                            'Value' => intval($invoicing->numOs) ?? null,
                        ],
                        [
                            'SendExternalKey'=>'bicorp_api_num_nf_out',
                            'Value' => intval($invoicing->numNfse) ?? null,
                        ],
                        [
                            'SendExternalKey'=>'bicorp_api_data_nf_out',
                            'Value' => $invoicing->dataEmissao ?? null,
                        ],
                        [
                            'SendExternalKey'=>'bicorp_api_valor_nf_out',
                            'Value' => $invoicing->valor ?? null,
                        ],
                        [
                            'SendExternalKey'=>'bicorp_api_status_nfse_out',
                            'Value' => $invoicing->status ?? null,
                        ],
                    ];
                    
                break;
                
                case 'nfseCancelada':
                    //criar o objeto com os dados do documento para atualizar no Ploomes
                    $fields = [
                        [
                            'SendExternalKey'=>'bicorp_api_status_nfse_out',
                            'Value' => $invoicing->status ?? null,
                        ],
                    ];
                break;


            
            }
            
            $op = CustomFieldsFunction::createPloomesCustomFields($fields,$ploomesServices);

            if($op){
                $alterDocumentPloomes['OtherProperties'] = $op;
                $documentJson = json_encode($alterDocumentPloomes);
                //atualizar o documento no Ploomes

                if($ploomesServices->alterPloomesDocument($documentJson, $idPloomes)){
                    $alter = true;
                }else{
                    $alter = $ploomesServices->alterPloomesOrder($documentJson, $idPloomes);
                }

                if($alter){
                    $number = intval($invoicing->numNfse);
                    //retorno sucesso
                    $content = "NFS-e ({$number}) {$invoicing->acao} no ERP na base: {$invoicing->baseFaturamento} em: {$current}";

                    $message = self::sendInteractionPloomes($invoicing, $ploomesServices, $content, $current);
                }else{
                    throw new WebhookReadErrorException('Erro ao montar os dados da NF para retornar ao Ploomes. Não foi encontrado o Documento/Venda referente.', 500);
                }
            }

        }
        //retorno sucesso
        return $message;
       
    }

    public static function processOrderInvoicedErpToCrm($args, $ploomesServices, $formatter, $action):string
    {    
        $message = [];
        $current = date('d/m/Y H:i:s');
        $OrderInvoiced = $formatter->createOrderInvoicedObject($args);
        $alter = false;
        $alterDocumentPloomes = [];
        
        //identificar se é OS ou Contrato
        if(!isset($OrderInvoiced->idPedidoInt) || empty($OrderInvoiced->idPedidoInt)){
            
            $html = '';
            $table = '<table border="1px" style= "width=100%; border-collapse: collapse;">';
            $table .= '<tr>';
            $table .= '<th style="width: 15%; border-collapse: collapse; padding:2px; text-align:center"> Data Emissão';
            $table .= '</th>';
            $table .= '<th style="width: 15%; border-collapse: collapse; padding:2px; text-align:center"> Núm. Contrato / Num. O.S.';
            $table .= '</th>';
            $table .= '<th style="width: 15%; border-collapse: collapse; padding:2px; text-align:center"> Num. NFSe / Valor';
            $table .= '</th>';
            $table .= '<th style="width: 55%; border-collapse: collapse; padding:2px; text-align:center"> Link XML';
            $table .= '</th>';
            $table .= '</tr>';
            foreach($OrderInvoiced->todas as $nfeContrato){
               
                $table .= '<tr>';
                if($nfeContrato['OrdemServico']['cNumeroContrato'] === $OrderInvoiced->nContrato){
                    
                    $table .= '<td style="width: 15%; border-collapse: collapse; padding:2px; text-align:center">'
                        .$nfeContrato['Emissao']['cDataEmissao'].' '.$nfeContrato['Emissao']['cHoraEmissao'];
                    $table .= '</td>';

                    $table .= '<td style="width: 15%; border-collapse: collapse; padding:2px; text-align:center">'
                        .$nfeContrato['OrdemServico']['cNumeroContrato'].' / '.$nfeContrato['OrdemServico']['nNumeroOS'];
                    $table .= '</td>';

                    $table .= '<td style="width: 15%; border-collapse: collapse; padding:2px; text-align:center">'
                        .$nfeContrato['Cabecalho']['nNumeroNFSe'].' / '.$nfeContrato['Cabecalho']['nValorNFSe'];
                    $table .= '</td>';

                    $table .= 
                        "<td style='width: 55%; border-collapse: collapse; padding:2px; text-align:center'> <a href='{$OrderInvoiced->nfseXml}'>{$OrderInvoiced->nfseXml} </a>";
                    $table .= '</td>';

                }
                $table .= '</tr>';
                
            }
            
            
            //se contrato montar tabela de notas emitidas durante a vigência do contrato
            $table .= '</table>';

            $html = $table;

            //identificar a referencia no Ploomes
            $idPloomes = $formatter->getIdPloomesBysContractNumber($OrderInvoiced->nContrato);

             //criar o objeto do ploomes com os dados do documento para atualizar no Ploomes
            $alterDocumentPloomes = [];
        
            $fields = [
                [
                    'SendExternalKey'=>'bicorp_api_tabela_faturamento_contrato_out',
                    'Value' => $html  ?? null,
                ],
                [
                    'SendExternalKey'=>'bicorp_api_num_os_externo_out',
                    'Value' => $nfeContrato['OrdemServico']['nNumeroOS'] ?? null,
                ],
                [
                    'SendExternalKey'=>'bicorp_api_num_nf_out',
                    'Value' => $nfeContrato['Cabecalho']['nNumeroNFSe'] ?? null,
                ],
                [
                    'SendExternalKey'=>'bicorp_api_data_nf_out',
                    'Value' => $nfeContrato['Emissao']['cDataEmissao'] ?? null,
                ],
                [
                    'SendExternalKey'=>'bicorp_api_valor_nf_out',
                    'Value' => $nfeContrato['Cabecalho']['nValorNFSe'] ?? null,
                ],
                
                
            ];

            $op = CustomFieldsFunction::createPloomesCustomFields($fields,$ploomesServices);
        
            if($op){
                $alterDocumentPloomes['OtherProperties'] = $op;
                $documentJson = json_encode($alterDocumentPloomes); 
                //atualizar o documento no Ploomes
                $alter = $ploomesServices->alterPloomesDocument($documentJson, $idPloomes);

                if($alter){
                    $number = intval($OrderInvoiced->numNfse);
                    //retorno sucesso
                    $message['success'] = "NFS-e ({$number}) emitida no ERP na base: {$OrderInvoiced->baseFaturamento} em: {$current}";
                }else{
                    throw new WebhookReadErrorException('Erro ao montar os dados da NF para retornar ao Ploomes', 500);
                }
            }
            
        }else{
       
            //identificar a referencia no Ploomes
            $partsIdPloomes = explode('/',$OrderInvoiced->idPedidoInt);
            $idPloomes = $partsIdPloomes[1];

            switch($action['action']){

                case 'venFaturada':
                    return 'NF-e ('. intval($OrderInvoiced->numNfe) .') emitida no ERP na base: '.$OrderInvoiced->baseFaturamento; 
                     
                break;

                case 'osFaturada':
                    
                    //criar o objeto com os dados do documento para atualizar no Ploomes
                    $fields = [
                        [
                            'SendExternalKey'=>'bicorp_api_data_faturamento_out',
                            'Value' => $OrderInvoiced->dataFaturamento ?? null,
                        ],
                        [
                            'SendExternalKey'=>'bicorp_api_num_recibo_out',
                            'Value' => intval($OrderInvoiced->numReceipt) ?? null,
                        ],
                        [
                            'SendExternalKey'=>'bicorp_api_num_os_externo_out',
                            'Value' => intval($OrderInvoiced->numOS) ?? null,
                        ],
                        [
                            'SendExternalKey'=>'bicorp_api_valor_faturamento_out',
                            'Value' => $OrderInvoiced->amountOS ?? null,
                        ],
                        [
                            'SendExternalKey'=>'bicorp_api_status_faturamento_out',
                            'Value' => $OrderInvoiced->status ?? null,
                        ],
                    ];
                    
                break;
                
                // case 'nfseCancelada':
                //     //criar o objeto com os dados do documento para atualizar no Ploomes
                //     $fields = [
                //         [
                //             'SendExternalKey'=>'bicorp_api_status_nfse_out',
                //             'Value' => $OrderInvoiced->status ?? null,
                //         ],
                //     ];
                // break;


            
            }
            
            $op = CustomFieldsFunction::createPloomesCustomFields($fields,$ploomesServices);
          
            if($op){
                $alterDocumentPloomes['OtherProperties'] = $op;
                $documentJson = json_encode($alterDocumentPloomes);
                //atualizar o documento no Ploomes
                
                if($ploomesServices->alterPloomesDocument($documentJson, $idPloomes)){
       
                    $alter = true;
                }elseif($ploomesServices->alterPloomesOrder($documentJson, $idPloomes)){

                    $alter = true;
                    self::alterStageInvoiceIssue($OrderInvoiced,$ploomesServices);
                }else{
  
                    $alter = false;
                }


                if($alter){
                    if(isset($OrderInvoiced->idPedido) && !empty($OrderInvoiced->idPedido)){
                        $number = intval($OrderInvoiced->idPedido);
                        $type ='Venda';
                    }else{

                        $number = intval($OrderInvoiced->numOS);
                        $type ='OS';
                    }
                    //retorno sucesso
                    $content = "{$type} ({$number}) faturado no ERP na base: {$OrderInvoiced->baseFaturamento} em: {$current}";

                    $message = self::sendInteractionPloomes($OrderInvoiced, $ploomesServices, $content, $current);
                }else{
                    throw new WebhookReadErrorException('Erro ao montar os dados da NF para retornar ao Ploomes. Não foi encontrado o Documento/Venda referente.', 500);
                }
            }

        }
        //retorno sucesso
        return $message;
       
    }

    public static function sendInteractionPloomes($invoicing, $ploomesServices, $content, $current=null)
    {
        $numCnpjCpf = $invoicing->cnpjDestinatario ?? $invoicing->cpfDestinatario ?? $invoicing->docDestinatario;
   
        if (!empty($numCnpjCpf))
        {
            //busca o contact_id artravés do cnpj do cliente do ERP mas antes precisa tirar pontos e barra 
            $cnpjCpf = DiverseFunctions::limpa_cpf_cnpj($numCnpjCpf);
            
            $contactId = $ploomesServices->consultaClientePloomesCnpj($cnpjCpf);

            if(!isset($contactId)){
                throw new WebhookReadErrorException('Não foi possível encontrar o cliente no Ploomes - '.$current,500);                
            }

            $frase = 'Interação de nota fiscal adicionada no cliente '. $contactId .' em: '.$current;
            //monta a mensagem para retornar ao ploomes
            $msg = [
                'ContactId'=>  $contactId,
                'TypeId'=> 1,
                'Title'=> 'Nota Fiscal emitida',
                'Content'=> $content,
            ];

            $interaction = $ploomesServices->createPloomesIteraction(json_encode($msg));

            if(!$interaction){

                throw new WebhookReadErrorException('Não foi possível adicionar a interação de nota fiscal emitida no card, possívelmente a venda foi criada direto no omie - '.$current,1025);

            }

            return $frase;
        }
        else{
            //RETORNA excessão caso não tenha o cliente
            throw new WebhookReadErrorException('CNPJ do cliente não encontrado.', 500);
        }

    }

    //muda etapa da venda após nota ser emitida
    public static function alterStageInvoiceIssue($invoicing, $ploomesServices)
    {

        //muda a etapa da venda específica para NF-Emitida stage Id 40042597
        $allStages = $ploomesServices->getOrderStages();

        foreach($allStages as $stage){
            if($stage['Name'] == 'NF Emitida'){
                $dataStage = [
                    'Id'=> $stage['Id'],
                    'Name' => $stage['Name']
                ];
            }else{
                return false;
            }
        }

        $array = [
            'StageId'=>$dataStage['Id']
        ];

        $json = json_encode($array);

        $id = explode('/',$invoicing->idPedidoInt);
        $idPedidoPloomes = $id[1];
        
        return $ploomesServices->alterStageOrder($json, $idPedidoPloomes);

    }

    



    // //cria obj cliente
    // //cria Old obj cliente
    // public static function createOldObj($decoded, $ploomesServices)
    // {

    //     // não dá pra buscar pela api ploomes pq o cliente foi deletado 
    //     $cliente = $decoded['Old'];
    //     $contact = new Contact();     

    //     /************************************************************
    //      *                   Other Properties                        *
    //      *                                                           *
    //      * No webhook do Contact pegamos os campos de Other Properies*
    //      * para encontrar a chave da base de faturamento do Omie     *
    //      *                                                           *
    //      *************************************************************/
    //     $prop = [];
    //     foreach ($decoded['Old']['OtherProperties'] as $key => $op) {
    //         $prop[$key] = $op;
    //         // print '['.$key.']=>['.$op.']';
    //     }
    //     //contact_879A3AA2-57B1-49DC-AEC2-21FE89617665 = tipo de cliente
    //     $contact->tipoCliente = $prop['contact_879A3AA2-57B1-49DC-AEC2-21FE89617665'] ?? null;
    //     //contact_FF485334-CE8C-4FB9-B3CA-4FF000E75227 = ramo de atividade
    //     $ramo= $prop['contact_FF485334-CE8C-4FB9-B3CA-4FF000E75227'] ?? null;
    //     if($ramo !== null){
    //         $id = match($ramo){
               
    //             420130572=>0,
    //             420130568=>1,
    //             420130574=>2,
    //             420130569=>3,
    //             420130565=>4,
    //             420130566=>5,           
    //             420130571=>6,
    //             420130570=>7,
    //             420130563=>8,
    //             420130573=>9,
    
    //         };
    //         $contact->ramoAtividade = $id;
    //     }else{
    //         $contact->ramoAtividade = null;
    //     }
    //     //contact_FA99392B-CED8-4668-B003-DFC1111DACB0 = Porte
    //     $contact->porte = $prop['contact_FA99392B-CED8-4668-B003-DFC1111DACB0'] ?? null;
    //     //contact_20B72360-82CF-4806-BB05-21E89D5C61FD = importância
    //     $contact->importancia = $prop['contact_20B72360-82CF-4806-BB05-21E89D5C61FD'] ?? null;
    //     //contact_5F52472B-E311-4574-96E2-3181EADFAFBE = situação
    //     $contact->situacao = $prop['contact_5F52472B-E311-4574-96E2-3181EADFAFBE'] ?? null;
    //     //contact_9E595E72-E50C-4E95-9A05-D3B024C177AD = ciclo de compra
    //     $contact->cicloCompra = $prop['contact_9E595E72-E50C-4E95-9A05-D3B024C177AD'] ?? null;
    //     //contact_5D5A8D57-A98F-4857-9D11-FCB7397E53CB = inscrição estadual
    //     $contact->inscricaoEstadual = $prop['contact_5D5A8D57-A98F-4857-9D11-FCB7397E53CB'] ?? null;
    //     //contact_D21FAEED-75B2-40E4-B169-503131EB3609 = inscrição municipal
    //     $contact->inscricaoMunicipal = $prop['contact_D21FAEED-75B2-40E4-B169-503131EB3609'] ?? null;
    //     //contact_3094AFFE-4263-43B6-A14B-8B0708CA1160 = inscrição suframa
    //     $contact->inscricaoSuframa = $prop['contact_3094AFFE-4263-43B6-A14B-8B0708CA1160'] ?? null;
    //     //contact_9BB527FD-8277-4D1F-AF99-DD88D5064719 = Simples nacional?(s/n)  
    //     $contact->simplesNacional = $prop['contact_9BB527FD-8277-4D1F-AF99-DD88D5064719'] ?? null;//é obrigatório para emissão de nf
    //     (isset($contact->simplesNacional) && $contact->simplesNacional !== false) ? $contact->simplesNacional = 'S' : $contact->simplesNacional = 'N';
    //     //contact_3C521209-46BD-4EA5-9F41-34756621CCB4 = contato1
    //     $contact->contato1 = $prop['contact_3C521209-46BD-4EA5-9F41-34756621CCB4'] ?? null;
    //     //contact_F9B60153-6BDF-4040-9C3A-E23B1469894A = Produtor Rural
    //     $contact->produtorRural = $prop['contact_F9B60153-6BDF-4040-9C3A-E23B1469894A'] ?? null;
    //     (isset($contact->produtorRural) && $contact->produtorRural !== false) ? $contact->produtorRural = 'S' : $contact->produtorRural = 'N';
    //     //contact_FC16AEA5-E4BF-44CE-83DA-7F33B7D56453 = Contribuinte(s/n)
    //     $contact->contribuinte = $prop['contact_FC16AEA5-E4BF-44CE-83DA-7F33B7D56453'] ?? null;
    //     (isset($contact->contribuinte) && $contact->contribuinte !== false) ? $contact->contribuinte = 'S' : $contact->contribuinte = 'N';
    //     //contact_10D27B0F-F9EF-4378-B1A8-099319BAC0AD = limite de crédito
    //     $contact->limiteCredito = $prop['contact_10D27B0F-F9EF-4378-B1A8-099319BAC0AD'] ?? null;
    //     //contact_CED4CBAD-92C7-4A51-9985-B9010D27E1A4 = inativo (s/n)
    //     $contact->inativo = $prop['contact_CED4CBAD-92C7-4A51-9985-B9010D27E1A4'] ?? null;
    //     (isset($contact->inativo) && $contact->inativo !== false) ? $contact->inativo = 'S' : $contact->inativo = 'N';
    //     //contact_C613A391-155B-42F5-9C92-20C3371CC3DE = bloqueia excusão (s/n)
    //     $contact->bloquearExclusao = $prop['contact_C613A391-155B-42F5-9C92-20C3371CC3DE'] ?? null;
    //     (isset($contact->bloquearExclusao) && $contact->bloquearExclusao !== false) ? $contact->bloquearExclusao = 'S' : $contact->bloquearExclusao = 'N';
    //     //contact_77CCD2FB-53D7-4203-BE6B-14B671A06F33 = transportadora padrão
    //     $contact->cTransportadoraPadrao = $prop['contact_77CCD2FB-53D7-4203-BE6B-14B671A06F33'] ?? null;
    //     //contact_6BB80AEA-43D0-45E8-B9E4-28D89D9773B9 = codigo do banco
    //     $contact->cBanco = $prop['contact_6BB80AEA-43D0-45E8-B9E4-28D89D9773B9'] ?? null;
    //     //contact_1F1E1F00-34CB-4356-B852-496D62A90E10 = Agência
    //     $contact->agencia = $prop['contact_1F1E1F00-34CB-4356-B852-496D62A90E10'] ?? null;
    //     //contact_38E58F93-1A6C-4E40-9F5B-45B5692D7C80 = Num conta corrente
    //     $contact->nContaCorrente = $prop['contact_38E58F93-1A6C-4E40-9F5B-45B5692D7C80'] ?? null;
    //     //contact_FDFB1BE8-ECC8-4CFF-8A37-58DCF24CDB50 = CNPJ/CPF titular
    //     $contact->docTitular = $prop['contact_FDFB1BE8-ECC8-4CFF-8A37-58DCF24CDB50'] ?? null;
    //     //contact_DDD76E27-8EFA-416B-B7DF-321C1FB31066 = Nome di titular
    //     $contact->nomeTitular = $prop['contact_DDD76E27-8EFA-416B-B7DF-321C1FB31066'] ?? null;
    //     //contact_847FE760-74D0-462D-B464-9E89C7E1C28E = chave pix
    //     $contact->chavePix = $prop['contact_847FE760-74D0-462D-B464-9E89C7E1C28E'] ?? null;
    //     //contact_33015EDD-B3A7-464E-81D0-5F38D31F604A = Transferência Padrão
    //     $contact->transferenciaPadrao = $prop['contact_33015EDD-B3A7-464E-81D0-5F38D31F604A'] ?? null;
    //     (isset($contact->transferenciaPadrao) && $contact->transferenciaPadrao !== false) ? $contact->transferenciaPadrao = 'S' : $contact->transferenciaPadrao = 'N';
    //     //contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C = Integrar com base omie 1? (s/n)
    //     (isset($prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C']) && $prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C'] !== false) ? $prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C'] = 1 : $prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C'] = 0;
    //     //contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C = Integrar com base omie 2? (s/n)
    //     (isset($prop['contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C']) && $prop['contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C'] !== false) ? $prop['contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C'] = 1 : $prop['contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C'] = 0;
    //     //contact_02AA406F-F955-4AE0-B380-B14301D1188B = Integrar com base omie 3? (s/n)
    //     (isset($prop['contact_02AA406F-F955-4AE0-B380-B14301D1188B']) && $prop['contact_02AA406F-F955-4AE0-B380-B14301D1188B'] !== false) ? $prop['contact_02AA406F-F955-4AE0-B380-B14301D1188B'] = 1 : $prop['contact_02AA406F-F955-4AE0-B380-B14301D1188B'] = 0;
    //     //contact_E497C521-4275-48E7-B44E-7A057844B045 = Integrar com base omie 4? (s/n)
    //     (isset($prop['contact_E497C521-4275-48E7-B44E-7A057844B045']) && $prop['contact_E497C521-4275-48E7-B44E-7A057844B045'] !== false) ? $prop['contact_E497C521-4275-48E7-B44E-7A057844B045'] = 1 : $prop['contact_E497C521-4275-48E7-B44E-7A057844B045'] = 0;
    
    //     $contact->codOmie = [];
    //     $contact->codOmie[0] = $prop['contact_4F0C36B9-5990-42FB-AEBC-5DCFD7A837C3'] ?? null;
    //     $contact->codOmie[1] = $prop['contact_6DB7009F-1E58-4871-B1E6-65534737C1D0'] ?? null;
    //     $contact->codOmie[2] = $prop['contact_AE3D1F66-44A8-4F88-AAA5-F10F05E662C2'] ?? null;
    //     $contact->codOmie[3] = $prop['contact_07784D81-18E1-42DC-9937-AB37434176FB'] ?? null;
    
    //     $phones = [];
    //     foreach($cliente['Phones'] as $phone){
            
    //         $partes = explode(' ',$phone['PhoneNumber']);
    //         $ddd = $partes[0];
    //         $nPhone = $partes[1];
    //         $phones[] = [
    //             'ddd'=>$ddd,
    //             'nPhone' => $nPhone
    //         ];        
    //     }

    //     $contact->id = $cliente['Id']; //Id do Contact
    //     $contact->name = $cliente['Name']; // Nome ou nome fantasia do contact
    //     $contact->legalName = $cliente['LegalName']; // Razão social do contact
    //     $contact->cnpj = $cliente['CNPJ'] ?? null; // Contatos CNPJ
    //     $contact->cpf = $cliente['CPF'] ?? null; // Contatos CPF
    //     $contact->documentoExterior = $cliente['IdentityDocument'] ?? null; // Contatos CPF
    //     $contact->segmento = $cliente['LineOfBusinessId'] ?? null; // Contatos CPF
    //     $contact->email = $cliente['Email']; // Contatos Email obrigatório
    //     $contact->website = $cliente['Website'] ?? null; // Contatos Email obrigatório
    //     $contact->ddd1 = $phones[0]['ddd'] ?? null; //"telefone1_ddd": "011",
    //     $contact->phone1 = $phones[0]['nPhone'] ?? null; //"telefone1_numero": "2737-2737",
    //     $contact->ddd2 = $phones[1]['ddd'] ?? null; //"telefone1_ddd": "011",
    //     $contact->phone2 = $phones[1]['nPhone'] ?? null; //"telefone1_numero": "2737-2737",
    //     //$contact->contato1 = $prop['contact_E6008BF6-A43D-4D1C-813E-C6BD8C077F77'] ?? null;
    //     $contact->streetAddress = $cliente['StreetAddress']; // Endereço Obrigatório
    //     $contact->streetAddressNumber = $cliente['StreetAddressNumber']; // Número Endereço Obrigatório
    //     $contact->streetAddressLine2 = $cliente['StreetAddressLine2'] ?? null; // complemento do Endereço 
    //     $contact->neighborhood = $cliente['Neighborhood']; // bairro do Endereço é obrigatório
    //     $contact->zipCode = $cliente['ZipCode']; // CEP do Endereço é obrigatório
    //     //$contact->cityId = $cliente['City']['IBGECode']; // Id da cidade é obrigatório
    //     $cities = $ploomesServices->getCitiesById($cliente['CityId']);
    //     $contact->cityId = $cities['IBGECode'];
    //     $contact->cityName = $cities['Name']; // estamos pegando o IBGE code
    //     $contact->cityLagitude = $cities['Latitude'] ?? null; // Latitude da cidade é obrigatório
    //     $contact->cityLongitude = $cities['Longitude'] ?? null; // Longitude da cidade é obrigatório
    //     $state = $ploomesServices->getStateById($cities['StateId']);
    //     $contact->stateShort = $state['Short']; // Sigla do estado é obrigatório
    //     $contact->stateName = $state['Name'] ?? null; //estamos pegando a sigla do estado
    //     //$contact->countryId = $cliente['CountryId'] ?? null; // Omie preenche o país automaticamente
    //     $contact->cnaeCode = $cliente['CnaeCode'] ?? null; // Id do cnae 
    //     $contact->cnaeName = $cliente['CnaeName'] ?? null; // Name do cnae 
    //     $contact->ownerId = $cliente['OwnerId']; // Responsável (Vendedor)

    //     $contact->ownerEmail = $ploomesServices->ownerMail($contact);// Responsável (Vendedor) 
    //     //$contact->ownerEmail = $cliente['Owner']['Email']; // Responsável (Vendedor) 
    //     $contact->observacao = $cliente['Note'] ?? null; // Observação 
        
    //     // Base de Faturamento para fiel não precisa pois integra e depois a automação distribui em todas as bases, em gamathermic precisa
    //     $bases = [];
        
    //     $bases[0]['fieldKey'] = 'contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C';
    //     $bases[0]['title'] = 'ENGEPARTS';
    //     $bases[0]['sigla'] = 'EPT';
    //     $bases[0]['integrar'] = $prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C'];
    //     // $bases[0]['appKey'] = $_ENV['APPK_DEMO']??null;
    //     // $bases[0]['appSecret'] = $_ENV['SECRETS_DEMO']??null;
    //     $bases[0]['appKey'] = $_ENV['APPK_EPT']??null;
    //     $bases[0]['appSecret'] = $_ENV['SECRETS_EPT']??null;

    //     $bases[1]['fieldKey'] = 'contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C';
    //     $bases[1]['title'] = 'GAMATERMIC';
    //     $bases[1]['sigla'] = 'GTC';
    //     $bases[1]['integrar'] = $prop['contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C'];
    //     $bases[1]['appKey'] = $_ENV['APPK_GTC']??null;
    //     $bases[1]['appSecret'] = $_ENV['SECRETS_GTC']??null;
    //     // $bases[1]['appKey'] = $_ENV['APPK_DEMO2']??null;
    //     // $bases[1]['appSecret'] = $_ENV['SECRETS_DEMO2']??null;

    //     $bases[2]['fieldKey'] = 'contact_02AA406F-F955-4AE0-B380-B14301D1188B';
    //     $bases[2]['title'] = 'SEMIN';
    //     $bases[2]['sigla'] = 'SMN';
    //     $bases[2]['integrar'] = $prop['contact_02AA406F-F955-4AE0-B380-B14301D1188B'];
    //     $bases[2]['appKey'] = $_ENV['APPK_SMN']??null;
    //     $bases[2]['appSecret'] = $_ENV['SECRETS_SMN']??null;
    //     // $bases[2]['appKey'] = $_ENV['APPK_MHL']??null;
    //     // $bases[2]['appSecret'] = $_ENV['SECRETS_MHL']??null;
        
    //     $bases[3]['fieldKey'] = 'contact_E497C521-4275-48E7-B44E-7A057844B045';
    //     $bases[3]['title'] = 'GSU';
    //     $bases[3]['sigla'] = 'GSU';
    //     $bases[3]['integrar'] = $prop['contact_E497C521-4275-48E7-B44E-7A057844B045'] ?? null;
    //     $bases[3]['appKey'] = $_ENV['APPK_GSU']??null;
    //     $bases[3]['appSecret'] = $_ENV['SECRETS_GSU']??null;
    //     // $bases[3]['appKey'] = $_ENV['APPK_MSC']??null;
    //     // $bases[3]['appSecret'] = $_ENV['SECRETS_MSC']??null;
    
    //     $contact->basesFaturamento = $bases;        

    //     $tags= [];
    //     $tag=[];
    //     if($cliente['Tags']){
            
    //         foreach($cliente['Tags'] as $iTag){

    //             $tagName = match($iTag['TagId']){
    //                 40203491=>'Fornecedor',
    //                 40203492=>'Transportadora',
    //                 40203493=>'Funcionário',
    //                 40203494=>'Min. da Fazenda',
    //                 40203495=>'Banco e Inst. Financeiras',
    //                 40203497=>'Diretor',
    //                 40203778=>'Cliente',
    //             };
                
    //             $tag['tag']=$tagName;
                
    //             $tags[]=$tag;
    //         }
    //     }
    //     $contact->tags = $tags;
    //     // print_r($contact);
    //     // exit;
    //     return $contact;
    // }

    // //cria New obj cliente
    // public static function createNewObj($webhook, $ploomesServices)
    // {
    //     //decodifica o json de clientes vindos do webhook
    //     $json = $webhook['json'];
    //     $decoded = json_decode($json,true);
    //     $cliente = $decoded['New'];
    //     $contact = new Contact();        
    
    //     /************************************************************
    //      *                   Other Properties                        *
    //      *                                                           *
    //      * No webhook do Contact pegamos os campos de Other Properies*
    //      * para encontrar a chave da base de faturamento do Omie     *
    //      *                                                           *
    //      *************************************************************/
    //     $prop = [];
    //     foreach ($decoded['New']['OtherProperties'] as $key => $op) {
    //         $prop[$key] = $op;
    //         // print '['.$key.']=>['.$op.']';
    //     }
    //     //contact_879A3AA2-57B1-49DC-AEC2-21FE89617665 = tipo de cliente
    //     $contact->tipoCliente = $prop['contact_879A3AA2-57B1-49DC-AEC2-21FE89617665'] ?? null;
    //     //contact_FF485334-CE8C-4FB9-B3CA-4FF000E75227 = ramo de atividade
    //     $ramo= $prop['contact_FF485334-CE8C-4FB9-B3CA-4FF000E75227'] ?? null;
    //     if($ramo !== null){
    //         $id = match($ramo){
               
    //             420130572=>0,
    //             420130568=>1,
    //             420130574=>2,
    //             420130569=>3,
    //             420130565=>4,
    //             420130566=>5,           
    //             420130571=>6,
    //             420130570=>7,
    //             420130563=>8,
    //             420130573=>9,
    
    //         };
    //         $contact->ramoAtividade = $id;
    //     }else{
    //         $contact->ramoAtividade = null;
    //     }
    //     //contact_FA99392B-CED8-4668-B003-DFC1111DACB0 = Porte
    //     $contact->porte = $prop['contact_FA99392B-CED8-4668-B003-DFC1111DACB0'] ?? null;
    //     //contact_20B72360-82CF-4806-BB05-21E89D5C61FD = importância
    //     $contact->importancia = $prop['contact_20B72360-82CF-4806-BB05-21E89D5C61FD'] ?? null;
    //     //contact_5F52472B-E311-4574-96E2-3181EADFAFBE = situação
    //     $contact->situacao = $prop['contact_5F52472B-E311-4574-96E2-3181EADFAFBE'] ?? null;
    //     //contact_9E595E72-E50C-4E95-9A05-D3B024C177AD = ciclo de compra
    //     $contact->cicloCompra = $prop['contact_9E595E72-E50C-4E95-9A05-D3B024C177AD'] ?? null;
    //     //contact_5D5A8D57-A98F-4857-9D11-FCB7397E53CB = inscrição estadual
    //     $contact->inscricaoEstadual = $prop['contact_5D5A8D57-A98F-4857-9D11-FCB7397E53CB'] ?? null;
    //     //contact_D21FAEED-75B2-40E4-B169-503131EB3609 = inscrição municipal
    //     $contact->inscricaoMunicipal = $prop['contact_D21FAEED-75B2-40E4-B169-503131EB3609'] ?? null;
    //     //contact_3094AFFE-4263-43B6-A14B-8B0708CA1160 = inscrição suframa
    //     $contact->inscricaoSuframa = $prop['contact_3094AFFE-4263-43B6-A14B-8B0708CA1160'] ?? null;
    //     //contact_9BB527FD-8277-4D1F-AF99-DD88D5064719 = Simples nacional?(s/n)  
    //     // $contact->simplesNacional = $prop['contact_9BB527FD-8277-4D1F-AF99-DD88D5064719'] ?? null;
    //     $contact->simplesNacional = $prop['contact_9BB527FD-8277-4D1F-AF99-DD88D5064719'] ?? null;//é obrigatório para emissão de nf
    //     (isset($contact->simplesNacional) && $contact->simplesNacional !== false) ? $contact->simplesNacional = 'S' : $contact->simplesNacional = 'N';
    //     //contact_3C521209-46BD-4EA5-9F41-34756621CCB4 = contato1
    //     $contact->contato1 = $prop['contact_3C521209-46BD-4EA5-9F41-34756621CCB4'] ?? null;
    //     //contact_F9B60153-6BDF-4040-9C3A-E23B1469894A = Produtor Rural
    //     $contact->produtorRural = $prop['contact_F9B60153-6BDF-4040-9C3A-E23B1469894A'] ?? null;
    //     (isset($contact->produtorRural) && $contact->produtorRural !== false) ? $contact->produtorRural = 'S' : $contact->produtorRural = 'N';
    //     //contact_FC16AEA5-E4BF-44CE-83DA-7F33B7D56453 = Contribuinte(s/n)
    //     $contact->contribuinte = $prop['contact_FC16AEA5-E4BF-44CE-83DA-7F33B7D56453'] ?? null;
    //     (isset($contact->contribuinte) && $contact->contribuinte !== false) ? $contact->contribuinte = 'S' : $contact->contribuinte = 'N';
    //     //contact_10D27B0F-F9EF-4378-B1A8-099319BAC0AD = limite de crédito
    //     $contact->limiteCredito = $prop['contact_10D27B0F-F9EF-4378-B1A8-099319BAC0AD'] ?? null;
    //     //contact_CED4CBAD-92C7-4A51-9985-B9010D27E1A4 = inativo (s/n)
    //     $contact->inativo = $prop['contact_CED4CBAD-92C7-4A51-9985-B9010D27E1A4'] ?? null;
    //     (isset($contact->inativo) && $contact->inativo !== false) ? $contact->inativo = 'S' : $contact->inativo = 'N';
    //     //contact_C613A391-155B-42F5-9C92-20C3371CC3DE = bloqueia excusão (s/n)
    //     $contact->bloquearExclusao = $prop['contact_C613A391-155B-42F5-9C92-20C3371CC3DE'] ?? null;
    //     (isset($contact->bloquearExclusao) && $contact->bloquearExclusao !== false) ? $contact->bloquearExclusao = 'S' : $contact->bloquearExclusao = 'N';
    //     //contact_77CCD2FB-53D7-4203-BE6B-14B671A06F33 = transportadora padrão
    //     $contact->cTransportadoraPadrao = $prop['contact_77CCD2FB-53D7-4203-BE6B-14B671A06F33'] ?? null;
    //     //contact_6BB80AEA-43D0-45E8-B9E4-28D89D9773B9 = codigo do banco
    //     $contact->cBanco = $prop['contact_6BB80AEA-43D0-45E8-B9E4-28D89D9773B9'] ?? null;
    //     //contact_1F1E1F00-34CB-4356-B852-496D62A90E10 = Agência
    //     $contact->agencia = $prop['contact_1F1E1F00-34CB-4356-B852-496D62A90E10'] ?? null;
    //     //contact_38E58F93-1A6C-4E40-9F5B-45B5692D7C80 = Num conta corrente
    //     $contact->nContaCorrente = $prop['contact_38E58F93-1A6C-4E40-9F5B-45B5692D7C80'] ?? null;
    //     //contact_FDFB1BE8-ECC8-4CFF-8A37-58DCF24CDB50 = CNPJ/CPF titular
    //     $contact->docTitular = $prop['contact_FDFB1BE8-ECC8-4CFF-8A37-58DCF24CDB50'] ?? null;
    //     //contact_DDD76E27-8EFA-416B-B7DF-321C1FB31066 = Nome di titular
    //     $contact->nomeTitular = $prop['contact_DDD76E27-8EFA-416B-B7DF-321C1FB31066'] ?? null;
    //     //contact_847FE760-74D0-462D-B464-9E89C7E1C28E = chave pix
    //     $contact->chavePix = $prop['contact_847FE760-74D0-462D-B464-9E89C7E1C28E'] ?? null;
    //     //contact_33015EDD-B3A7-464E-81D0-5F38D31F604A = Transferência Padrão
    //     $contact->transferenciaPadrao = $prop['contact_33015EDD-B3A7-464E-81D0-5F38D31F604A'] ?? null;
    //     ($contact->transferenciaPadrao) ? $contact->transferenciaPadrao = 'S' : $contact->transferenciaPadrao = 'N';
    //     //contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C = Integrar com base omie 1? (s/n)
    //     (isset($prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C']) && $prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C'] !== false) ? $prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C'] = 1 : $prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C'] = 0;
    //     //contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C = Integrar com base omie 2? (s/n)
    //     (isset($prop['contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C']) && $prop['contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C'] !== false) ? $prop['contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C'] = 1 : $prop['contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C'] = 0;
    //     //contact_02AA406F-F955-4AE0-B380-B14301D1188B = Integrar com base omie 3? (s/n)
    //     (isset($prop['contact_02AA406F-F955-4AE0-B380-B14301D1188B']) && $prop['contact_02AA406F-F955-4AE0-B380-B14301D1188B'] !== false) ? $prop['contact_02AA406F-F955-4AE0-B380-B14301D1188B'] = 1 : $prop['contact_02AA406F-F955-4AE0-B380-B14301D1188B'] = 0;
    //     //contact_E497C521-4275-48E7-B44E-7A057844B045 = Integrar com base omie 4? (s/n)
    //     (isset($prop['contact_E497C521-4275-48E7-B44E-7A057844B045']) && $prop['contact_E497C521-4275-48E7-B44E-7A057844B045'] !== false) ? $prop['contact_E497C521-4275-48E7-B44E-7A057844B045'] = 1 : $prop['contact_E497C521-4275-48E7-B44E-7A057844B045'] = 0;
    //     $contact->codOmie = [];
    //     $contact->codOmie[0] = $prop['contact_4F0C36B9-5990-42FB-AEBC-5DCFD7A837C3'] ?? null;
    //     $contact->codOmie[1] = $prop['contact_6DB7009F-1E58-4871-B1E6-65534737C1D0'] ?? null;
    //     $contact->codOmie[2] = $prop['contact_AE3D1F66-44A8-4F88-AAA5-F10F05E662C2'] ?? null;
    //     $contact->codOmie[3] = $prop['contact_07784D81-18E1-42DC-9937-AB37434176FB'] ?? null;
        
    //     $phones = [];
    //     foreach($cliente['Phones'] as $phone){
            
    //         $partes = explode(' ',$phone['PhoneNumber']);
    //         $ddd = $partes[0];
    //         $nPhone = $partes[1];
    //         $phones[] = [
    //             'ddd'=>$ddd,
    //             'nPhone' => $nPhone
    //         ];        
    //     }

    //     $contact->id = $cliente['Id']; //Id do Contact
    //     $contact->name = $cliente['Name'] ?? null; // Nome ou nome fantasia do contact
    //     $contact->legalName = $cliente['LegalName'] ?? null; // Razão social do contact
    //     $contact->cnpj = $cliente['CNPJ'] ?? null; // Contatos CNPJ
    //     $contact->cpf = $cliente['CPF'] ?? null; // Contatos CPF
    //     $contact->documentoExterior = $cliente['IdentityDocument']; // Contatos CPF
    //     $contact->segmento = $cliente['LineOfBusinessId'] ?? null; // Contatos CPF
    //     $contact->email = $cliente['Email']; // Contatos Email obrigatório
    //     $contact->website = $cliente['Website'] ?? null; // Contatos Email obrigatório
    //     $contact->ddd1 = $phones[0]['ddd']; //"telefone1_ddd": "011",
    //     $contact->phone1 = $phones[0]['nPhone']; //"telefone1_numero": "2737-2737",
    //     $contact->ddd2 = $phones[1]['ddd'] ?? null; //"telefone1_ddd": "011",
    //     $contact->phone2 = $phones[1]['nPhone'] ?? null; //"telefone1_numero": "2737-2737",
    //     //$contact->contato1 = $prop['contact_E6008BF6-A43D-4D1C-813E-C6BD8C077F77'] ?? null;
    //     $contact->streetAddress = $cliente['StreetAddress']; // Endereço Obrigatório
    //     $contact->streetAddressNumber = $cliente['StreetAddressNumber']; // Número Endereço Obrigatório
    //     $contact->streetAddressLine2 = $cliente['StreetAddressLine2'] ?? null; // complemento do Endereço 
    //     $contact->neighborhood = $cliente['Neighborhood']; // bairro do Endereço é obrigatório
    //     $contact->zipCode = $cliente['ZipCode']; // CEP do Endereço é obrigatório
    //     //$contact->cityId = $cliente['City']['IBGECode']; // Id da cidade é obrigatório
    //     $cities = $ploomesServices->getCitiesById($cliente['CityId']);
    //     $contact->cityId = $cities['IBGECode'];
    //     $contact->cityName = $cities['Name']; // estamos pegando o IBGE code
    //     $contact->cityLagitude = $cities['Latitude']; // Latitude da cidade é obrigatório
    //     $contact->cityLongitude = $cities['Longitude']; // Longitude da cidade é obrigatório
    //     $state = $ploomesServices->getStateById($cities['StateId']);
    //     $contact->stateShort = $state['Short']; // Sigla do estado é obrigatório
    //     $contact->stateName = $state['Name']; //estamos pegando a sigla do estado
    //     //$contact->countryId = $cliente['CountryId'] ?? null; // Omie preenche o país automaticamente
    //     $contact->cnaeCode = $cliente['CnaeCode'] ?? null; // Id do cnae 
    //     $contact->cnaeName = $cliente['CnaeName'] ?? null; // Name do cnae 
    //     $contact->ownerId = $cliente['OwnerId']; // Responsável (Vendedor)

    //     $contact->ownerEmail = $ploomesServices->ownerMail($contact);// Responsável (Vendedor) 
    //     //$contact->ownerEmail = $cliente['Owner']['Email']; // Responsável (Vendedor) 
    //     $contact->observacao = $cliente['Note']; // Observação 
        
    //     // Base de Faturamento para fiel não precisa pois integra e depois a automação distribui em todas as bases, em gamathermic precisa
    //     $bases = [];
        
    //     $bases[0]['fieldKey'] = 'contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C';
    //     $bases[0]['title'] = 'ENGEPARTS';
    //     $bases[0]['sigla'] = 'EPT';
    //     $bases[0]['integrar'] = $prop['contact_55D34FF5-2389-4FEE-947C-ACCC576DB85C'];
    //     // $bases[0]['appKey'] = $_ENV['APPK_DEMO']??null;
    //     // $bases[0]['appSecret'] = $_ENV['SECRETS_DEMO']??null;
    //     $bases[0]['appKey'] = $_ENV['APPK_EPT']??null;
    //     $bases[0]['appSecret'] = $_ENV['SECRETS_EPT']??null;

    //     $bases[1]['fieldKey'] = 'contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C';
    //     $bases[1]['title'] = 'GAMATERMIC';
    //     $bases[1]['sigla'] = 'GTC';
    //     $bases[1]['integrar'] = $prop['contact_32A7FEE7-C46A-40BE-BABD-2973A63C092C'];
    //     $bases[1]['appKey'] = $_ENV['APPK_GTC']??null;
    //     $bases[1]['appSecret'] = $_ENV['SECRETS_GTC']??null;
    //     // $bases[1]['appKey'] = $_ENV['APPK_DEMO2']??null;
    //     // $bases[1]['appSecret'] = $_ENV['SECRETS_DEMO2']??null;

    //     $bases[2]['fieldKey'] = 'contact_02AA406F-F955-4AE0-B380-B14301D1188B';
    //     $bases[2]['title'] = 'SEMIN';
    //     $bases[2]['sigla'] = 'SMN';
    //     $bases[2]['integrar'] = $prop['contact_02AA406F-F955-4AE0-B380-B14301D1188B'];
    //     $bases[2]['appKey'] = $_ENV['APPK_SMN']??null;
    //     $bases[2]['appSecret'] = $_ENV['SECRETS_SMN']??null;
    //     // $bases[2]['appKey'] = $_ENV['APPK_MHL']??null;
    //     // $bases[2]['appSecret'] = $_ENV['SECRETS_MHL']??null;
        
    //     $bases[3]['fieldKey'] = 'contact_E497C521-4275-48E7-B44E-7A057844B045';
    //     $bases[3]['title'] = 'GSU';
    //     $bases[3]['sigla'] = 'GSU';
    //     $bases[3]['integrar'] = $prop['contact_E497C521-4275-48E7-B44E-7A057844B045'] ?? null;
    //     $bases[3]['appKey'] = $_ENV['APPK_GSU']??null;
    //     $bases[3]['appSecret'] = $_ENV['SECRETS_GSU']??null;
    //     // $bases[3]['appKey'] = $_ENV['APPK_MSC']??null;
    //     // $bases[3]['appSecret'] = $_ENV['SECRETS_MSC']??null;
    //     //switch para uma base serve mas para as 4 base não pois ele vai verificar se existe base de faturamento em apenas uma das opções
    //     // switch($prop){
            
    //     //     case $base1:
    //         //         $contact->baseFaturamentoTitle = 'ENGEPARTS';
    //         //         $contact->baseFaturamentoSigla = 'EPT';
    //         //         break;
    //         //     case $base2:
    //             //         $contact->baseFaturamentoTitle = 'GAMATERMIC';
    //             //         $contact->baseFaturamentoSigla = 'GTC';
    //             //         break;
    //     //     case $base3:
    //     //         $contact->baseFaturamentoTitle = 'SEMIN';
    //     //         $contact->baseFaturamentoSigla = 'SMN';
    //     //         break;
    //     //     case $base4:
    //     //         $contact->baseFaturamentoTitle = 'GSU';
    //     //         $contact->baseFaturamentoSigla = 'GSU';
    //     //         break;
        
    //     // }
        
    //     // (!empty($contact->baseFaturamento))? $contact->baseFaturamento : $m[] = 'Base de faturamento inexistente';
    //     $contact->basesFaturamento = $bases;        

    //     $tags= [];
    //     $tag=[];
    //     if($cliente['Tags']){
    
    //         foreach($cliente['Tags'] as $iTag){

    //             $tagName = match($iTag['TagId']){
    //                 40203491=>'Fornecedor',
    //                 40203492=>'Transportadora',
    //                 40203493=>'Funcionário',
    //                 40203494=>'Min. da Fazenda',
    //                 40203495=>'Banco e Inst. Financeiras',
    //                 40203497=>'Diretor',
    //                 40203778=>'Cliente',
    //             };
                
    //             $tag['tag']=$tagName;
                
    //             $tags[]=$tag;
    //         }
    //     }
    //     $contact->tags = $tags;

    //     return $contact;
    // }

    // public static function createOmieObj($decoded, $omieServices)
    // {
        
    //     $cliente = new stdClass();
    //     $cliente->codigoClienteOmie = $decoded['event']['codigo_cliente_omie'];
        
    //     $c =  $omieServices->getClientById( $cliente);
  
    //     $array = DiverseFunctions::achatarArray($c);

    //     //$cliente->messageId = $array['messageId'];
    //     // $cliente->topic = $array['topic'];
    //     $cliente->bairro = $array['bairro'] ?? null;
    //     $cliente->bloqueado = $array['bloqueado']  ?? null;
    //     $cliente->bloquearFaturamento = $array['bloquear_faturamento']  ?? null;
    //     $cep = (int)str_replace('-','',$array['cep'])  ?? null;
    //     $cliente->cep = $cep  ?? null;
    //     $cliente->cidade = $array['cidade']  ?? null;
    //     $cliente->cidadeIbge = $array['cidade_ibge'] ?? null  ?? null;
    //     $cliente->cnae = $array['cnae']  ?? null;
    //     $cliente->cnpjCpf = $array['cnpj_cpf']  ?? null;
    //     $cliente->codigoClienteIntegracao = $array['codigo_cliente_integracao']  ?? null;
    //     $cliente->codigoClienteOmie = $array['codigo_cliente_omie']  ?? null;
    //     $cliente->codigoPais = $array['codigo_pais']  ?? null;
    //     $cliente->complemento = $array['complemento']  ?? null;
    //     $cliente->contato = $array['contato']  ?? null;
    //     $cliente->contribuinte = $array['contribuinte']  ?? null;
    //     $cliente->agencia = $array['dadosBancarios_agencia']  ?? null;
    //     $cliente->cBanco = $array['dadosBancarios_codigo_banco']  ?? null;
    //     $cliente->nContaCorrente = $array['dadosBancarios_conta_corrente']  ?? null;
    //     $cliente->docTitular = $array['dadosBancarios_doc_titular']  ?? null;
    //     $cliente->nomeTitular = $array['dadosBancarios_nome_titular']  ?? null;
    //     $cliente->email = $array['email']  ?? null;
    //     $cliente->endereco = $array['endereco']  ?? null;
    //     $cliente->enderecoNumero = $array['endereco_numero']  ?? null;
    //     $cliente->estado = $array['estado']  ?? null;
    //     $cliente->exterior = $array['exterior']  ?? null;
    //     $cliente->faxDdd = $array['fax_ddd']  ?? null;
    //     $cliente->faxNumero = $array['fax_numero']  ?? null;
    //     $cliente->homepage = $array['homepage']  ?? null;
    //     $cliente->inativo = $array['inativo']  ?? null;
    //     $cliente->inscricaoEstadual = $array['inscricao_estadual']  ?? null;
    //     $cliente->inscricaoMunicipal = $array['inscricao_municipal']  ?? null;
    //     $cliente->inscricaoSuframa = $array['inscricao_suframa']  ?? null;
    //     $cliente->logradouro = $array['logradouro']  ?? null;
    //     $cliente->nif = $array['nif']  ?? null;
    //     $cliente->nomeFantasia = htmlspecialchars_decode($array['nome_fantasia'])  ?? null;
    //     $cliente->obsDetalhadas = $array['obs_detalhadas']  ?? null;
    //     $cliente->observacao = $array['observacao']  ?? null;
    //     $cliente->simplesNacional = $array['optante_simples_nacional']  ?? null;
    //     $cliente->pessoaFisica = $array['pessoa_fisica']  ?? null;
    //     $cliente->produtorRural = $array['produtor_rural']  ?? null;
    //     $cliente->razaoSocial = htmlspecialchars_decode($array['razao_social'])  ?? null;
    //     $cliente->recomendacaoAtraso = $array['recomendacao_atraso']  ?? null;
    //     $cliente->codigoVendedor = $array['recomendacoes_codigo_vendedor'] ?? null;
    //     $cliente->emailFatura = $array['recomendacoes_email_fatura'] ?? null;
    //     $cliente->gerarBoletos = $array['recomendacoes_gerar_boletos'] ?? null;
    //     $cliente->numeroParcelas = $array['recomendacoes_numero_parcelas'] ?? null;
    //     $cliente->idTranspPadrao = $array['recomendacoes_codigo_transportadora'] ?? null;
    //     $transp = new stdClass();
    //     $transp->codigoClienteOmie = $cliente->idTranspPadrao;
    //     $transp = $omieServices->getClientByid($transp );
    //     $cliente->idTranspPadraoPloomes = $transp['codigo_cliente_integracao'] ?? null;
    //     $tags=[];
     
    //     foreach($decoded['event']['tags'] as $t=>$v){
    //         $tags[$t]=$v;
           
    //     }
    //     $cliente->tags = $tags;
    //     $cliente->telefoneDdd1 = $array['telefone1_ddd'];
    //     $cliente->telefoneNumero1 = $array['telefone1_numero'];
    //     $cliente->telefoneDdd2 = $array['telefone2_ddd'];
    //     $cliente->telefoneNumero2 = $array['telefone2_numero'];
    //     $cliente->tipoAtividade = $array['tipo_atividade'];
    //     $cliente->limiteCredito = $array['valor_limite_credito'];
    //     // $cliente->authorEmail = $array['author_email'];
    //     // $cliente->authorName = $array['author_name'];
    //     // $cliente->authorUserId = $array['author_userId'];
    //     $cliente->appKey = $decoded['appKey'];
    //     // $cliente->appHash = $array['appHash'];
    //     // $cliente->origin = $array['origin'];

    //     return $cliente;
    // }

    // public static function createOmieOldObjectByIdPloomes($webhook, $ploomesServices)
    // {

    //     $json = $webhook['json'];
    //     $decoded = json_decode($json,true);
    //     $array = array_filter(DiverseFunctions::achatarArray($ploomesServices->getClientById($decoded['event']['codigo_cliente_integracao'])));
        
    //     $cliente = new stdClass();

    //     $cliente->bairro = $array['Neighborhood'];
    //     // $cliente->bloqueado = $array[''];
    //     // $cliente->bloquearFaturamento = $array[''];
    //     $cliente->cep = $array['ZipCode'];
    //     $cliente->cidade = $array['City_Name'];
    //     $cliente->cidadeIbge = $array['City_IBGECode'];
    //     $cliente->cnae = $array['CNAECode'];
    //     $cliente->cnpjCpf = $array['Register'];
    //     $cliente->codigoPais = $array['Country_Id'];
    //     $cliente->complemento = $array['StreetAddressLine2'];
    //     $cliente->contato = $array['OtherProperties_26_StringValue'];
    //     // $cliente->contribuinte = $array[''];
    //     $cliente->agencia = $array['OtherProperties_14_StringValue'];
    //     $cliente->cBanco = $array['OtherProperties_13_StringValue'];
    //     $cliente->nContaCorrente = $array['OtherProperties_15_StringValue'];
    //     $cliente->docTitular = $array['OtherProperties_16_StringValue'];
    //     $cliente->nomeTitular = $array['OtherProperties_17_StringValue'];
    //     $cliente->chavePix = $array['OtherProperties_18_StringValue'];
    //     $cliente->email = $array['Email'];
    //     $cliente->endereco = $array['StreetAddress'];
    //     $cliente->enderecoNumero = $array['StreetAddressNumber'];
    //     $cliente->estado = $array['State_Short'];
    //     // $cliente->exterior = $array[''];
    //     // $cliente->homepage = $array['WebSite'];
    //     // $cliente->inativo = $array[''];
    //     $cliente->inscricaoEstadual = $array['OtherProperties_5_StringValue'];
    //     $cliente->inscricaoMunicipal = $array['OtherProperties_6_StringValue'];
    //     $cliente->inscricaoSuframa = $array['OtherProperties_6_StringValue'];
    //     $cliente->logradouro = $array['StreetAddress'];
    //     // $cliente->nif = $array[''];
    //     $cliente->nomeFantasia = $array['Name'];
    //     //$cliente->obsDetalhadas = $array['event_obs_detalhadas'];
    //     $cliente->observacao = $array['Note'];
    //     // $cliente->simplesNacional = $array[''];
    //    // $cliente->pessoaFisica = $array['event_pessoa_fisica'];
    //     //$cliente->produtorRural = $array['event_produtor_rural'];
    //     $cliente->razaoSocial = $array['LegalName'];
    //    //o $cliente->recomendacaoAtraso = $array['event_recomendacao_atraso'];
    //     $cliente->codigoVendedor = $array['OwnerId'];
    //    // $cliente->emailFatura = $array['event_recomendacoes_email_fatura'];
    //    // $cliente->gerarBoletos = $array['event_recomendacoes_gerar_boletos'];
    //    // $cliente->numeroParcelas = $array['event_recomendacoes_numero_parcelas'];
    //     $cliente->telefoneDdd1 = $array['Phones_0_PhoneNumber'];
    //     $cliente->telefoneNumero1 = $array['Phones_0_PhoneNumber'];
    //     $cliente->telefoneDdd2 = $array['Phones_1_PhoneNumber'];
    //     $cliente->telefoneNumero2 = $array['Phones_1_PhoneNumber'];
    //     $cliente->tipoAtividade = $array['LineOfBusiness_Name'];
    //     $cliente->limiteCredito = $array['OtherProperties_10_DecimalValue'];
    //     // $cliente->authorEmail = $array['author_email'];
    //     // $cliente->authorName = $array['author_name'];
    //     // $cliente->authorUserId = $array['author_userId'];
    //     // $cliente->appKey = $array['appKey'];
    //     // $cliente->appHash = $array['appHash'];
    //     // $cliente->origin = $array['origin'];
    //     // print_r($cliente);
    //     // exit;
    //     return $cliente;
    //     // if(empty($id)){
    //     //     throw new WebhookReadErrorException('Impossível alterar, clinete não possui código de integração',500);
    //     // }
    // }

    

}