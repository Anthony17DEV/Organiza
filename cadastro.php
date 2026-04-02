<?php
require 'conecta.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO usuarios (email, senha) VALUES (?, ?)");
    try {
        $stmt->execute([$email, $senha]);
        $sucesso = "Conta criada com sucesso!";
    } catch (Exception $e) {
        $erro = "Erro ao cadastrar. O e-mail já pode estar em uso.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - Organiza</title>
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="container">
        <h2>Nova Conta</h2>
        
        <?php 
            if(isset($erro)) echo "<div class='alerta erro'>$erro</div>"; 
            if(isset($sucesso)) echo "<div class='alerta sucesso'>$sucesso</div>"; 
        ?>
        
        <form method="POST">
            <div class="input-group">
                <label>E-mail</label>
                <input type="email" name="email" required placeholder="seu@email.com">
            </div>
            <div class="input-group">
                <label>Senha</label>
                <input type="password" name="senha" required placeholder="Crie uma senha forte">
            </div>
            <button type="submit">Cadastrar</button>
        </form>
        <p>Já tem uma conta? <a href="index.php">Faça login</a></p>
    </div>
</body>
</html>