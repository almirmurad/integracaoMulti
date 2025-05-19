<?php

namespace src\functions;

use src\exceptions\WebhookReadErrorException;

class DiverseFunctions{

           //identifica qual action do webhook
    public static function findAction(array $args): array
    {
        $current = date('d/m/Y H:i:s');
        $decoded = $args['body'];
        
        if(isset($decoded['Action'])){
            try{
                $action = match($decoded['Action']){
                    'Create' => 'create',
                    'Update' => 'update',
                    'Delete' => 'delete'
                };

                $type = match($decoded['New']['TypeId']){
                    1 => 'empresa',
                    2 => 'pessoa'
                };

                $array = [
                    'action' =>$action,
                    'type' => $type,
                    'origem' => 'CRMToERP'
                ];

            }catch(\UnhandledMatchError $e){
                throw new WebhookReadErrorException('Não foi encontrada nenhuma ação no webhook ['.$e->getMessage().']'.$current, 500);
            }
            
        }elseif(isset($decoded['topic'])){
            try{
                $action = match($decoded['topic']){
                    'ClienteFornecedor.Incluido' => 'create',
                    'ClienteFornecedor.Alterado' => 'update',
                    'ClienteFornecedor.Excluido' => 'delete',
                    'Produto.Incluido' => 'create',
                    'Produto.Alterado' => 'update',
                    'Produto.Excluido' => 'delete',
                    'Produto.MovimentacaoEstoque' => 'stock',
                    'Servico.Incluido' => 'create',
                    'Servico.Alterado' => 'update',
                    'Servico.Excluido' => 'delete',
                };

                $array = [
                    'action' =>$action,
                    'origem' => 'ERPToCRM'
                ];

            }catch(\UnhandledMatchError $e){
                throw new WebhookReadErrorException('Não foi encontrada nenhuma ação no webhook ['.$e->getMessage().']'.$current, 500);
            }
        }else{
            throw new WebhookReadErrorException('Não foi encontrada nenhuma ação no webhook '.$current, 500);
        }
        
        return $array;
    }

    //CONVERTE PARA DATA EM PORTUGUÊS
    public static function convertDate($date)
    {
        $dateIni = explode('T', $date);
        $datePt = explode('-', $dateIni[0]);
        $datePtFn = implode("/", array_reverse($datePt)); // . " às " . $dateIni[1];
        return $datePtFn;
    }

    public static function convertDateHora($date)
    {

        $dateIni = explode('T', $date);
        $datePt = explode('-', $dateIni[0]);
        $datePtFn = implode("/", array_reverse($datePt)) . " às " . $dateIni[1];

        return $datePtFn;
    }

    public static function limpa_cpf_cnpj($valor)
    {
        $valor = trim($valor);
        $valor = str_replace(array('.','-','/'), "", $valor);
        return $valor;
    }

    public static function calculaParcelas($dataInicio,$totalParcelas,$totalPedido)
    {
        
        $intervalo = explode('/',$totalParcelas);
        $nParcelas = count($intervalo);
        $valorParcela = round($totalPedido / $nParcelas,2);
        $percentual = round(($valorParcela / $totalPedido)*100,2);
        $somaParcelas = 0;
        $somaPercentuais = 0;
       
        for ($i = 0; $i < $nParcelas; $i++) {
            
            $somaPercentuais += $percentual;
            $somaParcelas += $valorParcela;
            $dataVencimento = date('d/m/Y',strtotime("+ $intervalo[$i] day", strtotime($dataInicio)));
            
            $parcela[] = [
                "data_vencimento" => $dataVencimento,
                "numero_parcela" => $i + 1,
                "percentual" => $percentual,
                "valor" => $valorParcela,
            ];
            
        }
        // Ajustar o primeiro percentual para garantir que a soma seja 100%
        $diferenca = 100 - $somaPercentuais;
        $parcela[0]['percentual'] += $diferenca;
        // Ajustar o primeiro valor parcela para garantir que a soma das parcelas seja o total do pedido
        $diferencaParcela = $totalPedido - $somaParcelas;
        $parcela[0]['valor'] += $diferencaParcela;

        return $parcela;
    
    }

    public static function getIdParcelamento ($parcelamento)
    {
        if($parcelamento == "0"){
            $parcelamento = 'a vista';
        }
        $intervalo = [
            'a vista'=>'000',
            '14'=>'A14',
            '14/21/28/35/42'=>'S34',
            '14/21/28/35/42/49'=>'Z61',
            '14/21/28/35/42/49/56'=>'U33',
            '21'=>'A21',
            '21/28'=>'S26',
            '21/28/35'=>'S03',
            '21/28/35/42'=>'S04',       
            '21/28/35/42/49'=>'T02',  
            '28'=>'A28',      
            '28/35'=>'S13',       
            '28/35/42'=>'S05',      
            '28/42/49'=>'U53',       
            '7'=>'A07',
            '7/14'=>'S20',
        ];
        return $intervalo[$parcelamento];       
    }

    public static function achatarArray($array, $prefixo = '')
    {
        $resultado = array();
        
        foreach ($array as $chave => $valor) {
            // Cria a nova chave adicionando o prefixo se existir
            $novaChave = $prefixo ? $prefixo . '_' . $chave : $chave;
            
            if (is_array($valor)) {
                // Se o valor for um array, chama a função recursivamente
                $resultado = array_merge($resultado, self::achatarArray($valor, $novaChave));
            } else {
                // Caso contrário, adiciona o valor ao resultado com a nova chave
                $resultado[$novaChave] = $valor;
            }
        }
        return $resultado;
    }

public static function compareArrays($old, $new, $ignorar = [])
{
    $diferencas = [];

    // Função auxiliar para ordenar arrays associativos e seus subarrays recursivamente
    $ordenarRecursivo = function (&$array) use (&$ordenarRecursivo) {
        if (!is_array($array)) return;

        foreach ($array as &$valor) {
            if (is_array($valor)) {
                $ordenarRecursivo($valor);
            }
        }

        // Ordena pelas chaves
        ksort($array);
    };

    foreach ($old as $chave => $valorOld) {
        if (in_array($chave, $ignorar)) {
            continue;
        }

        if (!array_key_exists($chave, $new)) {
            $diferencas[$chave] = ['old' => $valorOld, 'new' => null];
        } elseif (is_array($valorOld) && is_array($new[$chave])) {
            // Ordena os dois arrays recursivamente antes de comparar
            $ordenarRecursivo($valorOld);
            $ordenarRecursivo($new[$chave]);

            if (json_encode($valorOld) !== json_encode($new[$chave])) {
                $diferencas[$chave] = ['old' => $valorOld, 'new' => $new[$chave]];
            }
        } elseif ($valorOld !== $new[$chave]) {
            $diferencas[$chave] = ['old' => $valorOld, 'new' => $new[$chave]];
        }
    }

    // Verifica se há chaves novas no array $new que não existem no $old
    foreach ($new as $chave => $valorNew) {
        if (!array_key_exists($chave, $old) && !in_array($chave, $ignorar)) {
            $diferencas[$chave] = ['old' => null, 'new' => $valorNew];
        }
    }

    return $diferencas;
}


    // public static function compararArrays($old, $new, $path = '') 
    // {
    //     $diferencas = [];

    //     foreach ($old as $chave => $valor) {
    //         $novaChave = $path === '' ? $chave : $path . '.' . $chave;
    //         // Se for um array, faz a chamada recursiva
    //         if (is_array($valor) && isset($new[$chave]) && is_array($new[$chave])) {
    //             $subDiferencas = self::compararArrays($valor, $new[$chave], $novaChave);
    //             $diferencas = array_merge($diferencas, $subDiferencas);
    //         }
    //         // Verifica se o valor foi alterado
    //         elseif (isset($new[$chave]) && $new[$chave] !== $valor) {
    //             $diferencas[$novaChave] = [
    //                 'old' => $valor,
    //                 'new' => $new[$chave]
    //             ];
    //         }
    //     }

    //     return $diferencas;
    // }

    // public static function compararArrays($old, $new, $path = '') 
    // {
    //     $diferencas = [];

    //     foreach ($old as $chave => $valor) {
    //         $novaChave = $path === '' ? $chave : $path . '.' . $chave;
            
    //         // Se for um array, faz a chamada recursiva
    //         if (is_array($valor) && isset($new[$chave]) && is_array($new[$chave])) {
    //             $subDiferencas = self::compararArrays($valor, $new[$chave], $novaChave);
    //             $diferencas = array_merge($diferencas, $subDiferencas);
    //         }
    //         // Verifica se o valor é diferente
    //         elseif (isset($new[$chave]) && $new[$chave] !== $valor) {
    //             $diferencas[$novaChave] = [
    //                 'old' => $valor,
    //                 'new' => $new[$chave]
    //             ];
    //         }
    //         // Caso exista a chave no array antigo, mas não no novo
    //         elseif (!isset($new[$chave])) {
    //             $diferencas[$novaChave] = [
    //                 'old' => $valor,
    //                 'new' => null
    //             ];
    //         }
    //     }

    //     // Verifica se há chaves no novo array que não estão no antigo
    //     foreach ($new as $chave => $valor) {
    //         $novaChave = $path === '' ? $chave : $path . '.' . $chave;
    //         if (!isset($old[$chave])) {
    //             $diferencas[$novaChave] = [
    //                 'old' => null,
    //                 'new' => $valor
    //             ];
    //         }
    //     }

    //     return $diferencas;
    // }


}