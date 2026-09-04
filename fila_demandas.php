<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo = db();
$u   = auth_user();

$q      = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$status = isset($_GET['status']) ? trim((string)$_GET['status']) : 'todos';
$crit   = isset($_GET['crit']) ? trim((string)$_GET['crit']) : 'todos';
$ftipo  = isset($_GET['tipo']) ? trim((string)$_GET['tipo']) : '';

$allowedTipos = array('bug','solicitacao_novo_item','uso_incorreto','orientacoes_duvidas','desenvolvimento','sem_tipo');

// contagem por tipo para os cards
$stmtCards = $pdo->prepare("
    SELECT
        CASE WHEN tipo_demanda IS NULL OR tipo_demanda = '' THEN 'sem_tipo' ELSE tipo_demanda END AS tipo,
        COUNT(*) AS total
    FROM demandas
    WHERE ativo=1
      AND id_responsavel = ?
      AND status NOT IN ('finalizado','publicado')
    GROUP BY tipo
");
$stmtCards->execute(array((int)$u['id']));
$contagensTipo = array();
foreach ($stmtCards->fetchAll() as $row) {
    $contagensTipo[$row['tipo']] = (int)$row['total'];
}

$where  = array("d.ativo=1", "d.id_responsavel = ?");
$params = array((int)$u['id']);

if ($q !== '') {
    $where[] = "(d.titulo LIKE ? OR c.nome LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$allowedStatus = array('nao_iniciado','em_andamento','aguardando_resposta_cliente','finalizado','publicado');
if ($status !== 'todos' && in_array($status, $allowedStatus, true)) {
    $where[] = "d.status = ?";
    $params[] = $status;
}

$allowedCrit = array('baixa','media','alta','urgente');
if ($crit !== 'todos' && in_array($crit, $allowedCrit, true)) {
    $where[] = "d.criticidade = ?";
    $params[] = $crit;
}

if ($ftipo !== '' && in_array($ftipo, $allowedTipos, true)) {
    if ($ftipo === 'sem_tipo') {
        $where[] = "(d.tipo_demanda IS NULL OR d.tipo_demanda = '')";
    } else {
        $where[] = "d.tipo_demanda = ?";
        $params[] = $ftipo;
    }
}

$orderBy = "d.criado_em ASC";

$sql = "
SELECT d.id,
       d.titulo,
       d.status,
       d.criticidade,
       d.prazo,
       d.criado_em,
       d.tipo_demanda,
       c.nome AS cliente_nome,
       u2.nome AS responsavel_nome
FROM demandas d
JOIN clientes c ON c.id = d.id_cliente
JOIN usuarios u2 ON u2.id = d.id_responsavel
WHERE (d.status <> 'finalizado' OR d.status IS NULL)
  AND ".implode(" AND ", $where)."
ORDER BY $orderBy
LIMIT 300
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$linhas = $stmt->fetchAll();

function labelTipoDem($t){
    $map = array(
        'bug'                  => 'Bug',
        'solicitacao_novo_item'=> 'Solicitação novo item ou melhoria',
        'uso_incorreto'        => 'Uso incorreto do Cliente',
        'orientacoes_duvidas'  => 'Orientações e Dúvidas',
        'desenvolvimento'      => 'Desenvolvimento',
        'sem_tipo'             => 'Sem Tipo',
    );
    return isset($map[$t]) ? $map[$t] : $t;
}
function labelStatusDem($s){
    $map = array(
        'nao_iniciado'=>'Não Iniciado',
        'em_andamento'=>'Em Andamento',
        'aguardando_resposta_cliente'=>'Aguardando Resposta',
        'finalizado'=>'Finalizado',
        'publicado'=>'Publicado'
    );
    return isset($map[$s]) ? $map[$s] : $s;
}
function labelCritDem($c){
    $map = array('baixa'=>'Baixa','media'=>'Média','alta'=>'Alta','urgente'=>'Urgente');
    return isset($map[$c]) ? $map[$c] : $c;
}
function diasPrazoDem($prazo, $criado_em, $status = ''){
    $finalizado = in_array($status, array('finalizado', 'publicado'), true);
    $hoje = strtotime(date('Y-m-d'));
    if (!empty($prazo)) {
        $tp   = strtotime($prazo);
        if (!$tp) return null;
        $diff = (int)round(($tp - $hoje) / 86400);
        if ($diff === 0) return array('txt' => 'Hoje', 'cor' => '#16a34a', 'tip' => 'Prazo é hoje');
        if ($finalizado) return array('txt' => '✓ Concluído', 'cor' => '#16a34a', 'tip' => 'Demanda finalizada');
        $abs  = abs($diff);
        $tip  = $diff > 0 ? "Faltam {$abs} dia(s)" : "Vencido há {$abs} dia(s)";
        return array('txt' => $diff . 'd', 'cor' => '#dc2626', 'tip' => $tip);
    }
    if (!empty($criado_em)) {
        if ($finalizado) return array('txt' => '✓ Concluído', 'cor' => '#16a34a', 'tip' => 'Demanda finalizada');
        $tc  = strtotime(date('Y-m-d', strtotime($criado_em)));
        if (!$tc) return null;
        $dias = (int)round(($hoje - $tc) / 86400);
        return array('txt' => $dias . 'd em aberto', 'cor' => '#9ca3af', 'tip' => "Sem prazo — {$dias} dia(s) desde a criação");
    }
    return null;
}
function diasCriacaoDem($criado_em, $prazo){
    if (empty($criado_em) || empty($prazo)) return null;
    $tc = strtotime(date('Y-m-d', strtotime($criado_em)));
    $tp = strtotime(date('Y-m-d', strtotime($prazo)));
    if (!$tc || !$tp) return null;
    $dias = (int)round(($tp - $tc) / 86400);
    if ($dias === 0) return array('txt' => 'Mesmo dia', 'cor' => '#16a34a');
    if ($dias <= 5)  return array('txt' => $dias . 'd',  'cor' => '#d97706');
    return              array('txt' => $dias . 'd',      'cor' => '#dc2626');
}

$menuActive = 'fila_demandas';
$pageTitle  = 'Fila de Demandas';
require_once __DIR__ . '/includes/layout_top.php';
?>

    <style>
        .toprow{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:18px;}
        .title h1{margin:0;font-size:22px;font-weight:900;}
        .title .sub{color:var(--muted);font-size:13px;margin-top:4px;}

        .panel{padding:16px;}
        .toolbar{display:flex;justify-content:flex-end;gap:10px;margin-bottom:10px;}
        .input, .select{
            height:40px;border-radius:12px;border:1px solid var(--line);
            padding:0 12px;font-size:13px;outline:none;background:#fff;
        }
        .input{width:260px;}
        .select{width:190px;}

        table{width:100%;border-collapse:separate;border-spacing:0;}
        thead th{font-size:12px;color:var(--muted);text-align:left;font-weight:900;padding:12px 10px;border-bottom:1px solid var(--line);}
        tbody td{padding:14px 10px;border-bottom:1px solid var(--line);font-size:13px;font-weight:800;}
        .badge{
            display:inline-flex;align-items:center;gap:8px;
            padding:4px 12px;border-radius:999px;border:1px solid var(--line);
            font-size:12px;font-weight:900;background:#fff;
        }
        .badge-dark{background:#111;color:#fff;border-color:#111;}
        .right{display:flex;justify-content:flex-end;}
        .kebab{cursor:pointer;border:1px solid transparent;background:transparent;font-size:18px;line-height:1;padding:6px 10px;border-radius:10px;}
        .kebab:hover{border-color:var(--line);background:#fff;}
        .menu{position:relative;}
        .menu-panel{
            position:absolute;right:0;top:38px;z-index:50;
            border:1px solid var(--line);background:#fff;border-radius:12px;
            min-width:160px;box-shadow:0 10px 30px rgba(0,0,0,.08);
            overflow:hidden;display:none;
        }
        .menu-panel button{
            width:100%;text-align:left;border:0;background:#fff;padding:10px 12px;
            font-weight:800;font-size:13px;cursor:pointer;
        }
        .menu-panel button:hover{background:#f6f6f6;}
        .empty{padding:46px 10px;text-align:center;color:var(--muted);font-weight:900;}

        /* cards de tipo */
        .tipo-cards{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:18px;}

        /* botão imprimir */
        .btn-print{
            display:inline-flex;align-items:center;gap:8px;
            padding:9px 16px;border-radius:12px;
            border:1px solid var(--line);background:#fff;
            font-weight:800;font-size:13px;cursor:pointer;
        }
        .btn-print:hover{background:#f3f4f6;}

        /* ===== PRINT ===== */
        @media print {
            .sidebar, .topbar, .toolbar, .tipo-cards, .right, .menu, script { display:none !important; }
            body { background:#fff !important; }
            .main { display:block !important; }
            .app { display:block !important; }
            .content { padding:0 !important; }
            .panel { padding:0 !important; box-shadow:none !important; border:none !important; }
            table { width:100%; border-collapse:collapse; }
            thead th { font-size:11px; color:#444; border-bottom:2px solid #111; padding:8px 6px; text-align:left; }
            tbody td { font-size:12px; padding:7px 6px; border-bottom:1px solid #ddd; }
            .badge { border:1px solid #ccc; padding:2px 8px; border-radius:999px; font-size:11px; }
            .badge-dark { background:#111 !important; color:#fff !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
            .print-header { display:block !important; }
            .toprow { display:none !important; }
        }
        .print-header { display:none; }
        .tipo-card{
            flex:1;min-width:160px;
            border:2px solid var(--line);border-radius:16px;
            padding:16px 18px;background:#fff;cursor:pointer;
            text-decoration:none;color:inherit;
            transition:border-color .15s, box-shadow .15s;
        }
        .tipo-card:hover{border-color:#111;box-shadow:0 4px 16px rgba(0,0,0,.08);}
        .tipo-card.ativo{border-color:#111;background:#111;color:#fff;}
        .tipo-card .tc-num{font-size:28px;font-weight:900;line-height:1;}
        .tipo-card .tc-lbl{font-size:12px;font-weight:800;margin-top:6px;opacity:.75;}
        .tipo-card.ativo .tc-lbl{opacity:.85;}
        .tipo-card .tc-clear{font-size:11px;font-weight:700;margin-top:6px;text-decoration:underline;opacity:.6;}
    </style>

    <!-- Cabeçalho visível apenas na impressão -->
    <div class="print-header" style="margin-bottom:18px;">
        <div style="font-size:20px;font-weight:900;border-bottom:2px solid #111;padding-bottom:8px;margin-bottom:4px;">
            Fila de Demandas
        </div>
        <div style="font-size:12px;color:#555;margin-bottom:4px;">
            Responsável: <?= h($u['nome']) ?> &nbsp;|&nbsp; Gerado em: <?= date('d/m/Y H:i') ?>
        </div>
        <?php
        $filtros = array();
        if ($q !== '')                                         $filtros[] = 'Busca: "' . $q . '"';
        if ($status !== 'todos')                               $filtros[] = 'Status: ' . labelStatusDem($status);
        if ($crit !== 'todos')                                 $filtros[] = 'Criticidade: ' . labelCritDem($crit);
        if ($ftipo !== '' && in_array($ftipo, $allowedTipos, true)) $filtros[] = 'Tipo: ' . labelTipoDem($ftipo);
        if (!empty($filtros)):
        ?>
        <div style="font-size:12px;color:#555;">Filtros: <?= h(implode(' · ', $filtros)) ?></div>
        <?php endif; ?>
    </div>

    <div class="toprow">
        <div class="title">
            <h1>Fila de Demandas</h1>
            <div class="sub">Tudo que está atribuído a você como responsável</div>
        </div>
        <button class="btn-print" onclick="window.print()" title="Imprimir lista atual">
            🖨️ Imprimir
        </button>
    </div>

    <?php
    $tiposOrdem = array('bug','solicitacao_novo_item','uso_incorreto','orientacoes_duvidas','desenvolvimento','sem_tipo');
    $totalCards = 0;
    foreach ($tiposOrdem as $t) { $totalCards += isset($contagensTipo[$t]) ? $contagensTipo[$t] : 0; }
    ?>
    <?php if ($totalCards > 0): ?>
    <div class="tipo-cards">
        <?php foreach ($tiposOrdem as $t):
            $cnt = isset($contagensTipo[$t]) ? $contagensTipo[$t] : 0;
            if ($cnt === 0) continue;
            $ativo = ($ftipo === $t);
            $url   = '?' . http_build_query(array_merge(
                array('q'=>$q,'status'=>$status,'crit'=>$crit),
                $ativo ? array() : array('tipo'=>$t)
            ));
        ?>
        <a href="<?= h($url) ?>" class="tipo-card <?= $ativo ? 'ativo' : '' ?>">
            <div class="tc-num"><?= $cnt ?></div>
            <div class="tc-lbl"><?= h(labelTipoDem($t)) ?></div>
            <?php if ($ativo): ?>
                <div class="tc-clear">Clique para remover filtro</div>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="panel">
        <div class="toolbar">
            <form method="get" style="display:flex;gap:10px;">
                <input class="input" type="text" name="q" value="<?= h($q) ?>" placeholder="Buscar demandas...">

                <select class="select" name="status">
                    <option value="todos" <?= ($status==='todos')?'selected':''; ?>>Todos Status</option>
                    <option value="nao_iniciado" <?= ($status==='nao_iniciado')?'selected':''; ?>>Não Iniciado</option>
                    <option value="em_andamento" <?= ($status==='em_andamento')?'selected':''; ?>>Em Andamento</option>
                    <option value="aguardando_resposta_cliente" <?= ($status==='aguardando_resposta_cliente')?'selected':''; ?>>Aguardando Resposta</option>
                    <option value="finalizado" <?= ($status==='finalizado')?'selected':''; ?>>Finalizado</option>
                    <option value="publicado" <?= ($status==='publicado')?'selected':''; ?>>Publicado</option>
                </select>

                <select class="select" name="crit">
                    <option value="todos" <?= ($crit==='todos')?'selected':''; ?>>Todas Criticidades</option>
                    <option value="baixa" <?= ($crit==='baixa')?'selected':''; ?>>Baixa</option>
                    <option value="media" <?= ($crit==='media')?'selected':''; ?>>Média</option>
                    <option value="alta" <?= ($crit==='alta')?'selected':''; ?>>Alta</option>
                    <option value="urgente" <?= ($crit==='urgente')?'selected':''; ?>>Urgente</option>
                </select>

                <button class="btn" type="submit">Filtrar</button>
            </form>
        </div>

        <div style="font-weight:900;font-size:15px;margin-bottom:10px;display:flex;align-items:center;gap:12px;">
            Minhas Demandas
            <?php if ($ftipo !== '' && in_array($ftipo, $allowedTipos, true)): ?>
                <span style="font-size:12px;font-weight:700;color:var(--muted);">
                    — <?= h(labelTipoDem($ftipo)) ?> · do mais antigo ao mais novo
                </span>
            <?php endif; ?>
            <span style="font-size:12px;font-weight:700;color:var(--muted);margin-left:auto;">
                <?= count($linhas) ?> item(s)
            </span>
        </div>

        <?php if (!empty($linhas)): ?>
            <table>
                <thead>
                <tr>
                    <th>Título</th>
                    <th>Tipo</th>
                    <th>Cliente</th>
                    <th>Responsável</th>
                    <th>Criticidade</th>
                    <th>Status</th>
                    <th>Prazo</th>
                    <th>Criação</th>
                    <th class="right"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($linhas as $r): ?>
                    <tr>
                        <td><?= h($r['titulo']) ?></td>
                        <td style="font-size:12px;">
                            <?php if (!empty($r['tipo_demanda'])): ?>
                                <span class="badge"><?= h(labelTipoDem($r['tipo_demanda'])) ?></span>
                            <?php else: ?>
                                <span style="color:var(--muted);font-weight:700;">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= h($r['cliente_nome']) ?></td>
                        <td><?= h($r['responsavel_nome']) ?></td>
                        <td><span class="badge badge-dark"><?= h(labelCritDem($r['criticidade'])) ?></span></td>
                        <td><span class="badge"><?= h(labelStatusDem($r['status'])) ?></span></td>
                        <td>
                            <?= !empty($r['prazo']) ? h(date('d/m/Y', strtotime($r['prazo']))) : '-' ?>
                        </td>
                        <td style="font-size:12px;color:var(--muted);">
                            <?= !empty($r['criado_em']) ? h(date('d/m/Y H:i', strtotime($r['criado_em']))) : '-' ?>
                            <?php $dc = diasCriacaoDem($r['criado_em'], $r['prazo']); if ($dc): ?>
                                <div style="font-size:11px;margin-top:3px;font-weight:900;color:<?= $dc['cor'] ?>;"><?= $dc['txt'] ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="right">
                            <div class="menu">
                                <button class="kebab" type="button" onclick="toggleMenu(<?= (int)$r['id'] ?>)">…</button>
                                <div class="menu-panel" id="menu-<?= (int)$r['id'] ?>">
                                    <button type="button" onclick="window.location.href='demanda_view.php?id=<?= (int)$r['id'] ?>'">👁 Visualizar</button>
                                    <button type="button" onclick="window.location.href='demanda_edit.php?id=<?= (int)$r['id'] ?>'">✏️ Editar</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty">Nenhuma demanda atribuída a você no momento.</div>
        <?php endif; ?>
    </div>

    <script>
        function toggleMenu(id){
            closeAllMenus();
            var el = document.getElementById('menu-'+id);
            if (!el) return;
            el.style.display = (el.style.display === 'block') ? 'none' : 'block';
        }
        function closeAllMenus(){
            var menus = document.querySelectorAll('.menu-panel');
            for (var i=0;i<menus.length;i++) menus[i].style.display='none';
        }
        document.addEventListener('click', function(e){
            if (!e.target.classList.contains('kebab')) closeAllMenus();
        });
    </script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>