<?php
session_start();
// Redireciona para o login se o usuário não estiver logado
if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}
include('conexao.php');

// --- Bloco para Requisições AJAX (POST ou GET) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" || (isset($_GET['action']) && in_array($_GET['action'], ['get_user_data', 'get_users_dashboard']))) {

    $is_ajax_request = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if (isset($_GET['action']) && $_GET['action'] == 'get_user_data') {
        header('Content-Type: application/json');
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if ($id > 0) {
            // Reordenado: criado_em e atualizado_em antes de situacao
            $sql_select = "SELECT id, nome, email, data_admissao, criado_em, atualizado_em, situacao FROM tabela_nomes WHERE id = ?";
            $stmt_select = $mysqli->prepare($sql_select);

            if ($stmt_select) {
                $stmt_select->bind_param("i", $id);
                $stmt_select->execute();
                $result_select = $stmt_select->get_result();
                if ($result_select->num_rows == 1) {
                    echo json_encode(['status' => 'success', 'data' => $result_select->fetch_assoc()]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Usuário não encontrado.']);
                }
                $stmt_select->close();
            } else {
                error_log("Erro ao preparar a busca de usuário (get_user_data): " . $mysqli->error);
                echo json_encode(['status' => 'error', 'message' => 'Erro interno do servidor.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'ID de usuário inválido.']);
        }
        exit();
    }

    if (isset($_GET['action']) && $_GET['action'] == 'get_users_dashboard') {
        header('Content-Type: application/json');

        // Reordenado: criado_em e atualizado_em antes de situacao
        $sql_usuarios_ajax = "SELECT id, nome, email, data_admissao, criado_em, atualizado_em, situacao FROM tabela_nomes ORDER BY nome ASC";
        $result_usuarios_ajax = $mysqli->query($sql_usuarios_ajax);

        $dados_usuarios_ajax = [];
        if ($result_usuarios_ajax) {
            if ($result_usuarios_ajax->num_rows > 0) {
                while ($row = $result_usuarios_ajax->fetch_assoc()) {
                    $dados_usuarios_ajax[] = $row;
                }
            }
            echo json_encode(['status' => 'success', 'data' => $dados_usuarios_ajax]);
        } else {
            error_log("Erro ao buscar dados da tabela_nomes via AJAX (get_users_dashboard): " . $mysqli->error);
            echo json_encode(['status' => 'error', 'message' => 'Erro ao carregar dados dos usuários via AJAX.']);
        }
        exit();
    }


    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_user') {
        header('Content-Type: application/json');

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $nome = trim($_POST['nome']);
        $email = trim($_POST['email']);
        $situacao = trim($_POST['situacao']);

        if ($id <= 0 || empty($nome) || empty($email) || empty($situacao)) {
            echo json_encode(['status' => 'error', 'message' => 'Dados inválidos para atualização.']);
            exit();
        }

        $sql_update = "UPDATE tabela_nomes SET nome = ?, email = ?, situacao = ?, atualizado_em = NOW() WHERE id = ?";
        $stmt_update = $mysqli->prepare($sql_update);

        if ($stmt_update) {
            $stmt_update->bind_param("sssi", $nome, $email, $situacao, $id);
            if ($stmt_update->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Dados atualizados com sucesso!']);
            } else {
                error_log("Erro ao executar atualização de usuário (update_user): " . $stmt_update->error);
                echo json_encode(['status' => 'error', 'message' => 'Erro ao atualizar dados: ' . $stmt_update->error]);
            }
            $stmt_update->close();
        } else {
            error_log("Erro ao preparar atualização de usuário (update_user): " . $mysqli->error);
            echo json_encode(['status' => 'error', 'message' => 'Erro interno do servidor.']);
        }
        exit();
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_user') {
        header('Content-Type: application/json');

        $nome = trim($_POST['nome']);
        $email = trim($_POST['email']);
        $situacao = trim($_POST['situacao']);

        $data_admissao = date('Y-m-d'); // Para o campo DATE

        if (empty($nome) || empty($email) || empty($situacao)) {
            echo json_encode(['status' => 'error', 'message' => 'Por favor, preencha todos os campos obrigatórios (Nome, Email, Situação).']);
            exit();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Formato de e-mail inválido.']);
            exit();
        }

        $check_email_sql = "SELECT id FROM tabela_nomes WHERE email = ? LIMIT 1";
        $stmt_check_email = $mysqli->prepare($check_email_sql);
        if ($stmt_check_email) {
            $stmt_check_email->bind_param("s", $email);
            $stmt_check_email->execute();
            $result_check_email = $stmt_check_email->get_result();
            if ($result_check_email->num_rows > 0) {
                echo json_encode(['status' => 'error', 'message' => 'Este e-mail já está cadastrado.']);
                $stmt_check_email->close();
                exit();
            }
            $stmt_check_email->close();
        } else {
            error_log("Erro ao preparar verificação de e-mail: " . $mysqli->error);
            echo json_encode(['status' => 'error', 'message' => 'Erro interno do servidor ao verificar e-mail.']);
            exit();
        }

        $sql_insert = "INSERT INTO tabela_nomes (nome, email, data_admissao, situacao, criado_em, atualizado_em) VALUES (?, ?, ?, ?, NOW(), NOW())";
        $stmt_insert = $mysqli->prepare($sql_insert);

        if ($stmt_insert) {
            $stmt_insert->bind_param("ssss", $nome, $email, $data_admissao, $situacao);
            if ($stmt_insert->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Novo usuário adicionado com sucesso!']);
            } else {
                error_log("Erro ao executar inserção de usuário (add_user): " . $stmt_insert->error);
                echo json_encode(['status' => 'error', 'message' => 'Erro ao adicionar usuário: ' . $stmt_insert->error]);
            }
            $stmt_insert->close();
        } else {
            error_log("Erro ao preparar inserção de usuário (add_user): " . $mysqli->error);
            echo json_encode(['status' => 'error', 'message' => 'Erro interno do servidor.']);
        }
        exit();
    }
}

// Consulta os dados da tabela_nomes para exibição inicial (incluindo as novas colunas)
$sql = "SELECT id, nome, email, data_admissao, criado_em, atualizado_em, situacao FROM tabela_nomes ORDER BY nome ASC";
$result = $mysqli->query($sql);

$nomes_cadastrados = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $nomes_cadastrados[] = $row;
    }
} else {
    error_log("Erro na consulta de nomes no dashboard (inicial): " . $mysqli->error);
    $_SESSION['mensagem_erro'] = "Erro ao carregar a lista de nomes.";
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel de Administração</title>
    <link rel="stylesheet" href="style2.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    <header id="dashboard-header">
        <h1>Bem-vindo, <?php echo htmlspecialchars($_SESSION['usuario']); ?>!</h1>
        <div class="header-buttons">
            <button type="button" class="header-button" id="open-add-user-modal-btn">Adicionar Novo Usuário</button>
            <a href="logout.php" class="header-button">Sair</a>
        </div>
    </header>

    <?php
    if (isset($_SESSION['mensagem_sucesso'])) {
        echo '<p class="message success">' . htmlspecialchars($_SESSION['mensagem_sucesso']) . '</p>';
        unset($_SESSION['mensagem_sucesso']);
    }
    if (isset($_SESSION['mensagem_erro'])) {
        echo '<p class="message error">' . htmlspecialchars($_SESSION['mensagem_erro']) . '</p>';
        unset($_SESSION['mensagem_erro']);
    }
    ?>

    <main>
        <table border="1">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Data de Admissão</th>
                    <th>Data e hora do cadastrado</th>
                    <th>Data e hora da atualização</th>
                    <th>Situação</th>
                </tr>
            </thead>
            <tbody id="user-table-body">
                <?php if (!empty($nomes_cadastrados)): ?>
                    <?php foreach ($nomes_cadastrados as $row): ?>
                    <tr data-user-id="<?= htmlspecialchars($row['id']) ?>">
                        <td><?= htmlspecialchars($row['id']) ?></td>
                        <td><?= htmlspecialchars($row['nome']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['data_admissao']) ?></td>
                        <td><?= htmlspecialchars($row['criado_em']) ?></td>
                        <td><?= htmlspecialchars($row['atualizado_em']) ?></td>
                        <td><?= htmlspecialchars($row['situacao']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">Nenhum nome cadastrado ainda.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>

    <div class="modal-overlay" id="edit-user-modal-overlay">
        <div class="modal-content">
            <button class="close-modal-btn" id="close-edit-user-modal-btn">&times;</button>
            <h2>Editar Usuário</h2>
            <form id="edit-user-form" action="" method="POST">
                <input type="hidden" id="edit-user-id" name="id">

                <label for="edit-user-nome">Nome:</label>
                <input type="text" id="edit-user-nome" name="nome" required>

                <label for="edit-user-email">Email:</label>
                <input type="email" id="edit-user-email" name="email" required>

                <label for="edit-user-situacao">Situação:</label>
                <select id="edit-user-situacao" name="situacao" required>
                    <option value="Ativo">Ativo</option>
                    <option value="Inativo">Inativo</option>
                </select>

                <button type="submit">Salvar Alterações</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="add-user-modal-overlay">
        <div class="modal-content">
            <button class="close-modal-btn" id="close-add-user-modal-btn">&times;</button>
            <h2>Adicionar Novo Usuário</h2>
            <form id="add-user-form" action="" method="POST">
                <label for="add-user-nome">Nome:</label>
                <input type="text" id="add-user-nome" name="nome" placeholder="Nome Completo" required>

                <label for="add-user-email">Email:</label>
                <input type="email" id="add-user-email" name="email" placeholder="email@exemplo.com" required>

                <label for="add-user-situacao">Situação:</label>
                <select id="add-user-situacao" name="situacao" required>
                    <option value="Ativo">Ativo</option>
                    <option value="Inativo">Inativo</option>
                </select>

                <button type="submit">Adicionar Usuário</button>
            </form>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const userTableBody = document.getElementById('user-table-body');

            const editUserModalOverlay = document.getElementById('edit-user-modal-overlay');
            const closeEditUserModalBtn = document.getElementById('close-edit-user-modal-btn');
            const editUserForm = document.getElementById('edit-user-form');
            const editUserIdInput = document.getElementById('edit-user-id');
            const editUserNomeInput = document.getElementById('edit-user-nome');
            const editUserEmailInput = document.getElementById('edit-user-email');
            const editUserSituacaoSelect = document.getElementById('edit-user-situacao');

            const openAddUserModalBtn = document.getElementById('open-add-user-modal-btn');
            const addUserModalOverlay = document.getElementById('add-user-modal-overlay');
            const closeAddUserModalBtn = document.getElementById('close-add-user-modal-btn');
            const addUserForm = document.getElementById('add-user-form');

            // Função auxiliar para formatar a data e hora
            function formatDateTime(dateTimeString) {
                if (!dateTimeString) return '';

                // Adiciona 'Z' para indicar que a string está em UTC, para que o JavaScript
                // a interprete corretamente antes de aplicar o fuso horário local.
                // Se a string já vier com 'Z' ou um offset (+00:00), essa adição não será necessária.
                // Mas, como a maioria dos DATETIME/TIMESTAMP do MySQL não vem com isso, ajuda.
                let date = new Date(dateTimeString + (dateTimeString.includes('Z') || dateTimeString.includes('+') ? '' : 'Z'));
                
                // Se o fuso horário do seu banco de dados já for o seu fuso horário local,
                // e não UTC, você pode precisar ajustar a interpretação.
                // Uma alternativa para testar se o "Z" não resolver:
                // let date = new Date(dateTimeString.replace(/-/g, '/') + ' GMT-0300'); // Ou o offset correto do seu servidor/DB

                if (isNaN(date.getTime())) { // Verifica se a data é inválida
                    return dateTimeString; // Retorna a string original se for inválida
                }

                // Usamos métodos getUTC... para garantir que estamos pegando os componentes UTC
                // e então os formatamos manualmente para exibição local, evitando a conversão automática.
                // Ou, se o date object já foi criado com o fuso horário local, podemos usar os métodos "get" normais.
                // A abordagem mais simples é usar toLocaleString() se você quer o fuso horário do usuário.
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

            // Função auxiliar para formatar apenas a data (sem hora)
            function formatDate(dateString) {
                if (!dateString) return '';

                // Para datas (YYYY-MM-DD), o 'T00:00:00' e 'Z' garante interpretação como UTC à meia-noite
                // do dia, o que é importante para evitar problemas de "dia anterior" com fuso horário.
                let date = new Date(dateString + 'T00:00:00Z'); 
                
                if (isNaN(date.getTime())) {
                    return dateString;
                }

                return date.toLocaleDateString('pt-BR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
            }


            function openEditModal(userId) {
                Swal.fire({
                    title: 'Carregando dados...',
                    text: 'Aguarde.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch(`dashboard.php?action=get_user_data&id=${userId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erro de rede ao buscar dados do usuário.');
                        }
                        return response.json();
                    })
                    .then(data => {
                        Swal.close();
                        if (data.status === 'success' && data.data) {
                            const user = data.data;
                            editUserIdInput.value = user.id;
                            editUserNomeInput.value = user.nome;
                            editUserEmailInput.value = user.email;
                            editUserSituacaoSelect.value = user.situacao;

                            editUserModalOverlay.classList.add('active');
                            document.body.style.overflow = 'hidden';
                        } else {
                            Swal.fire('Erro!', data.message || 'Não foi possível carregar os dados do usuário.', 'error');
                        }
                    })
                    .catch(error => {
                        Swal.close();
                        console.error('Erro ao buscar dados do usuário para edição:', error);
                        Swal.fire('Erro!', 'Ocorreu um erro ao carregar os dados para edição.', 'error');
                    });
            }

            openAddUserModalBtn.addEventListener('click', function() {
                addUserModalOverlay.classList.add('active');
                document.body.style.overflow = 'hidden';
                addUserForm.reset();
            });

            closeAddUserModalBtn.addEventListener('click', function() {
                addUserModalOverlay.classList.remove('active');
                document.body.style.overflow = 'auto';
                addUserForm.reset();
            });

            addUserModalOverlay.addEventListener('click', function(event) {
                if (event.target === addUserModalOverlay) {
                    addUserModalOverlay.classList.remove('active');
                    document.body.style.overflow = 'auto';
                    addUserForm.reset();
                }
            });

            userTableBody.addEventListener('click', function(event) {
                const row = event.target.closest('tr');
                if (row && row.dataset.userId) {
                    const userId = row.dataset.userId;
                    openEditModal(userId);
                }
            });

            closeEditUserModalBtn.addEventListener('click', function() {
                editUserModalOverlay.classList.remove('active');
                document.body.style.overflow = 'auto';
                editUserForm.reset();
            });

            editUserModalOverlay.addEventListener('click', function(event) {
                if (event.target === editUserModalOverlay) {
                    editUserModalOverlay.classList.remove('active');
                    document.body.style.overflow = 'auto';
                    editUserForm.reset();
                }
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    if (editUserModalOverlay.classList.contains('active')) {
                        editUserModalOverlay.classList.remove('active');
                        document.body.style.overflow = 'auto';
                        editUserForm.reset();
                    }
                    if (addUserModalOverlay.classList.contains('active')) {
                        addUserModalOverlay.classList.remove('active');
                        document.body.style.overflow = 'auto';
                        addUserForm.reset();
                    }
                }
            });

            editUserForm.addEventListener('submit', function(event) {
                event.preventDefault();

                Swal.fire({
                    title: 'Atualizando...',
                    text: 'Salvando as alterações.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const formData = new FormData(editUserForm);
                formData.append('action', 'update_user');

                fetch('dashboard.php', {
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
                        Swal.fire('Sucesso!', data.message, 'success');
                        editUserModalOverlay.classList.remove('active');
                        document.body.style.overflow = 'auto';
                        editUserForm.reset();
                        updateUserTable(); // Atualiza a tabela na página principal
                    } else {
                        Swal.fire('Erro!', data.message, 'error');
                    }
                })
                .catch(error => {
                    Swal.close();
                    console.error('Erro no AJAX de atualização:', error);
                    Swal.fire('Erro de Conexão!', 'Não foi possível conectar ao servidor para atualizar.', 'error');
                });
            });

            addUserForm.addEventListener('submit', function(event) {
                event.preventDefault();

                Swal.fire({
                    title: 'Adicionando Usuário...',
                    text: 'Aguarde.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const formData = new FormData(addUserForm);
                formData.append('action', 'add_user');

                fetch('dashboard.php', {
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
                        Swal.fire('Sucesso!', data.message, 'success');
                        addUserModalOverlay.classList.remove('active');
                        document.body.style.overflow = 'auto';
                        addUserForm.reset();
                        updateUserTable(); // Atualiza a tabela na página principal
                    } else {
                        Swal.fire('Erro!', data.message, 'error');
                    }
                })
                .catch(error => {
                    Swal.close();
                    console.error('Erro no AJAX de adição de usuário:', error);
                    Swal.fire('Erro de Conexão!', 'Não foi possível conectar ao servidor para adicionar o usuário.', 'error');
                });
            });


            // Função para buscar e atualizar a tabela de usuários
            function updateUserTable() {
                fetch('dashboard.php?action=get_users_dashboard')
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
                                data.data.forEach(user => {
                                    const row = userTableBody.insertRow();
                                    row.dataset.userId = user.id;
                                    row.insertCell().textContent = user.id;
                                    row.insertCell().textContent = user.nome;
                                    row.insertCell().textContent = user.email;
                                    row.insertCell().textContent = formatDate(user.data_admissao); // Usando formatDate
                                    // Adiciona as células para data/hora formatadas na nova ordem
                                    row.insertCell().textContent = formatDateTime(user.criado_em);
                                    row.insertCell().textContent = formatDateTime(user.atualizado_em);
                                    row.insertCell().textContent = user.situacao;
                                });
                            } else {
                                const row = userTableBody.insertRow();
                                const cell = row.insertCell();
                                cell.colSpan = 7; // Ajuste o colspan para 7 colunas (ID, Nome, Email, Data Admissão, Cadastrado em, Última Atualização, Situação)
                                cell.style.textAlign = 'center';
                                cell.textContent = 'Nenhum nome cadastrado ainda.';
                            }
                        } else {
                            console.error('Erro ao buscar dados da tabela:', data.message || 'Status não é sucesso ou dados ausentes.');
                        }
                    })
                    .catch(error => {
                        console.error('Erro na requisição AJAX da tabela:', error);
                    });
            }

            // Efeito de scroll no header (adaptado para o ID 'dashboard-header')
            const dashboardHeader = document.getElementById('dashboard-header');
            const scrollThreshold = 100;

            window.addEventListener('scroll', function() {
                if (window.scrollY > scrollThreshold) {
                    dashboardHeader.classList.add('scrolled');
                } else {
                    dashboardHeader.classList.remove('scrolled');
                }
            });


            const INTERVALO_ATUALIZACAO_MS = 10000;

            // Carrega a tabela na inicialização
            updateUserTable();
            // E define o intervalo para atualizações periódicas
            setInterval(updateUserTable, INTERVALO_ATUALIZACAO_MS);
        });
    </script>
</body>
</html>