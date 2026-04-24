<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo = db();
date_default_timezone_set('America/Sao_Paulo');

$menuActive = 'graficos';
$pageTitle  = 'Gráficos';

// Filtro de ano/mês para Gráfico 1
$anoAtual = (int)date('Y');
$mesAtual = (int)date('n');
$anoFiltro = isset($_GET['ano']) ? (int)$_GET['ano'] : $anoAtual;
$mesFiltro = isset($_GET['mes']) ? (int)$_GET['mes'] : $mesAtual;

// Garantir valores válidos
if ($anoFiltro < 2020 || $anoFiltro > $anoAtual + 1) $anoFiltro = $anoAtual;
if ($mesFiltro < 1 || $mesFiltro > 12) $mesFiltro = $mesAtual;

/* ====== Cards de resumo ====== */
$sqlResumo = "
SELECT
  (SELECT COUNT(*) FROM demandas WHERE ativo=1 AND status IN ('finalizado','publicado'))  AS d_finalizadas,
  (SELECT COUNT(*) FROM demandas WHERE ativo=1 AND status='em_andamento')                 AS d_andamento,
  (SELECT COUNT(*) FROM demandas WHERE ativo=1 AND status='nao_iniciado')                 AS d_nao_iniciado,
  (SELECT COUNT(*) FROM suportes WHERE ativo=1)                                           AS s_total,
  (SELECT COUNT(*) FROM suportes WHERE ativo=1 AND status='finalizado')                   AS s_finalizados,
  (SELECT COUNT(*) FROM suportes WHERE ativo=1 AND status='em_andamento')                 AS s_andamento
";
$resumo = $pdo->query($sqlResumo)->fetch();

/* ====== Gráfico 1 — Demandas finalizadas por semana do mês filtrado (baseado no prazo) ====== */
$stmtG1 = $pdo->prepare("
    SELECT
        WEEK(prazo, 1) - WEEK(DATE_FORMAT(prazo,'%Y-%m-01'), 1) + 1 AS semana_do_mes,
        COUNT(*) AS total
    FROM demandas
    WHERE ativo=1
      AND status IN ('finalizado','publicado')
      AND prazo IS NOT NULL
      AND YEAR(prazo) = :ano
      AND MONTH(prazo) = :mes
    GROUP BY semana_do_mes
    ORDER BY semana_do_mes
");
$stmtG1->execute(array(':ano' => $anoFiltro, ':mes' => $mesFiltro));
$rowsG1 = $stmtG1->fetchAll();

// Calcular o intervalo de datas de cada semana dentro do mês filtrado
$primeiroDiaMes  = mktime(0, 0, 0, $mesFiltro, 1, $anoFiltro);
$totalDiasMes    = (int)date('t', $primeiroDiaMes);

$g1Labels = array();
$g1Data   = array();
$g1Map    = array();
foreach ($rowsG1 as $r) {
    $g1Map[(int)$r['semana_do_mes']] = (int)$r['total'];
}

// Percorrer semanas: semana 1 começa no dia 1 e vai até o próximo domingo (ou fim do mês)
$cursorDia = 1;
$totalDias = $totalDiasMes;
$semana    = 1;
while ($cursorDia <= $totalDias) {
    $tsInicio  = mktime(0, 0, 0, $mesFiltro, $cursorDia, $anoFiltro);
    $diaFim    = $cursorDia + (7 - (int)date('N', $tsInicio)); // até domingo
    if ($diaFim > $totalDias) $diaFim = $totalDias;
    $tsFim     = mktime(0, 0, 0, $mesFiltro, $diaFim, $anoFiltro);
    $g1Labels[] = date('d/m', $tsInicio) . ' - ' . date('d/m', $tsFim);
    $g1Data[]   = isset($g1Map[$semana]) ? $g1Map[$semana] : 0;
    $cursorDia  = $diaFim + 1;
    $semana++;
}

/* ====== Gráfico 2 — Demandas finalizadas por cliente (top 15) ====== */
$stmtG2 = $pdo->prepare("
    SELECT c.nome, COUNT(*) AS total
    FROM demandas d
    JOIN clientes c ON c.id = d.id_cliente
    WHERE d.ativo=1
      AND d.status IN ('finalizado','publicado')
      AND d.prazo IS NOT NULL
      AND YEAR(d.prazo) = :ano
      AND MONTH(d.prazo) = :mes
    GROUP BY c.id, c.nome
    ORDER BY total DESC
    LIMIT 15
");
$stmtG2->execute(array(':ano' => $anoFiltro, ':mes' => $mesFiltro));
$rowsG2 = $stmtG2->fetchAll();

$g2Labels = array();
$g2Data   = array();
foreach ($rowsG2 as $r) {
    $g2Labels[] = $r['nome'];
    $g2Data[]   = (int)$r['total'];
}

/* ====== Gráfico 3 — Status das demandas com prazo no mês filtrado (rosca) ====== */
$stmtG3 = $pdo->prepare("
    SELECT status, COUNT(*) AS total
    FROM demandas
    WHERE ativo=1
      AND prazo IS NOT NULL
      AND YEAR(prazo) = :ano
      AND MONTH(prazo) = :mes
    GROUP BY status
");
$stmtG3->execute(array(':ano' => $anoFiltro, ':mes' => $mesFiltro));
$rowsG3 = $stmtG3->fetchAll();

$statusLabels = array(
    'nao_iniciado'               => 'Não iniciado',
    'em_andamento'               => 'Em andamento',
    'aguardando_resposta_cliente'=> 'Aguardando cliente',
    'finalizado'                 => 'Finalizado',
    'publicado'                  => 'Publicado',
);
$statusColors = array(
    'nao_iniciado'               => '#e5e7eb',
    'em_andamento'               => '#fbbf24',
    'aguardando_resposta_cliente'=> '#60a5fa',
    'finalizado'                 => '#34d399',
    'publicado'                  => '#111827',
);
$g3Labels = array();
$g3Data   = array();
$g3Colors = array();
foreach ($rowsG3 as $r) {
    $k = $r['status'];
    $g3Labels[] = isset($statusLabels[$k]) ? $statusLabels[$k] : $k;
    $g3Data[]   = (int)$r['total'];
    $g3Colors[] = isset($statusColors[$k]) ? $statusColors[$k] : '#6b7280';
}

/* ====== Gráfico 4 — Suportes por semana (últimas 12 semanas) ====== */
$rowsG4 = $pdo->query("
    SELECT
        YEARWEEK(criado_em, 1) AS semana_key,
        MIN(DATE(criado_em))   AS semana_inicio,
        COUNT(*)               AS total
    FROM suportes
    WHERE ativo=1
      AND criado_em >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
    GROUP BY semana_key
    ORDER BY semana_key
")->fetchAll();

$g4Labels = array();
$g4Data   = array();
foreach ($rowsG4 as $r) {
    $g4Labels[] = date('d/m', strtotime($r['semana_inicio']));
    $g4Data[]   = (int)$r['total'];
}

/* ====== Gráfico 5 — Clientes com mais suportes (top 15) ====== */
$rowsG5 = $pdo->query("
    SELECT c.nome, COUNT(*) AS total
    FROM suportes s
    JOIN clientes c ON c.id = s.id_cliente
    WHERE s.ativo=1
    GROUP BY c.id, c.nome
    ORDER BY total DESC
    LIMIT 15
")->fetchAll();

$g5Labels = array();
$g5Data   = array();
foreach ($rowsG5 as $r) {
    $g5Labels[] = $r['nome'];
    $g5Data[]   = (int)$r['total'];
}
$g5Height = max(220, count($g5Labels) * 42);
$g2Height = max(220, count($g2Labels) * 42);

/* ====== Meses para o selector ====== */
$meses = array(
    1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',
    5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',
    9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro',
);

require_once __DIR__ . '/includes/layout_top.php';
?>

<style>
    .toprow{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:18px;flex-wrap:wrap;}
    .title h1{margin:0;font-size:22px;font-weight:900;}
    .title .sub{color:var(--muted);font-size:13px;margin-top:4px;}

    .cards{display:grid;grid-template-columns:repeat(6, minmax(0,1fr));gap:10px;margin-bottom:20px;}
    .card{border:1px solid var(--line);border-radius:16px;padding:14px;background:#fff;display:flex;justify-content:space-between;gap:10px;}
    .card .k{font-size:12px;color:var(--muted);font-weight:900;}
    .card .v{margin-top:10px;font-size:22px;font-weight:900;}
    .iconbox{width:36px;height:36px;border-radius:12px;border:1px solid var(--line);display:flex;align-items:center;justify-content:center;font-size:16px;background:#f8f8f8;flex-shrink:0;}

    .section-title{font-size:16px;font-weight:900;margin:24px 0 12px;padding-bottom:8px;border-bottom:2px solid var(--line);}

    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;}
    .grid1{margin-bottom:14px;}
    .panel{border:1px solid var(--line);border-radius:16px;background:#fff;padding:18px;}
    .panel-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px;}
    .panel-title{font-weight:900;font-size:14px;}
    .panel-sub{color:var(--muted);font-size:12px;margin-top:2px;}

    .filter-form{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
    .filter-form select{border:1px solid var(--line);border-radius:10px;padding:7px 10px;font-size:13px;font-weight:700;background:#fff;cursor:pointer;}
    .btn-filter{border:1px solid #111;background:#111;color:#fff;border-radius:10px;padding:7px 14px;font-size:13px;font-weight:700;cursor:pointer;}
    .btn-filter:hover{background:#333;}

    .chart-wrap{position:relative;height:280px;}
    .chart-wrap-lg{position:relative;height:340px;}

    @media(max-width:1200px){
        .cards{grid-template-columns:repeat(3, minmax(0,1fr));}
        .grid2{grid-template-columns:1fr;}
    }
    @media(max-width:860px){
        .cards{grid-template-columns:repeat(2, minmax(0,1fr));}
    }
</style>

<div class="toprow">
    <div class="title">
        <h1>Gráficos</h1>
        <div class="sub">Visão analítica de demandas e suportes</div>
    </div>
</div>

<!-- Cards de Resumo -->
<div class="cards">
    <div class="card">
        <div>
            <div class="k">Demandas Finalizadas</div>
            <div class="v"><?= (int)$resumo['d_finalizadas'] ?></div>
        </div>
        <div class="iconbox">✅</div>
    </div>
    <div class="card">
        <div>
            <div class="k">Em Andamento</div>
            <div class="v"><?= (int)$resumo['d_andamento'] ?></div>
        </div>
        <div class="iconbox">🕘</div>
    </div>
    <div class="card">
        <div>
            <div class="k">Não Iniciadas</div>
            <div class="v"><?= (int)$resumo['d_nao_iniciado'] ?></div>
        </div>
        <div class="iconbox">⏳</div>
    </div>
    <div class="card">
        <div>
            <div class="k">Total de Suportes</div>
            <div class="v"><?= (int)$resumo['s_total'] ?></div>
        </div>
        <div class="iconbox">🎧</div>
    </div>
    <div class="card">
        <div>
            <div class="k">Suportes Finalizados</div>
            <div class="v"><?= (int)$resumo['s_finalizados'] ?></div>
        </div>
        <div class="iconbox">✔️</div>
    </div>
    <div class="card">
        <div>
            <div class="k">Suportes em Andamento</div>
            <div class="v"><?= (int)$resumo['s_andamento'] ?></div>
        </div>
        <div class="iconbox">🔄</div>
    </div>
</div>

<!-- ===== SEÇÃO DEMANDAS ===== -->
<div class="section-title">📋 Demandas</div>

<div class="grid2">
    <!-- Gráfico 1: Finalizadas por semana -->
    <div class="panel">
        <div class="panel-head">
            <div>
                <div class="panel-title">Finalizadas por Semana</div>
                <div class="panel-sub">
                    <?= $meses[$mesFiltro] ?> de <?= $anoFiltro ?>
                </div>
            </div>
            <form method="get" class="filter-form">
                <select name="mes">
                    <?php foreach ($meses as $num => $nome): ?>
                        <option value="<?= $num ?>" <?= $num === $mesFiltro ? 'selected' : '' ?>>
                            <?= $nome ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="ano">
                    <?php for ($y = $anoAtual; $y >= 2022; $y--): ?>
                        <option value="<?= $y ?>" <?= $y === $anoFiltro ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="btn-filter">Filtrar</button>
            </form>
        </div>
        <div class="chart-wrap">
            <canvas id="chartG1"></canvas>
        </div>
    </div>

    <!-- Gráfico 3: Status atual (rosca) -->
    <div class="panel">
        <div class="panel-head">
            <div>
                <div class="panel-title">Status das Demandas</div>
                <div class="panel-sub">Por prazo em <?= $meses[$mesFiltro] ?> de <?= $anoFiltro ?></div>
            </div>
        </div>
        <div class="chart-wrap">
            <canvas id="chartG3"></canvas>
        </div>
    </div>
</div>

<!-- Gráfico 2: Finalizadas por cliente -->
<div class="grid1">
    <div class="panel">
        <div class="panel-head">
            <div>
                <div class="panel-title">Demandas Finalizadas por Cliente</div>
                <div class="panel-sub">Por prazo em <?= $meses[$mesFiltro] ?> de <?= $anoFiltro ?> — top <?= count($g2Labels) ?> clientes</div>
            </div>
        </div>
        <div style="position:relative;height:<?= $g2Height ?>px;">
            <canvas id="chartG2"></canvas>
        </div>
    </div>
</div>

<!-- ===== SEÇÃO SUPORTES ===== -->
<div class="section-title">🎧 Suportes</div>

<div class="grid2">
    <!-- Gráfico 4: Suportes por semana -->
    <div class="panel">
        <div class="panel-head">
            <div>
                <div class="panel-title">Suportes por Semana</div>
                <div class="panel-sub">Últimas 12 semanas</div>
            </div>
        </div>
        <div class="chart-wrap">
            <canvas id="chartG4"></canvas>
        </div>
    </div>

    <!-- Gráfico 5: Suportes por cliente -->
    <div class="panel">
        <div class="panel-head">
            <div>
                <div class="panel-title">Clientes com Mais Suportes</div>
                <div class="panel-sub">Top <?= count($g5Labels) ?> clientes</div>
            </div>
        </div>
        <div style="position:relative;height:<?= $g5Height ?>px;">
            <canvas id="chartG5"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function(){
    var defaults = Chart.defaults;
    defaults.font.family = 'Arial, Helvetica, sans-serif';
    defaults.font.size   = 12;
    defaults.color       = '#6b7280';

    /* ── Gráfico 1: Finalizadas por semana ── */
    new Chart(document.getElementById('chartG1'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($g1Labels) ?>,
            datasets: [{
                label: 'Demandas finalizadas',
                data: <?= json_encode($g1Data) ?>,
                backgroundColor: '#111827',
                borderRadius: 8,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, precision: 0 },
                    grid: { color: '#f3f4f6' }
                },
                x: { grid: { display: false } }
            }
        }
    });

    /* ── Gráfico 2: Finalizadas por cliente ── */
    new Chart(document.getElementById('chartG2'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($g2Labels) ?>,
            datasets: [{
                label: 'Demandas finalizadas',
                data: <?= json_encode($g2Data) ?>,
                backgroundColor: '#111827',
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, precision: 0 },
                    grid: { color: '#f3f4f6' }
                },
                y: { grid: { display: false } }
            }
        }
    });

    /* ── Gráfico 3: Status atual (rosca) ── */
    new Chart(document.getElementById('chartG3'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($g3Labels) ?>,
            datasets: [{
                data: <?= json_encode($g3Data) ?>,
                backgroundColor: <?= json_encode($g3Colors) ?>,
                borderWidth: 2,
                borderColor: '#ffffff',
                hoverOffset: 8,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { padding: 16, font: { size: 12, weight: '700' } }
                }
            }
        }
    });

    /* ── Gráfico 4: Suportes por semana ── */
    new Chart(document.getElementById('chartG4'), {
        type: 'line',
        data: {
            labels: <?= json_encode($g4Labels) ?>,
            datasets: [{
                label: 'Suportes abertos',
                data: <?= json_encode($g4Data) ?>,
                borderColor: '#111827',
                backgroundColor: 'rgba(17,24,39,0.08)',
                borderWidth: 2,
                pointRadius: 5,
                pointBackgroundColor: '#111827',
                tension: 0.3,
                fill: true,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, precision: 0 },
                    grid: { color: '#f3f4f6' }
                },
                x: { grid: { display: false } }
            }
        }
    });

    /* ── Gráfico 5: Suportes por cliente ── */
    new Chart(document.getElementById('chartG5'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($g5Labels) ?>,
            datasets: [{
                label: 'Total de suportes',
                data: <?= json_encode($g5Data) ?>,
                backgroundColor: '#374151',
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, precision: 0 },
                    grid: { color: '#f3f4f6' }
                },
                y: { grid: { display: false } }
            }
        }
    });
})();
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
