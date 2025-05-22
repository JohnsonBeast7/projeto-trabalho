<?php
// Mostrar todos os erros
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
    echo "Falha ao conectar: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
    exit();
} else {
    echo "Conectado com sucesso!<br>";
}

// Dados
$nome = "João";
$email = "joao@email.com";

// Inserção
$sql = "INSERT INTO usuario (nome, email) VALUES (?, ?)";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    echo "Erro na preparação: " . $mysqli->error;
    exit();
}

$stmt->bind_param("ss", $nome, $email);

if ($stmt->execute()) {
    echo "Usuário inserido com sucesso!";
} else {
    echo "Erro ao inserir: " . $stmt->error;
}

$stmt->close();
$mysqli->close();
?>
