<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Garante que a sessão esteja ativa
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Controller.php';

$router = require __DIR__ . '/../routes/web.php';




$router->resolve(); // Executa o roteador
