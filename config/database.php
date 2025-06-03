<?php
// Mostrar todos os erros (apenas para desenvolvimento)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Autoload do Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad(); // Evita exceções se o .env estiver faltando

// Captura variáveis
$hostname      = $_ENV['DB_HOST'] ?? null;
$bancodedados  = $_ENV['DB_DATABASE'] ?? null;
$usuario       = $_ENV['DB_USERNAME'] ?? null;
$senha         = $_ENV['DB_PASSWORD'] ?? null;

// Validação simples
if (!$hostname || !$bancodedados || !$usuario || $senha === null) {
    die('Erro: Variáveis de ambiente de banco de dados não estão corretamente definidas.');
}

// Conexão
$mysqli = new mysqli($hostname, $usuario, $senha, $bancodedados);

// Verificação de erro na conexão
if ($mysqli->connect_errno) {
    die("Erro de conexão: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
}

// Charset
$mysqli->set_charset("utf8mb4");

// Retorna a instância
return $mysqli;

?>