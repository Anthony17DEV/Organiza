<?php
session_start();
require 'conecta.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
$mes_atual = date('m');
$ano_atual = date('Y');

$stmt_user = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt_user->execute([$id_usuario]);
$usuario = $stmt_user->fetch();

$nome_usuario = !empty($usuario['nome']) ? $usuario['nome'] : explode('@', $usuario['email'])[0];
$caminho_foto = !empty($usuario['foto']) ? 'uploads/' . $usuario['foto'] : 'https://cdn-icons-png.flaticon.com/512/149/149071.png';
$limite = $usuario['limite_mensal'];

$stmt_gastos = $pdo->prepare("SELECT SUM(valor) as total FROM gastos WHERE id_usuario = ? AND MONTH(data_gasto) = ? AND YEAR(data_gasto) = ?");
$stmt_gastos->execute([$id_usuario, $mes_atual, $ano_atual]);
$total_gastos = $stmt_gastos->fetch()['total'] ?? 0;

$stmt_parcelas = $pdo->prepare("SELECT SUM(valor_parcela) as total FROM parcelas WHERE id_usuario = ? AND parcelas_pagas < total_parcelas");
$stmt_parcelas->execute([$id_usuario]);
$total_parcelas = $stmt_parcelas->fetch()['total'] ?? 0;

$stmt_ass = $pdo->prepare("SELECT SUM(valor) as total FROM assinaturas WHERE id_usuario = ? AND status = 'ativa'");
$stmt_ass->execute([$id_usuario]);
$total_assinaturas = $stmt_ass->fetch()['total'] ?? 0;

$gasto_total_mes = $total_gastos + $total_parcelas + $total_assinaturas;
$pode_gastar = $limite - $gasto_total_mes;
$cor_saldo = ($pode_gastar < 0) ? "#e74c3c" : "#27ae60";

$stmt_cat = $pdo->prepare("SELECT tipo, SUM(valor) as total FROM gastos WHERE id_usuario = ? AND MONTH(data_gasto) = ? GROUP BY tipo");
$stmt_cat->execute([$id_usuario, $mes_atual]);
$dados_categorias = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

$labels_cat = [];
$valores_cat = [];
foreach($dados_categorias as $d) {
    $labels_cat[] = mb_convert_case($d['tipo'], MB_CASE_TITLE, "UTF-8");
    $valores_cat[] = $d['total'];
}

$historico_meses = [];
$valores_meses = [];
for ($i = 5; $i >= 0; $i--) {
    $mes_ref = date('m', strtotime("-$i months"));
    $ano_ref = date('Y', strtotime("-$i months"));
    $nome_mes = date('M', strtotime("-$i months"));

    $stmt_mes = $pdo->prepare("SELECT SUM(valor) as total FROM gastos WHERE id_usuario = ? AND MONTH(data_gasto) = ? AND YEAR(data_gasto) = ?");
    $stmt_mes->execute([$id_usuario, $mes_ref, $ano_ref]);
    $res = $stmt_mes->fetch();
    
    $historico_meses[] = $nome_mes;
    $valores_meses[] = $res['total'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Organiza</title>
    <link rel="stylesheet" href="css/dashboard.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .charts-container { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px; }
        .chart-box { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        canvas { max-height: 300px; width: 100% !important; }
        @media (max-width: 900px) { .charts-container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <h3>Organiza</h3>
            <a href="dashboard.php" class="ativo">Visão Geral</a>
            <a href="inserir_gasto.php">Inserir Gastos</a>
            <a href="inserir_parcela.php">Inserir Parcelas</a>
            <a href="inserir_assinatura.php">Assinaturas Fixas</a>
            <a href="notificacoes.php">Notificações</a>
            <a href="perfil.php">Meu Perfil</a>
            <a href="sair.php" style="margin-top: auto; color: #e74c3c;">Sair</a>
        </aside>

        <main class="main-content">
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 25px;">
                <img src="<?php echo $caminho_foto; ?>" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 3px solid #27ae60;">
                <h2>Olá, <?php echo htmlspecialchars(ucfirst($nome_usuario)); ?>! 👋</h2>
            </div>

            <div class="cards-grid">
                <div class="card">
                    <h4>Limite Mensal</h4>
                    <p>R$ <?php echo number_format($limite, 2, ',', '.'); ?></p>
                </div>
                <div class="card">
                    <h4>Gasto do Mês</h4>
                    <p style="color: #e67e22;">R$ <?php echo number_format($gasto_total_mes, 2, ',', '.'); ?></p>
                </div>
                <div class="card">
                    <h4>Saldo Disponível</h4>
                    <p style="color: <?php echo $cor_saldo; ?>;">R$ <?php echo number_format($pode_gastar, 2, ',', '.'); ?></p>
                </div>
            </div>

            <div class="charts-container">
                <div class="chart-box">
                    <h3 style="font-size: 16px; margin-bottom: 15px; color: #7f8c8d;">Gastos por Categoria (%)</h3>
                    <?php if(empty($valores_cat)): ?>
                        <p style="text-align: center; color: #bdc3c7; margin-top: 50px;">Insira gastos para gerar o gráfico.</p>
                    <?php else: ?>
                        <canvas id="chartCategorias"></canvas>
                    <?php endif; ?>
                </div>

                <div class="chart-box">
                    <h3 style="font-size: 16px; margin-bottom: 15px; color: #7f8c8d;">Evolução de Gastos (6 Meses)</h3>
                    <canvas id="chartMensal"></canvas>
                </div>
            </div>
        </main>
    </div>

    <script>
        const ctxCat = document.getElementById('chartCategorias');
        if(ctxCat) {
            new Chart(ctxCat, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($labels_cat); ?>,
                    datasets: [{
                        data: <?php echo json_encode($valores_cat); ?>,
                        backgroundColor: ['#27ae60', '#2980b9', '#f1c40f', '#e67e22', '#e74c3c', '#9b59b6', '#34495e'],
                        borderWidth: 0
                    }]
                },
                options: { plugins: { legend: { position: 'bottom' } } }
            });
        }

        const ctxMes = document.getElementById('chartMensal');
        new Chart(ctxMes, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($historico_meses); ?>,
                datasets: [{
                    label: 'Gasto R$',
                    data: <?php echo json_encode($valores_meses); ?>,
                    backgroundColor: '#3498db',
                    borderRadius: 5
                }]
            },
            options: {
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>
</html>