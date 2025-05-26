<?php
// 1. Início da sessão (sempre no topo e antes de qualquer saída para o navegador)
session_start();

// 2. Inclusão da conexão com o banco de dados
include('conexao.php');

// Define a chave de acesso fixa aqui.
// ATENÇÃO: Em um ambiente de produção real, essa chave NÃO DEVE estar hardcoded no código.
// Considere usar variáveis de ambiente do servidor, um arquivo de configuração externo
// que não seja acessível via web, ou um sistema de segredos.
define('ACESS_KEY_FIXA', '72233720368547758072'); // <<<<<<<< MUDE ESTA CHAVE PARA A SUA CHAVE REAL E FORTE

// Define o usuário que será a exceção no login (não precisará da KEY)
// MUDE 'seu_usuario_excepcional' PARA O NOME REAL DA CONTA DE ADMINISTRADOR QUE NÃO PRECISARÁ DA KEY
define('USUARIO_EXCECAO_LOGIN', 'superadmin');

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


// 3. O GRANDE BLOCO CONDICIONAL PARA REQUISIÇÕES POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Lógica de processamento do formulário de LOGIN
    // Verifica se os campos do formulário de login foram enviados
    if (isset($_POST['usuario']) && isset($_POST['senha']) && isset($_POST['key'])) {

        $usuario = trim($_POST['usuario']); // Remove espaços em branco
        $senha_digitada = $_POST['senha'];
        $key_digitada = $_POST['key'];

        // Validações de campos vazios
        if (empty($usuario) || empty($senha_digitada) || empty($key_digitada)) {
            $erro_login = 'Por favor, preencha todos os campos para login.';
        } else {
            // LÓGICA DE EXCEÇÃO: Verifica se o usuário é a conta que não precisa da chave
            if ($usuario === USUARIO_EXCECAO_LOGIN) {
                // Se for o usuário excepcional, IGNORAMOS a verificação da chave.
                // Apenas usuário e senha serão verificados.
                $verificar_key = false;
            } else {
                // Para todos os outros usuários, a chave é OBRIGATÓRIA.
                $verificar_key = true;
            }

            // Verifica a chave de acesso fixa primeiro, SE NECESSÁRIO
            if ($verificar_key && $key_digitada !== ACESS_KEY_FIXA) {
                $erro_login = 'Chave de acesso incorreta.';
            } else {
                // Se a chave estiver correta (ou se for o usuário de exceção), tenta a consulta no banco
                // USANDO PREPARED STATEMENTS PARA PREVENIR SQL INJECTION
                $sql_code = "SELECT id, usuario, senha FROM login WHERE usuario = ? LIMIT 1";

                $stmt = $mysqli->prepare($sql_code);

                if ($stmt === false) {
                    $erro_login = "Falha interna ao preparar a consulta de login: " . $mysqli->error;
                    error_log("Erro de prepared statement (login): " . $mysqli->error); // Loga o erro real
                } else {
                    $stmt->bind_param("s", $usuario); // 's' para string
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows == 1) {
                        $dados_usuario = $result->fetch_assoc();
                        // Verifica a senha usando password_verify() (assumindo que as senhas estão hashadas)
                        if (password_verify($senha_digitada, $dados_usuario['senha'])) {
                            $_SESSION['id'] = $dados_usuario['id'];
                            $_SESSION['usuario'] = $dados_usuario['usuario'];

                            header("Location: dashboard.php");
                            exit(); // IMPORTANTE: Sempre use exit() após header()
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
    // Lógica de processamento do formulário de CADASTRO
    // Verifica se os campos do formulário de cadastro foram enviados
    else if (isset($_POST['usuario_cadastro']) && isset($_POST['senha_cadastro']) && isset($_POST['senha_confirm_cadastro']) && isset($_POST['key_cadastro'])) {

        $usuario_cadastro = trim($_POST['usuario_cadastro']); // Remove espaços em branco
        $senha_cadastro = $_POST['senha_cadastro'];
        $senha_confirm_cadastro = $_POST['senha_confirm_cadastro'];
        $key_cadastro_digitada = $_POST['key_cadastro'];

        // Validações de campos
        if (empty($usuario_cadastro) || empty($senha_cadastro) || empty($senha_confirm_cadastro) || empty($key_cadastro_digitada)) {
            $erro_cadastro = "Por favor, preencha todos os campos do cadastro.";
        } else if ($senha_cadastro !== $senha_confirm_cadastro) {
            $erro_cadastro = "As senhas não coincidem.";
        } else if (strlen($senha_cadastro) < 8) { // Exemplo de validação de força mínima da senha
            $erro_cadastro = "A senha deve ter no mínimo 8 caracteres.";
        } else if ($key_cadastro_digitada !== ACESS_KEY_FIXA) { // Verifica a chave de acesso fixa para cadastro
            $erro_cadastro = "Chave de acesso para cadastro incorreta.";
        } else {
            // Verifica se o nome de usuário já existe
            // USANDO PREPARED STATEMENTS PARA PREVENIR SQL INJECTION
            $check_user_sql = "SELECT id FROM login WHERE usuario = ? LIMIT 1";
            $stmt_check = $mysqli->prepare($check_user_sql);

            if ($stmt_check === false) {
                $erro_cadastro = "Erro interno ao verificar usuário existente.";
                error_log("Erro de prepared statement (check user): " . $mysqli->error);
            } else {
                $stmt_check->bind_param("s", $usuario_cadastro);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();

                if ($result_check->num_rows > 0) {
                    $erro_cadastro = "Este usuário já existe. Por favor, escolha outro.";
                } else {
                    // Hash da senha antes de inserir no banco de dados
                    $senha_hash = password_hash($senha_cadastro, PASSWORD_DEFAULT);

                    // Insere o novo usuário
                    // USANDO PREPARED STATEMENTS E REMOVENDO 'acesskey' DA INSERÇÃO (já que é fixa no código)
                    $insert_sql = "INSERT INTO login (usuario, senha) VALUES (?, ?)";
                    $stmt_insert = $mysqli->prepare($insert_sql);

                    if ($stmt_insert === false) {
                        $erro_cadastro = "Erro interno ao preparar o cadastro de usuário.";
                        error_log("Erro de prepared statement (insert user): " . $mysqli->error);
                    } else {
                        $stmt_insert->bind_param("ss", $usuario_cadastro, $senha_hash); // 'ss' para dois strings
                        if ($stmt_insert->execute()) {
                            $cadastro_sucesso = "Cadastro realizado com sucesso! Você já pode fazer login.";
                            // Opcional: limpar os campos do formulário de cadastro ou redirecionar
                            // header("Location: index.php?cadastro_sucesso=1"); exit();
                        } else {
                            $erro_cadastro = "Erro ao cadastrar: " . $stmt_insert->error;
                            error_log("Erro ao executar inserção de usuário: " . $stmt_insert->error);
                        }
                        $stmt_insert->close();
                    }
                }
                $stmt_check->close();
            }
        }
    }
} // FIM DO if ($_SERVER["REQUEST_METHOD"] == "POST")

// Consulta para buscar os dados da tabela 'tabela_nomes' para exibição
// Esta consulta não recebe entrada do usuário, então é mais segura.
$sql_usuarios = "SELECT nome, email, data_admissao, criado_em, atualizado_em, situacao FROM tabela_nomes WHERE situacao = 'ativo' ORDER BY nome ASC";
$result_usuarios = $mysqli->query($sql_usuarios);

$dados_usuarios = [];
if ($result_usuarios) { // Verifica se a consulta foi bem-sucedida
    if ($result_usuarios->num_rows > 0) {
        while ($row = $result_usuarios->fetch_assoc()) {
            $dados_usuarios[] = $row;
        }
    }
} else {
    // Trata erro na consulta (ex: logar ou exibir mensagem amigável)
    error_log("Erro ao buscar dados da tabela_nomes: " . $mysqli->error);
    // Opcional: $dados_usuarios_erro = "Erro ao carregar dados.";
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
            <tbody>
                <?php if (!empty($dados_usuarios)): ?>
                    <?php foreach ($dados_usuarios as $usuario_item): // Renomeado para evitar conflito com $usuario do formulário ?>
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
                        <td colspan="6" style="text-align: center;">Nenhum usuário encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>

    <div class="modal-overlay" id="cadastro-modal-overlay">
        <div class="modal-content">
            <button class="close-modal-btn" id="close-cadastro-modal-btn">&times;</button>
            <h2>Cadastro de Administrador</h2>
            <form action="" method="POST">
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
            // Exibe a mensagem de erro ou sucesso do cadastro, se houver
            if (!empty($erro_cadastro)) {
                echo '<p style="color: red; text-align: center; margin-top: 20px;">' . htmlspecialchars($erro_cadastro) . '</p>';
            }
            if (!empty($cadastro_sucesso)) {
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
            // Exibe a mensagem de erro de login, se houver
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

            // --- Código PHP para reabrir modais em caso de erro ---
            // Este bloco PHP será renderizado no HTML se houver um erro, ativando o modal.
            <?php
            if (!empty($erro_cadastro)) {
                echo 'cadastroModalOverlay.classList.add("active"); document.body.style.overflow = "hidden";';
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
            });

            closeCadastroModalBtn.addEventListener('click', function() {
                cadastroModalOverlay.classList.remove('active');
                document.body.style.overflow = 'auto';
            });

            cadastroModalOverlay.addEventListener('click', function(event) {
                if (event.target === cadastroModalOverlay) {
                    cadastroModalOverlay.classList.remove('active');
                    document.body.style.overflow = 'auto';
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
                    }
                    if (loginModalOverlay.classList.contains('active')) {
                        loginModalOverlay.classList.remove('active');
                        document.body.style.overflow = 'auto';
                    }
                }
            });

            // --- KEY Input Validation (for both forms) ---
            const keyInputs = document.querySelectorAll('input[name="key"], input[name="key_cadastro"]'); // Seleciona ambos os campos de chave
            keyInputs.forEach(function(input) {
                input.addEventListener('input', function() {
                    // Permite apenas dígitos (0-9)
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
                input.addEventListener('keydown', function(event) {
                    // Permite teclas de controle como Backspace, Delete, Tab, Enter, setas, Ctrl/Cmd + V, etc.
                    if (event.key === 'Backspace' || event.key === 'Delete' || event.key === 'Tab' ||
                        event.key === 'Enter' || event.key.startsWith('Arrow') || event.ctrlKey || event.metaKey) {
                        return;
                    }
                    // Impede a digitação de caracteres que não são dígitos
                    if (!/^\d$/.test(event.key)) {
                        event.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>