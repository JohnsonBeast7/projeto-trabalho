<?php
file_put_contents('/tmp/log.txt', date('Y-m-d H:i:s') . " — CADASTRO executado\n", FILE_APPEND);

class InserirNomeController {
    public function handle() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
            exit;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sessão expirada. Faça login novamente.']);
            exit;
        }

        $usuario_logado = $_SESSION['usuario'] ?? '';

        require_once __DIR__ . '/../../config/database.php';

        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $data_admissao = $_POST['data_admissao'] ?? '';
        $situacao = $_POST['situacao'] ?? '';
        $key_digitada = $_POST['key'] ?? '';

        if ($usuario_logado !== 'superadmin' && $key_digitada !== $_ENV['ACESS_KEY_FIXA']) {
            echo json_encode(['status' => 'error', 'message' => 'Chave de acesso incorreta.']);
            exit;
        }

        if (!$nome || !$email || !$data_admissao || !$situacao) {
            echo json_encode(['status' => 'error', 'message' => 'Preencha todos os campos.']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'E-mail inválido.']);
            exit;
        }

        if (!in_array($situacao, ['Ativo', 'Inativo'])) {
            echo json_encode(['status' => 'error', 'message' => 'Situação inválida.']);
            exit;
        }

        $agora = date('Y-m-d H:i:s');

        $sql = "INSERT INTO tabela_nomes (nome, email, data_admissao, situacao, criado_em, atualizado_em)
                VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $mysqli->prepare($sql);

        if (!$stmt) {
            error_log("Erro ao preparar: " . $mysqli->error);
            echo json_encode(['status' => 'error', 'message' => 'Erro interno ao preparar cadastro.']);
            exit;
        }

        $stmt->bind_param("ssssss", $nome, $email, $data_admissao, $situacao, $agora, $agora);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Administrador cadastrado com sucesso.']);
        } else {
            error_log("Erro ao cadastrar: " . $stmt->error);
            echo json_encode(['status' => 'error', 'message' => 'Erro ao cadastrar.']);
        }

        $stmt->close();
        exit;
    }
}
