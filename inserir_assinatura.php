<?php
session_start();
require 'conecta.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
$mensagem = "";

if (isset($_GET['excluir'])) {
    $id_excluir = (int)$_GET['excluir'];
    $stmt = $pdo->prepare("DELETE FROM assinaturas WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$id_excluir, $id_usuario]);
    header("Location: inserir_assinatura.php?msg=excluido");
    exit;
}

$edit = null;
if (isset($_GET['editar'])) {
    $id_editar = (int)$_GET['editar'];
    $stmt = $pdo->prepare("SELECT * FROM assinaturas WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$id_editar, $id_usuario]);
    $edit = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'];
    $valor_limpo = preg_replace('/[^0-9,]/', '', $_POST['valor']);
    $valor_final = str_replace(',', '.', $valor_limpo);
    $dia = (int)$_POST['dia_vencimento'];
    $categoria = $_POST['categoria'];
    $id_atualizar = !empty($_POST['id_editar']) ? (int)$_POST['id_editar'] : null;

    if ($id_atualizar) {
        $stmt = $pdo->prepare("UPDATE assinaturas SET nome = ?, valor = ?, dia_vencimento = ?, categoria = ? WHERE id = ? AND id_usuario = ?");
        $stmt->execute([$nome, $valor_final, $dia, $categoria, $id_atualizar, $id_usuario]);
        $mensagem = "<div class='alerta sucesso'>🔄 Assinatura atualizada!</div>";
        $edit = null;
    } else {
        $stmt = $pdo->prepare("INSERT INTO assinaturas (id_usuario, nome, valor, dia_vencimento, categoria) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$id_usuario, $nome, $valor_final, $dia, $categoria]);
        $mensagem = "<div class='alerta sucesso'>✅ Assinatura cadastrada!</div>";
    }
}

$stmt_list = $pdo->prepare("SELECT * FROM assinaturas WHERE id_usuario = ? ORDER BY dia_vencimento ASC");
$stmt_list->execute([$id_usuario]);
$assinaturas = $stmt_list->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assinaturas - Organiza</title>
    <link rel="stylesheet" href="css/dashboard.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <h3>Organiza</h3>
            <a href="dashboard.php">Visão Geral</a>
            <a href="inserir_gasto.php">Inserir Gastos</a>
            <a href="inserir_parcela.php">Inserir Parcelas</a>
            <a href="inserir_assinatura.php" class="ativo">Assinaturas Fixas</a>
            <a href="familia.php">Família 🤝</a>
            <a href="notificacoes.php">Notificações</a>
            <a href="perfil.php">Meu Perfil</a>
            <a href="sair.php" style="margin-top: auto; color: #e74c3c;">Sair</a>
        </aside>

        <main class="main-content">
            <h2><?php echo $edit ? "Editar Assinatura 📝" : "Assinaturas e Recorrências 🔄"; ?></h2>
            <?php echo $mensagem; ?>

            <div class="layout-grid">
                <div class="form-box">
                    <form method="POST">
                        <input type="hidden" name="id_editar" value="<?php echo $edit ? $edit['id'] : ''; ?>">
                        <div class="input-group">
                            <label>Nome do Serviço</label>
                            <input type="text" name="nome" required placeholder="Ex: Netflix, Internet" value="<?php echo $edit ? htmlspecialchars($edit['nome']) : ''; ?>">
                        </div>
                        <div class="input-group">
                            <label>Valor Mensal</label>
                            <input type="text" name="valor" id="valor" required placeholder="R$ 0,00" value="<?php echo $edit ? number_format($edit['valor'], 2, ',', '.') : ''; ?>">
                        </div>
                        <div class="input-group">
                            <label>Dia do Vencimento (1 a 31)</label>
                            <input type="number" name="dia_vencimento" min="1" max="31" required value="<?php echo $edit ? $edit['dia_vencimento'] : ''; ?>">
                        </div>
                        <div class="input-group">
                            <label>Categoria</label>
                            <input type="text" name="categoria" placeholder="Ex: Streaming, Contas Fixas" value="<?php echo $edit ? htmlspecialchars($edit['categoria']) : ''; ?>">
                        </div>
                        
                        <?php if($edit): ?>
                            <div style="margin-bottom: 15px; text-align: right;">
                                <a href="inserir_assinatura.php" style="font-size: 14px; color: #3498db; text-decoration: underline;">Cancelar Edição</a>
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="btn-salvar"><?php echo $edit ? "Salvar Alterações" : "Salvar Assinatura"; ?></button>
                    </form>
                </div>

                <div class="history-box">
                    <h3>Minhas Assinaturas</h3>
                    <div class="table-container"> 
                        <table>
                            <thead>
                                <tr>
                                    <th>Dia</th>
                                    <th>Serviço</th>
                                    <th>Valor</th>
                                    <th style="text-align: center;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($assinaturas as $a): ?>
                                <tr>
                                    <td>Dia <?php echo $a['dia_vencimento']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($a['nome']); ?></strong></td>
                                    <td>R$ <?php echo number_format($a['valor'], 2, ',', '.'); ?></td>
                                    <td style="text-align: center;">
                                        <a href="inserir_assinatura.php?editar=<?php echo $a['id']; ?>" style="text-decoration: none; margin-right: 10px; font-size: 1.1rem;">✏️</a>
                                        <a href="inserir_assinatura.php?excluir=<?php echo $a['id']; ?>" onclick="return confirm('Apagar assinatura?')" style="text-decoration: none; font-size: 1.1rem;">🗑️</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(count($assinaturas) == 0): ?>
                                    <tr><td colspan="4" style="text-align:center;">Nenhuma assinatura cadastrada.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
        const inputValor = document.getElementById('valor');
        
        if(inputValor.value) {
            let value = inputValor.value.replace(/\D/g, '');
            value = (value / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            inputValor.value = value;
        }

        inputValor.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            value = (value / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            e.target.value = value;
        });
    </script>
</body>
</html>