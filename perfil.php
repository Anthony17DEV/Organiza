<?php
session_start();
require 'conecta.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
$mensagem = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    
    $limite_limpo = preg_replace('/[^0-9,]/', '', $_POST['limite_mensal']);
    $limite_final = str_replace(',', '.', $limite_limpo);

    $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, limite_mensal = ? WHERE id = ?");
    $stmt->execute([$nome, $email, $limite_final, $id_usuario]);

    if (!empty($_POST['senha'])) {
        $senha_hash = password_hash($_POST['senha'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
        $stmt->execute([$senha_hash, $id_usuario]);
    }

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === 0) {
        $extensao = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $novo_nome_foto = md5(time()) . '.' . $extensao; 
        $destino = 'uploads/' . $novo_nome_foto;

        if (move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
            $stmt = $pdo->prepare("UPDATE usuarios SET foto = ? WHERE id = ?");
            $stmt->execute([$novo_nome_foto, $id_usuario]);
        }
    }
    
    $mensagem = "<div class='alerta sucesso'>Perfil atualizado com sucesso!</div>";
}

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$id_usuario]);
$usuario = $stmt->fetch();

$caminho_foto = !empty($usuario['foto']) ? 'uploads/' . $usuario['foto'] : 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Organiza</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .perfil-form { max-width: 500px; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; font-weight: bold; margin-bottom: 5px; color: #555; }
        .input-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; }
        .btn-salvar { background: #27ae60; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; width: 100%; }
        .foto-preview { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin-bottom: 15px; border: 3px solid #27ae60; }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        
        <aside class="sidebar">
            <h3>Organiza</h3>
            <a href="dashboard.php">Visão Geral</a>
            <a href="inserir_gasto.php">Inserir Gastos</a>
            <a href="inserir_parcela.php">Inserir Parcelas</a>
            <a href="inserir_assinatura.php">Assinaturas Fixas</a>
            <a href="notificacoes.php">Notificações</a>
            <a href="perfil.php" class="ativo">Meu Perfil</a>
            <a href="sair.php" style="margin-top: auto; color: #e74c3c;">Sair</a>
        </aside>

        <main class="main-content">
            <h2>Meu Perfil ⚙️</h2>
            
            <?php echo $mensagem; ?>

            <div class="perfil-form">
                <form method="POST" enctype="multipart/form-data">
                    
                    <div style="text-align: center;">
                        <img src="<?php echo $caminho_foto; ?>" alt="Foto de Perfil" class="foto-preview"><br>
                        <input type="file" name="foto" accept="image/*" style="margin-bottom: 15px;">
                    </div>

                    <div class="input-group">
                        <label>Nome de Exibição</label>
                        <input type="text" name="nome" value="<?php echo htmlspecialchars($usuario['nome'] ?? ''); ?>" placeholder="Seu nome">
                    </div>

                    <div class="input-group">
                        <label>Quanto você pode gastar por mês? (Limite)</label>
                        <input type="text" name="limite_mensal" id="limite_mensal" 
                            value="<?php echo number_format($usuario['limite_mensal'], 2, ',', '.'); ?>" placeholder="R$ 0,00">
                    </div>

                    <div class="input-group">
                        <label>E-mail</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                    </div>

                    <div class="input-group">
                        <label>Nova Senha (deixe em branco para não alterar)</label>
                        <input type="password" name="senha" placeholder="Nova senha">
                    </div>

                    <button type="submit" class="btn-salvar">Salvar Alterações</button>
                </form>
            </div>
        </main>
    </div>
<script>
    const inputLimite = document.getElementById('limite_mensal');
    inputLimite.addEventListener('input', (e) => {
        let value = e.target.value.replace(/\D/g, '');
        value = (value / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        e.target.value = value;
    });
</script>
</body>
</html>