<?php
session_start();
require 'conecta.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
$mensagem = "";

if (isset($_GET['msg']) && $_GET['msg'] == 'excluido') {
    $mensagem = "<div class='alerta sucesso'>🗑️ Gasto excluído com sucesso!</div>";
}

if (isset($_GET['excluir'])) {
    $id_excluir = (int)$_GET['excluir'];
    $stmt_del = $pdo->prepare("DELETE FROM gastos WHERE id = ? AND id_usuario = ?");
    $stmt_del->execute([$id_excluir, $id_usuario]);
    header("Location: inserir_gasto.php?msg=excluido");
    exit;
}

$gasto_edit = null;
if (isset($_GET['editar'])) {
    $id_editar = (int)$_GET['editar'];
    $stmt_ed = $pdo->prepare("SELECT * FROM gastos WHERE id = ? AND id_usuario = ?");
    $stmt_ed->execute([$id_editar, $id_usuario]);
    $gasto_edit = $stmt_ed->fetch();
}

function formatarTipoParaBanco($string) {
    $mapa = array(
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i',
        'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ü' => 'u', 'ç' => 'c',
        'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'É' => 'E', 'Ê' => 'E', 'Í' => 'I',
        'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ç' => 'C'
    );
    return strtoupper(strtr($string, $mapa));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome_gasto = $_POST['nome_gasto'];
    $valor_limpo = preg_replace('/[^0-9,]/', '', $_POST['valor']);
    $valor_final = str_replace(',', '.', $valor_limpo);
    $data_gasto = $_POST['data_gasto'];
    $tipo = formatarTipoParaBanco($_POST['tipo']);
    $observacao = !empty($_POST['observacao']) ? trim($_POST['observacao']) : null;

    $id_atualizar = !empty($_POST['id_editar']) ? (int)$_POST['id_editar'] : null;

    try {
        if ($id_atualizar) {
            $stmt = $pdo->prepare("UPDATE gastos SET nome = ?, valor = ?, data_gasto = ?, tipo = ?, observacao = ? WHERE id = ? AND id_usuario = ?");
            $stmt->execute([$nome_gasto, $valor_final, $data_gasto, $tipo, $observacao, $id_atualizar, $id_usuario]);
            $mensagem = "<div class='alerta sucesso'>✏️ Gasto atualizado com sucesso!</div>";
            $gasto_edit = null; 
        } else {
            $is_conjunto = isset($_POST['is_conjunto']) ? 1 : 0;
            $id_usuario_conjunto = ($is_conjunto && !empty($_POST['id_usuario_conjunto'])) ? $_POST['id_usuario_conjunto'] : null;
            $status_conjunto = $is_conjunto ? 'pendente' : 'solo';

            $stmt = $pdo->prepare("INSERT INTO gastos (id_usuario, nome, valor, data_gasto, tipo, is_conjunto, id_usuario_conjunto, status_conjunto, observacao) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_usuario, $nome_gasto, $valor_final, $data_gasto, $tipo, $is_conjunto, $id_usuario_conjunto, $status_conjunto, $observacao]);
            $mensagem = "<div class='alerta sucesso'>✅ Gasto registrado!</div>";
        }
    } catch (Exception $e) {
        $mensagem = "<div class='alerta erro'>❌ Erro: " . $e->getMessage() . "</div>";
    }
}

$stmt_users = $pdo->prepare("SELECT id, nome, email FROM usuarios WHERE id != ?");
$stmt_users->execute([$id_usuario]);
$outros_usuarios = $stmt_users->fetchAll();

$stmt_hist = $pdo->prepare("
    SELECT g.*, u.nome as nome_conjunto, u.email as email_conjunto 
    FROM gastos g 
    LEFT JOIN usuarios u ON g.id_usuario_conjunto = u.id 
    WHERE g.id_usuario = ? 
    ORDER BY g.data_gasto DESC, g.id DESC LIMIT 10
");
$stmt_hist->execute([$id_usuario]);
$historico = $stmt_hist->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inserir Gasto - Organiza</title>
    <link rel="stylesheet" href="css/dashboard.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <h3>Organiza</h3>
            <a href="dashboard.php">Visão Geral</a>
            <a href="inserir_gasto.php" class="ativo">Inserir Gastos</a>
            <a href="inserir_parcela.php">Inserir Parcelas</a>
            <a href="inserir_assinatura.php">Assinaturas Fixas</a>
            <a href="notificacoes.php">Notificações</a>
            <a href="perfil.php">Meu Perfil</a>
            <a href="sair.php" style="margin-top: auto; color: #e74c3c;">Sair</a>
        </aside>

        <main class="main-content">
            <h2><?php echo $gasto_edit ? "Editando Gasto ✏️" : "Gerenciar Gastos 💸"; ?></h2>
            <?php echo $mensagem; ?>

            <div class="layout-grid">
                <div class="form-box">
                    <form method="POST" action="inserir_gasto.php">
                        
                        <input type="hidden" name="id_editar" value="<?php echo $gasto_edit ? $gasto_edit['id'] : ''; ?>">

                        <div class="input-group">
                            <label>O que foi comprado?</label>
                            <input type="text" name="nome_gasto" required placeholder="Ex: Mercado" value="<?php echo $gasto_edit ? htmlspecialchars($gasto_edit['nome']) : ''; ?>">
                        </div>
                        <div class="input-group">
                            <label>Valor</label>
                            <?php 
                                $valor_exibicao = $gasto_edit ? number_format($gasto_edit['valor'], 2, ',', '.') : ''; 
                            ?>
                            <input type="text" name="valor" id="valor" required placeholder="R$ 0,00" value="<?php echo $valor_exibicao; ?>">
                        </div>
                        <div class="input-group">
                            <label>Data</label>
                            <input type="date" name="data_gasto" required value="<?php echo $gasto_edit ? $gasto_edit['data_gasto'] : date('Y-m-d'); ?>">
                        </div>
                        <div class="input-group">
                            <label>Categoria</label>
                            <input type="text" name="tipo" required placeholder="Ex: Alimentação" value="<?php echo $gasto_edit ? mb_convert_case($gasto_edit['tipo'], MB_CASE_TITLE, "UTF-8") : ''; ?>">
                        </div>
                        
                        <div class="input-group">
                            <label>Observação (Opcional)</label>
                            <textarea name="observacao" rows="2" placeholder="Detalhes extras sobre a compra..."><?php echo $gasto_edit ? htmlspecialchars($gasto_edit['observacao']) : ''; ?></textarea>
                        </div>

                        <?php if(!$gasto_edit): ?>
                            <div class="checkbox-group">
                                <input type="checkbox" id="check_conjunto" name="is_conjunto"> 
                                <label for="check_conjunto">Dividir gasto?</label>
                            </div>
                            <div class="input-group" id="div_usuario_conjunto" style="display:none; margin-top: 10px;">
                                <select name="id_usuario_conjunto">
                                    <option value="">Com quem?</option>
                                    <?php foreach($outros_usuarios as $u) echo "<option value='{$u['id']}'>".($u['nome'] ?? $u['email'])."</option>"; ?>
                                </select>
                            </div>
                        <?php else: ?>
                            <div style="margin-bottom: 15px;">
                                <small style="color: #e67e22;">Nota: Não é possível alterar a divisão de um gasto já criado. Se precisar, exclua e crie um novo.</small><br>
                                <a href="inserir_gasto.php" style="font-size: 14px; color: #3498db; text-decoration: underline;">Cancelar Edição</a>
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="btn-salvar"><?php echo $gasto_edit ? "Salvar Alterações" : "Salvar Gasto"; ?></button>
                    </form>
                </div>

                <div class="history-box">
                    <h3>Últimos Gastos</h3>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Gasto</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                    <th>Ações</th> </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historico as $h): ?>
                                <tr>
                                    <td><?php echo date('d/m', strtotime($h['data_gasto'])); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($h['nome']); ?>
                                        <?php if(!empty($h['observacao'])): ?>
                                            <span title="<?php echo htmlspecialchars($h['observacao']); ?>">📝</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>R$ <?php echo number_format($h['valor'], 2, ',', '.'); ?></td>
                                    <td>
                                        <?php 
                                            $status_label = $h['status_conjunto'];
                                            $badge_class = "status-" . $status_label;
                                            $exibir_status = $status_label;
                                            
                                            if($status_label == 'pendente') {
                                                $exibir_status = "Aguardando";
                                            } elseif ($status_label == 'aceito') {
                                                $exibir_status = "🤝 Dividido";
                                                $badge_class = "status-divisao";
                                            }
                                        ?>
                                        <span class="status-badge <?php echo $badge_class; ?>">
                                            <?php echo $exibir_status; ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <a href="inserir_gasto.php?editar=<?php echo $h['id']; ?>" style="text-decoration: none; font-size: 16px; margin-right: 10px;" title="Editar">✏️</a>
                                        <a href="inserir_gasto.php?excluir=<?php echo $h['id']; ?>" onclick="return confirm('Tem certeza que deseja apagar este gasto?')" style="text-decoration: none; font-size: 16px;" title="Excluir">🗑️</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
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

        const checkConjunto = document.getElementById('check_conjunto');
        if(checkConjunto) {
            checkConjunto.addEventListener('change', function() {
                document.getElementById('div_usuario_conjunto').style.display = this.checked ? 'block' : 'none';
            });
        }
    </script>
</body>
</html>