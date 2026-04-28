<?php
session_start();
require 'conecta.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$id_usuario = $_SESSION['usuario_id'];

$stmt_user = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt_user->execute([$id_usuario]);
$usuario = $stmt_user->fetch();

$dia_reset = $usuario['dia_reset'] ?? 1;
$limite = $usuario['limite_mensal'];
$nome_usuario = !empty($usuario['nome']) ? $usuario['nome'] : explode('@', $usuario['email'])[0];
$caminho_foto = !empty($usuario['foto']) ? 'uploads/' . $usuario['foto'] : 'https://cdn-icons-png.flaticon.com/512/149/149071.png';

$dia_hoje = (int)date('d');

if (!isset($_GET['mes'])) {
    if ($dia_hoje < $dia_reset) {
        $mes_filtro = date('m', strtotime("-1 month"));
        $ano_filtro = date('Y', strtotime("-1 month"));
    } else {
        $mes_filtro = date('m');
        $ano_filtro = date('Y');
    }
} else {
    $mes_filtro = $_GET['mes'];
    $ano_filtro = $_GET['ano'];
}

$data_inicio = "$ano_filtro-$mes_filtro-" . str_pad($dia_reset, 2, "0", STR_PAD_LEFT);
$data_fim = date('Y-m-d', strtotime("+1 month -1 day", strtotime($data_inicio)));

$stmt_gastos = $pdo->prepare("SELECT SUM(valor) as total FROM gastos WHERE id_usuario = ? AND data_gasto BETWEEN ? AND ?");
$stmt_gastos->execute([$id_usuario, $data_inicio, $data_fim]);
$total_gastos = $stmt_gastos->fetch()['total'] ?? 0;

$stmt_ass = $pdo->prepare("SELECT SUM(valor) as total FROM assinaturas WHERE id_usuario = ? AND status = 'ativa'");
$stmt_ass->execute([$id_usuario]);
$total_assinaturas = $stmt_ass->fetch()['total'] ?? 0;

$stmt_parcelas = $pdo->prepare("SELECT SUM(valor_parcela) as total FROM parcelas WHERE id_usuario = ? AND parcelas_pagas < total_parcelas");
$stmt_parcelas->execute([$id_usuario]);
$total_parcelas = $stmt_parcelas->fetch()['total'] ?? 0;

$gasto_total_ciclo = $total_gastos + $total_assinaturas + $total_parcelas;
$pode_gastar = $limite - $gasto_total_ciclo;
$cor_saldo = ($pode_gastar < 0) ? "#e74c3c" : "#27ae60";

$stmt_cat = $pdo->prepare("SELECT tipo, SUM(valor) as total FROM gastos WHERE id_usuario = ? AND data_gasto BETWEEN ? AND ? GROUP BY tipo");
$stmt_cat->execute([$id_usuario, $data_inicio, $data_fim]);
$dados_categorias = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);

$labels_cat = []; $valores_cat = [];
foreach($dados_categorias as $d) {
    $labels_cat[] = mb_convert_case($d['tipo'], MB_CASE_TITLE, "UTF-8");
    $valores_cat[] = $d['total'];
}

$historico_ciclos = []; $valores_ciclos = [];
for ($i = 5; $i >= 0; $i--) {
    $inicio_hist = date('Y-m-d', strtotime("-$i months", strtotime("$ano_filtro-$mes_filtro-$dia_reset")));
    $fim_hist = date('Y-m-d', strtotime("+1 month -1 day", strtotime($inicio_hist)));
    
    $stmt_hist = $pdo->prepare("SELECT SUM(valor) as total FROM gastos WHERE id_usuario = ? AND data_gasto BETWEEN ? AND ?");
    $stmt_hist->execute([$id_usuario, $inicio_hist, $fim_hist]);
    $res = $stmt_hist->fetch();
    
    $historico_ciclos[] = date('M', strtotime($inicio_hist));
    $valores_ciclos[] = $res['total'] ?? 0;
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
        .filtros-box { background: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .filtros-box select { padding: 8px; border-radius: 5px; border: 1px solid #ddd; }
        .btn-filtrar { background: #27ae60; color: white; border: none; padding: 8px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        .charts-container { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .chart-box { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        @media (max-width: 900px) { .charts-container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <h3>Organiza</h3>
            <a href="dashboard.php" class="ativo" >Visão Geral</a>
            <a href="inserir_gasto.php">Inserir Gastos</a>
            <a href="inserir_parcela.php">Inserir Parcelas</a>
            <a href="inserir_assinatura.php">Assinaturas Fixas</a>
            <a href="familia.php">Família 🤝</a>
            <a href="notificacoes.php">Notificações</a>
            <a href="perfil.php">Meu Perfil</a>
            <a href="sair.php" style="margin-top: auto; color: #e74c3c;">Sair</a>
        </aside>

        <main class="main-content">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <img src="<?php echo $caminho_foto; ?>" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #27ae60;">
                    <h2 style="margin:0;">Olá, <?php echo htmlspecialchars(ucfirst($nome_usuario)); ?>!</h2>
                </div>
                <div style="text-align: right;">
                    <small style="color: #888;">Ciclo atual:</small><br>
                    <strong><?php echo date('d/m', strtotime($data_inicio)); ?> até <?php echo date('d/m', strtotime($data_fim)); ?></strong>
                </div>
            </div>

            <div class="filtros-box">
                <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                    <select name="mes">
                        <?php
                        $meses = ["Jan", "Fev", "Mar", "Abr", "Mai", "Jun", "Jul", "Ago", "Set", "Out", "Nov", "Dez"];
                        for ($i = 1; $i <= 12; $i++) {
                            $m = str_pad($i, 2, "0", STR_PAD_LEFT);
                            echo "<option value='$m' ".($mes_filtro == $m ? 'selected' : '').">".$meses[$i-1]."</option>";
                        }
                        ?>
                    </select>
                    <select name="ano">
                        <?php for($i=date('Y')-1; $i<=date('Y')+1; $i++) echo "<option value='$i' ".($ano_filtro == $i ? 'selected' : '').">$i</option>"; ?>
                    </select>
                    <button type="submit" class="btn-filtrar">Ver Período</button>
                    <a href="dashboard.php" style="font-size: 12px; color: #888; text-decoration: none;">Voltar ao Atual</a>
                </form>
            </div>

            <div class="cards-grid">
                <div class="card">
                    <h4>Limite do Ciclo</h4>
                    <p>R$ <?php echo number_format($limite, 2, ',', '.'); ?></p>
                </div>
                <div class="card">
                    <h4>Gasto Acumulado</h4>
                    <p style="color: #e67e22;">R$ <?php echo number_format($gasto_total_ciclo, 2, ',', '.'); ?></p>
                </div>
                <div class="card">
                    <h4>Pode gastar ainda</h4>
                    <p style="color: <?php echo $cor_saldo; ?>;">R$ <?php echo number_format($pode_gastar, 2, ',', '.'); ?></p>
                </div>
            </div>

            <div class="charts-container">
                <div class="chart-box">
                    <h4 style="color: #7f8c8d; margin-bottom: 15px;">Gastos por Categoria</h4>
                    <?php if(empty($valores_cat)): ?>
                        <p style="text-align:center; padding-top: 50px; color: #ccc;">Sem gastos neste ciclo.</p>
                    <?php else: ?>
                        <canvas id="chartCategorias"></canvas>
                    <?php endif; ?>
                </div>
                <div class="chart-box">
                    <h4 style="color: #7f8c8d; margin-bottom: 15px;">Evolução dos Últimos Ciclos</h4>
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
                        backgroundColor: ['#27ae60', '#2980b9', '#f1c40f', '#e67e22', '#e74c3c', '#9b59b6'],
                        borderWidth: 0
                    }]
                },
                options: { plugins: { legend: { position: 'bottom' } } }
            });
        }

        new Chart(document.getElementById('chartMensal'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($historico_ciclos); ?>,
                datasets: [{
                    label: 'Gasto Total R$',
                    data: <?php echo json_encode($valores_ciclos); ?>,
                    backgroundColor: '#3498db',
                    borderRadius: 5
                }]
            },
            options: { scales: { y: { beginAtZero: true } }, plugins: { legend: { display: false } } }
        });
    </script>
</body>
</html>