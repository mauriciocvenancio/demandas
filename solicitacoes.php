<?php
$menuActive = 'solicitacoes';
$pageTitle  = 'Solicitações';
require_once __DIR__ . '/includes/layout_top.php';

$pdo = db();
$u   = auth_user();

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16));
$csrf = $_SESSION['csrf'];

$msg = isset($_GET['msg']) ? trim((string)$_GET['msg']) : '';
function msgSol($m){
    $map = array(
        'criada'             => 'Solicitação criada com sucesso.',
        'editada'            => 'Solicitação atualizada.',
        'excluida'           => 'Solicitação excluída.',
        'status_atualizado'  => 'Status atualizado com sucesso.',
        'campos_obrigatorios'=> 'Preencha os campos obrigatórios: título, cidade e nome.',
        'csrf'               => 'Sessão expirada. Recarregue e tente novamente.',
    );
    return isset($map[$m]) ? $map[$m] : '';
}
$flash = msgSol($msg);

/* ── Filtros ── */
$q       = isset($_GET['q'])       ? trim((string)$_GET['q'])       : '';
$fcidade = isset($_GET['cidade'])  ? trim((string)$_GET['cidade'])  : '';
$fstatus = isset($_GET['status'])  ? trim((string)$_GET['status'])  : '';
$ftipo   = isset($_GET['tipo'])    ? trim((string)$_GET['tipo'])    : '';

$allowedStatus = array('nova','em_analise','aprovada','em_desenvolvimento','aguardando_homologacao','implantada','rejeitada','cancelada');
$allowedTipo   = array('novo_item','melhoria');

/* ── Cards de resumo ── */
$cards = $pdo->query("
    SELECT
      COUNT(*) AS total,
      SUM(status='nova') AS novas,
      SUM(status='em_analise') AS em_analise,
      SUM(status='em_desenvolvimento') AS em_dev,
      SUM(status='implantada') AS implantadas,
      SUM(status='rejeitada') AS rejeitadas
    FROM solicitacoes WHERE ativo=1
")->fetch();

/* ── Cidades distintas para tabs ── */
$cidades = $pdo->query("SELECT DISTINCT cidade FROM solicitacoes WHERE ativo=1 ORDER BY cidade ASC")->fetchAll(PDO::FETCH_COLUMN);

/* ── Lista filtrada ── */
$where  = array("s.ativo=1");
$params = array();

if ($q !== '') {
    $where[]  = "(s.titulo LIKE ? OR s.nome_solicitante LIKE ? OR s.cidade LIKE ?)";
    $like     = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($fcidade !== '') { $where[] = "s.cidade = ?"; $params[] = $fcidade; }
if ($fstatus !== '' && in_array($fstatus, $allowedStatus, true)) { $where[] = "s.status = ?"; $params[] = $fstatus; }
if ($ftipo   !== '' && in_array($ftipo, $allowedTipo, true))     { $where[] = "s.tipo = ?";   $params[] = $ftipo; }

$stmt = $pdo->prepare("
    SELECT s.id, s.tipo, s.titulo, s.cidade, s.nome_solicitante, s.cargo_solicitante,
           s.status, s.prioridade, s.origem, s.criado_em,
           u.nome AS responsavel_nome
    FROM solicitacoes s
    LEFT JOIN usuarios u ON u.id = s.id_responsavel
    WHERE " . implode(' AND ', $where) . "
    ORDER BY s.criado_em DESC
    LIMIT 300
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$usuarios = $pdo->query("SELECT id, nome FROM usuarios WHERE ativo=1 ORDER BY nome ASC")->fetchAll();

/* ── Labels ── */
function labelSolStatus($s){
    $m = array('nova'=>'Nova','em_analise'=>'Em Análise','aprovada'=>'Aprovada',
        'em_desenvolvimento'=>'Em Desenvolvimento','aguardando_homologacao'=>'Ag. Homologação',
        'implantada'=>'Implantada','rejeitada'=>'Rejeitada','cancelada'=>'Cancelada');
    return isset($m[$s]) ? $m[$s] : $s;
}
function corSolStatus($s){
    $m = array('nova'=>'#6b7280','em_analise'=>'#2563eb','aprovada'=>'#16a34a',
        'em_desenvolvimento'=>'#d97706','aguardando_homologacao'=>'#ea580c',
        'implantada'=>'#15803d','rejeitada'=>'#dc2626','cancelada'=>'#991b1b');
    return isset($m[$s]) ? $m[$s] : '#6b7280';
}
function labelSolTipo($t){
    return $t === 'melhoria' ? 'Melhoria' : 'Novo Item';
}
function labelPrioSol($p){
    $m = array('baixa'=>'Baixa','media'=>'Média','alta'=>'Alta','urgente'=>'Urgente');
    return isset($m[$p]) ? $m[$p] : $p;
}
?>

<style>
    .head-row{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:18px;}
    .btn-primary{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:12px;background:#111;color:#fff;font-weight:800;font-size:13px;border:0;cursor:pointer;}
    .btn-primary:hover{opacity:.9}
    .flash{margin-bottom:14px;padding:10px 14px;border-radius:12px;border:1px solid var(--line);background:#fff;font-size:13px;}

    .cards{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin-bottom:18px;}
    .card{border:1px solid var(--line);border-radius:16px;padding:14px;background:#fff;display:flex;justify-content:space-between;gap:8px;}
    .card .k{font-size:11px;color:var(--muted);font-weight:900;text-transform:uppercase;}
    .card .v{margin-top:8px;font-size:22px;font-weight:900;}
    .iconbox{width:34px;height:34px;border-radius:10px;border:1px solid var(--line);display:flex;align-items:center;justify-content:center;font-size:15px;background:#f8f8f8;flex-shrink:0;}

    .tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;}
    .tab{padding:7px 14px;border-radius:999px;border:1px solid var(--line);background:#fff;font-size:12px;font-weight:800;cursor:pointer;text-decoration:none;color:#111;}
    .tab:hover{background:#f3f4f6}
    .tab.active{background:#111;color:#fff;border-color:#111;}

    .panel{background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden;}
    .panel-top{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid var(--line);flex-wrap:wrap;}
    .filters{display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
    .search{display:flex;align-items:center;gap:8px;border:1px solid var(--line);border-radius:12px;background:#fff;padding:9px 12px;min-width:200px;}
    .search input{border:0;outline:none;width:100%;font-size:13px;}
    select{border:1px solid var(--line);border-radius:12px;background:#fff;padding:9px 12px;font-size:13px;outline:none;}
    .btn{padding:9px 14px;border-radius:12px;border:1px solid var(--line);background:#fff;font-weight:800;font-size:13px;cursor:pointer;}

    table{width:100%;border-collapse:collapse;}
    th,td{padding:11px 14px;border-bottom:1px solid var(--line);font-size:13px;text-align:left;}
    th{color:var(--muted);font-size:11px;font-weight:900;text-transform:uppercase;}
    .td-muted{color:var(--muted);}
    .badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:900;border:1px solid transparent;}
    .badge-tipo{background:#f3f4f6;color:#374151;border-color:#e5e7eb;}
    .badge-prio-urgente{background:#fef2f2;color:#dc2626;}
    .badge-prio-alta{background:#fff7ed;color:#ea580c;}
    .badge-prio-media{background:#fefce8;color:#ca8a04;}
    .badge-prio-baixa{background:#f0fdf4;color:#16a34a;}
    .badge-origem{background:#eff6ff;color:#2563eb;border-color:#bfdbfe;font-size:10px;}

    .dots{width:34px;height:34px;border-radius:10px;border:1px solid var(--line);background:#fff;cursor:pointer;font-weight:900;}
    .row-menu{position:relative;text-align:right;}
    .dropdown{position:absolute;right:14px;top:42px;background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.1);min-width:160px;display:none;overflow:hidden;z-index:30;}
    .dropdown button{width:100%;text-align:left;border:0;background:#fff;padding:10px 12px;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;}
    .dropdown button:hover{background:#f3f4f6;}

    /* modal */
    .modal-backdrop{position:fixed;inset:0;background:rgba(17,17,17,.5);display:none;align-items:center;justify-content:center;z-index:1000;padding:18px;}
    .modal{width:660px;max-width:96vw;background:#fff;border-radius:16px;border:1px solid var(--line);box-shadow:0 24px 64px rgba(0,0,0,.2);max-height:90vh;overflow-y:auto;}
    .modal-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding:16px 18px 12px;border-bottom:1px solid var(--line);position:sticky;top:0;background:#fff;z-index:1;}
    .modal-head .t{font-weight:900;font-size:17px;margin:0;}
    .modal-close{width:34px;height:34px;border-radius:10px;border:1px solid var(--line);background:#fff;cursor:pointer;font-size:18px;flex-shrink:0;}
    .modal-body{padding:16px 18px;}
    .modal-foot{display:flex;justify-content:flex-end;gap:10px;padding:12px 18px 16px;border-top:1px solid var(--line);position:sticky;bottom:0;background:#fff;}
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    .grid1{grid-template-columns:1fr!important;}
    label{display:block;font-size:12px;font-weight:900;margin:10px 0 5px;color:#374151;}
    input[type=text],input[type=email],input[type=tel],textarea,select.field{width:100%;padding:10px 12px;border-radius:12px;border:1px solid var(--line);outline:none;font-size:13px;background:#fff;box-sizing:border-box;}
    textarea{min-height:80px;resize:vertical;}
    .btn-dark{background:#111;color:#fff;border-color:#111;}
    .btn-dark:hover{opacity:.9}
    .req{color:#dc2626;}

    @media(max-width:1100px){.cards{grid-template-columns:repeat(3,minmax(0,1fr));}}
    @media(max-width:700px){.cards{grid-template-columns:repeat(2,minmax(0,1fr));}}
</style>

<div class="head-row">
    <div>
        <h1 class="page-title">Solicitações</h1>
        <div class="page-sub">Novo item e melhorias solicitadas</div>
        <?php if ($flash): ?><div class="flash"><?= h($flash) ?></div><?php endif; ?>
    </div>
    <button class="btn-primary" onclick="openModal('modalNova')">＋ Nova Solicitação</button>
</div>

<!-- Cards -->
<div class="cards">
    <div class="card"><div><div class="k">Total</div><div class="v"><?= (int)$cards['total'] ?></div></div><div class="iconbox">📋</div></div>
    <div class="card"><div><div class="k">Novas</div><div class="v"><?= (int)$cards['novas'] ?></div></div><div class="iconbox">🆕</div></div>
    <div class="card"><div><div class="k">Em Análise</div><div class="v"><?= (int)$cards['em_analise'] ?></div></div><div class="iconbox">🔍</div></div>
    <div class="card"><div><div class="k">Em Desenvolvimento</div><div class="v"><?= (int)$cards['em_dev'] ?></div></div><div class="iconbox">⚙️</div></div>
    <div class="card"><div><div class="k">Implantadas</div><div class="v"><?= (int)$cards['implantadas'] ?></div></div><div class="iconbox">✅</div></div>
    <div class="card"><div><div class="k">Rejeitadas</div><div class="v"><?= (int)$cards['rejeitadas'] ?></div></div><div class="iconbox">❌</div></div>
</div>

<!-- Tabs por cidade -->
<?php if (!empty($cidades)): ?>
<div class="tabs">
    <a class="tab <?= $fcidade === '' ? 'active' : '' ?>" href="solicitacoes.php?<?= h(http_build_query(array_merge($_GET, array('cidade'=>'')))) ?>">Todas</a>
    <?php foreach ($cidades as $cid): ?>
        <a class="tab <?= $fcidade === $cid ? 'active' : '' ?>"
           href="solicitacoes.php?<?= h(http_build_query(array_merge($_GET, array('cidade'=>$cid)))) ?>">
            <?= h($cid) ?>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Tabela -->
<div class="panel">
    <div class="panel-top">
        <div style="font-weight:900;">Lista de Solicitações</div>
        <form method="get" class="filters">
            <div class="search">
                <span style="color:#9ca3af;">🔍</span>
                <input type="text" name="q" value="<?= h($q) ?>" placeholder="Buscar...">
            </div>
            <?php if ($fcidade !== ''): ?>
                <input type="hidden" name="cidade" value="<?= h($fcidade) ?>">
            <?php endif; ?>
            <select name="status">
                <option value="">Todos Status</option>
                <?php foreach (array('nova','em_analise','aprovada','em_desenvolvimento','aguardando_homologacao','implantada','rejeitada','cancelada') as $st): ?>
                    <option value="<?= $st ?>" <?= $fstatus===$st?'selected':'' ?>><?= h(labelSolStatus($st)) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="tipo">
                <option value="">Todos Tipos</option>
                <option value="novo_item" <?= $ftipo==='novo_item'?'selected':'' ?>>Novo Item</option>
                <option value="melhoria"  <?= $ftipo==='melhoria'?'selected':'' ?>>Melhoria</option>
            </select>
            <button class="btn" type="submit">Filtrar</button>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:28%;">Título</th>
                <th style="width:12%;">Tipo</th>
                <th style="width:13%;">Cidade</th>
                <th style="width:16%;">Solicitante</th>
                <th style="width:10%;">Prioridade</th>
                <th style="width:13%;">Status</th>
                <th style="width:10%;">Criação</th>
                <th style="width:4%;"></th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($rows)): ?>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td>
                    <a href="solicitacao_view.php?id=<?= (int)$r['id'] ?>" style="font-weight:900;color:#111;text-decoration:none;">
                        <?= h($r['titulo']) ?>
                    </a>
                    <?php if ($r['origem']==='api'): ?><span class="badge badge-origem" style="margin-left:6px;">API</span><?php endif; ?>
                </td>
                <td><span class="badge badge-tipo"><?= h(labelSolTipo($r['tipo'])) ?></span></td>
                <td class="td-muted"><?= h($r['cidade']) ?></td>
                <td class="td-muted">
                    <div style="font-weight:700;"><?= h($r['nome_solicitante']) ?></div>
                    <?php if (!empty($r['cargo_solicitante'])): ?>
                        <div style="font-size:11px;"><?= h($r['cargo_solicitante']) ?></div>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-prio-<?= h($r['prioridade']) ?>"><?= h(labelPrioSol($r['prioridade'])) ?></span></td>
                <td>
                    <span class="badge" style="background:<?= corSolStatus($r['status']) ?>1a;color:<?= corSolStatus($r['status']) ?>;border-color:<?= corSolStatus($r['status']) ?>44;">
                        <?= h(labelSolStatus($r['status'])) ?>
                    </span>
                </td>
                <td class="td-muted" style="font-size:12px;"><?= date('d/m/Y', strtotime($r['criado_em'])) ?></td>
                <td class="row-menu">
                    <button class="dots" onclick="toggleMenu(<?= (int)$r['id'] ?>)">…</button>
                    <div class="dropdown" id="menu-<?= (int)$r['id'] ?>">
                        <button onclick="location.href='solicitacao_view.php?id=<?= (int)$r['id'] ?>'">👁 Visualizar</button>
                        <button onclick="location.href='solicitacao_view.php?id=<?= (int)$r['id'] ?>&editar=1'">✏️ Editar</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="8" class="td-muted" style="padding:22px;text-align:center;">Nenhuma solicitação encontrada.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal: Nova Solicitação -->
<div class="modal-backdrop" id="modalNova" onclick="if(event.target===this)closeModal('modalNova')">
    <div class="modal">
        <div class="modal-head">
            <div class="t">Nova Solicitação</div>
            <button class="modal-close" onclick="closeModal('modalNova')">×</button>
        </div>
        <form method="post" action="solicitacoes_process.php">
            <div class="modal-body">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="create">

                <div class="grid2 grid1">
                    <label>Título <span class="req">*</span></label>
                    <input type="text" name="titulo" placeholder="Descreva brevemente a solicitação" required>
                </div>

                <div class="grid2" style="margin-top:0;">
                    <div>
                        <label>Tipo <span class="req">*</span></label>
                        <select name="tipo" class="field">
                            <option value="novo_item">Novo Item</option>
                            <option value="melhoria">Melhoria</option>
                        </select>
                    </div>
                    <div>
                        <label>Prioridade</label>
                        <select name="prioridade" class="field">
                            <option value="baixa">Baixa</option>
                            <option value="media" selected>Média</option>
                            <option value="alta">Alta</option>
                            <option value="urgente">Urgente</option>
                        </select>
                    </div>
                </div>

                <div class="grid2">
                    <div>
                        <label>Cidade <span class="req">*</span></label>
                        <input type="text" name="cidade" placeholder="Nome da cidade" required>
                    </div>
                    <div>
                        <label>Responsável Interno</label>
                        <select name="id_responsavel" class="field">
                            <option value="">— Sem responsável —</option>
                            <?php foreach ($usuarios as $us): ?>
                                <option value="<?= (int)$us['id'] ?>"><?= h($us['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="margin-top:10px;">
                    <label>Descrição</label>
                    <textarea name="descricao" placeholder="Detalhes da solicitação..."></textarea>
                </div>

                <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--line);">
                    <div style="font-weight:900;font-size:12px;color:var(--muted);text-transform:uppercase;margin-bottom:8px;">Dados do Solicitante</div>
                    <div class="grid2">
                        <div>
                            <label>Nome <span class="req">*</span></label>
                            <input type="text" name="nome_solicitante" placeholder="Nome completo" required>
                        </div>
                        <div>
                            <label>Cargo / Função</label>
                            <input type="text" name="cargo_solicitante" placeholder="Ex: Assistente Social">
                        </div>
                    </div>
                    <div class="grid2">
                        <div>
                            <label>E-mail</label>
                            <input type="email" name="email_solicitante" placeholder="email@municipio.gov.br">
                        </div>
                        <div>
                            <label>Telefone</label>
                            <input type="tel" name="telefone_solicitante" placeholder="(42) 99999-9999">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" onclick="closeModal('modalNova')">Cancelar</button>
                <button type="submit" class="btn btn-dark">Criar Solicitação</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id){ document.getElementById(id).style.display='flex'; }
function closeModal(id){ document.getElementById(id).style.display='none'; }
document.addEventListener('keydown',function(e){ if(e.key==='Escape') document.querySelectorAll('.modal-backdrop').forEach(function(m){m.style.display='none';}); });

function toggleMenu(id){
    var el=document.getElementById('menu-'+id);
    var open=(el.style.display==='block');
    closeAllMenus();
    if(!open) el.style.display='block';
}
function closeAllMenus(){ document.querySelectorAll('.dropdown').forEach(function(d){d.style.display='none';}); }
document.addEventListener('click',function(e){ if(!e.target.classList.contains('dots')) closeAllMenus(); });
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
