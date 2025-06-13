<?php
// 1. Início da sessão 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Conexão com o banco de dados
include __DIR__ . '/../../config/database.php';

// Chave de acesso fixa aqui.

define('ACESS_KEY_FIXA', $_ENV['ACESS_KEY_FIXA']);

// Usuario que nao precisa da chave
define('USUARIO_EXCECAO_LOGIN', $_ENV['USUARIO_EXCECAO_LOGIN'] ?? 'superadmin');

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

    <!-- Bootstrap apenas para responsividade -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <title>Sistema de Usuários</title>
</head>
<body>
<header id="main-header">
        <img src="/assets/img/johnson_logo_transparent.png" alt="Logo"> <h1>Sistema de Usuários</h1>
        <div class="header-buttons">
            <button id="open-cadastro-modal-btn">Cadastro</button>
            <button id="open-login-modal-btn">Login</button>
            <button id="open-filter-modal-btn">Filtrar Usuários</button>
        </div>
    </header>


    <main class="container my-4">
        <div class="table-responsive-md">
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
        </div>
    </main>

    <div class="modal-overlay" id="cadastro-modal-overlay">
        <div class="modal-content">
            <button class="close-modal-btn" id="close-cadastro-modal-btn">&times;</button>
            <h2>Cadastro de Administrador</h2>
            <form id="cadastro-form" action="/cadastrar" method="POST">
                <label for="usuario_cadastro">Usuário</label>
                <input type="text" id="usuario_cadastro" name="usuario_cadastro" placeholder="Nome de usuário" maxlength="12" required>

                <label for="senha_cadastro">Senha</label>
                <input type="password" id="senha_cadastro" name="senha_cadastro" placeholder="Senha" minlength="8" required>

                <label for="senha_confirm_cadastro">Confirmar Senha</label>
                <input type="password" id="senha_confirm_cadastro" name="senha_confirm_cadastro" placeholder="Repita a senha" minlength="8" required>

                <label for="key_cadastro">KEY</label>
                <input type="text" id="key_cadastro" name="key_cadastro" placeholder="Chave de acesso" maxlength="20" required>

                <button type="submit">Cadastrar</button>
            </form>

            <?php
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
            <form id="login-form" action="/login" method="POST">
                <label for="usuario-login">Usuário</label>
                <input type="text" id="usuario-login" name="usuario" maxlength="12" autocomplete="off" placeholder="Seu usuário" value="<?php echo htmlspecialchars($usuario); ?>" required>

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

            // Atualiza o sessionStorage em tempo real durante a digitação ou seleção
            [modalSearchNameInput, modalSearchEmailInput, modalFilterCriadoDeInput, modalFilterCriadoAteInput, modalFilterAtualizadoDeInput, modalFilterAtualizadoAteInput].forEach(input => {
                input.addEventListener('input', function () {
                    const key = {
                        [modalSearchNameInput.id]: 'filter_search_name',
                        [modalSearchEmailInput.id]: 'filter_search_email',
                        [modalFilterCriadoDeInput.id]: 'filter_criado_de',
                        [modalFilterCriadoAteInput.id]: 'filter_criado_ate',
                        [modalFilterAtualizadoDeInput.id]: 'filter_atualizado_de',
                        [modalFilterAtualizadoAteInput.id]: 'filter_atualizado_ate',
                    }[input.id];

                    if (key) {
                        sessionStorage.setItem(key, input.value);
                    }
           document.addEventListener('DOMContentLoaded', function () {
    // Inputs do filtro
    const modalSearchNameInput = document.getElementById('modal-search-name');
    const modalSearchEmailInput = document.getElementById('modal-search-email');
    const modalFilterCriadoDeInput = document.getElementById('modal-filter-criado-de');
    const modalFilterCriadoAteInput = document.getElementById('modal-filter-criado-ate');
    const modalFilterAtualizadoDeInput = document.getElementById('modal-filter-atualizado-de');
    const modalFilterAtualizadoAteInput = document.getElementById('modal-filter-atualizado-ate');

    function sincronizarCamposFiltroComsessionStorage() {
        modalSearchNameInput.value = sessionStorage.getItem('filter_search_name') || '';
        modalSearchEmailInput.value = sessionStorage.getItem('filter_search_email') || '';
        modalFilterCriadoDeInput.value = sessionStorage.getItem('filter_criado_de') || '';
        modalFilterCriadoAteInput.value = sessionStorage.getItem('filter_criado_ate') || '';
        modalFilterAtualizadoDeInput.value = sessionStorage.getItem('filter_atualizado_de') || '';
        modalFilterAtualizadoAteInput.value = sessionStorage.getItem('filter_atualizado_ate') || '';
    }

    sincronizarCamposFiltroComsessionStorage();
    setInterval(sincronizarCamposFiltroComsessionStorage, 3000);

    function updateUserTable() {
        const searchName = modalSearchNameInput.value;
        const searchEmail = modalSearchEmailInput.value;
        const criadoDe = modalFilterCriadoDeInput.value;
        const criadoAte = modalFilterCriadoAteInput.value;
        const atualizadoDe = modalFilterAtualizadoDeInput.value;
        const atualizadoAte = modalFilterAtualizadoAteInput.value;

        sessionStorage.setItem('filter_search_name', searchName);
        sessionStorage.setItem('filter_search_email', searchEmail);
        sessionStorage.setItem('filter_criado_de', criadoDe);
        sessionStorage.setItem('filter_criado_ate', criadoAte);
        sessionStorage.setItem('filter_atualizado_de', atualizadoDe);
        sessionStorage.setItem('filter_atualizado_ate', atualizadoAte);

        const params = new URLSearchParams();
        if (searchName) params.append('search_name', searchName);
        if (searchEmail) params.append('search_email', searchEmail);
        if (criadoDe) params.append('criado_de', criadoDe);
        if (criadoAte) params.append('criado_ate', criadoAte);
        if (atualizadoDe) params.append('atualizado_de', atualizadoDe);
        if (atualizadoAte) params.append('atualizado_ate', atualizadoAte);

        fetch('/get_users&' + params.toString())
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('user-table-body');
                tbody.innerHTML = '';
                if (data.status === 'success') {
                    if (data.data.length === 0) {
                        const row = tbody.insertRow();
                        const cell = row.insertCell();
                        cell.colSpan = 6;
                        cell.style.textAlign = 'center';
                        cell.textContent = 'Nenhum usuário ativo encontrado.';
                    } else {
                        data.data.forEach(user => {
                            const row = tbody.insertRow();
                            row.insertCell().textContent = user.nome;
                            row.insertCell().textContent = user.email;
                            row.insertCell().textContent = user.data_admissao;
                            row.insertCell().textContent = user.criado_em;
                            row.insertCell().textContent = user.atualizado_em;
                            row.insertCell().textContent = user.situacao;
                        });
                    }
                }
            })
            .catch(err => console.error('Erro ao buscar usuários:', err));
    }

    [
        modalSearchNameInput,
        modalSearchEmailInput,
        modalFilterCriadoDeInput,
        modalFilterCriadoAteInput,
        modalFilterAtualizadoDeInput,
        modalFilterAtualizadoAteInput
    ].forEach(input => {
        input.addEventListener('input', () => {
            const keyMap = {
                [modalSearchNameInput.id]: 'filter_search_name',
                [modalSearchEmailInput.id]: 'filter_search_email',
                [modalFilterCriadoDeInput.id]: 'filter_criado_de',
                [modalFilterCriadoAteInput.id]: 'filter_criado_ate',
                [modalFilterAtualizadoDeInput.id]: 'filter_atualizado_de',
                [modalFilterAtualizadoAteInput.id]: 'filter_atualizado_ate',
            };
            const key = keyMap[input.id];
            if (key) {
                sessionStorage.setItem(key, input.value);
            }
            updateUserTable();
        });
    });

    document.getElementById('modal-apply-filters-btn').addEventListener('click', () => {
        updateUserTable();
        document.getElementById('filter-modal-overlay').classList.remove('active');
        document.body.style.overflow = 'auto';
    });

    document.getElementById('modal-clear-filters-btn').addEventListener('click', () => {
        [
            'filter_search_name',
            'filter_search_email',
            'filter_criado_de',
            'filter_criado_ate',
            'filter_atualizado_de',
            'filter_atualizado_ate'
        ].forEach(key => sessionStorage.removeItem(key));
        sincronizarCamposFiltroComsessionStorage();
        updateUserTable();
    });

    updateUserTable();
});
        
                    updateUserTable(); // continua aplicando o filtro automaticamente
                });
            });

            const usuarioLoginInput = document.getElementById('usuario-login');
            const keyLoginInput = document.getElementById('key-login');

            
            // Remover "required" do campo key se o usuário for superadmin
            usuarioLoginInput.addEventListener('input', function () {
                const usuarioVal = usuarioLoginInput.value.trim().toLowerCase();
                if (usuarioVal === 'superadmin') {
                    keyLoginInput.required = false;
           
                } else {
                    keyLoginInput.required = true;
  
                }
            });


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

                // Armazena os valores dos filtros separados no sessionStorage
                sessionStorage.setItem('filter_search_name', searchName);
                sessionStorage.setItem('filter_search_email', searchEmail);
                sessionStorage.setItem('filter_criado_de', criadoDe);
                sessionStorage.setItem('filter_criado_ate', criadoAte);
                sessionStorage.setItem('filter_atualizado_de', atualizadoDe);
                sessionStorage.setItem('filter_atualizado_ate', atualizadoAte);

                let queryParams = [];
                // Adiciona parâmetros de nome e e-mail separados
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
                            userTableBody.innerHTML = ''; 

                            if (data.data.length > 0) {
                                // Populate the table with new formatted data
                                data.data.forEach(user => {
                                    const row = userTableBody.insertRow();
                                    row.insertCell().textContent = user.nome;
                                    row.insertCell().textContent = user.email;
                                    row.insertCell().textContent = formatDate(user.data_admissao); 
                                    row.insertCell().textContent = formatDateTime(user.criado_em); 
                                    row.insertCell().textContent = formatDateTime(user.atualizado_em); 
                                    row.insertCell().textContent = user.situacao;
                                });
                            } else {
                               
                                const row = userTableBody.insertRow();
                                const cell = row.insertCell();
                                cell.colSpan = 6; 
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

           
            modalApplyFiltersBtn.addEventListener('click', function() {
                updateUserTable(); 
                filterModalOverlay.classList.remove('active'); 
                document.body.style.overflow = 'auto';
            });

            modalClearFiltersBtn.addEventListener('click', function() {
                //Limpa os valores dos campos de nome e e-mail separados
                modalSearchNameInput.value = '';
                modalSearchEmailInput.value = '';
                modalFilterCriadoDeInput.value = '';
                modalFilterCriadoAteInput.value = '';
                modalFilterAtualizadoDeInput.value = '';
                modalFilterAtualizadoAteInput.value = '';
                //Limpa os filtros no sessionStorage também
                sessionStorage.removeItem('filter_search_name');
                sessionStorage.removeItem('filter_search_email');
                sessionStorage.removeItem('filter_criado_de');
                sessionStorage.removeItem('filter_criado_ate');
                sessionStorage.removeItem('filter_atualizado_de');
                sessionStorage.removeItem('filter_atualizado_ate');
                updateUserTable();
            });

          
            //Carrega os valores dos filtros de nome e e-mail separados na inicialização
            modalSearchNameInput.value = sessionStorage.getItem('filter_search_name') || '';
            modalSearchEmailInput.value = sessionStorage.getItem('filter_search_email') || '';
            modalFilterCriadoDeInput.value = sessionStorage.getItem('filter_criado_de') || '';
            modalFilterCriadoAteInput.value = sessionStorage.getItem('filter_criado_ate') || '';
            modalFilterAtualizadoDeInput.value = sessionStorage.getItem('filter_atualizado_de') || '';
            modalFilterAtualizadoAteInput.value = sessionStorage.getItem('filter_atualizado_ate') || '';


            // Atualização de dados na tabela a cada -- segundos
            const INTERVALO_ATUALIZACAO_MS = 5000; 

            updateUserTable();

          
            setInterval(updateUserTable, INTERVALO_ATUALIZACAO_MS);
            

        });
    </script>
</body>
</html>