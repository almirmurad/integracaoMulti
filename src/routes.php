<?php
use core\Router;
use src\middlewares\AuthenticateMiddleware;
use src\middlewares\LoadCustomFieldsMiddleware;
use src\middlewares\RequestMiddleware;

// $router = new Router();
// $router->get('/', 'HomeController@index');
// $router->get('/dashboard', 'DashboardController@index');
// $router->get('/login', 'LoginController@signin');
// $router->get('/logout', 'LoginController@signout'); 

// //permissões
// $router->get('/permissions', 'PermissionController@index');
// $router->get('/addPermissionGroup', 'PermissionController@addPermissionGroup');
// $router->get('/delGroupPermission/{id}', 'PermissionController@delGroupPermission');
// $router->post('/addPermissionGroupAction', 'PermissionController@addPermissionGroupAction');
// $router->get('/editPermissionGroup/{id}', 'PermissionController@editPermissionGroup');
// $router->post('/editPermissionGroupAction/{id}', 'PermissionController@editPermissionGroupAction');

// //Users
// $router->get('/users', 'UserController@listUsers');
// $router->get('/addUser','UserController@addUser');
// $router->post('/addUser','UserController@addUserAction');
// $router->get('/delUser/{id}', 'UserController@delUser');
// $router->get('/user/{id}/editUser', 'UserController@editUser');
// $router->post('/user/{id}/editUser','UserController@editUserAction');


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
        // /api/omie/add
        '/api/omie/add' => [
            'callback' => 'TenancyController@createNewAppOmie',
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
        '/omieClients'=>[
            'callback' => 'ContactController@omieClients', //Novo cliente no omie
            'middlewares' => [RequestMiddleware::class]
        ],
        '/processNewContact'=>[
            'callback' => 'ContactController@processNewContact', //inicia processo
            'middlewares' => [RequestMiddleware::class, LoadCustomFieldsMiddleware::class]
        ],
        //products
        //https://gamatermic.bicorp.online/public/ploomesProducts
        //https://gamatermic.bicorp.online/public/omieProducts
        '/omieProducts'=>[
            'callback' => 'ProductController@omieProducts', //Novo produto no Omie
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
        '/omieServices'=>[
            'callback' => 'ServiceController@omieServices', //Novo serviço no omie
            'middlewares' => [RequestMiddleware::class,]
        ],
        '/processNewService'=>[
            'callback' => 'ServiceController@processNewService', //inicia processo
            'middlewares' => [RequestMiddleware::class, LoadCustomFieldsMiddleware::class]
        ],
        //Orders
        //https://gamatermic.bicorp.online/public/ploomesOrder
        '/ploomesOrder'=>[
            'callback' => 'OrderController@ploomesOrder', //novo pedido no ploomes
            'middlewares' => []
        ],
        '/processNewOrder'=>[
            'callback' => 'OrderController@processNewOrder', //inicia processo
            'middlewares' => []
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
        // //Interactions
        '/newInteraction'=>[
            'callback' => 'InteractionController@createInteraction', //nova interação
            'middlewares' => []
        ],
        //Nasajon
        '/erpClients'=>[
            'callback' => 'ContactController@nasajonClients', //Novo cliente no nasajon
            'middlewares' => []
        ],
        '/erpProducts'=>[
            'callback' => 'ProductController@nasajonProducts', //Novo produto no nasajon
            'middlewares' => []
        ],
        // //Interactions
        '/erpServices'=>[
            'callback' => 'ServiceController@nasajonService', //Novo serviço no nasajon
            'middlewares' => []
        ],
            
    ]
];