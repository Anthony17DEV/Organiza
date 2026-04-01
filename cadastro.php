<?php
require 'conecta.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT); 

    $stmt = $pdo->prepare("INSERT INTO usuarios (email, senha) VALUES (?, ?)");
    try {
        $stmt->execute([$email, $senha]);
        $mensagem = "Cadastro realizado com sucesso! <a href='index.php'>Faça login</a>";
    } catch (Exception $e) {
        $mensagem = "Erro ao cadastrar. O email já pode estar em uso.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Cadastro - Organiza</title>
</head>
<body>
    <h2>Criar Conta</h2>
    <?php if(isset($mensagem)) echo "<p>$mensagem</p>"; ?>
    <form method="POST">
        <label>E-mail:</label><br>
        <input type="email" name="email" required><br><br>
        <label>Senha:</label><br>
        <input type="password" name="senha" required><br><br>
        <button type="submit">Cadastrar</button>
    </form>
    <p><a href="index.php">Já tenho uma conta</a></p>
</body>
</html>