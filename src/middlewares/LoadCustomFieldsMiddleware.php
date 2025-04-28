<?php

namespace src\middlewares;


use src\functions\CustomFieldsFunction;
use src\handlers\TenancyHandler;
use src\models\User;

class LoadCustomFieldsMiddleware extends Middleware
{
    public function handle($args, $next)
    {
        $ploomesBase = $args['Tenancy']['ploomes_bases'][0];
        
        if (!$ploomesBase) {
            http_response_code(401);
            echo json_encode(['error' => 'APlicativo Ploomes não cadastrado para o usuário'.$args['Tenancy']['tenancies']['fantasy_name']]);
            exit;
        }      


        // Carrega os campos personalizados do Ploomes
        CustomFieldsFunction::loadCustomField($ploomesBase);    
         
        $_SESSION['contact_custom_fields'][$args['Tenancy']['tenancies']['id']] = CustomFieldsFunction::getCustomFields();


        // Passa a requisição para o próximo middleware ou controller
        return $next($args);
    }
}
