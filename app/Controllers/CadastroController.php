<?php

class CadastroController {
    public function handle() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
            exit;
        }

        require_once __DIR__ . '/../../config/database.php';

        $usuario = trim($_POST['usuario_cadastro'] ?? '');
        $senha = $_POST['senha_cadastro'] ?? '';
        $senha_confirm = $_POST['senha_confirm_cadastro'] ?? '';
        $key = $_POST['key_cadastro'] ?? '';

        define('ACESS_KEY_FIXA', '72233720368547758072');

        header('Content-Type: application/json');

        if (!$usuario || !$senha || !$senha_confirm || !$key) {
            echo json_encode(['status' => 'error', 'message' => 'Preencha todos os campos.']);
            exit;
        }

        if ($senha !== $senha_confirm) {
            echo json_encode(['status' => 'error', 'message' => 'As senhas não coincidem.']);
            exit;
        }

        if (strlen($senha) < 8) {
            echo json_encode(['status' => 'error', 'message' => 'A senha deve ter no mínimo 8 caracteres.']);
            exit;
        }

        if ($key !== ACESS_KEY_FIXA) {
            echo json_encode(['status' => 'error', 'message' => 'Chave de acesso incorreta.']);
            exit;
        }

        // Verifica se já existe
        $mysqli = $GLOBALS['mysqli'];
        $stmt = $mysqli->prepare("SELECT id FROM login WHERE usuario = ? LIMIT 1");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Usuário já existe.']);
            exit;
        }

        $stmt->close();

        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("INSERT INTO login (usuario, senha) VALUES (?, ?)");
        $stmt->bind_param("ss", $usuario, $senha_hash);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Usuário cadastrado com sucesso!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Erro ao cadastrar usuário.']);
        }

        $stmt->close();
    }
}
