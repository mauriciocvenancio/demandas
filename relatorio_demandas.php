<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo = db();
date_default_timezone_set('America/Sao_Paulo');

if (session_status() === PHP_SESSION_NONE) session_start();

/* ===== Filtros ===== */
$data_ini    = isset($_GET['data_ini']) ? trim((string)$_GET['data_ini']) : date('Y-m-01');
$data_fim    = isset($_GET['data_fim']) ? trim((string)$_GET['data_fim']) : date('Y-m-d');
$id_cliente  = isset($_GET['id_cliente']) ? (int)$_GET['id_cliente'] : 0;
$id_usuario  = isset($_GET['id_usuario']) ? (int)$_GET['id_usuario'] : 0;
$status      = isset($_GET['status']) ? trim((string)$_GET['status']) : 'todos';
$criticidade = isset($_GET['criticidade']) ? trim((string)$_GET['criticidade']) : 'todos';

function validDate($d){ return (bool)preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $d); }
if (!validDate($data_ini)) $data_ini = date('Y-m-01');
if (!validDate($data_fim)) $data_fim = date('Y-m-d');

$allowedStatus = array('nao_iniciado','em_andamento','aguardando_resposta_cliente','finalizado','publicado');
$allowedCrit   = array('baixa','media','alta','urgente');

$where  = array("d.ativo=1", "DATE(d.criado_em) >= ?", "DATE(d.criado_em) <= ?");
$params = array($data_ini, $data_fim);

if ($id_cliente > 0) { $where[] = "d.id_cliente=?"; $params[] = $id_cliente; }
if ($id_usuario > 0) { $where[] = "d.id_responsavel=?"; $params[] = $id_usuario; }

if ($status !== 'todos' && in_array($status, $allowedStatus, true)) {
    $where[] = "d.status=?";
    $params[] = $status;
}
if ($criticidade !== 'todos' && in_array($criticidade, $allowedCrit, true)) {
    $where[] = "d.criticidade=?";
    $params[] = $criticidade;
}

/* ===== Resumo (cards) ===== */
$sqlResumo = "
SELECT
  COUNT(*) as total,
  SUM(CASE WHEN d.status='nao_iniciado' THEN 1 ELSE 0 END) as nao_iniciado,
  SUM(CASE WHEN d.status='em_andamento' THEN 1 ELSE 0 END) as em_andamento,
  SUM(CASE WHEN d.status='aguardando_resposta_cliente' THEN 1 ELSE 0 END) as aguardando_resposta,
  SUM(CASE WHEN d.status='finalizado' THEN 1 ELSE 0 END) as finalizado,
  SUM(CASE WHEN d.status='publicado' THEN 1 ELSE 0 END) as publicado
FROM demandas d
WHERE ".implode(" AND ", $where)."
";
$stR = $pdo->prepare($sqlResumo);
$stR->execute($params);
$resumo = $stR->fetch();

/* ===== Lista detalhada ===== */
$sql = "
SELECT
  d.*,
  c.nome AS cliente_nome,
  u.nome AS responsavel_nome
FROM demandas d
JOIN clientes c ON c.id=d.id_cliente
JOIN usuarios u ON u.id=d.id_responsavel
WHERE ".implode(" AND ", $where)."
ORDER BY d.criado_em DESC
LIMIT 3000
";
$st = $pdo->prepare($sql);
$st->execute($params);
$linhas = $st->fetchAll();

/* combos */
$clientes = $pdo->query("SELECT id, nome FROM clientes WHERE ativo=1 ORDER BY nome ASC")->fetchAll();
$usuarios = $pdo->query("SELECT id, nome FROM usuarios WHERE ativo=1 ORDER BY nome ASC")->fetchAll();

function labelStatus($s){
    $map = array(
        'nao_iniciado' => 'Não iniciado',
        'em_andamento' => 'Em andamento',
        'aguardando_resposta_cliente' => 'Aguardando resposta cliente',
        'finalizado' => 'Finalizado',
        'publicado' => 'Publicado',
    );
    return isset($map[$s]) ? $map[$s] : $s;
}
function labelCrit($c){
    $map = array('baixa'=>'Baixa','media'=>'Média','alta'=>'Alta','urgente'=>'Urgente');
    return isset($map[$c]) ? $map[$c] : $c;
}

$menuActive = 'relatorios';
$pageTitle  = 'Relatório de Demandas';
require_once __DIR__ . '/includes/layout_top.php';

$csvUrl = 'relatorio_demandas_csv.php?' . http_build_query($_GET);
?>

<style>
    .toprow{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:18px;}
    .title h1{margin:0;font-size:22px;font-weight:900;}
    .title .sub{color:var(--muted);font-size:13px;margin-top:4px;}

    .filters{border:1px solid var(--line);border-radius:16px;padding:14px;background:#fff;margin-bottom:14px;}
    .grid{display:grid;grid-template-columns: 160px 160px 260px 260px 220px 220px auto;gap:10px;align-items:end;}
    label{display:block;font-size:12px;font-weight:900;margin:0 0 6px;}
    input, select{
        width:100%;height:40px;border-radius:12px;border:1px solid var(--line);padding:0 12px;font-size:13px;outline:none;background:#fff;
    }
    .btn{height:40px;padding:0 14px;border-radius:12px;border:1px solid var(--line);background:#fff;font-weight:900;font-size:13px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px;}
    .btn:hover{background:#f6f6f6;}
    .btn-dark{background:#111;color:#fff;border-color:#111;}
    .btn-dark:hover{opacity:.92}

    .cards{display:grid;grid-template-columns:repeat(6, minmax(0,1fr));gap:10px;margin-bottom:14px;}
    .card{border:1px solid var(--line);border-radius:16px;padding:14px;background:#fff;}
    .card .k{font-size:12px;color:var(--muted);font-weight:900;}
    .card .v{margin-top:10px;font-size:22px;font-weight:900;}

    .panel{border:1px solid var(--line);border-radius:16px;padding:14px;background:#fff;}
    table{width:100%;border-collapse:separate;border-spacing:0;}
    thead th{font-size:12px;color:var(--muted);text-align:left;font-weight:900;padding:12px 10px;border-bottom:1px solid var(--line);}
    tbody td{padding:12px 10px;border-bottom:1px solid var(--line);font-size:13px;font-weight:800;}
    .badge{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;border:1px solid var(--line);font-size:12px;font-weight:900;background:#fff;}
    .badge-dark{background:#111;color:#fff;border-color:#111;}
    .muted{color:var(--muted);font-weight:800;}

    @media(max-width: 1180px){
        .grid{grid-template-columns: 1fr 1fr; }
    }
</style>

<div class="toprow">
    <div class="title">
        <h1>Relatório de Demandas</h1>
        <div class="sub">Filtre por período, cliente, responsável, status e criticidade</div>
    </div>
    <div style="display:flex;gap:10px;">
        <a class="btn btn-dark" href="<?= h($csvUrl) ?>">Exportar CSV</a>
    </div>
</div>

<form class="filters" method="get">
    <div class="grid">
        <div>
            <label>Data início</label>
            <input type="date" name="data_ini" value="<?= h($data_ini) ?>">
        </div>
        <div>
            <label>Data fim</label>
            <input type="date" name="data_fim" value="<?= h($data_fim) ?>">
        </div>
        <div>
            <label>Cliente</label>
            <select name="id_cliente">
                <option value="0">Todos</option>
                <?php foreach ($clientes as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= ($id_cliente==(int)$c['id'])?'selected':''; ?>>
                        <?= h($c['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Responsável</label>
            <select name="id_usuario">
                <option value="0">Todos</option>
                <?php foreach ($usuarios as $u): ?>
                    <option value="<?= (int)$u['id'] ?>" <?= ($id_usuario==(int)$u['id'])?'selected':''; ?>>
                        <?= h($u['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Status</label>
            <select name="status">
                <option value="todos" <?= ($status==='todos')?'selected':''; ?>>Todos</option>
                <option value="nao_iniciado" <?= ($status==='nao_iniciado')?'selected':''; ?>>Não iniciado</option>
                <option value="em_andamento" <?= ($status==='em_andamento')?'selected':''; ?>>Em andamento</option>
                <option value="aguardando_resposta_cliente" <?= ($status==='aguardando_resposta_cliente')?'selected':''; ?>>Aguardando resposta cliente</option>
                <option value="finalizado" <?= ($status==='finalizado')?'selected':''; ?>>Finalizado</option>
                <option value="publicado" <?= ($status==='publicado')?'selected':''; ?>>Publicado</option>
            </select>
        </div>
        <div>
            <label>Criticidade</label>
            <select name="criticidade">
                <option value="todos" <?= ($criticidade==='todos')?'selected':''; ?>>Todas</option>
                <option value="baixa" <?= ($criticidade==='baixa')?'selected':''; ?>>Baixa</option>
                <option value="media" <?= ($criticidade==='media')?'selected':''; ?>>Média</option>
                <option value="alta" <?= ($criticidade==='alta')?'selected':''; ?>>Alta</option>
                <option value="urgente" <?= ($criticidade==='urgente')?'selected':''; ?>>Urgente</option>
            </select>
        </div>
        <div style="display:flex;gap:10px;">
            <button class="btn" type="submit">Filtrar</button>
            <a class="btn" href="relatorio_demandas.php">Limpar</a>
        </div>
    </div>
</form>

<div class="cards">
    <div class="card"><div class="k">Total</div><div class="v"><?= (int)$resumo['total'] ?></div></div>
    <div class="card"><div class="k">Não iniciado</div><div class="v"><?= (int)$resumo['nao_iniciado'] ?></div></div>
    <div class="card"><div class="k">Em andamento</div><div class="v"><?= (int)$resumo['em_andamento'] ?></div></div>
    <div class="card"><div class="k">Aguardando resposta</div><div class="v"><?= (int)$resumo['aguardando_resposta'] ?></div></div>
    <div class="card"><div class="k">Finalizado</div><div class="v"><?= (int)$resumo['finalizado'] ?></div></div>
    <div class="card"><div class="k">Publicado</div><div class="v"><?= (int)$resumo['publicado'] ?></div></div>
</div>

<div class="panel">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;">
        <div style="font-weight:900;">Detalhamento</div>
        <div class="muted">Mostrando até 3000 registros</div>
    </div>

    <?php if (!empty($linhas)): ?>
        <table>
            <thead>
            <tr>
                <th>Data</th>
                <th>Título</th>
                <th>Cliente</th>
                <th>Responsável</th>
                <th>Criticidade</th>
                <th>Status</th>
                <th>Prazo</th>
                <th style="width:140px;">Ações</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($linhas as $r): ?>
                <tr>
                    <td><?= h(date('d/m/Y', strtotime($r['criado_em']))) ?></td>
                    <td><?= h($r['titulo']) ?></td>
                    <td><?= h($r['cliente_nome']) ?></td>
                    <td><?= h($r['responsavel_nome']) ?></td>
                    <td><span class="badge badge-dark"><?= h(labelCrit($r['criticidade'])) ?></span></td>
                    <td><span class="badge"><?= h(labelStatus($r['status'])) ?></span></td>
                    <td>
                        <?php if (!empty($r['prazo'])): ?>
                            <?= h(date('d/m/Y', strtotime($r['prazo']))) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="btn" style="height:32px;padding:0 10px;" href="demanda_view.php?id=<?= (int)$r['id'] ?>">Ver</a>
                        <a class="btn" style="height:32px;padding:0 10px;" href="demandas.php?edit=<?= (int)$r['id'] ?>">Editar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="muted" style="padding:22px 10px;text-align:center;">Nenhuma demanda encontrada para os filtros aplicados.</div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
