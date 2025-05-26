<?php
session_start();
// Redireciona se o usuário não estiver logado
if (!isset($_SESSION['id'])) {
    header("Location: index.php"); // Redireciona para index.php que tem o modal de login
    exit();
}
include('conexao.php');

// Validação dos dados de entrada
if (empty($_POST['nome']) || empty($_POST['email']) || empty($_POST['data_admissao']) || empty($_POST['situacao'])) {
    $_SESSION['mensagem_erro'] = "Por favor, preencha todos os campos do formulário para cadastrar um nome.";
    header("Location: dashboard.php");
    exit();
}

$nome = trim($_POST['nome']);
$email = trim($_POST['email']);
$data_admissao = $_POST['data_admissao'];
$situacao = $_POST['situacao'];
$agora = date('Y-m-d H:i:s');

// Validação adicional de formato de email e situação
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['mensagem_erro'] = "Formato de e-mail inválido.";
    header("Location: dashboard.php");
    exit();
}

if (!in_array($situacao, ['Ativo', 'Inativo'])) {
    $_SESSION['mensagem_erro'] = "Situação inválida. Escolha 'Ativo' ou 'Inativo'.";
    header("Location: dashboard.php");
    exit();
}

// USANDO PREPARED STATEMENTS PARA PREVENIR SQL INJECTION
$sql = "INSERT INTO tabela_nomes (nome, email, data_admissao, situacao, criado_em, atualizado_em)
        VALUES (?, ?, ?, ?, ?, ?)";

$stmt = $mysqli->prepare($sql);

if ($stmt === false) {
    // Logar o erro em vez de exibir para o usuário final
    error_log("Erro ao preparar a declaração de inserção de nome: " . $mysqli->error);
    $_SESSION['mensagem_erro'] = "Ocorreu um erro interno ao tentar salvar o nome. Tente novamente.";
    header("Location: dashboard.php");
    exit();
}

// "ssssss" indica que todos os 6 parâmetros são strings
$stmt->bind_param("ssssss", $nome, $email, $data_admissao, $situacao, $agora, $agora);

if ($stmt->execute()) {
    $_SESSION['mensagem_sucesso'] = "Nome cadastrado com sucesso!";
    header("Location: dashboard.php");
    exit(); // IMPORTANTE: Sempre use exit() após header()
} else {
    // Logar o erro
    error_log("Erro ao executar a inserção de nome: " . $stmt->error);
    // Mensagem mais genérica para o usuário final
    $_SESSION['mensagem_erro'] = "Erro ao cadastrar o nome. Por favor, tente novamente.";
    header("Location: dashboard.php");
    exit();
}

$stmt->close();
?>