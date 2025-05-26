<?php
session_start();
// Redireciona para o login se o usuário não estiver logado
if (!isset($_SESSION['id'])) {
    header("Location: index.php"); // Redireciona para index.php que tem o modal de login
    exit();
}
include('conexao.php');

// Consulta os dados da tabela_nomes
// A consulta é segura pois não recebe entrada do usuário.
$sql = "SELECT id, nome, email, data_admissao, situacao FROM tabela_nomes ORDER BY nome ASC";
$result = $mysqli->query($sql);

$nomes_cadastrados = [];
if ($result) { // Verifica se a consulta foi bem-sucedida
    while ($row = $result->fetch_assoc()) {
        $nomes_cadastrados[] = $row;
    }
} else {
    // Em caso de erro na consulta, logar e exibir uma mensagem amigável
    error_log("Erro na consulta de nomes no dashboard: " . $mysqli->error);
    $_SESSION['mensagem_erro'] = "Erro ao carregar a lista de nomes.";
    // Não redirecionar, apenas mostrar a mensagem na página
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Painel de Administração</title>
    <link rel="stylesheet" href="style2.css">
</head>
<body>
    <header>
        <h1>Bem-vindo, <?php echo htmlspecialchars($_SESSION['usuario']); ?>!</h1>
        <a href="logout.php">Sair</a>
    

    <?php
    // Exibe mensagens de sucesso ou erro, se houver
    if (isset($_SESSION['mensagem_sucesso'])) {
        echo '<p style="color: green; text-align: center;">' . htmlspecialchars($_SESSION['mensagem_sucesso']) . '</p>';
        unset($_SESSION['mensagem_sucesso']); // Limpa a mensagem após exibir
    }
    if (isset($_SESSION['mensagem_erro'])) {
        echo '<p style="color: red; text-align: center;">' . htmlspecialchars($_SESSION['mensagem_erro']) . '</p>';
        unset($_SESSION['mensagem_erro']); // Limpa a mensagem após exibir
    }
    ?>

        <h2>Cadastrar novo nome</h2>
        <form action="inserir_nome.php" method="POST">
            <input type="text" name="nome" placeholder="Nome" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="date" name="data_admissao" required>
            <select name="situacao" required>
                <option value="Ativo">Ativo</option>
                <option value="Inativo">Inativo</option>
            </select>
            <button type="submit">Salvar</button>
        </form>
    </header>
    <main>
    
        <table border="1">
            <tr>
                <th>Nome</th>
                <th>Email</th>
                <th>Data de Admissão</th>
                <th>Situação</th>
                </tr>
            <?php if (!empty($nomes_cadastrados)): ?>
                <?php foreach ($nomes_cadastrados as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['nome']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['data_admissao']) ?></td>
                    <td><?= htmlspecialchars($row['situacao']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center;">Nenhum nome cadastrado ainda.</td>
                </tr>
            <?php endif; ?>
        </table>
    </main>
</body>
</html>