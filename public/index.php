<?php

use Dotenv\Dotenv;
use core\Router;

session_start();
require '../vendor/autoload.php';
require '../src/routes.php'; // Este arquivo deve definir as rotas corretamente.

// Carrega as variáveis de ambiente
$dotenv = Dotenv::createMutable('../', '.env');
$dotenv->load();

// Inicializa o Router
$router = new Router(); // Instanciando o Router correto

// Carrega as rotas no router
$routes = require '../src/routes.php';
$router->routes = $routes; // Atribuindo as rotas carregadas

// Executa o roteador, passando as rotas para o método run
$router->run($router->routes); // Chamando o método run
