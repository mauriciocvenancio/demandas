<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo = db();
date_default_timezone_set('America/Sao_Paulo');

// ── Filtros ────────────────────────────────────────────────────────────
$data_ini   = isset($_GET['data_ini']) ? trim((string)$_GET['data_ini']) : date('Y-m-01');
$data_fim   = isset($_GET['data_fim']) ? trim((string)$_GET['data_fim']) : date('Y-m-d');
$id_dev_fil = isset($_GET['id_dev']) ? (int)$_GET['id_dev'] : 0;

function validDate($d){ return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d); }
if (!validDate($data_ini)) $data_ini = date('Y-m-01');
if (!validDate($data_fim)) $data_fim = date('Y-m-d');

$devs = $pdo->query("SELECT id, nome FROM usuarios WHERE ativo=1 AND tipo='desenvolvedor' ORDER BY nome ASC")->fetchAll();

// ── Itens planejados no período ────────────────────────────────────────
$whereIt  = array("pi.ativo=1", "pc.data_alocada BETWEEN ? AND ?");
$paramsIt = array($data_ini, $data_fim);
if ($id_dev_fil > 0) {
    $whereIt[] = "pc.id_desenvolvedor = ?";
    $paramsIt[] = $id_dev_fil;
}

$stmtIt = $pdo->prepare("
    SELECT
        pi.id, pi.titulo,
        pi.estimativa_min, pi.estimativa_max, pi.estimativa_media,
        pi.tempo_real, pi.status, pi.finalizado_em,
        pc.data_alocada,
        u.nome AS dev_nome
    FROM planejamento_itens pi
    JOIN planejamento_calendario pc ON pc.id_item = pi.id
    JOIN usuarios u ON u.id = pc.id_desenvolvedor
    WHERE " . implode(" AND ", $whereIt) . "
    ORDER BY pc.data_alocada ASC, u.nome ASC
");
$stmtIt->execute($paramsIt);
$itens = $stmtIt->fetchAll();

// ── Itens não planejados: suportes finalizados ────────────────────────
$whereSup  = array("s.ativo=1", "s.status='finalizado'", "DATE(s.criado_em) BETWEEN ? AND ?");
$paramsSup = array($data_ini, $data_fim);
if ($id_dev_fil > 0) {
    $whereSup[] = "s.id_usuario_responsavel = ?";
    $paramsSup[] = $id_dev_fil;
}
$stmtSup = $pdo->prepare("
    SELECT
        s.assunto                   AS titulo,
        COALESCE(s.duracao_min, 30) AS duracao_min,
        DATE(s.criado_em)           AS data_np,
        u.nome                      AS dev_nome,
        'Suporte'                   AS origem
    FROM suportes s
    JOIN usuarios u ON u.id = s.id_usuario_responsavel
    WHERE " . implode(" AND ", $whereSup) . "
    ORDER BY data_np ASC, u.nome ASC
");
$stmtSup->execute($paramsSup);
$naoPlanejados = $stmtSup->fetchAll();

// ── Itens não planejados: demandas finalizadas (30 min padrão) ─────────
$whereDem  = array("d.ativo=1", "d.status='finalizado'", "d.atualizado_em IS NOT NULL", "DATE(d.atualizado_em) BETWEEN ? AND ?");
$paramsDem = array($data_ini, $data_fim);
if ($id_dev_fil > 0) {
    $whereDem[] = "d.id_responsavel = ?";
    $paramsDem[] = $id_dev_fil;
}
$stmtDem = $pdo->prepare("
    SELECT
        d.titulo,
        COALESCE(d.duracao_min, 30) AS duracao_min,
        DATE(d.atualizado_em)       AS data_np,
        u.nome                      AS dev_nome,
        'Demanda'                   AS origem
    FROM demandas d
    JOIN usuarios u ON u.id = d.id_responsavel
    WHERE " . implode(" AND ", $whereDem) . "
    ORDER BY data_np ASC, u.nome ASC
");
$stmtDem->execute($paramsDem);
$naoPlanejados = array_merge($naoPlanejados, $stmtDem->fetchAll());

// ordenar por data
usort($naoPlanejados, function($a, $b){ return strcmp($a['data_np'].$a['dev_nome'], $b['data_np'].$b['dev_nome']); });

// ── Totais para cards ─────────────────────────────────────────────────
$totalItens     = count($itens);
$totalEstimado  = 0;
$totalRealizado = 0;
$totalFinaliz   = 0;
foreach ($itens as $it) {
    $totalEstimado  += (float)$it['estimativa_media'];
    if ($it['status'] === 'finalizado' && !empty($it['tempo_real'])) {
        $totalRealizado += (float)$it['tempo_real'];
        $totalFinaliz++;
    }
}
$totalNaoPlan = 0;
foreach ($naoPlanejados as $np) { $totalNaoPlan += round($np['duracao_min'] / 60, 2); }

// ── Totais por desenvolvedor (itens finalizados) ─────────────────────
$porDev = array(); // [nome] = [estimado, realizado, nao_planejado]
foreach ($itens as $it) {
    $nome = $it['dev_nome'];
    if (!isset($porDev[$nome])) $porDev[$nome] = array('estimado'=>0,'realizado'=>0,'nao_planejado'=>0);
    $porDev[$nome]['estimado'] += (float)$it['estimativa_media'];
    if ($it['status'] === 'finalizado' && !empty($it['tempo_real'])) {
        $porDev[$nome]['realizado'] += (float)$it['tempo_real'];
    }
}
foreach ($naoPlanejados as $np) {
    $nome = $np['dev_nome'];
    if (!isset($porDev[$nome])) $porDev[$nome] = array('estimado'=>0,'realizado'=>0,'nao_planejado'=>0);
    $porDev[$nome]['nao_planejado'] += round($np['duracao_min'] / 60, 2);
}
ksort($porDev);

$menuActive = 'planejamento';
$pageTitle  = 'Relatório de Planejamento';
require_once __DIR__ . '/includes/layout_top.php';
?>

<style>
    .toprow{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:18px;flex-wrap:wrap;}
    .title h1{margin:0;font-size:22px;font-weight:900;}
    .title .sub{color:var(--muted);font-size:13px;margin-top:4px;}
    .btn{height:38px;padding:0 14px;border-radius:12px;border:1px solid var(--line);background:#fff;font-weight:800;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;text-decoration:none;color:inherit;}
    .btn:hover{background:#f3f4f6;}
    .btn-dark{background:#111;color:#fff;border-color:#111;}

    .filters{border:1px solid var(--line);border-radius:16px;padding:14px;background:#fff;margin-bottom:14px;}
    .fgrid{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;}
    .fgrid .fg{display:flex;flex-direction:column;gap:5px;}
    .fgrid label{font-size:12px;font-weight:900;}
    .fgrid input, .fgrid select{height:38px;border-radius:12px;border:1px solid var(--line);padding:0 12px;font-size:13px;outline:none;background:#fff;}

    .cards{display:grid;grid-template-columns:repeat(5, minmax(0,1fr));gap:10px;margin-bottom:16px;}
    .card{border:1px solid var(--line);border-radius:16px;padding:14px;background:#fff;}
    .card .k{font-size:12px;color:var(--muted);font-weight:900;}
    .card .v{margin-top:10px;font-size:22px;font-weight:900;}
    .card .vsub{font-size:11px;color:var(--muted);font-weight:700;margin-top:3px;}

    .section-title{font-weight:900;font-size:15px;margin:18px 0 8px;padding-bottom:6px;border-bottom:2px solid var(--line);}

    .panel{border:1px solid var(--line);border-radius:16px;padding:14px;background:#fff;margin-bottom:14px;}
    table{width:100%;border-collapse:separate;border-spacing:0;}
    thead th{font-size:12px;color:var(--muted);font-weight:900;text-align:left;padding:10px 10px;border-bottom:1px solid var(--line);}
    tbody td{padding:11px 10px;border-bottom:1px solid var(--line);font-size:13px;font-weight:800;}
    .badge{display:inline-flex;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:900;border:1px solid var(--line);background:#f9fafb;}
    .badge.fin{background:#dcfce7;border-color:#86efac;color:#166534;}
    .badge.pend{background:#f1f5f9;border-color:#cbd5e1;color:#475569;}
    .pos{color:#16a34a;} .neg{color:#dc2626;}
    .muted{color:var(--muted);}
    .np-row td{background:#fffbeb;}
</style>

<div class="toprow">
    <div class="title">
        <h1>Relatório de Planejamento</h1>
        <div class="sub">Horas planejadas × realizadas e horas não planejadas (suportes)</div>
    </div>
    <a class="btn" href="planejamento.php">← Calendário</a>
</div>

<form class="filters" method="get">
    <div class="fgrid">
        <div class="fg">
            <label>Data início</label>
            <input type="date" name="data_ini" value="<?= h($data_ini) ?>">
        </div>
        <div class="fg">
            <label>Data fim</label>
            <input type="date" name="data_fim" value="<?= h($data_fim) ?>">
        </div>
        <div class="fg">
            <label>Desenvolvedor</label>
            <select name="id_dev" style="min-width:200px;">
                <option value="0">Todos</option>
                <?php foreach ($devs as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= ($id_dev_fil==(int)$d['id'])?'selected':''; ?>>
                        <?= h($d['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="fg">
            <label>&nbsp;</label>
            <div style="display:flex;gap:8px;">
                <button class="btn btn-dark" type="submit">Filtrar</button>
                <a class="btn" href="planejamento_relatorio.php">Limpar</a>
            </div>
        </div>
    </div>
</form>

<div class="cards">
    <div class="card">
        <div class="k">Itens planejados</div>
        <div class="v"><?= $totalItens ?></div>
    </div>
    <div class="card">
        <div class="k">Total estimado</div>
        <div class="v"><?= number_format($totalEstimado, 1) ?>h</div>
    </div>
    <div class="card">
        <div class="k">Total realizado</div>
        <div class="v"><?= number_format($totalRealizado, 1) ?>h</div>
        <div class="vsub"><?= $totalFinaliz ?> item(ns) finalizado(s)</div>
    </div>
    <div class="card">
        <div class="k">Diferença</div>
        <?php $diff = $totalRealizado - $totalEstimado; ?>
        <div class="v <?= $diff > 0 ? 'neg' : ($diff < 0 ? 'pos' : '') ?>">
            <?= ($diff > 0 ? '+' : '') . number_format($diff, 1) ?>h
        </div>
        <div class="vsub"><?= $diff > 0 ? 'Acima do estimado' : ($diff < 0 ? 'Abaixo do estimado' : 'Dentro do estimado') ?></div>
    </div>
    <div class="card">
        <div class="k">Horas não planejadas</div>
        <div class="v" style="color:#b45309;"><?= number_format($totalNaoPlan, 1) ?>h</div>
        <div class="vsub">via suportes finalizados</div>
    </div>
</div>

<!-- ── Horas por Desenvolvedor ────────────────────────────────────── -->
<?php if (!empty($porDev)): ?>
<div class="section-title">👤 Horas por Desenvolvedor — itens finalizados</div>
<div class="panel" style="padding:0;overflow:hidden;">
    <table>
        <thead>
        <tr>
            <th>Desenvolvedor</th>
            <th style="text-align:right;">Estimado</th>
            <th style="text-align:right;">Realizado</th>
            <th style="text-align:right;">Não planejado</th>
            <th style="text-align:right;">Total trabalhado</th>
            <th style="text-align:right;">Diferença (real vs estimado)</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($porDev as $devNome => $d):
            $total = $d['realizado'] + $d['nao_planejado'];
            $difDev = $d['realizado'] - $d['estimado'];
        ?>
        <tr>
            <td style="font-weight:900;"><?= h($devNome) ?></td>
            <td style="text-align:right;color:var(--muted);"><?= number_format($d['estimado'],1) ?>h</td>
            <td style="text-align:right;font-weight:900;"><?= number_format($d['realizado'],1) ?>h</td>
            <td style="text-align:right;color:#b45309;font-weight:900;"><?= number_format($d['nao_planejado'],1) ?>h</td>
            <td style="text-align:right;font-weight:900;font-size:15px;"><?= number_format($total,1) ?>h</td>
            <td style="text-align:right;">
                <?php if ($d['realizado'] > 0): ?>
                    <span class="<?= $difDev > 0 ? 'neg' : 'pos' ?>">
                        <?= ($difDev > 0 ? '+' : '') . number_format($difDev,1) ?>h
                    </span>
                <?php else: ?>
                    <span class="muted">—</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
        <tr style="background:#f9fafb;">
            <?php
                $sumEst  = array_sum(array_column($porDev,'estimado'));
                $sumReal = array_sum(array_column($porDev,'realizado'));
                $sumNp   = array_sum(array_column($porDev,'nao_planejado'));
                $sumTot  = $sumReal + $sumNp;
                $sumDif  = $sumReal - $sumEst;
            ?>
            <td style="font-weight:900;">Total</td>
            <td style="text-align:right;color:var(--muted);font-weight:900;"><?= number_format($sumEst,1) ?>h</td>
            <td style="text-align:right;font-weight:900;"><?= number_format($sumReal,1) ?>h</td>
            <td style="text-align:right;color:#b45309;font-weight:900;"><?= number_format($sumNp,1) ?>h</td>
            <td style="text-align:right;font-weight:900;font-size:15px;"><?= number_format($sumTot,1) ?>h</td>
            <td style="text-align:right;">
                <?php if ($sumReal > 0): ?>
                    <span class="<?= $sumDif > 0 ? 'neg' : 'pos' ?>" style="font-weight:900;">
                        <?= ($sumDif > 0 ? '+' : '') . number_format($sumDif,1) ?>h
                    </span>
                <?php else: ?>
                    <span class="muted">—</span>
                <?php endif; ?>
            </td>
        </tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>

<!-- ── Tabela de itens planejados ────────────────────────────────── -->
<div class="section-title">📋 Itens Planejados</div>
<div class="panel">
    <?php if (!empty($itens)): ?>
    <table>
        <thead>
        <tr>
            <th>Data</th>
            <th>Desenvolvedor</th>
            <th>Título</th>
            <th>Est. mín</th>
            <th>Est. máx</th>
            <th>Média</th>
            <th>Tempo real</th>
            <th>Diferença</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($itens as $it):
            $difIt = (!empty($it['tempo_real']) && $it['status']==='finalizado')
                     ? ((float)$it['tempo_real'] - (float)$it['estimativa_media'])
                     : null;
        ?>
        <tr>
            <td><?= h(date('d/m/Y', strtotime($it['data_alocada']))) ?></td>
            <td><?= h($it['dev_nome']) ?></td>
            <td><?= h($it['titulo']) ?></td>
            <td class="muted"><?= number_format((float)$it['estimativa_min'],1) ?>h</td>
            <td class="muted"><?= number_format((float)$it['estimativa_max'],1) ?>h</td>
            <td><strong><?= number_format((float)$it['estimativa_media'],1) ?>h</strong></td>
            <td>
                <?php if (!empty($it['tempo_real']) && $it['status']==='finalizado'): ?>
                    <strong><?= number_format((float)$it['tempo_real'],1) ?>h</strong>
                <?php else: ?>
                    <span class="muted">—</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($difIt !== null): ?>
                    <span class="<?= $difIt > 0 ? 'neg' : 'pos' ?>">
                        <?= ($difIt > 0 ? '+' : '') . number_format($difIt, 1) ?>h
                    </span>
                <?php else: ?>
                    <span class="muted">—</span>
                <?php endif; ?>
            </td>
            <td>
                <span class="badge <?= $it['status']==='finalizado' ? 'fin' : 'pend' ?>">
                    <?= $it['status']==='finalizado' ? 'Finalizado' : ucfirst($it['status']) ?>
                </span>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div style="padding:24px;text-align:center;color:var(--muted);font-weight:800;">
            Nenhum item planejado no período.
        </div>
    <?php endif; ?>
</div>

<!-- ── Tabela de horas não planejadas ────────────────────────────── -->
<div class="section-title">⚡ Horas Não Planejadas — Demandas e Suportes Finalizados</div>
<div class="panel">
    <?php if (!empty($naoPlanejados)): ?>
    <table>
        <thead>
        <tr>
            <th>Data</th>
            <th>Desenvolvedor</th>
            <th>Título</th>
            <th>Origem</th>
            <th>Duração</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($naoPlanejados as $np): ?>
        <tr class="np-row">
            <td><?= h(date('d/m/Y', strtotime($np['data_np']))) ?></td>
            <td><?= h($np['dev_nome']) ?></td>
            <td><?= h($np['titulo']) ?></td>
            <td>
                <span class="badge" style="<?= $np['origem']==='Demanda' ? 'background:#eff6ff;border-color:#93c5fd;color:#1d4ed8;' : 'background:#fffbeb;border-color:#fde68a;color:#92400e;' ?>">
                    <?= $np['origem']==='Demanda' ? '📋 Demanda' : '🎧 Suporte' ?>
                </span>
            </td>
            <td style="color:#b45309;font-weight:900;"><?= number_format($np['duracao_min']/60, 1) ?>h</td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div style="padding:24px;text-align:center;color:var(--muted);font-weight:800;">
            Nenhuma hora não planejada no período.
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
