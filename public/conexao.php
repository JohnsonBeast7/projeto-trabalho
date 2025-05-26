<?php
// Mostrar todos os erros (apenas para desenvolvimento)
// Em produção, comente ou remova as linhas abaixo e configure o PHP para logar erros.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


$hostname = "mariadb";
$bancodedados = "formulario_projeto";
$usuario = "root";
$senha = "root";

// Conexão
$mysqli = new mysqli($hostname, $usuario, $senha, $bancodedados);
if ($mysqli->connect_errno) {
    // Para produção, esta mensagem deveria ser mais genérica e o erro logado.
    echo "Falha ao conectar ao banco de dados: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
    exit();
}

// Opcional: Definir o charset para garantir que caracteres especiais sejam tratados corretamente
$mysqli->set_charset("utf8mb4");

?>