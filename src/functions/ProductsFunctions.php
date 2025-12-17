<?php
namespace src\functions;

use src\exceptions\WebhookReadErrorException;
use src\models\Contact;
use src\services\ProductServices;
use stdClass;


class ProductsFunctions{


    public static function processProductErpToCrm(array $args, object $ploomesServices, object $formatter, array $action):array
    {
        $message = [];
        $current = date('d/m/Y H:i:s');
        
        if($action['action'] !== 'stock')
        { 

            
            $product = $formatter->createObjectCrmProductFromErpData($args, $ploomesServices);

            $json = $formatter->createPloomesProductFromErpObject($product, $ploomesServices);
            $pProduct = $ploomesServices->getProductByCode($product->codigo);
           
            if(isset($pProduct['Id']))
            {
                if($ploomesServices->updatePloomesProduct($json, $pProduct['Id']))
                {
                    //depois que cadastrar todoss os produtos inclusive os com estrutura, comenta as duas linhas a baixo e descomenta o return com estrutura
                    // $message['success'] = 'Produto '.$product->descricao.' alterado no Ploomes CRM com sucesso! Data: '.$current;
                    // return $message;
                    
                    return $formatter->productStructure($args, $ploomesServices, $pProduct );     
                }

                throw new WebhookReadErrorException('Erro ao alterar o produto Ploomes id: '.$pProduct['Id'].' em: '.$current, 500);  
                
            }
            else{
                if($ploomesServices->createPloomesProduct($json))
                {
                    $message['success'] = 'Produto '.$product->descricao.' Cadastrado no Ploomes CRM com sucesso! Data: '.$current;
                    return $message;
                }
                
                throw new WebhookReadErrorException('Erro ao cadastrar o produto no Ploomes id: '.$pProduct['Id'].' em: '.$current, 500);
            }

        }
        else
        {           
            if($formatter->moveStock($args, $ploomesServices)){
                $message['success'] = 'Estoque do produto alterado com sucesso! Data: '.$current;
                return $message;
            }

            throw new WebhookReadErrorException('Erro ao alterar estoque do produto'.$current, 500);
        }

        return $message;

    }

    //verifica se o produto tem uma estrutura cadastrada no ERP e traz para cadastrar no ploomes como vinculo de opcionais
    public static function getProductStructure(array $args, object $formatter):array
    {

        $structure = $formatter->getProductStructureERP($args);

        if(!isset($structure)){
            //se não encontrar a estrutura retorna um array vazio
            return [];
        }

        return $structure;

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

    

}