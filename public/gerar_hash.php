<?php
// Define a senha que você quer usar para o seu superadmin
$senha_clara = "adminadmin"; // <<< MUDE ESTA SENHA PARA A SUA SENHA REAL E FORTE

// Gera o hash da senha usando o algoritmo padrão (Bcrypt, recomendado)
$senha_hash = password_hash($senha_clara, PASSWORD_DEFAULT);

echo "A senha em texto puro é: " . $senha_clara . "<br>";
echo "O HASH da senha é: <pre>" . htmlspecialchars($senha_hash) . "</pre><br>";
echo "Copie o HASH acima e use-o no seu comando SQL.";
?>$2y$10$WaZeC4T1NtanF2FHAcsKd.MnkToPh97bkPss3FyZiCR/BIjAT3e1a