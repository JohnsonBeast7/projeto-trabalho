<?php
    $nome = "João Pedro";
    $sobrenome = "Ferraresi";
    const PAIS = "Brasil";
    $salário = 3000.40;

    
    echo "É verdade que seu nome é $nome $sobrenome e vocé mora no " . PAIS . "?";
    echo "<br>";
    echo "<p>Seu salário é $salário R\$</p>";
    echo '<p>Seu salário é $salário R\$</p>';
    echo <<< FRASE
        Estou estudando
        PHP no Brasil $nome PAIS

    FRASE;
?>