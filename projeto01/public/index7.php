<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tipos primitivos em PHP</title>
</head>
<body>
    <h1>Teste de tipos primitivos</h1>
    <?php
        $num = 0b101;
        echo "O valor da variável \$num e $num";
        echo "<br>";
        $v = 300;
        var_dump($v);
        echo "<br>";
        $num = 3e2; 
        var_dump($num)
    ?>
</body>
</html>