<?php
use core\Router;
use src\middlewares\AuthenticateMiddleware;
use src\middlewares\LoadCustomFieldsMiddleware;
use src\middlewares\RequestMiddleware;

return [
    'get' => [
        //api/users
        '/api/users' => [
            'callback' => 'ApiController@listApiUsers',
            'middlewares' => [AuthenticateMiddleware::class]
        ],
        //api/users
        '/logout' => [
            'callback' => 'LoginController@signout',
            'middlewares' => [AuthenticateMiddleware::class]
        ],
        //api/tenancy/getById
        '/api/tenancy/getTenancyById/{id}' => [
            'callback' => 'TenancyController@allInfoUserApi',
            'middlewares' => [AuthenticateMiddleware::class] // Middleware de autenticação
        ]
    ],
    'post' => [
        // login
        '/login'=>[
            'callback' => 'LoginController@signinAction',
            'middlewares' => [] // Nenhum middleware
        ],
        // /api/tenancy/add
        '/api/tenancy/add' => [
            'callback' => 'TenancyController@createNewTenancyAction',
            'middlewares' => [AuthenticateMiddleware::class] 
        ],
        // /api/erp/add
        '/api/erp/add' => [
            'callback' => 'TenancyController@createNewAppErp',
            'middlewares' => [AuthenticateMiddleware::class] 
        ],
        // /api/ploomes/add
        '/api/ploomes/add' => [
            'callback' => 'TenancyController@createNewAppPloomes',
            'middlewares' => [AuthenticateMiddleware::class]
        ],
        // /api/users/add
        '/api/users/add' => [
            'callback' => 'UserController@addUserAction',
            'middlewares' => [AuthenticateMiddleware::class]
        ],
        // /api/vhost/add
        '/api/vhost/add' => [
            'callback' => 'TenancyController@createVhostRabbitMQ',
            'middlewares' => [AuthenticateMiddleware::class]
        ],

        //rotas do sistema
        //contacts ploomes clientes Omie
        //https://gamatermic.bicorp.online/public/ploomesContacts
        '/ploomesContacts'=>[
            'callback' => 'ContactController@ploomesContacts', //Novo cliente no ploomes
            'middlewares' => [RequestMiddleware::class]
        ],
        //https://gamatermic.bicorp.online/public/omieClients
        '/erpClients'=>[
            'callback' => 'ContactController@erpClients', //Novo cliente no omie
            'middlewares' => [RequestMiddleware::class]
        ],
        '/processNewContact'=>[
            'callback' => 'ContactController@processNewContact', //inicia processo
            'middlewares' => [RequestMiddleware::class, LoadCustomFieldsMiddleware::class]
        ],
        //products
        '/erpProducts'=>[
            'callback' => 'ProductController@erpProducts', //Novo produto no Omie
            'middlewares' => [RequestMiddleware::class,]
        ],
        '/ploomesProducts'=>[
            'callback' => 'ProductController@ploomesProducts', //Novo produto no ploomes
            'middlewares' => [RequestMiddleware::class,]
        ],
        '/processNewProduct'=>[
            'callback' => 'ProductController@processNewProduct', //inicia o processo
            'middlewares' => [RequestMiddleware::class, LoadCustomFieldsMiddleware::class]
        ],
        //services
        '/erpServices'=>[
            'callback' => 'ServiceController@erpServices', //Novo serviço no omie
            'middlewares' => [RequestMiddleware::class,]
        ],
        '/processNewService'=>[
            'callback' => 'ServiceController@processNewService', //inicia processo
            'middlewares' => [RequestMiddleware::class, LoadCustomFieldsMiddleware::class]
        ],
        //Orders
        '/ploomesOrder'=>[
            'callback' => 'OrderController@ploomesOrder', //novo pedido no ploomes
            'middlewares' => [RequestMiddleware::class,]
        ],
        '/processNewOrder'=>[
            'callback' => 'OrderController@processNewOrder', //inicia processo
            'middlewares' => [RequestMiddleware::class, LoadCustomFieldsMiddleware::class]
        ],
        //Invoices NFE
        '/invoiceIssue'=>[
            'callback' => 'InvoicingController@invoiceIssue', //nota fiscal emitida
            'middlewares' => []
        ],
        '/processNewInvoice'=>[
            'callback' => 'InvoicingController@processNewInvoice', //inicia processo
            'middlewares' => []
        ],
        //financeiro ERP X Ploomes
        '/erpFinancial'=>[
            'callback' => 'FinancialController@financialIssued', //inicia processo
            'middlewares' => [RequestMiddleware::class]
        ],
        '/processNewFinancial'=>[
            'callback' => 'FinancialController@processNewFinancial', //inicia processo
            'middlewares' => [RequestMiddleware::class, LoadCustomFieldsMiddleware::class]
        ],
        // //Interactions
        '/newInteraction'=>[
            'callback' => 'InteractionController@createInteraction', //nova interação
            'middlewares' => []
        ]            
    ]
];