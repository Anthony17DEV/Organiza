<?php
session_start();
require 'conecta.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
$mensagem = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enviar_convite'])) {
    $email_convidado = trim($_POST['email']);

    $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE email = ?");
    $stmt->execute([$email_convidado]);
    $convidado = $stmt->fetch();

    if (!$convidado) {
        $mensagem = "<div class='alerta erro'>❌ Erro: Este e-mail não está registado no sistema.</div>";
    } elseif ($convidado['id'] == $id_usuario) {
        $mensagem = "<div class='alerta erro'>🤔 Não podes convidar-te a ti mesmo!</div>";
    } else {
        $id_membro = $convidado['id'];
        
        $stmt_check = $pdo->prepare("SELECT * FROM familia WHERE (id_usuario = ? AND id_membro = ?) OR (id_usuario = ? AND id_membro = ?)");
        $stmt_check->execute([$id_usuario, $id_membro, $id_membro, $id_usuario]);
        
        if ($stmt_check->rowCount() > 0) {
            $mensagem = "<div class='alerta erro'>⚠️ Já existe um convite ou vínculo com esta pessoa.</div>";
        } else {
            $stmt_ins = $pdo->prepare("INSERT INTO familia (id_usuario, id_membro) VALUES (?, ?)");
            $stmt_ins->execute([$id_usuario, $id_membro]);
            $mensagem = "<div class='alerta sucesso'>📩 Convite enviado com sucesso!</div>";
        }
    }
}

$stmt_fam = $pdo->prepare("
    SELECT u.id, u.nome, u.email, u.foto 
    FROM usuarios u
    JOIN familia f ON (f.id_usuario = u.id OR f.id_membro = u.id)
    WHERE (f.id_usuario = ? OR f.id_membro = ?) 
      AND f.status = 'aceito' 
      AND u.id != ?
");
$stmt_fam->execute([$id_usuario, $id_usuario, $id_usuario]);
$membros = $stmt_fam->fetchAll();

$stmt_sent = $pdo->prepare("
    SELECT u.nome, u.email, f.data_convite 
    FROM familia f 
    JOIN usuarios u ON f.id_membro = u.id 
    WHERE f.id_usuario = ? AND f.status = 'pendente'
");
$stmt_sent->execute([$id_usuario]);
$convites_enviados = $stmt_sent->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Família - Organiza</title>
    <link rel="stylesheet" href="css/dashboard.css?v=<?php echo time(); ?>">
    <style>
        .membro-card { display: flex; align-items: center; gap: 15px; padding: 15px; background: #fdfdfd; border-radius: 8px; border: 1px solid #eee; margin-bottom: 12px; transition: 0.3s; }
        .membro-card:hover { border-color: #27ae60; background: #fff; }
        .membro-foto { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #ecf0f1; }
        .membro-info h4 { margin: 0; color: #2c3e50; font-size: 16px; }
        .membro-info p { margin: 2px 0 0; color: #7f8c8d; font-size: 13px; word-break: break-all; }
        .convite-pendente-item { padding: 12px; border-bottom: 1px solid #f1f1f1; font-size: 14px; }
        .convite-pendente-item:last-child { border-bottom: none; }
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
            <a href="familia.php" class="ativo">Família 🤝</a>
            <a href="notificacoes.php">Notificações</a>
            <a href="perfil.php">Meu Perfil</a>
            <a href="sair.php" style="margin-top: auto; color: #e74c3c;">Sair</a>
        </aside>

        <main class="main-content">
            <div style="margin-bottom: 25px;">
                <h2 style="margin: 0;">Minha Família 🤝</h2>
                <p style="color: #7f8c8d; font-size: 14px;">Gere o seu círculo de confiança para partilha de despesas.</p>
            </div>

            <?php echo $mensagem; ?>

            <div class="layout-grid">
                <div class="form-box">
                    <h3>Convidar novo membro</h3>
                    <form method="POST" style="margin-top: 20px;">
                        <div class="input-group">
                            <label>E-mail do utilizador</label>
                            <input type="email" name="email" required placeholder="exemplo@email.com">
                            <small style="color: #888; display: block; margin-top: 5px;">A pessoa precisa de ter uma conta no Organiza.</small>
                        </div>
                        <button type="submit" name="enviar_convite" class="btn-salvar">Enviar Convite</button>
                    </form>

                    <?php if (count($convites_enviados) > 0): ?>
                        <div style="margin-top: 30px;">
                            <h4 style="color: #7f8c8d; margin-bottom: 10px; font-size: 14px; text-transform: uppercase;">Convites a aguardar resposta</h4>
                            <div style="background: #fcfcfc; border-radius: 8px; border: 1px solid #eee;">
                                <?php foreach($convites_enviados as $ce): ?>
                                    <div class="convite-pendente-item">
                                        <strong><?php echo htmlspecialchars($ce['nome']); ?></strong><br>
                                        <span style="color: #999; font-size: 12px;"><?php echo $ce['email']; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="history-box">
                    <h3>Membros Ativos</h3>
                    <div style="margin-top: 20px;">
                        <?php if (count($membros) == 0): ?>
                            <div style="text-align: center; padding: 40px 20px;">
                                <div style="font-size: 40px; margin-bottom: 10px;">🏘️</div>
                                <p style="color: #bdc3c7;">Ainda não tens ninguém na tua família.<br>Convida alguém para começar a dividir gastos!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($membros as $m): ?>
                                <div class="membro-card">
                                    <img src="<?php echo !empty($m['foto']) ? 'uploads/'.$m['foto'] : 'https://cdn-icons-png.flaticon.com/512/149/149071.png'; ?>" class="membro-foto">
                                    <div class="membro-info">
                                        <h4><?php echo htmlspecialchars($m['nome']); ?></h4>
                                        <p><?php echo htmlspecialchars($m['email']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>