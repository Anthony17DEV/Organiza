<?php
session_start();
require 'conecta.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $senha = $_POST['senha'];

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($senha, $usuario['senha'])) {
        $_SESSION['usuario_id'] = $usuario['id'];
        header("Location: dashboard.php");
        exit;
    } else {
        $erro = "E-mail ou senha incorretos!";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Organiza</title>
    <link rel="stylesheet" href="css/login.css">
<body>
    <div class="container">
        <h2>Entrar no Organiza</h2>
        
        <?php if(isset($erro)) echo "<div class='alerta erro'>$erro</div>"; ?>
        
        <form method="POST">
            <div class="input-group">
                <label>E-mail</label>
                <input type="email" name="email" required placeholder="seu@email.com">
            </div>
            <div class="input-group">
                <label>Senha</label>
                <input type="password" name="senha" required placeholder="••••••••">
            </div>
            <button type="submit">Entrar</button>
        </form>
        <p>Não tem uma conta? <a href="cadastro.php">Crie agora</a></p>
    </div>
</body>
</html>