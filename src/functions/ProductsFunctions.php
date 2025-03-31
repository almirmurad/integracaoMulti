<?php
namespace src\functions;

use src\exceptions\WebhookReadErrorException;
use src\models\Contact;
use src\services\ProductServices;
use stdClass;


class ProductsFunctions{

    // encontra o processo a ser executado caso haja cadastro, exclusão ou alteração no webhook
    public static function findAction($json)
    {

        //decodifica o json de clientes vindos do json
        $decoded = json_decode($json,true);
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
                'Produto.Incluido' => 'createERPToCRM',
                'Produto.Alterado' => 'updateERPToCRM',
                'Produto.Excluido' => 'deleteERPToCRM',
                'Produto.MovimentacaoEstoque'=>'stockMovementERPtoCRM',
            };
        }else{
            throw new WebhookReadErrorException('Não foi encontrda nenhuma ação no webhook '.$current, 500);
        }
      
        return $action;

    }

    //cria um objeto do webhook vindo do omie para enviar ao ploomes
    public static function createOmieObj($json, $omieServices)
    {
        //decodifica o json de produto vindos do webhook
     
        $decoded = json_decode($json,true);
    
        // Função recursiva para limpar todos os campos do array
        function auto_clean_json($data) {
            $entidades_customizadas = [
                "+Chr(39)+" => "'", // Aspas simples
                "Chr(34)"   => '"', // Aspas duplas
                "&apos;"    => "'", // Entidade HTML para aspas simples
            ];

            foreach ($data as $key => &$value) {
                if (is_array($value)) {
                    $value = auto_clean_json($value);
                } elseif (is_string($value)) {
                    // Decodifica entidades HTML
                    $value = htmlspecialchars_decode($value, ENT_QUOTES);

                    // Substitui padrões específicos
                    $value = strtr($value, $entidades_customizadas);

                    // Substitui múltiplas aspas simples (3 ou mais) por uma única
                    $value = preg_replace("/'{2,}/", "'", $value);
                }
            }
            return $data;
        }


        // Aplica a função para limpar todos os campos do JSON decodificado
        $cleaned_data = auto_clean_json($decoded);

        // print_r($cleaned_data);


        //achata o array multidimensional decoded em um array simples
        $array = DiverseFunctions::achatarArray($cleaned_data);
        //cria o objeto de produtos
        $product = new stdClass();

        switch($decoded['appKey']){
            case '1120581879417': 
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_EPT'];
                $omie->appSecret = $_ENV['SECRETS_EPT'];
                $product->baseFaturamentoTitle = 'Engeparts';
                // $cOmie = [
                //     'FieldKey'=>'product_E57EE0E4-2668-4424-AB79-1579840719BE',
                //     'StringValue'=>$product->codigoClienteOmie,
                // ];
                break;
            case '146532853467': 
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_GTC'];
                $omie->appSecret = $_ENV['SECRETS_GTC'];
                $product->baseFaturamentoTitle = 'Gamatermic';
                // $cOmie = [
                //     'FieldKey'=>'contact_6DB7009F-1E58-4871-B1E6-65534737C1D0',
                //     'StringValue'=>$product->codigoClienteOmie,

                // ];
                break;
            case '146571186762':
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_SMN'];
                $omie->appSecret = $_ENV['SECRETS_SMN']; 
                $product->baseFaturamentoTitle = 'Semin';
                // $cOmie = [
                //     'FieldKey'=>'contact_AE3D1F66-44A8-4F88-AAA5-F10F05E662C2',
                //     'StringValue'=>$product->codigoClienteOmie,
                // ];
                break;
            case '171250162083': 
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_GSU'];
                $omie->appSecret = $_ENV['SECRETS_GSU']; 
                $product->baseFaturamentoTitle = 'GSU';
                // $cOmie = [
                //     'FieldKey'=>'contact_07784D81-18E1-42DC-9937-AB37434176FB',
                //     'StringValue'=>$product->codigoClienteOmie,

                // ];
                break;
        }

        $product->appKey = $array['appKey'];
        $product->appSecret = $omie->appSecret;
        $product->grupo = 'Produtos';
        $product->idGrupo = '400345485';
        $product->messageId = $array['messageId'];
        $product->altura = $array['event_altura'];
        $product->bloqueado = $array['event_bloqueado'];
        $product->cnpj_fabricante = $array['event_cnpj_fabricante'];
        $product->codigo = $array['event_codigo'];
        $product->codigo_familia = $array['event_codigo_familia'];
        ($product->codigo_familia == 0 || $product->codigo_familia == null) ? $product->nome_familia = 'A DEFINIR' : $product->nome_familia = $omieServices->getFamiliaById($product);
        $product->codigo_produto = $array['event_codigo_produto'];
        $product->codigo_produto_integracao = $array['event_codigo_produto_integracao'];
        $product->combustivel_codigo_anp = $array['event_combustivel_codigo_anp'];
        $product->combustivel_descr_anp = $array['event_combustivel_descr_anp'];
        $product->cupom_fiscal = $array['event_cupom_fiscal'];
        $product->descr_detalhada = $array['event_descr_detalhada'];
        $product->descricao = $array['event_descricao'];
        $product->dias_crossdocking = $array['event_dias_crossdocking'];
        $product->dias_garantia = $array['event_dias_garantia'];
        $product->ean = $array['event_ean'];
        $product->estoque_minimo = $array['event_estoque_minimo'];
        $product->id_cest = $array['event_id_cest'];
        $product->id_preco_tabelado = $array['event_id_preco_tabelado'];
        $product->inativo = $array['event_inativo'];
        $product->indicador_escala = $array['event_indicador_escala'];
        $product->largura = $array['event_largura'];
        $product->marca = $array['event_marca'];
        $product->market_place = $array['event_market_place'];
        $product->modelo = $array['event_modelo'];
        $product->ncm = $array['event_ncm'];
        $product->obs_internas = $array['event_obs_internas'];
        $product->origem_mercadoria = $array['event_origem_mercadoria'];
        $product->peso_bruto = $array['event_peso_bruto'];
        $product->peso_liq = $array['event_peso_liq'];
        $product->profundidade = $array['event_profundidade'];
        $product->quantidade_estoque = $array['event_quantidade_estoque'];
        $product->tipoItem = $array['event_tipoItem'];
        $product->unidade = $array['event_unidade'];
        $product->valor_unitario = $array['event_valor_unitario'];
        $product->author_email = $array['author_email'];
        $product->author_name = $array['author_name'];
        $product->author_userId = $array['author_userId'];
        
        $product->appHash = $array['appHash'];
        $product->origin = $array['origin'];      
        //estoque

        $product->stock = self::getStock($product,$omie,$omieServices);      
        
        return $product;
    }

    public static function getStock(object $product, object $omie, object $omieServices){

        $stock = $omieServices->getStockById($product,$omie);
        $table = self::createTableStock($stock);

        return $table;

    }

    public static function createTableStock($stock)
    {
        $local = ($stock['codigo_local_estoque'] === 6879399409)? 'Padrão' : $stock['codigo_local_estoque'];
        //$html = file_get_contents('http://localhost/gamatermic/src/views/pages/gerenciador.pages.stockTable.php');
        $html = file_get_contents('https://gamatermic.bicorp.online/src/views/pages/gerenciador.pages.stockTable.php');
        $html = str_replace('{local}', $local, $html);
        $html = str_replace('{saldo}', $stock['saldo'], $html);
        $html = str_replace('{minimo}', $stock['estoque_minimo'], $html);
        $html = str_replace('{pendente}', $stock['pendente'], $html);
        $html = str_replace('{reservado}', $stock['reservado'], $html);
        $html = str_replace('{fisico}', $stock['fisico'], $html);
        $html = str_replace('{data}', date('d/m/Y H:i:s'), $html);

        return $html;
    }

    // public static function alterStock(string $movement, object $ploomesServices){

    //     $decoded = json_decode($movement);
    //     $product = $ploomesServices->getProductById()
    //     switch($decoded['appKey']){
    //         case '1120581879417': 
    //             $omie = new stdClass();
    //             $omie->appKey = $_ENV['APPK_EPT'];
    //             $omie->appSecret = $_ENV['SECRETS_EPT'];
    //             $product->baseFaturamentoTitle = 'Engeparts';
    //             // $cOmie = [
    //             //     'FieldKey'=>'product_E57EE0E4-2668-4424-AB79-1579840719BE',
    //             //     'StringValue'=>$product->codigoClienteOmie,
    //             // ];
    //             break;
    //         case '2335095664902': 
    //             $omie = new stdClass();
    //             $omie->appKey = $_ENV['APPK_GTC'];
    //             $omie->appSecret = $_ENV['SECRETS_GTC'];
    //             $product->baseFaturamentoTitle = 'Gamatermic';
    //             // $cOmie = [
    //             //     'FieldKey'=>'contact_6DB7009F-1E58-4871-B1E6-65534737C1D0',
    //             //     'StringValue'=>$product->codigoClienteOmie,

    //             // ];
    //             break;
    //         case '146571186762':
    //             $omie = new stdClass();
    //             $omie->appKey = $_ENV['APPK_SMN'];
    //             $omie->appSecret = $_ENV['SECRETS_SMN']; 
    //             $product->baseFaturamentoTitle = 'Semin';
    //             // $cOmie = [
    //             //     'FieldKey'=>'contact_AE3D1F66-44A8-4F88-AAA5-F10F05E662C2',
    //             //     'StringValue'=>$product->codigoClienteOmie,
    //             // ];
    //             break;
    //         case '171250162083': 
    //             $omie = new stdClass();
    //             $omie->appKey = $_ENV['APPK_GSU'];
    //             $omie->appSecret = $_ENV['SECRETS_GSU']; 
    //             $product->baseFaturamentoTitle = 'GSU';
    //             // $cOmie = [
    //             //     'FieldKey'=>'contact_07784D81-18E1-42DC-9937-AB37434176FB',
    //             //     'StringValue'=>$product->codigoClienteOmie,

    //             // ];
    //             break;
    //     }

    //     $stock = $omieServices->movementStock($product,$omie);
    //     $local = ($stock['codigo_local_estoque'] === 6879399409)? 'Padrão' : $stock['codigo_local_estoque'];
    //     //$html = file_get_contents('http://localhost/gamatermic/src/views/pages/gerenciador.pages.stockTable.php');
    //     $html = file_get_contents('https://gamatermic.bicorp.online/src/views/pages/gerenciador.pages.stockTable.php');
    //     $html = str_replace('{local}', $local, $html);
    //     $html = str_replace('{saldo}', $stock['saldo'], $html);
    //     $html = str_replace('{minimo}', $stock['estoque_minimo'], $html);
    //     $html = str_replace('{pendente}', $stock['pendente'], $html);
    //     $html = str_replace('{reservado}', $stock['reservado'], $html);
    //     $html = str_replace('{fisico}', $stock['fisico'], $html);
    //     $html = str_replace('{data}', date('d/m/Y'), $html);

    //     return $html;

    // }


    // cria o objet e a requisição a ser enviada ao ploomes com o objeto do omie
    public static function createPloomesProductFromOmieObject($product, $ploomesServices, $omieServices)
    {

        switch($product->appKey){
            case '1120581879417': 
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_EPT'];
                $omie->appSecret = $_ENV['SECRETS_EPT'];
                $product->baseFaturamentoTitle = 'Engeparts';
                $cOmie = [
                    'FieldKey'=>'product_0A53B875-0974-440F-B4CE-240E8F400B0F',
                    'StringValue'=>$product->codigo_produto,
                ];
                //tabela de estoque por base de faturamento
                $stockTable = [
                        'FieldKey'=>'product_4B2C943C-9EC4-4553-8B45-10C0FD2B0810',
                        'BigStringValue'=>$product->stock,
                ];

                break;
            case '146532853467': 
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_GTC'];
                $omie->appSecret = $_ENV['SECRETS_GTC'];
                $product->baseFaturamentoTitle = 'Gamatermic';
                $cOmie = [
                    'FieldKey'=>'product_E241BF1D-7622-45DF-9658-825331BD1C2D',
                    'StringValue'=>$product->codigo_produto,

                ];
                // tabela de estoque por base de faturamento
                $stockTable = [
                        'FieldKey'=>'product_E57EE0E4-2668-4424-AB79-1579840719BE',
                        'BigStringValue'=>$product->stock,
                ];
                break;
            case '146571186762':
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_SMN'];
                $omie->appSecret = $_ENV['SECRETS_SMN']; 
                $product->baseFaturamentoTitle = 'Semin';
                $cOmie = [
                    'FieldKey'=>'product_429C894A-708E-4125-A434-2A70EDCAFED6',
                    'StringValue'=>$product->codigo_produto,
                ];
                // tabela de estoque por base de faturamento
                $stockTable = [
                        'FieldKey'=>'product_3F2FCCB8-0537-483B-8A8F-EEE998152D51',
                        'BigStringValue'=>$product->stock,
                ];
                break;
            case '171250162083': 
                $omie = new stdClass();
                $omie->appKey = $_ENV['APPK_GSU'];
                $omie->appSecret = $_ENV['SECRETS_GSU']; 
                $product->baseFaturamentoTitle = 'GSU';
                $cOmie = [
                    'FieldKey'=>'product_08A41D8E-F593-4B74-8CF8-20A924209A09',
                    'StringValue'=>$product->codigo_produto,

                ];
                // tabela de estoque por base de faturamento
                $stockTable = [
                        'FieldKey'=>'product_03A29673-70E4-4887-9FC9-65D36791F2D7',
                        'BigStringValue'=>$product->stock,
                ];
                break;
        }

        $product->baseFaturamento = [];

        switch($product->baseFaturamentoTitle)
        {
            case 'Engeparts':
                $product->baseFaturamento['sigla'] =  'EPT';
                $product->baseFaturamento['appKey'] =  $_ENV['APPK_EPT'];
                $product->baseFaturamento['appSecret'] =  $_ENV['SECRETS_EPT'];
                $product->baseFaturamento['idOmie'] =   $product->codigo_produto;
                break;
            case 'Gamatermic':
                $product->baseFaturamento['sigla'] =  'GTC';
                $product->baseFaturamento['appKey'] = $_ENV['APPK_GTC'];//substituir pelo de cada base do cliente
                $product->baseFaturamento['appSecret'] = $_ENV['SECRETS_GTC'];//substituir pelo de cada base do cliente
                $product->baseFaturamento['idOmie'] = $product->codigo_produto;
                break;
            case 'Semin':
                $product->baseFaturamento['sigla'] = 'SMN';
                $product->baseFaturamento['appKey'] = $_ENV['APPK_SMN'];//substituir pelo de cada base do cliente
                $product->baseFaturamento['appSecret'] =  $_ENV['SECRETS_SMN'];//substituir pelo de cada base do cliente
                $product->baseFaturamento['idOmie'] = $product->codigo_produto;
                break;
            case 'GSU':
                $product->baseFaturamento['sigla'] =  'GSU';
                $product->baseFaturamento['appKey'] = $_ENV['APPK_GSU'];//substituir pelo de cada base do cliente
                $product->baseFaturamento['appSecret'] = $_ENV['SECRETS_GSU'];//substituir pelo de cada base do cliente
                $product->baseFaturamento['idOmie'] = $product->codigo_produto;
                break;
        }

        //cria o produto formato ploomes 
        $data = [];

        $data['Name'] = $product->descricao;
        $data['GroupId'] = $product->idGrupo;
        //$data['FamilyId'] = $product->codigo_familia;
        $data['Code'] = $product->codigo;
        // $data['Code'] = $product->codigo_produto;
        $data['MeasurementUnit'] = $product->unidade;
        //$data['ImageUrl'] = $product->endereco ?? null;
        //$data['CurrencyId'] = $product->enderecoNumero ?? null;
        $data['UnitPrice'] = $product->valor_unitario ?? null;
        // $data['CreateImportId'] = $city['Id'];//pegar na api do ploomes
        // $data['UpdateImportId'] = $product->segmento ?? null;//Id do Tipo de atividade(não veio no webhook de cadastro do omie)
        // $data['Editable'] = $product->nFuncionarios ?? null;//Id do número de funcionários(não veio no webhook de cadastro do omie)
        // $data['Deletable'] = $product->cVendedorPloomes ?? null;//Id do vendedor padrão(comparar api ploomes)
        // $data['Suspended'] = $product->observacao ?? null;
        // $data['CreatorId'] = $product->email ?? null;
        // $data['UpdaterId'] = $product->homepage ?? null;
        // $data['CreateDate'] = $product->cnae ?? null;
        // $data['LastUpdateDate'] = $product->codigoClienteOmie ?? null;//chave externa do cliente(código Omie)
        //$data['ImportationIdCreate'] = $product->latitude ?? null;(inexistente no omie)
        //$data['ImportationIdUpdate'] = $product->longitude ?? null;(inexistente no omie)

        $pProduct = $ploomesServices->getProductByCode($product->codigo);
        
        if(!$pProduct){
            $data['Lists']=null;
        }else{
           
            $marcador = $ploomesServices->getListByTagName($product->nome_familia);

            if($marcador){
        
                $data['Lists'] = [
                    [
                        'ListId'=> $marcador['Id'],
                        'ProductId'=> $pProduct['Id']
                    ]
                ];
    
            }else{

                $array = [
                    'Name'=>$product->nome_familia,
                    'Editable'=>true
                ];
                $json = json_encode($array);
    
                $nMarcador = $ploomesServices->createNewListTag($json);

    
                $data['Lists'] = [
                    [

                        'ListId'=> $nMarcador['Id'],
                        'ProductId'=> $pProduct['Id']
                    ]
                ];
    
            }

        }
        
        $op = [];
        $ncm = [
            'FieldKey'=> 'product_15405B03-AA47-4921-BC83-E358501C3227',
            'StringValue'=>$product->ncm ?? null,
        ];
        $marca = [
            'FieldKey'=>'product_4C2CCB79-448F-49CF-B27A-822DA762BE5E',
            'StringValue'=>$product->marca ?? null,
        ];

        $modelo = [
            'FieldKey'=>'product_A92259E5-1E19-44AC-B781-CB908F5602EC',
            'StringValue'=>$product->modelo ?? null,
        ];
        $descDetalhada = [
            'FieldKey'=>'product_F48280B4-688C-4346-833C-03E28991564C',
            'BigStringValue'=>$product->descr_detalhada ?? null,
        ];
        $obsInternas = [
            'FieldKey'=>'product_5FB6D80C-CB90-4A46-95BD-1A18141FBC46',
            'BigStringValue'=>$product->obs_internas ?? null,
        ];
        $categoria = [
            'FieldKey'=>'product_44CCBB11-CD81-439A-8304-921C2E39C25D',
            'StringValue'=>$product->codigo_familia ?? null,
        ];

        $op[] = $ncm;
        $op[] = $marca;
        $op[] = $modelo;
        $op[] = $descDetalhada;
        $op[] = $obsInternas;
        $op[] = $categoria;
        $op[] = $cOmie;
        $op[] = $stockTable;
   
        $data['OtherProperties'] = $op;
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        return $json;

    }

    public static function createProductFromPloomesWebhook($json)
    {
        
        $decoded = json_decode($json, true);
   
        $product = new stdClass();
        $product->idPloomes = $decoded['New']['Id'];
        $product->descricao = $decoded['New']['Name'];
        $product->codigo = $decoded['New']['Code'];
        $product->unidade = $decoded['New']['MeasurementUnit'];
        $product->precoUnitario = $decoded['New']['UnitPrice'] ?? null;
        $product->marca = $decoded['New']['OtherProperties']['product_4C2CCB79-448F-49CF-B27A-822DA762BE5E'] ?? null;
        $product->modelo = $decoded['New']['OtherProperties']['product_A92259E5-1E19-44AC-B781-CB908F5602EC'] ?? null;
        $product->ncm = $decoded['New']['OtherProperties']['product_15405B03-AA47-4921-BC83-E358501C3227'] ?? null;
        $product->descricaoDetalhada = $decoded['New']['OtherProperties']['product_F48280B4-688C-4346-833C-03E28991564C'] ?? null;
        $product->obsInternas = $decoded['New']['OtherProperties']['product_5FB6D80C-CB90-4A46-95BD-1A18141FBC46'] ?? null;
        $product->idCategoria = $decoded['New']['OtherProperties']['product_44CCBB11-CD81-439A-8304-921C2E39C25D'] ?? null;
        
        $product->idEngeparts = $decoded['New']['OtherProperties']['product_0A53B875-0974-440F-B4CE-240E8F400B0F'] ?? null;
        $product->idGamatermic = $decoded['New']['OtherProperties']['product_E241BF1D-7622-45DF-9658-825331BD1C2D'] ?? null;
        $product->idSemin = $decoded['New']['OtherProperties']['product_429C894A-708E-4125-A434-2A70EDCAFED6'] ?? null;
        $product->idGSU = $decoded['New']['OtherProperties']['product_08A41D8E-F593-4B74-8CF8-20A924209A09'] ?? null;

        $product->baseFaturamento = [];

        if(isset($product->idEngeparts) && $product->idEngeparts !== null)
        {
            $product->baseFaturamento[1]['title'] = 'Engeparts';
            $product->baseFaturamento[1]['sigla'] =  'EPT';
            $product->baseFaturamento[1]['appKey'] =  $_ENV['APPK_EPT'];
            $product->baseFaturamento[1]['appSecret'] =  $_ENV['SECRETS_EPT'];
            $product->baseFaturamento[1]['idOmie'] =  $product->idEngeparts;
        }
        if(isset($product->idGamatermic) && $product->idGamatermic !== null)
        {
            $product->baseFaturamento[2]['title'] = 'Gamatermic';
            $product->baseFaturamento[2]['sigla'] =  'GTC';
            $product->baseFaturamento[2]['appKey'] =  $_ENV['APPK_GTC'];//substituir pelo de cada base do cliente
            $product->baseFaturamento[2]['appSecret'] =  $_ENV['SECRETS_GTC'];//substituir pelo de cada base do cliente
            $product->baseFaturamento[2]['idOmie'] =  $product->idGamatermic;
        }
        if(isset($product->idSemin) && $product->idSemin !== null)
        {
            $product->baseFaturamento[3]['title'] = 'Semin';
            $product->baseFaturamento[3]['sigla'] =  'SMN';
            $product->baseFaturamento[3]['appKey'] =  $_ENV['APPK_SMN'];//substituir pelo de cada base do cliente
            $product->baseFaturamento[3]['appSecret'] =  $_ENV['SECRETS_SMN'];//substituir pelo de cada base do cliente
            $product->baseFaturamento[3]['idOmie'] =  $product->idSemin;
        }
        if(isset($product->idGSU) && $product->idGSU !== null){
            $product->baseFaturamento[4]['title'] = 'GSU';
            $product->baseFaturamento[4]['sigla'] =  'GSU';
            $product->baseFaturamento[4]['appKey'] = $_ENV['APPK_GSU'];//substituir pelo de cada base do cliente
            $product->baseFaturamento[4]['appSecret'] = $_ENV['SECRETS_GSU'];//substituir pelo de cada base do cliente
            $product->baseFaturamento[4]['idOmie'] =  $product->idGSU;
        };

        $product->tbEstoqueEngeparts = $decoded['New']['OtherProperties']['product_4B2C943C-9EC4-4553-8B45-10C0FD2B0810'] ?? null;
        $product->tbEstoqueGamatermic = $decoded['New']['OtherProperties']['product_E57EE0E4-2668-4424-AB79-1579840719BE'] ?? null;
        $product->tbEstoqueSemin = $decoded['New']['OtherProperties']['product_3F2FCCB8-0537-483B-8A8F-EEE998152D51'] ?? null;
        $product->tbEstoqueGSU = $decoded['New']['OtherProperties']['product_03A29673-70E4-4887-9FC9-65D36791F2D7'] ?? null;
        
        return $product;

    }

    public static function moveStock($json,  $ploomesServices)
    {
        $decoded = json_decode($json, true);
        //1 - preciso montar a tabela html cm estoque do produto 
        $stock = [];
        foreach($decoded['event'] as $k => $v){
            $stock[$k] = $v;
        }
        $table = self::createTableStock($stock);

        //2 - para encontrar o produto podemos pesquisar no ploomes pelo idPloomes(codigo integração omie), pelo Code(código omie) porém eles precisam ser unicos 
        // 2-1 - estamos com webhook do omie, temos o id omie mas não o id ploomes. Temos o code mas ele pode se repitir no ploomes. neste caso precisamos do id de integração no produto pois ele é o id unico do produto no ploomes. sendo assim precisamos forçar este codigo no produto do omie antes de fazer a consulta.

        //$pPloomes = $ploomesService->getProductById($stock['codigo_produto_integracao']);

        $product = new stdClass();
        $product->codigo = $stock['codigo'];

        switch($decoded['appKey']){
            case '1120581879417': 

                //tabela de estoque por base de faturamento
                $stockTable = [
                        'FieldKey'=>'product_4B2C943C-9EC4-4553-8B45-10C0FD2B0810',
                        'BigStringValue'=>$table,
                ];

                break;
            case '146532853467': 
                
                // tabela de estoque por base de faturamento
                $stockTable = [
                        'FieldKey'=>'product_E57EE0E4-2668-4424-AB79-1579840719BE',
                        'BigStringValue'=>$table,
                ];
                break;
            case '146571186762':
                
                // tabela de estoque por base de faturamento
                $stockTable = [
                        'FieldKey'=>'product_3F2FCCB8-0537-483B-8A8F-EEE998152D51',
                        'BigStringValue'=>$table,
                ];
                break;
            case '171250162083': 

                // tabela de estoque por base de faturamento
                $stockTable = [
                        'FieldKey'=>'product_03A29673-70E4-4887-9FC9-65D36791F2D7',
                        'BigStringValue'=>$table,
                ];
                break;
        }


        $array = [];
        $op =[];
        $op []= $stockTable;
        $array['OtherProperties'] = $op;

        $json = json_encode($array);

        return ProductServices::updateProductFromERPToCRM($json, $product, $ploomesServices);

        //3 - preciso salvar a tabela de estoque no campo estoque base x do produto x 



    }

}