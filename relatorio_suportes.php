<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo = db();

date_default_timezone_set('America/Sao_Paulo');

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16));

/* ===== Filtros ===== */
$data_ini   = isset($_GET['data_ini']) ? trim((string)$_GET['data_ini']) : date('Y-m-01');
$data_fim   = isset($_GET['data_fim']) ? trim((string)$_GET['data_fim']) : date('Y-m-d');
$id_cliente = isset($_GET['id_cliente']) ? (int)$_GET['id_cliente'] : 0;
$tipo       = isset($_GET['tipo']) ? trim((string)$_GET['tipo']) : 'todos';
$status     = isset($_GET['status']) ? trim((string)$_GET['status']) : 'todos';

/* valida datas (YYYY-MM-DD) */
function validDate($d){
    return (bool)preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $d);
}
if (!validDate($data_ini)) $data_ini = date('Y-m-01');
if (!validDate($data_fim)) $data_fim = date('Y-m-d');

$allowedTipos = array('melhoria','duvida','solicitacao','bug','configuracao');
$allowedStatus = array('em_andamento','finalizado','aguardando_resposta');

$where  = array("s.ativo=1", "DATE(s.criado_em) >= ?", "DATE(s.criado_em) <= ?");
$params = array($data_ini, $data_fim);

if ($id_cliente > 0) { $where[]="s.id_cliente=?"; $params[]=$id_cliente; }
if ($tipo !== 'todos' && in_array($tipo, $allowedTipos, true)) { $where[]="s.tipo_contato=?"; $params[]=$tipo; }
if ($status !== 'todos' && in_array($status, $allowedStatus, true)) { $where[]="s.status=?"; $params[]=$status; }

/* ===== Resumo (cards) ===== */
$sqlResumo = "
SELECT
  COUNT(*) as total,
  SUM(CASE WHEN s.status='em_andamento' THEN 1 ELSE 0 END) as em_andamento,
  SUM(CASE WHEN s.status='aguardando_resposta' THEN 1 ELSE 0 END) as aguardando_resposta,
  SUM(CASE WHEN s.status='finalizado' THEN 1 ELSE 0 END) as finalizado,
  COALESCE(SUM(s.duracao_min),0) as duracao_total,
  COALESCE(AVG(NULLIF(s.duracao_min,0)),0) as duracao_media
FROM suportes s
WHERE ".implode(" AND ", $where)."
";
$stR = $pdo->prepare($sqlResumo);
$stR->execute($params);
$resumo = $stR->fetch();

/* ===== Lista detalhada ===== */
$sql = "
SELECT s.*,
       c.nome AS cliente_nome,
       u.nome AS usuario_nome
FROM suportes s
JOIN clientes c ON c.id=s.id_cliente
JOIN usuarios u ON u.id=s.id_usuario_registro
WHERE ".implode(" AND ", $where)."
ORDER BY s.criado_em DESC
LIMIT 2000
";
$st = $pdo->prepare($sql);
$st->execute($params);
$linhas = $st->fetchAll();

/* combos */
$clientes = $pdo->query("SELECT id, nome FROM clientes WHERE ativo=1 ORDER BY nome ASC")->fetchAll();

function labelTipo($t){
    $map = array(
        'melhoria'=>'Melhoria','duvida'=>'Dúvida','solicitacao'=>'Solicitação','bug'=>'Bug','configuracao'=>'Configuração'
    );
    return isset($map[$t]) ? $map[$t] : $t;
}
function labelStatus($s){
    $map = array('em_andamento'=>'Em andamento','aguardando_resposta'=>'Aguardando resposta','finalizado'=>'Finalizado');
    return isset($map[$s]) ? $map[$s] : $s;
}
function labelCrit($c){
    $map = array('baixa'=>'Baixa','media'=>'Média','alta'=>'Alta','urgente'=>'Urgente');
    return isset($map[$c]) ? $map[$c] : $c;
}

$menuActive = 'relatorios';
$pageTitle  = 'Relatório de Suportes';
require_once __DIR__ . '/includes/layout_top.php';
?>

<style>
    .toprow{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:18px;}
    .title h1{margin:0;font-size:22px;font-weight:900;}
    .title .sub{color:var(--muted);font-size:13px;margin-top:4px;}

    .filters{border:1px solid var(--line);border-radius:16px;padding:14px;background:#fff;margin-bottom:14px;}
    .grid{display:grid;grid-template-columns: 160px 160px 260px 180px 220px auto;gap:10px;align-items:end;}
    label{display:block;font-size:12px;font-weight:900;margin:0 0 6px;}
    input, select{
        width:100%;height:40px;border-radius:12px;border:1px solid var(--line);padding:0 12px;font-size:13px;outline:none;background:#fff;
    }
    .btn{height:40px;padding:0 14px;border-radius:12px;border:1px solid var(--line);background:#fff;font-weight:900;font-size:13px;cursor:pointer;}
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
</style>

<div class="toprow">
    <div class="title">
        <h1>Relatório de Suportes</h1>
        <div class="sub">Filtre por tipo, status, período e cliente</div>
    </div>

    <div style="display:flex;gap:10px;">
        <?php
        $qs = $_GET;
        $csvUrl = 'relatorio_suportes_csv.php?' . http_build_query($qs);
        ?>
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
            <label>Tipo</label>
            <select name="tipo">
                <option value="todos" <?= ($tipo==='todos')?'selected':''; ?>>Todos</option>
                <option value="melhoria" <?= ($tipo==='melhoria')?'selected':''; ?>>Melhoria</option>
                <option value="duvida" <?= ($tipo==='duvida')?'selected':''; ?>>Dúvida</option>
                <option value="solicitacao" <?= ($tipo==='solicitacao')?'selected':''; ?>>Solicitação</option>
                <option value="bug" <?= ($tipo==='bug')?'selected':''; ?>>Bug</option>
                <option value="configuracao" <?= ($tipo==='configuracao')?'selected':''; ?>>Configuração</option>
            </select>
        </div>
        <div>
            <label>Status</label>
            <select name="status">
                <option value="todos" <?= ($status==='todos')?'selected':''; ?>>Todos</option>
                <option value="em_andamento" <?= ($status==='em_andamento')?'selected':''; ?>>Em andamento</option>
                <option value="aguardando_resposta" <?= ($status==='aguardando_resposta')?'selected':''; ?>>Aguardando resposta</option>
                <option value="finalizado" <?= ($status==='finalizado')?'selected':''; ?>>Finalizado</option>
            </select>
        </div>
        <div style="display:flex;gap:10px;">
            <button class="btn" type="submit">Filtrar</button>
            <a class="btn" href="relatorio_suportes.php">Limpar</a>
        </div>
    </div>
</form>

<div class="cards">
    <div class="card"><div class="k">Total de suportes</div><div class="v"><?= (int)$resumo['total'] ?></div></div>
    <div class="card"><div class="k">Em andamento</div><div class="v"><?= (int)$resumo['em_andamento'] ?></div></div>
    <div class="card"><div class="k">Aguardando resposta</div><div class="v"><?= (int)$resumo['aguardando_resposta'] ?></div></div>
    <div class="card"><div class="k">Finalizados</div><div class="v"><?= (int)$resumo['finalizado'] ?></div></div>
    <div class="card"><div class="k">Duração total (min)</div><div class="v"><?= (int)$resumo['duracao_total'] ?></div></div>
    <div class="card"><div class="k">Duração média (min)</div><div class="v"><?= (int)round((float)$resumo['duracao_media']) ?></div></div>
</div>

<div class="panel">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;">
        <div style="font-weight:900;">Detalhamento</div>
        <div class="muted">Mostrando até 2000 registros</div>
    </div>

    <?php if (!empty($linhas)): ?>
        <table>
            <thead>
            <tr>
                <th>Data</th>
                <th>Assunto</th>
                <th>Cliente</th>
                <th>Tipo</th>
                <th>Status</th>
                <th>Criticidade</th>
                <th>Duração</th>
                <th>Registrado por</th>
                <th>Demanda</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($linhas as $r): ?>
                <tr>
                    <td><?= h(date('d/m/Y', strtotime($r['criado_em']))) ?></td>
                    <td><?= h($r['assunto']) ?></td>
                    <td><?= h($r['cliente_nome']) ?></td>
                    <td><span class="badge"><?= h(labelTipo($r['tipo_contato'])) ?></span></td>
                    <td><span class="badge"><?= h(labelStatus($r['status'])) ?></span></td>
                    <td><span class="badge badge-dark"><?= h(labelCrit($r['criticidade'])) ?></span></td>
                    <td><?= $r['duracao_min'] !== null ? ((int)$r['duracao_min'].' min') : '-' ?></td>
                    <td><?= h($r['usuario_nome']) ?></td>
                    <td>
                        <?php if (!empty($r['id_demanda'])): ?>
                            <a href="demanda_view.php?id=<?= (int)$r['id_demanda'] ?>">#<?= (int)$r['id_demanda'] ?></a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="muted" style="padding:22px 10px;text-align:center;">Nenhum suporte encontrado para os filtros aplicados.</div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
