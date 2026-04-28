<?php
session_start();
require 'conecta.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
$mensagem = "";

$hoje = date('Y-m-d');
$stmt_vencidas = $pdo->prepare("SELECT * FROM parcelas WHERE id_usuario = ? AND parcelas_pagas < total_parcelas AND data_proxima_parcela <= ?");
$stmt_vencidas->execute([$id_usuario, $hoje]);
$parcelas_vencidas = $stmt_vencidas->fetchAll();

foreach ($parcelas_vencidas as $pv) {
    $id_p = $pv['id'];
    $data_prox = $pv['data_proxima_parcela'];
    $pagas = $pv['parcelas_pagas'];
    $total = $pv['total_parcelas'];

    while (strtotime($data_prox) <= strtotime($hoje) && $pagas < $total) {
        $pagas++;
        if ($pagas < $total) {
            $data_prox = date('Y-m-d', strtotime("+1 month", strtotime($data_prox)));
        } else {
            break; 
        }
    }
    $stmt_upd = $pdo->prepare("UPDATE parcelas SET parcelas_pagas = ?, data_proxima_parcela = ? WHERE id = ?");
    $stmt_upd->execute([$pagas, $data_prox, $id_p]);
}

if (isset($_GET['msg']) && $_GET['msg'] == 'excluido') {
    $mensagem = "<div class='alerta sucesso'>🗑️ Parcela excluída com sucesso!</div>";
}

if (isset($_GET['excluir'])) {
    $id_excluir = (int)$_GET['excluir'];
    $stmt_del = $pdo->prepare("DELETE FROM parcelas WHERE id = ? AND id_usuario = ?");
    $stmt_del->execute([$id_excluir, $id_usuario]);
    header("Location: inserir_parcela.php?msg=excluido");
    exit;
}

$parcela_edit = null;
if (isset($_GET['editar'])) {
    $id_editar = (int)$_GET['editar'];
    $stmt_ed = $pdo->prepare("SELECT * FROM parcelas WHERE id = ? AND id_usuario = ?");
    $stmt_ed->execute([$id_editar, $id_usuario]);
    $parcela_edit = $stmt_ed->fetch();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'];
    $valor_limpo = preg_replace('/[^0-9,]/', '', $_POST['valor_parcela']);
    $valor_final = str_replace(',', '.', $valor_limpo);

    $total_parcelas = (int)$_POST['total_parcelas'];
    $data_proxima_parcela = $_POST['data_proxima_parcela'];
    
    $is_antiga = isset($_POST['is_antiga']) ? true : false;
    $parcelas_pagas = ($is_antiga && !empty($_POST['parcelas_pagas'])) ? (int)$_POST['parcelas_pagas'] : 0;

    $id_atualizar = !empty($_POST['id_editar']) ? (int)$_POST['id_editar'] : null;

    if ($parcelas_pagas >= $total_parcelas) {
        $mensagem = "<div class='alerta erro'>❌ Erro: O número de parcelas pagas não pode ser maior ou igual ao total.</div>";
    } else {
        try {
            if ($id_atualizar) {
                $stmt = $pdo->prepare("UPDATE parcelas SET nome = ?, valor_parcela = ?, total_parcelas = ?, parcelas_pagas = ?, data_proxima_parcela = ? WHERE id = ? AND id_usuario = ?");
                $stmt->execute([$nome, $valor_final, $total_parcelas, $parcelas_pagas, $data_proxima_parcela, $id_atualizar, $id_usuario]);
                $mensagem = "<div class='alerta sucesso'>✏️ Parcela atualizada com sucesso!</div>";
                $parcela_edit = null; 
            } else {
                $stmt = $pdo->prepare("INSERT INTO parcelas (id_usuario, nome, valor_parcela, total_parcelas, parcelas_pagas, data_proxima_parcela) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$id_usuario, $nome, $valor_final, $total_parcelas, $parcelas_pagas, $data_proxima_parcela]);
                $mensagem = "<div class='alerta sucesso'>✅ Compra parcelada registrada com sucesso!</div>";
            }
        } catch (Exception $e) {
            $mensagem = "<div class='alerta erro'>❌ Erro ao salvar: " . $e->getMessage() . "</div>";
        }
    }
}

$stmt_user = $pdo->prepare("SELECT dia_reset FROM usuarios WHERE id = ?");
$stmt_user->execute([$id_usuario]);
$dia_reset = $stmt_user->fetch()['dia_reset'] ?? 1;

$dia_hoje = (int)date('d');
if ($dia_hoje < $dia_reset) {
    $mes_atual = date('m', strtotime("-1 month"));
    $ano_atual = date('Y', strtotime("-1 month"));
} else {
    $mes_atual = date('m');
    $ano_atual = date('Y');
}
$data_inicio = "$ano_atual-$mes_atual-" . str_pad($dia_reset, 2, "0", STR_PAD_LEFT);
$data_fim = date('Y-m-d', strtotime("+1 month -1 day", strtotime($data_inicio)));

$stmt_hist = $pdo->prepare("
    SELECT * FROM parcelas 
    WHERE id_usuario = ? AND parcelas_pagas < total_parcelas
    ORDER BY data_proxima_parcela ASC
");
$stmt_hist->execute([$id_usuario]);
$historico = $stmt_hist->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inserir Parcelas - Organiza</title>
    <link rel="stylesheet" href="css/dashboard.css?v=<?php echo time(); ?>">
    <style>
        .progress-text { font-weight: bold; color: #2c3e50; }
        .progress-bar-bg { background: #ecf0f1; border-radius: 10px; height: 8px; width: 100%; margin-top: 5px; overflow: hidden; }
        .progress-bar-fill { background: #27ae60; height: 100%; transition: width 0.3s; }
        .filtros-box { background: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <h3>Organiza</h3>
            <a href="dashboard.php">Visão Geral</a>
            <a href="inserir_gasto.php">Inserir Gastos</a>
            <a href="inserir_parcela.php" class="ativo" >Inserir Parcelas</a>
            <a href="inserir_assinatura.php">Assinaturas Fixas</a>
            <a href="familia.php">Família 🤝</a>
            <a href="notificacoes.php">Notificações</a>
            <a href="perfil.php">Meu Perfil</a>
            <a href="sair.php" style="margin-top: auto; color: #e74c3c;">Sair</a>
        </aside>

        <main class="main-content">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                <h2 style="margin: 0;"><?php echo $parcela_edit ? "Editando Parcela ✏️" : "Gerenciar Compras Parceladas 💳"; ?></h2>
                <div style="text-align: right;">
                    <small style="color: #888;">Ciclo financeiro atual:</small><br>
                    <strong><?php echo date('d/m', strtotime($data_inicio)); ?> até <?php echo date('d/m', strtotime($data_fim)); ?></strong>
                </div>
            </div>
            
            <?php echo $mensagem; ?>

            <div class="layout-grid">
                
                <div class="form-box">
                    <form method="POST" action="inserir_parcela.php">
                        
                        <input type="hidden" name="id_editar" value="<?php echo $parcela_edit ? $parcela_edit['id'] : ''; ?>">

                        <div class="input-group">
                            <label>O que foi comprado?</label>
                            <input type="text" name="nome" required placeholder="Ex: TV Smart 50 polegadas" value="<?php echo $parcela_edit ? htmlspecialchars($parcela_edit['nome']) : ''; ?>">
                        </div>

                        <div style="display: flex; gap: 15px;">
                            <div class="input-group" style="flex: 1;">
                                <label>Valor da Parcela</label>
                                <?php $valor_exibicao = $parcela_edit ? number_format($parcela_edit['valor_parcela'], 2, ',', '.') : ''; ?>
                                <input type="text" name="valor_parcela" id="valor_parcela" required placeholder="R$ 0,00" value="<?php echo $valor_exibicao; ?>">
                            </div>
                            <div class="input-group" style="flex: 1;">
                                <label>Total de Parcelas (Ex: 12)</label>
                                <input type="number" name="total_parcelas" required min="2" placeholder="Qtd" value="<?php echo $parcela_edit ? $parcela_edit['total_parcelas'] : ''; ?>">
                            </div>
                        </div>

                        <div class="input-group">
                            <label>Vencimento da Próxima Parcela</label>
                            <input type="date" name="data_proxima_parcela" required value="<?php echo $parcela_edit ? $parcela_edit['data_proxima_parcela'] : date('Y-m-d'); ?>">
                        </div>

                        <?php 
                            $tem_pagas = ($parcela_edit && $parcela_edit['parcelas_pagas'] > 0);
                        ?>
                        <div class="checkbox-group">
                            <input type="checkbox" id="check_antiga" name="is_antiga" <?php echo $tem_pagas ? 'checked' : ''; ?>> 
                            <label for="check_antiga">Já comecei a pagar essa compra (Fatura antiga)</label>
                        </div>

                        <div class="input-group" id="div_parcelas_pagas" style="display: <?php echo $tem_pagas ? 'block' : 'none'; ?>; background: #f9f9f9; padding: 15px; border-radius: 5px; border: 1px dashed #ccc;">
                            <label>Quantas parcelas você JÁ PAGOU?</label>
                            <input type="number" name="parcelas_pagas" min="1" placeholder="Ex: 2" value="<?php echo $tem_pagas ? $parcela_edit['parcelas_pagas'] : ''; ?>">
                            <small style="color: #e67e22; display: block; margin-top: 5px;">
                                O sistema começará a cobrar a partir da próxima.
                            </small>
                        </div>

                        <?php if($parcela_edit): ?>
                            <div style="margin-bottom: 15px; text-align: right;">
                                <a href="inserir_parcela.php" style="font-size: 14px; color: #3498db; text-decoration: underline;">Cancelar Edição</a>
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="btn-salvar"><?php echo $parcela_edit ? "Salvar Alterações" : "Salvar Compra Parcelada"; ?></button>
                    </form>
                </div>

                <div class="history-box">
                    <h3>Parcelas Ativas</h3>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Compra</th>
                                    <th>Valor (Mês)</th>
                                    <th>Progresso</th>
                                    <th>Próx. Vencimento</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($historico) === 0): ?>
                                    <tr><td colspan="5" style="text-align:center; padding: 20px; color:#7f8c8d;">Nenhuma parcela ativa no momento.</td></tr>
                                <?php endif; ?>
                                
                                <?php foreach ($historico as $p): ?>
                                <?php 
                                    $pagas = $p['parcelas_pagas'];
                                    $total = $p['total_parcelas'];
                                    $porcentagem = ($pagas / $total) * 100;
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($p['nome']); ?></strong></td>
                                    <td>R$ <?php echo number_format($p['valor_parcela'], 2, ',', '.'); ?></td>
                                    <td style="min-width: 100px;">
                                        <span class="progress-text"><?php echo $pagas; ?> de <?php echo $total; ?></span>
                                        <div class="progress-bar-bg">
                                            <div class="progress-bar-fill" style="width: <?php echo $porcentagem; ?>%;"></div>
                                        </div>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($p['data_proxima_parcela'])); ?></td>
                                    <td style="text-align: center; min-width: 70px;">
                                        <a href="inserir_parcela.php?editar=<?php echo $p['id']; ?>" style="text-decoration: none; font-size: 16px; margin-right: 10px;" title="Editar">✏️</a>
                                        <a href="inserir_parcela.php?excluir=<?php echo $p['id']; ?>" onclick="return confirm('Tem certeza que deseja apagar esta compra parcelada?')" style="text-decoration: none; font-size: 16px;" title="Excluir">🗑️</a>
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
        const inputValor = document.getElementById('valor_parcela');
        
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

        const checkAntiga = document.getElementById('check_antiga');
        const divParcelasPagas = document.getElementById('div_parcelas_pagas');
        const inputParcelasPagas = document.querySelector('input[name="parcelas_pagas"]');

        if(checkAntiga) {
            checkAntiga.addEventListener('change', function() {
                if (this.checked) {
                    divParcelasPagas.style.display = 'block';
                    inputParcelasPagas.required = true;
                } else {
                    divParcelasPagas.style.display = 'none';
                    inputParcelasPagas.required = false;
                    inputParcelasPagas.value = '';
                }
            });
        }
    </script>
</body>
</html>