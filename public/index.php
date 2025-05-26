<?php
// 1. Início da sessão (sempre no topo e antes de qualquer saída para o navegador)
session_start();

// 2. Inclusão da conexão com o banco de dados
include('conexao.php'); // Certifique-se de que 'conexao.php' está configurado corretamente

// Define a chave de acesso fixa aqui.
// ATENÇÃO: Em um ambiente de produção real, essa chave NÃO DEVE estar hardcoded no código.
// Considere usar variáveis de ambiente do servidor, um arquivo de configuração externo
// que não seja acessível via web, ou um sistema de segredos.
define('ACESS_KEY_FIXA', '72233720368547758072'); // <<<<<<<< MUDE ESTA CHAVE PARA A SUA CHAVE REAL E FORTE

// Define o usuário que será a exceção no login (não precisará da KEY)
// MUDE 'superadmin' PARA O NOME REAL DA CONTA DE ADMINISTRADOR QUE NÃO PRECISARÁ DA KEY
define('USUARIO_EXCECAO_LOGIN', 'superadmin'); // Exemplo: 'meu_superadmin'

// Inicializa as variáveis de mensagem
// NOTA: Com AJAX completo, essas variáveis PHP são menos usadas para exibir mensagens diretas.
$erro_login = "";
$cadastro_sucesso = "";
$erro_cadastro = "";

// Inicialize as variáveis que serão usadas nos campos de input para evitar o erro "null given"
$usuario = ''; // Para o campo de login
$senha_digitada = ''; // Para o campo de login (não usado diretamente no value, mas boa prática)
$key_digitada = ''; // Para o campo de login (ainda será preenchido no formulário)

$usuario_cadastro = ''; // Para o campo de cadastro
$senha_cadastro = ''; // Para o campo de cadastro (não usado diretamente no value)
$senha_confirm_cadastro = ''; // Para o campo de cadastro (não usado diretamente no value)
$key_cadastro_digitada = ''; // Para o campo de cadastro


// --- NOVO BLOCO: Lógica para retornar apenas os usuários ativos via AJAX (para atualização da tabela principal) ---
if (isset($_GET['action']) && $_GET['action'] == 'get_users') {
    header('Content-Type: application/json'); // Define o cabeçalho para que o navegador saiba que é JSON

    $sql_usuarios_ajax = "SELECT nome, email, data_admissao, criado_em, atualizado_em, situacao FROM tabela_nomes WHERE situacao = 'ativo' ORDER BY nome ASC";
    $result_usuarios_ajax = $mysqli->query($sql_usuarios_ajax);

    $dados_usuarios_ajax = [];
    if ($result_usuarios_ajax) { // Verifica se a consulta foi bem-sucedida
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
    exit(); // IMPORTANTE: Finaliza a execução do script aqui para não renderizar o HTML completo.
}
// --- FIM DO NOVO BLOCO ---


// 3. O GRANDE BLOCO CONDICIONAL PARA REQUISIÇÕES POST (para login e cadastro via submissão normal ou AJAX)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $is_login_form_submit = isset($_POST['usuario']) && isset($_POST['senha']) && isset($_POST['key']);
    $is_cadastro_form_submit = isset($_POST['usuario_cadastro']) && isset($_POST['senha_cadastro']) && isset($_POST['senha_confirm_cadastro']) && isset($_POST['key_cadastro']);

    // Flag para identificar se a requisição é AJAX (útil para decidir o tipo de resposta)
    $is_ajax_request = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($is_login_form_submit) {
        // Prepara para retornar JSON se for uma requisição AJAX
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
} // FIM DO if ($_SERVER["REQUEST_METHOD"] == "POST")

// Consulta para buscar os dados da tabela 'tabela_nomes' para exibição inicial (quando a página carrega pela primeira vez)
// Esta consulta NÃO é afetada pelo AJAX, ela apenas popula a tabela na carga inicial da página.
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
            // Exibe as mensagens PHP SOMENTE se a requisição NÃO foi AJAX (fallback)
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
            // Para erros de login, também vamos usar SweetAlert2 como fallback (apenas para submissões não-AJAX)
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

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const header = document.getElementById('main-header');
            const scrollThreshold = 100;

            const openCadastroModalBtn = document.getElementById('open-cadastro-modal-btn');
            const cadastroModalOverlay = document.getElementById('cadastro-modal-overlay');
            const closeCadastroModalBtn = document.getElementById('close-cadastro-modal-btn');

            const openLoginModalBtn = document.getElementById('open-login-modal-btn');
            const loginModalOverlay = document.getElementById('login-modal-overlay');
            const closeLoginModalBtn = document.getElementById('close-login-modal-btn');

            // Elementos dos formulários
            const cadastroForm = document.getElementById('cadastro-form');
            const loginForm = document.getElementById('login-form');
            const userTableBody = document.getElementById('user-table-body');

            // --- Código PHP para reabrir modais em caso de erro (AGORA PODE SER REMOVIDO OU SIMPLIFICADO) ---
            <?php
            // Este bloco PHP agora é quase redundante para AJAX, mas mantido para fallback de submissão não-AJAX.
            // Para login, o Swal.fire será disparado pelo JS se for AJAX, ou pelo PHP se não for AJAX e houver erro.
            if (!empty($erro_login) && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo 'loginModalOverlay.classList.add("active"); document.body.style.overflow = "hidden";';
            }
            // As mensagens de cadastro/sucesso agora são tratadas pelo Swal.fire no PHP ou JS.
            ?>
            // --- Fim do Código PHP para reabrir modais ---

            /**
             * Formata uma string de data e hora (YYYY-MM-DD HH:MM:SS) para DD/MM/AAAA HH:MM:SS.
             * Esta função tenta corrigir o fuso horário assumindo que a string do MySQL
             * representa um horário em UTC (Tempo Universal Coordenado), para que o navegador
             * a converta corretamente para o fuso horário local do usuário.
             *
             * @param {string} dateTimeString A string de data e hora do MySQL.
             * @returns {string} A data e hora formatada ou a string original se inválida.
             */
            function formatDateTime(dateTimeString) {
                if (!dateTimeString) return '';

                // Adiciona 'Z' ao final da string para explicitamente dizer ao JavaScript
                // que esta data/hora está em UTC. O método `toLocaleString` então a converterá
                // para o fuso horário local do usuário para exibição.
                // Isso é essencial para corrigir o problema de "3 horas adiantadas".
                const date = new Date(dateTimeString + 'Z');
                
                if (isNaN(date.getTime())) { // Verifica se o objeto Date é inválido
                    console.warn('formatDateTime: Data/hora inválida detectada:', dateTimeString);
                    return dateTimeString;
                }

                // Usa toLocaleString para formatar a data/hora no fuso horário local do navegador (pt-BR)
                return date.toLocaleString('pt-BR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false // Formato 24h
                });
            }

            /**
             * Formata uma string de data (YYYY-MM-DD) para DD/MM/AAAA.
             * Trata a data como UTC para evitar problemas de "dia anterior" em fusos horários negativos.
             *
             * @param {string} dateString A string de data (YYYY-MM-DD) do MySQL.
             * @returns {string} A data formatada ou a string original se inválida.
             */
            function formatDate(dateString) {
                if (!dateString) return '';

                // Para strings de data (YYYY-MM-DD) sem a parte da hora, o `new Date()` pode ter
                // um comportamento inconsistente com fusos horários.
                // Adicionar 'T00:00:00Z' garante que a data seja interpretada como meia-noite UTC,
                // evitando problemas de fuso horário que poderiam mover a data para o dia anterior.
                const date = new Date(dateString + 'T00:00:00Z'); 
                
                if (isNaN(date.getTime())) {
                    console.warn('formatDate: Data inválida detectada:', dateString);
                    return dateString;
                }

                // Usa toLocaleDateString para formatar a data no fuso horário local do navegador (pt-BR)
                return date.toLocaleDateString('pt-BR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
            }

            // Header scroll effect
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

            // --- General Modal Close with ESC Key ---
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
                }
            });

            // --- KEY Input Validation (for both forms) ---
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

            // --- Lógica AJAX para o formulário de Cadastro (COM SWEETALERT2) ---
            cadastroForm.addEventListener('submit', function(event) {
                event.preventDefault();

                Swal.fire({
                    title: 'Processando...',
                    text: 'Aguarde enquanto o cadastro é realizado.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

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
                        updateUserTable(); // Atualiza a tabela após o cadastro
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
                        confirmButtonText: 'Ok'
                    });
                });
            });

            // --- Lógica AJAX para o formulário de Login (TOTALMENTE AJAX COM SWEETALERT2) ---
            loginForm.addEventListener('submit', function(event) {
                event.preventDefault(); // Impede o envio padrão do formulário

                Swal.fire({
                    title: 'Entrando...',
                    text: 'Verificando suas credenciais.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const formData = new FormData(loginForm);

                // Variável para armazenar a promessa do fetch
                const fetchPromise = fetch(loginForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest' // Indica ao PHP que é uma requisição AJAX
                    }
                });

                // Variável para armazenar a promessa do tempo mínimo de exibição do SweetAlert
                const minDisplayTimePromise = new Promise(resolve => {
                    setTimeout(resolve, 2000); // Garante que o SweetAlert fique visível por 2 segundos
                });

                // Usa Promise.all para esperar que AMBAS as promessas (fetch e tempo mínimo) sejam resolvidas
                Promise.all([fetchPromise, minDisplayTimePromise])
                .then(results => {
                    const response = results[0]; // A primeira promessa é a resposta do fetch

                    // Processa a resposta do servidor
                    if (response.headers.get('Content-Type') && response.headers.get('Content-Type').includes('application/json')) {
                        return response.json();
                    } else if (response.redirected) {
                        return { status: 'redirect', url: response.url };
                    } else {
                        throw new Error('Resposta inesperada do servidor no login.');
                    }
                })
                .then(data => {
                    Swal.close(); // Fecha o SweetAlert de carregamento (agora garantido após 2s)

                    if (data.status === 'success') {
                        // NENHUM SWEETALERT DE SUCESSO AQUI, APENAS REDIRECIONA
                        window.location.href = data.redirect; // Redireciona imediatamente
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
                        loginModalOverlay.classList.add('active'); // Mantém o modal de login aberto
                        document.body.style.overflow = 'hidden';
                    }
                })
                .catch(error => {
                    // Garante que o SweetAlert seja fechado mesmo em caso de erro
                    Swal.close();
                    console.error('Erro no AJAX de login:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro de Conexão!',
                        text: 'Erro ao conectar com o servidor. Tente novamente.',
                        confirmButtonText: 'Ok'
                    });
                    loginModalOverlay.classList.add('active'); // Mantém o modal de login aberto
                    document.body.style.overflow = 'hidden';
                });
            });


            // --- Função para buscar e atualizar a tabela de usuários ativos ---
            function updateUserTable() {
                fetch('?action=get_users') // Faz uma requisição AJAX para o novo endpoint PHP
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erro de rede ou no servidor ao buscar a tabela: ' + response.statusText);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.status === 'success' && data.data) {
                            userTableBody.innerHTML = ''; // Limpa o corpo da tabela atual

                            if (data.data.length > 0) {
                                // Popula a tabela com os novos dados formatados
                                data.data.forEach(user => {
                                    const row = userTableBody.insertRow();
                                    row.insertCell().textContent = user.nome;
                                    row.insertCell().textContent = user.email;
                                    row.insertCell().textContent = formatDate(user.data_admissao); // Formata data_admissao
                                    row.insertCell().textContent = formatDateTime(user.criado_em); // Formata criado_em
                                    row.insertCell().textContent = formatDateTime(user.atualizado_em); // Formata atualizado_em
                                    row.insertCell().textContent = user.situacao;
                                });
                            } else {
                                // Mensagem se não houver usuários ativos
                                const row = userTableBody.insertRow();
                                const cell = row.insertCell();
                                cell.colSpan = 6; // Ocupa todas as colunas
                                cell.style.textAlign = 'center';
                                cell.textContent = 'Nenhum usuário ativo encontrado.';
                            }
                        } else {
                            console.error('Erro ao buscar dados da tabela:', data.message || 'Status não é sucesso ou dados ausentes.');
                        }
                    })
                    .catch(error => {
                        console.error('Erro na requisição AJAX da tabela:', error);
                        // Apenas loga no console, para não poluir a interface com alertas repetidos
                    });
            }

            // --- Implementação do Polling: Atualiza a tabela periodicamente ---
            const INTERVALO_ATUALIZACAO_MS = 5000; // 5 segundos. Ajuste conforme necessário.

            // Chama a função de atualização uma vez ao carregar a página
            updateUserTable();

            // Configura a chamada periódica da função de atualização
            setInterval(updateUserTable, INTERVALO_ATUALIZACAO_MS);
            // --- Fim da Implementação do Polling ---

        });
    </script>
</body>
</html>