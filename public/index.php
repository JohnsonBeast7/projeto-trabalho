<?php
// 1. Início da sessão 
session_start();

// Conexão com o banco de dados
include('conexao.php');

// Chave de acesso fixa aqui.
define('ACESS_KEY_FIXA', '72233720368547758072'); 

// Usuario que nao precisa da chave
define('USUARIO_EXCECAO_LOGIN', 'superadmin'); 

// Inicializa as variáveis de mensagem
$erro_login = "";
$cadastro_sucesso = "";
$erro_cadastro = "";

// Variáveis de login e cadastro
$usuario = ''; 
$senha_digitada = ''; 
$key_digitada = ''; 

$usuario_cadastro = ''; 
$senha_cadastro = '';
$senha_confirm_cadastro = ''; 
$key_cadastro_digitada = ''; 


// Lógica para retornar apenas os usuários ativos via AJAX (Filtro)
if (isset($_GET['action']) && $_GET['action'] == 'get_users') {
    header('Content-Type: application/json');


    $search_name = $_GET['search_name'] ?? '';
    $search_email = $_GET['search_email'] ?? '';
    $filter_criado_de = $_GET['criado_de'] ?? '';
    $filter_criado_ate = $_GET['criado_ate'] ?? '';
    $filter_atualizado_de = $_GET['atualizado_de'] ?? '';
    $filter_atualizado_ate = $_GET['atualizado_ate'] ?? '';

    $sql_usuarios_ajax = "SELECT nome, email, data_admissao, criado_em, atualizado_em, situacao FROM tabela_nomes WHERE situacao = 'ativo'";
    $params = [];
    $types = '';

    // Filtros
    if (!empty($search_name)) {
        $sql_usuarios_ajax .= " AND nome LIKE ?";
        $params[] = '%' . $search_name . '%';
        $types .= 's';
    }
    if (!empty($search_email)) {
        $sql_usuarios_ajax .= " AND email LIKE ?";
        $params[] = '%' . $search_email . '%';
        $types .= 's';
    }
    if (!empty($filter_criado_de)) {
        $sql_usuarios_ajax .= " AND criado_em >= ?";
        $params[] = $filter_criado_de . ' 00:00:00'; // Adiciona hora para cobrir o dia inteiro
        $types .= 's';
    }
    if (!empty($filter_criado_ate)) {
        $sql_usuarios_ajax .= " AND criado_em <= ?";
        $params[] = $filter_criado_ate . ' 23:59:59'; // Adiciona hora para cobrir o dia inteiro
        $types .= 's';
    }
  
    if (!empty($filter_atualizado_de)) {
        $sql_usuarios_ajax .= " AND atualizado_em >= ?";
        $params[] = $filter_atualizado_de . ' 00:00:00';
        $types .= 's';
    }
    if (!empty($filter_atualizado_ate)) {
        $sql_usuarios_ajax .= " AND atualizado_em <= ?";
        $params[] = $filter_atualizado_ate . ' 23:59:59';
        $types .= 's';
    }

    $sql_usuarios_ajax .= " ORDER BY nome ASC";

    $stmt_ajax = $mysqli->prepare($sql_usuarios_ajax);

    if ($stmt_ajax === false) {
        error_log("Erro ao preparar consulta de usuários via AJAX: " . $mysqli->error);
        echo json_encode(['status' => 'error', 'message' => 'Erro interno ao carregar dados dos usuários via AJAX.']);
        exit();
    }

    // Bind dos parâmetros dinamicamente
    if (!empty($params)) {
        $stmt_ajax->bind_param($types, ...$params);
    }

    $stmt_ajax->execute();
    $result_usuarios_ajax = $stmt_ajax->get_result();

    $dados_usuarios_ajax = [];
    if ($result_usuarios_ajax) {
        if ($result_usuarios_ajax->num_rows > 0) {
            while ($row = $result_usuarios_ajax->fetch_assoc()) {
                $dados_usuarios_ajax[] = $row;
            }
        }
        echo json_encode(['status' => 'success', 'data' => $dados_usuarios_ajax]);
    } else {
        error_log("Erro ao buscar dados da tabela_nomes via AJAX: " . $mysqli->error);
        echo json_encode(['status' => 'error', 'message' => 'Erro ao carregar dados dos usuários via AJAX.']);
    }
    $stmt_ajax->close();
    exit();
}



// 3. O GRANDE BLOCO CONDICIONAL PARA REQUISIÇÕES POST (para login e cadastro via submissão normal ou AJAX)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $is_login_form_submit = isset($_POST['usuario']) && isset($_POST['senha']) && isset($_POST['key']);
    $is_cadastro_form_submit = isset($_POST['usuario_cadastro']) && isset($_POST['senha_cadastro']) && isset($_POST['senha_confirm_cadastro']) && isset($_POST['key_cadastro']);

    // Flag para identificar se a requisição é AJAX (útil para decidir o tipo de resposta)
    $is_ajax_request = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($is_login_form_submit) {
       
        if ($is_ajax_request) {
            header('Content-Type: application/json');
        }

        $usuario = trim($_POST['usuario']);
        $senha_digitada = $_POST['senha'];
        $key_digitada = $_POST['key'];

        $response_status = 'error';
        $response_message = '';
        $redirect_url = '';

        // Validações de campos vazios
        if (empty($usuario) || empty($senha_digitada) || empty($key_digitada)) {
            $response_message = 'Por favor, preencha todos os campos para login.';
        } else {
            // LÓGICA DE EXCEÇÃO: Verifica se o usuário é a conta que não precisa da chave
            if ($usuario === USUARIO_EXCECAO_LOGIN) {
                $verificar_key = false; // Ignora a verificação da chave para este usuário
            } else {
                $verificar_key = true; // Para os outros, a chave é obrigatória
            }

            // Verifica a chave de acesso fixa primeiro, SE NECESSÁRIO
            if ($verificar_key && $key_digitada !== ACESS_KEY_FIXA) {
                $response_message = 'Chave de acesso incorreta.';
            } else {
                // Se a chave estiver correta (ou se for o usuário de exceção), tenta a consulta no banco
                $sql_code = "SELECT id, usuario, senha FROM login WHERE usuario = ? LIMIT 1";
                $stmt = $mysqli->prepare($sql_code);

                if ($stmt === false) {
                    $response_message = "Falha interna ao preparar a consulta de login: " . $mysqli->error;
                    error_log("Erro de prepared statement (login): " . $mysqli->error);
                } else {
                    $stmt->bind_param("s", $usuario);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows == 1) {
                        $dados_usuario = $result->fetch_assoc();
                        if (password_verify($senha_digitada, $dados_usuario['senha'])) {
                            $_SESSION['id'] = $dados_usuario['id'];
                            $_SESSION['usuario'] = $dados_usuario['usuario'];

                            $response_status = 'success';
                            $response_message = 'Login realizado com sucesso!';
                            $redirect_url = 'dashboard.php'; // URL para redirecionar no JS
                        } else {
                            $response_message = 'Usuário ou senha incorretos.';
                        }
                    } else {
                        $response_message = 'Usuário ou senha incorretos.';
                    }
                    $stmt->close();
                }
            }
        }

        // Se for AJAX, sempre retorna JSON
        if ($is_ajax_request) {
            echo json_encode([
                'status' => $response_status,
                'message' => $response_message,
                'redirect' => $redirect_url
            ]);
            exit();
        } else {
            // Fallback para submissão normal (sem JS ou erro de JS)
            if ($response_status === 'success') {
                header("Location: " . $redirect_url);
                exit();
            } else {
                $erro_login = $response_message; // Seta a variável para o script PHP do Swal.fire
            }
        }
    }
    // Lógica de processamento do formulário de CADASTRO (já preparado para AJAX)
    else if ($is_cadastro_form_submit) {

        $usuario_cadastro = trim($_POST['usuario_cadastro']);
        $senha_cadastro = $_POST['senha_cadastro'];
        $senha_confirm_cadastro = $_POST['senha_confirm_cadastro'];
        $key_cadastro_digitada = $_POST['key_cadastro'];

        // Prepara para retornar JSON se for uma requisição AJAX
        if ($is_ajax_request) {
            header('Content-Type: application/json');
        }

        // Validações de campos (mesma lógica, apenas alterado para retornar JSON se $is_ajax_request)
        if (empty($usuario_cadastro) || empty($senha_cadastro) || empty($senha_confirm_cadastro) || empty($key_cadastro_digitada)) {
            $msg = "Por favor, preencha todos os campos do cadastro.";
            if ($is_ajax_request) { echo json_encode(['status' => 'error', 'message' => $msg]); exit(); } else { $erro_cadastro = $msg; }
        } else if ($senha_cadastro !== $senha_confirm_cadastro) {
            $msg = "As senhas não coincidem.";
            if ($is_ajax_request) { echo json_encode(['status' => 'error', 'message' => $msg]); exit(); } else { $erro_cadastro = $msg; }
        } else if (strlen($senha_cadastro) < 8) {
            $msg = "A senha deve ter no mínimo 8 caracteres.";
            if ($is_ajax_request) { echo json_encode(['status' => 'error', 'message' => $msg]); exit(); } else { $erro_cadastro = $msg; }
        } else if ($key_cadastro_digitada !== ACESS_KEY_FIXA) {
            $msg = 'Chave de acesso para cadastro incorreta.';
            if ($is_ajax_request) { echo json_encode(['status' => 'error', 'message' => $msg]); exit(); } else { $erro_cadastro = $msg; }
        } else {
            // Verifica se o nome de usuário já existe
            $check_user_sql = "SELECT id FROM login WHERE usuario = ? LIMIT 1";
            $stmt_check = $mysqli->prepare($check_user_sql);

            if ($stmt_check === false) {
                $msg = "Erro interno ao verificar usuário existente.";
                error_log("Erro de prepared statement (check user): " . $mysqli->error);
                if ($is_ajax_request) { echo json_encode(['status' => 'error', 'message' => $msg]); exit(); } else { $erro_cadastro = $msg; }
            } else {
                $stmt_check->bind_param("s", $usuario_cadastro);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();

                if ($result_check->num_rows > 0) {
                    $msg = "Este usuário já existe. Por favor, escolha outro.";
                    if ($is_ajax_request) { echo json_encode(['status' => 'error', 'message' => $msg]); exit(); } else { $erro_cadastro = $msg; }
                } else {
                    // Hash da senha antes de inserir no banco de dados
                    $senha_hash = password_hash($senha_cadastro, PASSWORD_DEFAULT);

                    // Insere o novo usuário
                    $insert_sql = "INSERT INTO login (usuario, senha) VALUES (?, ?)";
                    $stmt_insert = $mysqli->prepare($insert_sql);

                    if ($stmt_insert === false) {
                        $msg = "Erro interno ao preparar o cadastro de usuário.";
                        error_log("Erro de prepared statement (insert user): " . $mysqli->error);
                        if ($is_ajax_request) { echo json_encode(['status' => 'error', 'message' => $msg]); exit(); } else { $erro_cadastro = $msg; }
                    } else {
                        $stmt_insert->bind_param("ss", $usuario_cadastro, $senha_hash);
                        if ($stmt_insert->execute()) {
                            $msg = "Cadastro realizado com sucesso! Você já pode fazer login.";
                            if ($is_ajax_request) { echo json_encode(['status' => 'success', 'message' => $msg]); exit(); } else { $cadastro_sucesso = $msg; }
                        } else {
                            $msg = "Erro ao cadastrar: " . $stmt_insert->error;
                            error_log("Erro ao executar inserção de usuário: " . $stmt_insert->error);
                            if ($is_ajax_request) { echo json_encode(['status' => 'error', 'message' => $msg]); exit(); } else { $erro_cadastro = $msg; }
                        }
                        $stmt_insert->close();
                    }
                }
                $stmt_check->close();
            }
        }
    }
} 

// Consulta para buscar os dados da tabela 'tabela_nomes' para exibição inicial 
$sql_usuarios_initial = "SELECT nome, email, data_admissao, criado_em, atualizado_em, situacao FROM tabela_nomes WHERE situacao = 'ativo' ORDER BY nome ASC";
$result_usuarios_initial = $mysqli->query($sql_usuarios_initial);

$dados_usuarios_initial = [];
if ($result_usuarios_initial) {
    if ($result_usuarios_initial->num_rows > 0) {
        while ($row = $result_usuarios_initial->fetch_assoc()) {
            $dados_usuarios_initial[] = $row;
        }
    }
} else {
    error_log("Erro ao buscar dados iniciais da tabela_nomes: " . $mysqli->error);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <title>Sistema de Usuários</title>
</head>
<body>
    <header id="main-header">
        <img src="johnson_logo_transparent.png" alt="Logo"> <h1>Sistema de Usuários</h1>
        <div class="header-buttons">
            <button id="open-cadastro-modal-btn">Cadastro</button>
            <button id="open-login-modal-btn">Login</button>
            <button id="open-filter-modal-btn">Filtrar Usuários</button>
        </div>
    </header>
    <main>
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Data de admissão</th>
                    <th>Data e hora do cadastro</th>
                    <th>Data e hora da atualização</th>
                    <th>Situação</th>
                </tr>
            </thead>
            <tbody id="user-table-body">
                <?php if (!empty($dados_usuarios_initial)): ?>
                    <?php foreach ($dados_usuarios_initial as $usuario_item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($usuario_item['nome']); ?></td>
                            <td><?php echo htmlspecialchars($usuario_item['email']); ?></td>
                            <td><?php echo htmlspecialchars($usuario_item['data_admissao']); ?></td>
                            <td><?php echo htmlspecialchars($usuario_item['criado_em']); ?></td>
                            <td><?php echo htmlspecialchars($usuario_item['atualizado_em']); ?></td>
                            <td><?php echo htmlspecialchars($usuario_item['situacao']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">Nenhum usuário ativo encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>

    <div class="modal-overlay" id="cadastro-modal-overlay">
        <div class="modal-content">
            <button class="close-modal-btn" id="close-cadastro-modal-btn">&times;</button>
            <h2>Cadastro de Administrador</h2>
            <form id="cadastro-form" action="" method="POST">
                <label for="nome-cadastro">Usuário</label>
                <input type="text" id="nome-cadastro" name="usuario_cadastro" autocomplete="off" maxlength="15" placeholder="Seu usuário" value="<?php echo htmlspecialchars($usuario_cadastro); ?>" required >

                <label for="senha-cadastro">Senha</label>
                <input type="password" id="senha-cadastro" name="senha_cadastro" minlength="8" autocomplete="off" placeholder="Sua senha" required >
                <label for="senha-cadastro-confirm">Repita a Senha</label>
                <input type="password" id="senha-cadastro-confirm" name="senha_confirm_cadastro" minlength="8" autocomplete="off" placeholder="Repita a senha" required >

                <label for="key-cadastro">KEY</label>
                <input type="text" id="key-cadastro" name="key_cadastro" autocomplete="off" maxlength="20" placeholder="Chave de acesso" value="<?php echo htmlspecialchars($key_cadastro_digitada); ?>" required>

                <button type="submit">Cadastrar</button>
            </form>
            <?php
            // Mensagens de erro ou sucesso
            if (!empty($erro_cadastro) && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo '<script>
                        Swal.fire({
                            icon: "error",
                            title: "Erro de Cadastro!",
                            text: "' . htmlspecialchars($erro_cadastro) . '",
                            confirmButtonText: "Ok"
                        });
                      </script>';
            }
            if (!empty($cadastro_sucesso) && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo '<script>
                        Swal.fire({
                            icon: "success",
                            title: "Sucesso!",
                            text: "' . htmlspecialchars($cadastro_sucesso) . '",
                            confirmButtonText: "Ok"
                        });
                      </script>';
            }
           
            if (!empty($erro_login) && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                 echo '<script>
                        Swal.fire({
                            icon: "error",
                            title: "Erro de Login!",
                            text: "' . htmlspecialchars($erro_login) . '",
                            confirmButtonText: "Ok"
                        });
                      </script>';
            }
            ?>
        </div>
    </div>
    <div class="modal-overlay" id="login-modal-overlay">
        <div class="modal-content">
            <button class="close-modal-btn" id="close-login-modal-btn">&times;</button>
            <h2>Login de Administrador</h2>
            <form id="login-form" action="" method="POST">
                <label for="usuario-login">Usuário</label>
                <input type="text" id="usuario-login" name="usuario" autocomplete="off" placeholder="Seu usuário" value="<?php echo htmlspecialchars($usuario); ?>" required>

                <label for="senha-login">Senha</label>
                <input type="password" id="senha-login" name="senha" autocomplete="off" placeholder="Sua senha" required>

                <label for="key-login">KEY</label>
                <input type="text" id="key-login" name="key" autocomplete="off" maxlength="20" placeholder="Chave de acesso" value="<?php echo htmlspecialchars($key_digitada); ?>" required>

                <button type="submit">Entrar</button>
            </form>
            </div>
    </div>

    <div class="modal-overlay" id="filter-modal-overlay">
        <div class="modal-content">
            <button class="close-modal-btn" id="close-filter-modal-btn">&times;</button>
            <h2>Filtrar Usuários</h2>
            <div class="modal-filter-section">
                <label for="modal-search-name">Nome:</label>
                <input type="text" id="modal-search-name" placeholder="Buscar por nome" style="margin-bottom: 15px">

                <label for="modal-search-email">E-mail:</label>
                <input type="text" id="modal-search-email" placeholder="Buscar por e-mail" style="margin-bottom: 15px">

                <label for="modal-filter-criado-de">Cadastro (De):</label>
                <input type="date" id="modal-filter-criado-de" style="margin-bottom: 15px">

                <label for="modal-filter-criado-ate">Cadastro (Até):</label>
                <input type="date" id="modal-filter-criado-ate" style="margin-bottom: 15px">

                <label for="modal-filter-atualizado-de">Atualização (De):</label>
                <input type="date" id="modal-filter-atualizado-de" style="margin-bottom: 15px">

                <label for="modal-filter-atualizado-ate">Atualização (Até):</label>
                <input type="date" id="modal-filter-atualizado-ate" style="margin-bottom: 15px">

                <div class="modal-filter-buttons">
                    <button id="modal-apply-filters-btn">Aplicar Filtros</button>
                    <button id="modal-clear-filters-btn">Limpar Filtros</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    <script>
        // Checa se o DOM está pronto
        document.addEventListener('DOMContentLoaded', function() {
            const header = document.getElementById('main-header');
            const scrollThreshold = 100;

            // Existing modal open/close buttons and overlays
            const openCadastroModalBtn = document.getElementById('open-cadastro-modal-btn');
            const cadastroModalOverlay = document.getElementById('cadastro-modal-overlay');
            const closeCadastroModalBtn = document.getElementById('close-cadastro-modal-btn');

            const openLoginModalBtn = document.getElementById('open-login-modal-btn');
            const loginModalOverlay = document.getElementById('login-modal-overlay');
            const closeLoginModalBtn = document.getElementById('close-login-modal-btn');

            // Filtro modal
            const openFilterModalBtn = document.getElementById('open-filter-modal-btn');
            const filterModalOverlay = document.getElementById('filter-modal-overlay');
            const closeFilterModalBtn = document.getElementById('close-filter-modal-btn');

            // Elementos da tabela e formulários
            const cadastroForm = document.getElementById('cadastro-form');
            const loginForm = document.getElementById('login-form');
            const userTableBody = document.getElementById('user-table-body');

            // Filtro - inputs e botões
            const modalSearchNameInput = document.getElementById('modal-search-name');
            const modalSearchEmailInput = document.getElementById('modal-search-email');
            const modalFilterCriadoDeInput = document.getElementById('modal-filter-criado-de');
            const modalFilterCriadoAteInput = document.getElementById('modal-filter-criado-ate');
            const modalFilterAtualizadoDeInput = document.getElementById('modal-filter-atualizado-de');
            const modalFilterAtualizadoAteInput = document.getElementById('modal-filter-atualizado-ate');
            const modalApplyFiltersBtn = document.getElementById('modal-apply-filters-btn');
            const modalClearFiltersBtn = document.getElementById('modal-clear-filters-btn');


                     /**
             * Formata a data e hora (AAAA-MM-DD HH:MM:SS) pra DD/MM/AAAA HH:MM:SS.
             * Mostra o fuso-horário correto do usuário
              @param {string} dateTimeString 
             *@returns {string} 
             */
            function formatDateTime(dateTimeString) {
                if (!dateTimeString) return '';

               
                const date = new Date(dateTimeString + 'Z');

                if (isNaN(date.getTime())) { 
                    console.warn('formatDateTime: Invalid date/time detected:', dateTimeString);
                    return dateTimeString;
                }

               
                return date.toLocaleString('pt-BR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false 
                });
            }

            /**
             * Formats a date string (YYYY-MM-DD) to DD/MM/AAAA manually
             * to avoid timezone shifts for pure date strings.
             *
             * @param {string} dateString The MySQL date string (YYYY-MM-DD).
             * @returns {string} The formatted date or the original string if invalid.
             */
            function formatDate(dateString) {
                if (!dateString) return '';

               
                const parts = dateString.split('-');
                if (parts.length === 3) {
                    const year = parts[0];
                    const month = parts[1];
                    const day = parts[2];
                    return `${day}/${month}/${year}`;
                } else {
                    console.warn('formatDate: Unexpected date format detected, returning original:', dateString);
                    return dateString; 
                }
            }

            // Efeito no header quando a página rola
            window.addEventListener('scroll', function() {
                if (window.scrollY > scrollThreshold) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            });

            // --- Cadastro Modal ---
            openCadastroModalBtn.addEventListener('click', function() {
                cadastroModalOverlay.classList.add('active');
                document.body.style.overflow = 'hidden';
                cadastroForm.reset();
            });

            closeCadastroModalBtn.addEventListener('click', function() {
                cadastroModalOverlay.classList.remove('active');
                document.body.style.overflow = 'auto';
                cadastroForm.reset();
            });

            cadastroModalOverlay.addEventListener('click', function(event) {
                if (event.target === cadastroModalOverlay) {
                    cadastroModalOverlay.classList.remove('active');
                    document.body.style.overflow = 'auto';
                    cadastroForm.reset();
                }
            });

            // --- Login Modal ---
            openLoginModalBtn.addEventListener('click', function() {
                loginModalOverlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            });

            closeLoginModalBtn.addEventListener('click', function() {
                loginModalOverlay.classList.remove('active');
                document.body.style.overflow = 'auto';
            });

            loginModalOverlay.addEventListener('click', function(event) {
                if (event.target === loginModalOverlay) {
                    loginModalOverlay.classList.remove('active');
                    document.body.style.overflow = 'auto';
                }
            });

            // --- Filtro Modal ---
            openFilterModalBtn.addEventListener('click', function() {
                filterModalOverlay.classList.add('active');
                document.body.style.overflow = 'hidden';
                modalSearchNameInput.value = localStorage.getItem('filter_search_name') || '';
                modalSearchEmailInput.value = localStorage.getItem('filter_search_email') || '';
                modalFilterCriadoDeInput.value = localStorage.getItem('filter_criado_de') || '';
                modalFilterCriadoAteInput.value = localStorage.getItem('filter_criado_ate') || '';
                modalFilterAtualizadoDeInput.value = localStorage.getItem('filter_atualizado_de') || '';
                modalFilterAtualizadoAteInput.value = localStorage.getItem('filter_atualizado_ate') || '';
            });

            closeFilterModalBtn.addEventListener('click', function() {
                filterModalOverlay.classList.remove('active');
                document.body.style.overflow = 'auto';
            });

            filterModalOverlay.addEventListener('click', function(event) {
                if (event.target === filterModalOverlay) {
                    filterModalOverlay.classList.remove('active');
                    document.body.style.overflow = 'auto';
                }
            });

            // Fechamento de modais com ESC 
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    if (cadastroModalOverlay.classList.contains('active')) {
                        cadastroModalOverlay.classList.remove('active');
                        document.body.style.overflow = 'auto';
                        cadastroForm.reset();
                    }
                    if (loginModalOverlay.classList.contains('active')) {
                        loginModalOverlay.classList.remove('active');
                        document.body.style.overflow = 'auto';
                    }
                    if (filterModalOverlay.classList.contains('active')) {
                        filterModalOverlay.classList.remove('active');
                        document.body.style.overflow = 'auto';
                    }
                }
            });

            // Validação da KEY 
            const keyInputs = document.querySelectorAll('input[name="key"], input[name="key_cadastro"]');
            keyInputs.forEach(function(input) {
                input.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
                input.addEventListener('keydown', function(event) {
                    if (event.key === 'Backspace' || event.key === 'Delete' || event.key === 'Tab' ||
                        event.key === 'Enter' || event.key.startsWith('Arrow') || event.ctrlKey || event.metaKey) {
                        return;
                    }
                    if (!/^\d$/.test(event.key)) {
                        event.preventDefault();
                    }
                });
            });

            // Lógica do cadastro
            cadastroForm.addEventListener('submit', function(event) {
                event.preventDefault();


                const formData = new FormData(cadastroForm);

                fetch(cadastroForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erro de rede ou no servidor: ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    Swal.close();

                    if (data.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Sucesso!',
                            text: data.message,
                            confirmButtonText: 'Ok'
                        });
                        cadastroForm.reset();
                        updateUserTable();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro!',
                            text: data.message,
                            confirmButtonText: 'Ok'
                        });
                    }
                })
                .catch(error => {
                    Swal.close();
                    console.error('Erro no AJAX de cadastro:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro de Conexão!',
                        text: 'Erro ao conectar com o servidor para cadastro. Tente novamente.',
                        confirmButtonText: "Ok"
                    });
                });
            });

            // --- AJAX Logic for Login Form (fully AJAX with SweetAlert2) ---
            loginForm.addEventListener('submit', function(event) {
                event.preventDefault(); // Prevent default form submission

                Swal.fire({
                    title: 'Entrando...',
                    text: 'Verificando suas credenciais.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const formData = new FormData(loginForm);

                const fetchPromise = fetch(loginForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest' // Indicate to PHP it's an AJAX request
                    }
                });

                const minDisplayTimePromise = new Promise(resolve => {
                    setTimeout(resolve, 2000); // Ensure SweetAlert is visible for at least 2 seconds
                });

                Promise.all([fetchPromise, minDisplayTimePromise])
                .then(results => {
                    const response = results[0]; // The first promise is the fetch response

                    // Process server response
                    if (response.headers.get('Content-Type') && response.headers.get('Content-Type').includes('application/json')) {
                        return response.json();
                    } else if (response.redirected) {
                        return { status: 'redirect', url: response.url };
                    } else {
                        throw new Error('Resposta inesperada do servidor no login.');
                    }
                })
                .then(data => {
                    Swal.close(); // Close loading SweetAlert (now guaranteed after 2s)

                    if (data.status === 'success') {
                        // NO SUCCESS SWEETALERT HERE, JUST REDIRECT
                        window.location.href = data.redirect; // Redirect immediately
                    } else if (data.status === 'redirect') {
                        window.location.href = data.url;
                    }
                    else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro!',
                            text: data.message,
                            confirmButtonText: 'Ok'
                        });
                        loginModalOverlay.classList.add('active'); // Keep login modal open
                        document.body.style.overflow = 'hidden';
                    }
                })
                .catch(error => {
                    // Ensure SweetAlert is closed even on error
                    Swal.close();
                    console.error('Erro no AJAX de login:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro de Conexão!',
                        text: 'Erro ao conectar com o servidor. Tente novamente.',
                        confirmButtonText: 'Ok'
                    });
                    loginModalOverlay.classList.add('active'); // Keep login modal open
                    document.body.style.overflow = 'hidden';
                });
            });


            // --- Function to fetch and update the active users table ---
            function updateUserTable() {
                // MUDANÇA AQUI: Obtém os valores dos novos campos de filtro separados
                const searchName = modalSearchNameInput.value;
                const searchEmail = modalSearchEmailInput.value;
                const criadoDe = modalFilterCriadoDeInput.value;
                const criadoAte = modalFilterCriadoAteInput.value;
                const atualizadoDe = modalFilterAtualizadoDeInput.value;
                const atualizadoAte = modalFilterAtualizadoAteInput.value;

                // MUDANÇA AQUI: Armazena os valores dos filtros separados no localStorage
                localStorage.setItem('filter_search_name', searchName);
                localStorage.setItem('filter_search_email', searchEmail);
                localStorage.setItem('filter_criado_de', criadoDe);
                localStorage.setItem('filter_criado_ate', criadoAte);
                localStorage.setItem('filter_atualizado_de', atualizadoDe);
                localStorage.setItem('filter_atualizado_ate', atualizadoAte);

                let queryParams = [];
                // MUDANÇA AQUI: Adiciona parâmetros de nome e e-mail separados
                if (searchName) queryParams.push('search_name=' + encodeURIComponent(searchName));
                if (searchEmail) queryParams.push('search_email=' + encodeURIComponent(searchEmail));
                if (criadoDe) queryParams.push('criado_de=' + encodeURIComponent(criadoDe));
                if (criadoAte) queryParams.push('criado_ate=' + encodeURIComponent(criadoAte));
                if (atualizadoDe) queryParams.push('atualizado_de=' + encodeURIComponent(atualizadoDe));
                if (atualizadoAte) queryParams.push('atualizado_ate=' + encodeURIComponent(atualizadoAte));

                const url = '?action=get_users' + (queryParams.length > 0 ? '&' + queryParams.join('&') : '');

                fetch(url)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erro de rede ou no servidor ao buscar a tabela: ' + response.statusText);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.status === 'success' && data.data) {
                            userTableBody.innerHTML = ''; // Clear current table body

                            if (data.data.length > 0) {
                                // Populate the table with new formatted data
                                data.data.forEach(user => {
                                    const row = userTableBody.insertRow();
                                    row.insertCell().textContent = user.nome;
                                    row.insertCell().textContent = user.email;
                                    row.insertCell().textContent = formatDate(user.data_admissao); // Format data_admissao
                                    row.insertCell().textContent = formatDateTime(user.criado_em); // Format criado_em
                                    row.insertCell().textContent = formatDateTime(user.atualizado_em); // Format atualizado_em
                                    row.insertCell().textContent = user.situacao;
                                });
                            } else {
                                // Message if no active users found
                                const row = userTableBody.insertRow();
                                const cell = row.insertCell();
                                cell.colSpan = 6; // Span all columns
                                cell.style.textAlign = 'center';
                                cell.textContent = 'Nenhum usuário ativo encontrado com os filtros aplicados.';
                            }
                        } else {
                            console.error('Erro ao buscar dados da tabela:', data.message || 'Status não é sucesso ou dados ausentes.');
                        }
                    })
                    .catch(error => {
                        console.error('Erro na requisição AJAX da tabela:', error);
                        // Just log to console, to avoid cluttering the interface with repeated alerts
                    });
            }

            // --- Add Listeners to filter fields and buttons (INSIDE THE MODAL) ---
            modalApplyFiltersBtn.addEventListener('click', function() {
                updateUserTable(); // The updateUserTable function already gets modal values
                filterModalOverlay.classList.remove('active'); // Close modal after applying filters
                document.body.style.overflow = 'auto';
            });

            modalClearFiltersBtn.addEventListener('click', function() {
                // MUDANÇA AQUI: Limpa os valores dos campos de nome e e-mail separados
                modalSearchNameInput.value = '';
                modalSearchEmailInput.value = '';
                modalFilterCriadoDeInput.value = '';
                modalFilterCriadoAteInput.value = '';
                modalFilterAtualizadoDeInput.value = '';
                modalFilterAtualizadoAteInput.value = '';
                // MUDANÇA AQUI: Limpa os filtros no localStorage também
                localStorage.removeItem('filter_search_name');
                localStorage.removeItem('filter_search_email');
                localStorage.removeItem('filter_criado_de');
                localStorage.removeItem('filter_criado_ate');
                localStorage.removeItem('filter_atualizado_de');
                localStorage.removeItem('filter_atualizado_ate');
                updateUserTable(); // Clear modal filters and update table
                // We don't close the modal here; the user might want to apply empty filters and stay in the modal
            });

            // --- Initialization: Retrieve filters from localStorage on page load
            // and apply them to the modal fields (and consequently to the table)
            // MUDANÇA AQUI: Carrega os valores dos filtros de nome e e-mail separados na inicialização
            modalSearchNameInput.value = localStorage.getItem('filter_search_name') || '';
            modalSearchEmailInput.value = localStorage.getItem('filter_search_email') || '';
            modalFilterCriadoDeInput.value = localStorage.getItem('filter_criado_de') || '';
            modalFilterCriadoAteInput.value = localStorage.getItem('filter_criado_ate') || '';
            modalFilterAtualizadoDeInput.value = localStorage.getItem('filter_atualizado_de') || '';
            modalFilterAtualizadoAteInput.value = localStorage.getItem('filter_atualizado_ate') || '';


            // Configure polling to continue updating the table periodically
            const INTERVALO_ATUALIZACAO_MS = 5000; // 5 seconds. Adjust as needed.

            // Call the update function once on page load
            updateUserTable();

            // Configure periodic calls to the update function
            setInterval(updateUserTable, INTERVALO_ATUALIZACAO_MS);
            // --- End of Polling Implementation ---

        });
    </script>
</body>
</html>