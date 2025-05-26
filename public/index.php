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
// Este bloco será chamado pelo JavaScript para obter os dados mais recentes.
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

    // Lógica de processamento do formulário de LOGIN
    // Este bloco ainda processa submissões normais e não é afetado pela atualização AJAX da tabela de USUÁRIOS
    if (isset($_POST['usuario']) && isset($_POST['senha']) && isset($_POST['key'])) {

        $usuario = trim($_POST['usuario']);
        $senha_digitada = $_POST['senha'];
        $key_digitada = $_POST['key'];

        // Validações de campos vazios
        if (empty($usuario) || empty($senha_digitada) || empty($key_digitada)) {
            $erro_login = 'Por favor, preencha todos os campos para login.';
        } else {
            // LÓGICA DE EXCEÇÃO: Verifica se o usuário é a conta que não precisa da chave
            if ($usuario === USUARIO_EXCECAO_LOGIN) {
                $verificar_key = false; // Ignora a verificação da chave para este usuário
            } else {
                $verificar_key = true; // Para os outros, a chave é obrigatória
            }

            // Verifica a chave de acesso fixa primeiro, SE NECESSÁRIO
            if ($verificar_key && $key_digitada !== ACESS_KEY_FIXA) {
                $erro_login = 'Chave de acesso incorreta.';
            } else {
                // Se a chave estiver correta (ou se for o usuário de exceção), tenta a consulta no banco
                $sql_code = "SELECT id, usuario, senha FROM login WHERE usuario = ? LIMIT 1";
                $stmt = $mysqli->prepare($sql_code);

                if ($stmt === false) {
                    $erro_login = "Falha interna ao preparar a consulta de login: " . $mysqli->error;
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

                            header("Location: dashboard.php"); // Redireciona para o dashboard em caso de sucesso
                            exit();
                        } else {
                            $erro_login = 'Usuário ou senha incorretos.';
                        }
                    } else {
                        $erro_login = 'Usuário ou senha incorretos.';
                    }
                    $stmt->close();
                }
            }
        }
    }
    // Lógica de processamento do formulário de CADASTRO (agora preparado para AJAX)
    else if (isset($_POST['usuario_cadastro']) && isset($_POST['senha_cadastro']) && isset($_POST['senha_confirm_cadastro']) && isset($_POST['key_cadastro'])) {

        $usuario_cadastro = trim($_POST['usuario_cadastro']);
        $senha_cadastro = $_POST['senha_cadastro'];
        $senha_confirm_cadastro = $_POST['senha_confirm_cadastro'];
        $key_cadastro_digitada = $_POST['key_cadastro'];

        // Flag para identificar se a requisição é AJAX (útil para decidir o tipo de resposta)
        $is_ajax_request = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        // Prepara para retornar JSON se for uma requisição AJAX
        if ($is_ajax_request) {
            header('Content-Type: application/json');
        }

        // Validações de campos
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
    // Não setar erro_login aqui, pois é um erro de carregamento da tabela, não de login.
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
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
            <tbody id="user-table-body"> <?php if (!empty($dados_usuarios_initial)): ?>
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
            <form id="cadastro-form" action="" method="POST"> <label for="nome-cadastro">Usuário</label>
                <input type="text" id="nome-cadastro" name="usuario_cadastro" autocomplete="off" maxlength="15" placeholder="Seu usuário" value="<?php echo htmlspecialchars($usuario_cadastro); ?>" required >

                <label for="senha-cadastro">Senha</label>
                <input type="password" id="senha-cadastro" name="senha_cadastro" minlength="8" autocomplete="off" placeholder="Sua senha" required >
                <label for="senha-cadastro-confirm">Repita a Senha</label>
                <input type="password" id="senha-cadastro-confirm" name="senha_confirm_cadastro" minlength="8" autocomplete="off" placeholder="Repita a senha" required >

                <label for="key-cadastro">KEY</label>
                <input type="text" id="key-cadastro" name="key_cadastro" autocomplete="off" maxlength="20" placeholder="Chave de acesso" value="<?php echo htmlspecialchars($key_cadastro_digitada); ?>" required>

                <button type="submit">Cadastrar</button>
            </form>
            <p id="cadastro-message" style="text-align: center; margin-top: 10px;"></p>
            <?php
            // Exibe as mensagens PHP SOMENTE se a requisição NÃO foi AJAX
            if (!empty($erro_cadastro) && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo '<p style="color: red; text-align: center; margin-top: 20px;">' . htmlspecialchars($erro_cadastro) . '</p>';
            }
            if (!empty($cadastro_sucesso) && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo '<p style="color: green; text-align: center; margin-top: 20px">' . htmlspecialchars($cadastro_sucesso) . '</p>';
            }
            ?>
        </div>
    </div>
    <div class="modal-overlay" id="login-modal-overlay">
        <div class="modal-content">
            <button class="close-modal-btn" id="close-login-modal-btn">&times;</button>
            <h2>Login de Administrador</h2>
            <form action="" method="POST">
                <label for="usuario-login">Usuário</label>
                <input type="text" id="usuario-login" name="usuario" autocomplete="off" placeholder="Seu usuário" value="<?php echo htmlspecialchars($usuario); ?>" required>

                <label for="senha-login">Senha</label>
                <input type="password" id="senha-login" name="senha" autocomplete="off" placeholder="Sua senha" required>

                <label for="key-login">KEY</label>
                <input type="text" id="key-login" name="key" autocomplete="off" maxlength="20" placeholder="Chave de acesso" value="<?php echo htmlspecialchars($key_digitada); ?>" required>

                <button type="submit">Entrar</button>
            </form>
            <?php
            if (!empty($erro_login)) {
                echo '<p style="color: red; text-align: center;">' . htmlspecialchars($erro_login) . '</p>';
            }
            ?>
        </div>
    </div>

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

            // Novos elementos para o cadastro AJAX e atualização da tabela
            const cadastroForm = document.getElementById('cadastro-form');
            const cadastroMessage = document.getElementById('cadastro-message');
            const userTableBody = document.getElementById('user-table-body'); // O tbody da sua tabela principal

            // --- Código PHP para reabrir modais em caso de erro (ajustado para usar #cadastro-message) ---
            <?php
            // Ajustado para usar o elemento #cadastro-message para exibir mensagens PHP também,
            // caso o JS não tenha sido carregado ou a submissão não tenha sido AJAX.
            // A condição !isset($_SERVER['HTTP_X_REQUESTED_WITH']) garante que isso só aconteça
            // em uma carga de página completa, não em uma resposta AJAX.
            if (!empty($erro_cadastro) && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo 'cadastroModalOverlay.classList.add("active"); document.body.style.overflow = "hidden";';
                echo 'cadastroMessage.textContent = "' . htmlspecialchars($erro_cadastro) . '";';
                echo 'cadastroMessage.style.color = "red";';
            } else if (!empty($cadastro_sucesso) && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo 'cadastroModalOverlay.classList.add("active"); document.body.style.overflow = "hidden";';
                echo 'cadastroMessage.textContent = "' . htmlspecialchars($cadastro_sucesso) . '";';
                echo 'cadastroMessage.style.color = "green";';
            }
            if (!empty($erro_login)) {
                echo 'loginModalOverlay.classList.add("active"); document.body.style.overflow = "hidden";';
            }
            ?>
            // --- Fim do Código PHP para reabrir modais ---


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
                cadastroMessage.textContent = ''; // Limpa a mensagem ao abrir
                cadastroForm.reset(); // Limpa o formulário ao abrir
            });

            closeCadastroModalBtn.addEventListener('click', function() {
                cadastroModalOverlay.classList.remove('active');
                document.body.style.overflow = 'auto';
                cadastroMessage.textContent = ''; // Limpa a mensagem ao fechar
                cadastroForm.reset(); // Limpa o formulário ao fechar
            });

            cadastroModalOverlay.addEventListener('click', function(event) {
                if (event.target === cadastroModalOverlay) {
                    cadastroModalOverlay.classList.remove('active');
                    document.body.style.overflow = 'auto';
                    cadastroMessage.textContent = ''; // Limpa a mensagem ao fechar
                    cadastroForm.reset(); // Limpa o formulário ao fechar
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
                        cadastroMessage.textContent = '';
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

            // --- Lógica AJAX para o formulário de Cadastro ---
            cadastroForm.addEventListener('submit', function(event) {
                event.preventDefault(); // Impede o envio padrão do formulário

                cadastroMessage.textContent = 'Cadastrando...'; // Feedback visual
                cadastroMessage.style.color = 'blue';

                const formData = new FormData(cadastroForm);

                fetch(cadastroForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest' // Indica ao PHP que é uma requisição AJAX
                    }
                })
                .then(response => {
                    if (!response.ok) { // Checa se a resposta HTTP foi bem-sucedida (ex: 200 OK)
                        throw new Error('Erro de rede ou no servidor: ' + response.statusText);
                    }
                    return response.json(); // Tenta parsear a resposta como JSON
                })
                .then(data => {
                    if (data.status === 'success') {
                        cadastroMessage.textContent = data.message;
                        cadastroMessage.style.color = 'green';
                        cadastroForm.reset(); // Limpa o formulário após o sucesso
                        updateUserTable(); // ATUALIZA A TABELA PRINCIPAL após o cadastro
                    } else {
                        cadastroMessage.textContent = data.message;
                        cadastroMessage.style.color = 'red';
                    }
                })
                .catch(error => {
                    console.error('Erro no AJAX de cadastro:', error);
                    cadastroMessage.textContent = 'Erro ao conectar com o servidor para cadastro. Tente novamente.';
                    cadastroMessage.style.color = 'red';
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
                                // Popula a tabela com os novos dados
                                data.data.forEach(user => {
                                    const row = userTableBody.insertRow();
                                    row.insertCell().textContent = user.nome;
                                    row.insertCell().textContent = user.email;
                                    row.insertCell().textContent = user.data_admissao;
                                    row.insertCell().textContent = user.criado_em;
                                    row.insertCell().textContent = user.atualizado_em;
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
                            // Opcional: exibir uma mensagem de erro na interface para o usuário
                        }
                    })
                    .catch(error => {
                        console.error('Erro na requisição AJAX da tabela:', error);
                        // Opcional: exibir uma mensagem de erro na interface para o usuário
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