<?php
// app/Controllers/HomeController.php

require_once ROOT_PATH . '/app/Models/UserModel.php'; // Incluir o UserModel

class HomeController
{
    private $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        // Iniciar a sessão se ainda não estiver iniciada
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function index()
    {
        // Lógica para a página inicial (login)
        require ROOT_PATH . '/resources/views/home.php';
    }

    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $_POST['email'] ?? '';
            $senha = $_POST['senha'] ?? '';

            $user = $this->userModel->verifyLogin($email, $senha);

            if ($user) {
                // Login bem-sucedido
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['nome']; // Assumindo que você tem uma coluna 'nome'

                header('Location: /dashboard');
                exit();
            } else {
                // Login falhou
                $_SESSION['login_error'] = "Email ou senha incorretos.";
                header('Location: /'); // Redireciona de volta para a página de login
                exit();
            }
        } else {
            // Se tentar acessar /login diretamente via GET, redireciona para a home
            header('Location: /');
            exit();
        }
    }

    public function dashboard()
    {
        // Verifica se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['login_error'] = "Você precisa estar logado para acessar esta página.";
            header('Location: /'); // Redireciona para a página de login
            exit();
        }

        // Se estiver logado, busca os dados dos usuários para exibir na dashboard
        $users = $this->userModel->getAllUsers();

        // Inclui a view da dashboard, passando os dados
        require ROOT_PATH . '/resources/views/dashboard.php';
    }

    public function logout()
    {
        session_destroy();
        header('Location: /');
        exit();
    }
}