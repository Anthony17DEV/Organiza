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
<html>
<head>
    <title>Login - Organiza</title>
</head>
<body>
    <h2>Login</h2>
    <?php if(isset($erro)) echo "<p style='color:red;'>$erro</p>"; ?>
    <form method="POST">
        <label>E-mail:</label><br>
        <input type="email" name="email" required><br><br>
        <label>Senha:</label><br>
        <input type="password" name="senha" required><br><br>
        <button type="submit">Entrar</button>
    </form>
    <p><a href="cadastro.php">Criar nova conta</a></p>
</body>
</html>