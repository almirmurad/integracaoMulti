<?php
namespace src\factories;

use Exception;
use src\contracts\ErpFormattersInterface;
use src\formatters\OmieFormatter;

class ErpFormatterFactory
{
    public static function create(array $args): ErpFormattersInterface
    {
        $erp = $args['user']['erp_name'];
        $omieBases = $args['Tenancy']['omie_bases'];
        $appk = $args['body']['appKey'] ?? null;

        return match (strtolower($erp)) {
            'omie' => new OmieFormatter($appk, $omieBases),
            //'senior' => new SeniorFormatter(),
            default => throw new Exception("ERP {$erp} não suportado")
        };
    }
}
