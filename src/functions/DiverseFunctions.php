<?php

namespace src\functions;

class DiverseFunctions{

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