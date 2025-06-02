<?php

require_once __DIR__ . '/../core/Router.php';
require_once __DIR__ . '/../app/Controllers/InserirNomeController.php';
require_once __DIR__ . '/../app/Controllers/LoginController.php';
require_once __DIR__ . '/../app/Controllers/CadastroController.php';

$router = new Router();

// ROTAS GET
$router->get('/', function () {
    include __DIR__ . '/../app/Views/home.php';
});

$router->get('/index', function () {
    include __DIR__ . '/../app/Views/home.php';
});

$router->get('/dashboard', function () {
    if (!isset($_SESSION['usuario'])) {
        header('Location: /home');
        exit;
    }
    include __DIR__ . '/../app/Views/dashboard.php';
});

$router->post('/dashboard', function () {
    include __DIR__ . '/../app/Views/dashboard.php';
});

// ROTAS POST
$router->post('/login', function () {
    $controller = new LoginController();
    $controller->handle();
});

$router->post('/inserir_nome', function () {
    $controller = new InserirNomeController();
    $controller->handle();
});

$router->post('/cadastrar', function () {
    $controller = new CadastroController();
    $controller->handle();
});

return $router;
