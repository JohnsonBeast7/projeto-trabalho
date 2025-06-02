<?php
file_put_contents('/tmp/log.txt', date('Y-m-d H:i:s') . " — LOGIN executado\n", FILE_APPEND);

class LoginController {
    public function handle() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
            exit;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        header('Content-Type: application/json');

        $mysqli = require __DIR__ . '/../../config/database.php';

        $usuario = $_POST['usuario'] ?? '';
        $senha = $_POST['senha'] ?? '';
        $key_digitada = $_POST['key'] ?? '';

        $is_superadmin = strtolower($usuario) === 'superadmin';

if (!$is_superadmin && $key_digitada !== '72233720368547758072') {
    echo json_encode(['status' => 'error', 'message' => 'Chave de acesso incorreta.']);
    exit;
}


$sql = "SELECT * FROM login WHERE usuario = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows === 1) {
    $usuario_data = $result->fetch_assoc();

    if (!password_verify($senha, $usuario_data['senha'])) {
        echo json_encode(['status' => 'error', 'message' => 'Usuário ou senha incorretos.']);
        exit;
    }

    $_SESSION['id'] = $usuario_data['id'];
    $_SESSION['usuario'] = $usuario_data['usuario'];
    echo json_encode(['status' => 'success', 'redirect' => '/dashboard']);
    exit;
}


        $stmt->bind_param("ss", $usuario, $senha);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $usuario_data = $result->fetch_assoc();
            $_SESSION['id'] = $usuario_data['id'];
            $_SESSION['usuario'] = $usuario_data['usuario'];
            echo json_encode(['status' => 'success', 'redirect' => '/dashboard.php']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Usuário ou senha incorretos.']);
        }

        exit;
    }
}
