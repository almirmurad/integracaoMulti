<?php
namespace src\factories;

use src\contracts\ErpFormattersInterface;
use src\exceptions\WebhookReadErrorException;
use src\formatters\NasajonFormatter;
use src\formatters\OmieFormatter;

class ErpFormatterFactory
{
    public static function create(array $args): ErpFormattersInterface
    {
        
        $erp = $args['user']['erp_name'];
        $erpBases = $args['Tenancy']['erp_bases'];
        $appk = $args['body']['appKey'] ?? null;
       
        return match (strtolower($erp)) {
            'omie' => new OmieFormatter($appk, $erpBases),
            'nasajon'=> new NasajonFormatter($erpBases),
            //'senior' => new SeniorFormatter(),
            default => throw new WebhookReadErrorException("ERP {$erp} não suportado")
        };

   }
}
