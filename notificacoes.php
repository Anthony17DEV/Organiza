<?php
session_start();
require 'conecta.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
$mensagem = "";

if (isset($_GET['msg']) && $_GET['msg'] == 'limpo') {
    $mensagem = "<div class='alerta sucesso'>🧹 Histórico de atividades limpo com sucesso!</div>";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['acao']) && $_POST['acao'] == 'limpar_logs') {
        $stmt_max_g = $pdo->prepare("SELECT MAX(id) FROM gastos WHERE id_usuario = ? OR id_usuario_conjunto = ?");
        $stmt_max_g->execute([$id_usuario, $id_usuario]);
        $max_gasto = $stmt_max_g->fetchColumn() ?: 0;

        $stmt_max_p = $pdo->prepare("SELECT MAX(id) FROM parcelas WHERE id_usuario = ?");
        $stmt_max_p->execute([$id_usuario]);
        $max_parcela = $stmt_max_p->fetchColumn() ?: 0;

        $stmt_upd = $pdo->prepare("UPDATE usuarios SET ultimo_id_gasto_visto = ?, ultimo_id_parcela_visto = ? WHERE id = ?");
        $stmt_upd->execute([$max_gasto, $max_parcela, $id_usuario]);
        
        header("Location: notificacoes.php?msg=limpo");
        exit;
    }
    
    if (isset($_POST['acao_familia']) && isset($_POST['id_familia'])) {
        $id_familia = $_POST['id_familia'];
        $acao_fam = $_POST['acao_familia'];

        if ($acao_fam == 'aceitar') {
            $stmt = $pdo->prepare("UPDATE familia SET status = 'aceito' WHERE id = ? AND id_membro = ?");
            $stmt->execute([$id_familia, $id_usuario]);
            $mensagem = "<div class='alerta sucesso'>✅ Agora você tem um novo membro na sua família!</div>";
        } else {
            $stmt = $pdo->prepare("UPDATE familia SET status = 'recusado' WHERE id = ? AND id_membro = ?");
            $stmt->execute([$id_familia, $id_usuario]);
            $mensagem = "<div class='alerta sucesso'>🚫 Convite de família recusado.</div>";
        }
    }
    
    if (isset($_POST['acao']) && isset($_POST['id_gasto'])) {
        $id_gasto = $_POST['id_gasto'];
        $acao_gasto = $_POST['acao']; 
        
        $stmt_check = $pdo->prepare("SELECT g.*, u.nome as dono_nome, u.email as dono_email FROM gastos g JOIN usuarios u ON g.id_usuario = u.id WHERE g.id = ? AND g.id_usuario_conjunto = ? AND g.status_conjunto = 'pendente'");
        $stmt_check->execute([$id_gasto, $id_usuario]);
        $gasto = $stmt_check->fetch();

        if ($gasto) {
            try {
                if ($acao_gasto == 'aceitar') {
                    $metade = $gasto['valor'] / 2;
                    $nome_dono = !empty($gasto['dono_nome']) ? $gasto['dono_nome'] : explode('@', $gasto['dono_email'])[0];

                    $stmt_update = $pdo->prepare("UPDATE gastos SET valor = ?, status_conjunto = 'aceito' WHERE id = ?");
                    $stmt_update->execute([$metade, $id_gasto]);

                    $obs = "Divisão: Recebido de " . $nome_dono;
                    $stmt_insert = $pdo->prepare("INSERT INTO gastos (id_usuario, nome, valor, data_gasto, tipo, is_conjunto, id_usuario_conjunto, status_conjunto, observacao) VALUES (?, ?, ?, ?, ?, 0, ?, 'aceito', ?)");
                    $stmt_insert->execute([$id_usuario, $gasto['nome'], $metade, $gasto['data_gasto'], $gasto['tipo'], $gasto['id_usuario'], $obs]);

                    $mensagem = "<div class='alerta sucesso'>✅ Divisão aceita! O valor foi dividido e R$ " . number_format($metade, 2, ',', '.') . " foi adicionado aos seus gastos.</div>";
                } elseif ($acao_gasto == 'rejeitar') {
                    $stmt_update = $pdo->prepare("UPDATE gastos SET status_conjunto = 'rejeitado' WHERE id = ?");
                    $stmt_update->execute([$id_gasto]);
                    $mensagem = "<div class='alerta sucesso'>🚫 Divisão recusada. O valor total ficou para quem enviou o gasto.</div>";
                }
            } catch (Exception $e) { 
                $mensagem = "<div class='alerta erro'>Erro: " . $e->getMessage() . "</div>"; 
            }
        }
    }
}

$stmt_fam_pend = $pdo->prepare("
    SELECT f.id as id_familia, u.nome, u.email 
    FROM familia f 
    JOIN usuarios u ON f.id_usuario = u.id 
    WHERE f.id_membro = ? AND f.status = 'pendente'
");
$stmt_fam_pend->execute([$id_usuario]);
$convites_familia = $stmt_fam_pend->fetchAll();

$stmt_pendentes = $pdo->prepare("
    SELECT g.*, u.nome as dono_nome, u.email as dono_email 
    FROM gastos g 
    JOIN usuarios u ON g.id_usuario = u.id 
    WHERE g.id_usuario_conjunto = ? AND g.status_conjunto = 'pendente'
");
$stmt_pendentes->execute([$id_usuario]);
$pendencias = $stmt_pendentes->fetchAll();

$total_notificacoes = count($convites_familia) + count($pendencias);

$stmt_user = $pdo->prepare("SELECT ultimo_id_gasto_visto, ultimo_id_parcela_visto FROM usuarios WHERE id = ?");
$stmt_user->execute([$id_usuario]);
$user_memoria = $stmt_user->fetch();

$id_visto_gasto = $user_memoria['ultimo_id_gasto_visto'] ?? 0;
$id_visto_parcela = $user_memoria['ultimo_id_parcela_visto'] ?? 0;

$stmt_logs_gastos = $pdo->prepare("
    SELECT g.id, g.id_usuario, g.nome, g.valor, g.data_gasto as data_ref, g.is_conjunto, g.id_usuario_conjunto, g.status_conjunto, g.observacao, 'gasto' as tipo_log, u_dono.nome as dono_nome, u_conj.nome as conj_nome 
    FROM gastos g 
    JOIN usuarios u_dono ON g.id_usuario = u_dono.id 
    LEFT JOIN usuarios u_conj ON g.id_usuario_conjunto = u_conj.id 
    WHERE (g.id_usuario = ? OR g.id_usuario_conjunto = ?) AND g.id > ?
");
$stmt_logs_gastos->execute([$id_usuario, $id_usuario, $id_visto_gasto]);
$logs_gastos = $stmt_logs_gastos->fetchAll(PDO::FETCH_ASSOC);

$stmt_logs_parcelas = $pdo->prepare("
    SELECT id, id_usuario, nome, valor_parcela as valor, data_proxima_parcela as data_ref, 0 as is_conjunto, NULL as id_usuario_conjunto, 'solo' as status_conjunto, NULL as observacao, 'parcela' as tipo_log, NULL as dono_nome, NULL as conj_nome, total_parcelas
    FROM parcelas 
    WHERE id_usuario = ? AND id > ?
");
$stmt_logs_parcelas->execute([$id_usuario, $id_visto_parcela]);
$logs_parcelas = $stmt_logs_parcelas->fetchAll(PDO::FETCH_ASSOC);

$logs_completos = array_merge($logs_gastos, $logs_parcelas);
usort($logs_completos, function($a, $b) {
    return $b['id'] <=> $a['id']; 
});
$logs_completos = array_slice($logs_completos, 0, 30);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificações - Organiza</title>
    <link rel="stylesheet" href="css/dashboard.css?v=<?php echo time(); ?>">
    <style>
        .convite-card { border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px; margin-bottom: 15px; background: #f9fbfa; border-left: 4px solid #f39c12; }
        .convite-card h4 { color: #2c3e50; margin-bottom: 5px; }
        .convite-valores { display: flex; justify-content: space-between; margin: 15px 0; background: #fff; padding: 10px; border-radius: 5px; border: 1px dashed #ccc; }
        .botoes-acao { display: flex; gap: 10px; }
        .btn-aceitar { background: #27ae60; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; flex: 1; font-weight: bold; }
        .btn-recusar { background: #e74c3c; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; flex: 1; font-weight: bold; }
        
        .log-list { list-style: none; padding: 0; }
        .log-item { padding: 12px 0; border-bottom: 1px solid #eee; display: flex; gap: 15px; align-items: center; font-size: 14px; }
        .log-icon { width: 35px; height: 35px; border-radius: 50%; background: #ecf0f1; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
        .log-time { color: #95a5a6; font-size: 12px; margin-top: 3px; }
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
            <a href="familia.php">Família 🤝</a>
            <a href="notificacoes.php" class="ativo">Notificações <?php if($total_notificacoes > 0) echo "<span style='background:#e74c3c; color:white; padding:2px 6px; border-radius:10px; font-size:12px; float:right;'>".$total_notificacoes."</span>"; ?></a>
            <a href="perfil.php">Meu Perfil</a>
            <a href="sair.php" style="margin-top: auto; color: #e74c3c;">Sair do Sistema</a>
        </aside>

        <main class="main-content">
            <h2>Notificações & Atividades 🔔</h2>
            <?php echo $mensagem; ?>

            <div class="layout-grid-noti">
                
                <div class="box-noti">
                    <h3>Aguardando sua ação</h3>
                    <p style="color: #7f8c8d; font-size: 14px; margin-bottom: 20px;">Convites para dividir gastos ou entrar em famílias.</p>
                    
                    <?php if ($total_notificacoes == 0): ?>
                        <p style="text-align: center; color: #bdc3c7; margin-top: 30px;">Tudo limpo por aqui! 🎉</p>
                    <?php else: ?>
                        
                        <?php foreach ($convites_familia as $cf): ?>
                            <div class="convite-card" style="border-left-color: #27ae60;">
                                <h4>Convite para Família 🤝</h4>
                                <p style="font-size: 14px; color: #555;"><strong><?php echo htmlspecialchars($cf['nome']); ?></strong> (<?php echo $cf['email']; ?>) quer adicionar você à família para dividirem gastos de forma fácil.</p>
                                
                                <form method="POST" class="botoes-acao" style="margin-top: 15px;">
                                    <input type="hidden" name="id_familia" value="<?php echo $cf['id_familia']; ?>">
                                    <button type="submit" name="acao_familia" value="recusar" class="btn-recusar">Recusar</button>
                                    <button type="submit" name="acao_familia" value="aceitar" class="btn-aceitar">Aceitar</button>
                                </form>
                            </div>
                        <?php endforeach; ?>

                        <?php foreach ($pendencias as $p): ?>
                            <?php 
                                $metade = $p['valor'] / 2; 
                                $nome_quem_convidou = !empty($p['dono_nome']) ? $p['dono_nome'] : explode('@', $p['dono_email'])[0];
                            ?>
                            <div class="convite-card">
                                <h4><?php echo htmlspecialchars($nome_quem_convidou); ?> quer dividir um gasto!</h4>
                                <p style="font-size: 14px; color: #555;">Referente a: <strong><?php echo htmlspecialchars($p['nome']); ?></strong></p>
                                
                                <div class="convite-valores">
                                    <div style="text-align: center;">
                                        <small style="color: #7f8c8d;">Valor Total</small><br>
                                        <strong style="color: #2c3e50;">R$ <?php echo number_format($p['valor'], 2, ',', '.'); ?></strong>
                                    </div>
                                    <div style="text-align: center;">
                                        <small style="color: #7f8c8d;">Sua Parte (50%)</small><br>
                                        <strong style="color: #e74c3c;">R$ <?php echo number_format($metade, 2, ',', '.'); ?></strong>
                                    </div>
                                </div>

                                <form method="POST" class="botoes-acao">
                                    <input type="hidden" name="id_gasto" value="<?php echo $p['id']; ?>">
                                    <button type="submit" name="acao" value="rejeitar" class="btn-recusar">Recusar</button>
                                    <button type="submit" name="acao" value="aceitar" class="btn-aceitar">Aceitar Divisão</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                        
                    <?php endif; ?>
                </div>

                <div class="box-noti">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <div>
                            <h3 style="margin: 0;">Logbook</h3>
                            <p style="color: #7f8c8d; font-size: 14px; margin: 5px 0 0 0;">Histórico das suas movimentações.</p>
                        </div>
                        <?php if (count($logs_completos) > 0): ?>
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="acao" value="limpar_logs">
                                <button type="submit" class="btn-recusar" style="font-size: 12px; padding: 6px 12px;" onclick="return confirm('Tem certeza que deseja limpar o histórico visual? Seus gastos não serão apagados do sistema.')">🧹 Limpar</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    
                    <ul class="log-list">
                        <?php if (count($logs_completos) == 0): ?>
                            <p style="text-align: center; color: #bdc3c7; margin-top: 30px;">Nenhuma atividade recente visualizada.</p>
                        <?php endif; ?>

                        <?php foreach ($logs_completos as $log): ?>
                            <?php
                                $icone = "📝";
                                $texto_log = "";
                                $data_log = date('d/m/Y', strtotime($log['data_ref']));
                                $nome_item = htmlspecialchars($log['nome']);
                                $valor_formatado = number_format($log['valor'], 2, ',', '.');

                                if ($log['tipo_log'] == 'parcela') {
                                    $icone = "💳";
                                    $texto_log = "Você registrou uma compra parcelada: <strong>{$nome_item}</strong> em {$log['total_parcelas']}x de R$ {$valor_formatado}.";
                                } 
                                else {
                                    $sou_dono = ($log['id_usuario'] == $id_usuario);
                                    
                                    if ($sou_dono) {
                                        if ($log['is_conjunto'] == 0) {
                                            if (!empty($log['observacao']) && strpos($log['observacao'], 'Divisão: Recebido') !== false) {
                                                $icone = "📥";
                                                $texto_log = "A sua parte da divisão de <strong>{$nome_item}</strong> (R$ {$valor_formatado}) foi registrada nos seus gastos.";
                                            } else {
                                                $icone = "💸";
                                                $texto_log = "Você registrou o gasto <strong>{$nome_item}</strong> no valor de R$ {$valor_formatado}.";
                                            }
                                        } else {
                                            $nome_convidado = !empty($log['conj_nome']) ? $log['conj_nome'] : 'um usuário';
                                            
                                            if ($log['status_conjunto'] == 'pendente') {
                                                $icone = "⏳";
                                                $texto_log = "Você convidou {$nome_convidado} para dividir <strong>{$nome_item}</strong>. Aguardando resposta.";
                                            } elseif ($log['status_conjunto'] == 'aceito') {
                                                $icone = "🤝";
                                                $texto_log = "{$nome_convidado} <strong>aceitou</strong> dividir <strong>{$nome_item}</strong>. O seu gasto original caiu para R$ {$valor_formatado}.";
                                            } elseif ($log['status_conjunto'] == 'rejeitado') {
                                                $icone = "❌";
                                                $texto_log = "{$nome_convidado} <strong>recusou</strong> dividir <strong>{$nome_item}</strong>. O valor total continuou com você.";
                                            }
                                        }
                                    } else {
                                        $nome_dono = !empty($log['dono_nome']) ? $log['dono_nome'] : 'Um usuário';
                                        
                                        if ($log['status_conjunto'] == 'pendente') {
                                            $metade = number_format($log['valor'] / 2, 2, ',', '.');
                                            $icone = "📩";
                                            $texto_log = "{$nome_dono} enviou um convite para dividir <strong>{$nome_item}</strong>. (R$ {$metade} para você)";
                                        } elseif ($log['status_conjunto'] == 'aceito') {
                                            $icone = "✅";
                                            $texto_log = "Você <strong>aceitou</strong> dividir <strong>{$nome_item}</strong> com {$nome_dono}.";
                                        } elseif ($log['status_conjunto'] == 'rejeitado') {
                                            $icone = "🚫";
                                            $texto_log = "Você <strong>recusou</strong> dividir <strong>{$nome_item}</strong> com {$nome_dono}.";
                                        }
                                    }
                                }
                            ?>
                            <li class="log-item">
                                <div class="log-icon"><?php echo $icone; ?></div>
                                <div>
                                    <div style="color: #333; line-height: 1.4;"><?php echo $texto_log; ?></div>
                                    <div class="log-time"><?php echo $data_log; ?></div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </main>
    </div>
</body>
</html>