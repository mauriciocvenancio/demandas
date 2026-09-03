<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo = db();
$u   = auth_user();

if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16));
$csrf = $_SESSION['csrf'];

date_default_timezone_set('America/Sao_Paulo');

$msg   = isset($_GET['msg']) ? trim((string)$_GET['msg']) : '';
$flash = '';
if ($msg === 'criado')   $flash = '✅ Plano cadastrado com sucesso.';
if ($msg === 'aprovado') $flash = '✅ Plano aprovado!';
if ($msg === 'rejeitado')$flash = '❌ Plano rejeitado.';
if ($msg === 'excluido') $flash = '🗑 Plano excluído.';

// Filtros
$fstatus = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$q       = isset($_GET['q'])      ? trim((string)$_GET['q'])      : '';
$allowedStatus = array('pendente','aprovado','rejeitado');

$where  = array("p.ativo = 1");
$params = array();
if (in_array($fstatus, $allowedStatus)) { $where[] = "p.status = ?"; $params[] = $fstatus; }
if ($q !== '') { $where[] = "(p.nome LIKE ? OR p.descricao LIKE ?)"; $params[] = '%'.$q.'%'; $params[] = '%'.$q.'%'; }

$stmt = $pdo->prepare("
    SELECT p.*, u.nome AS criador_nome, ua.nome AS aprovador_nome
    FROM planos_aprovacao p
    JOIN usuarios u ON u.id = p.criado_por
    LEFT JOIN usuarios ua ON ua.id = p.aprovado_por
    WHERE " . implode(" AND ", $where) . "
    ORDER BY p.criado_em DESC
");
$stmt->execute($params);
$planos = $stmt->fetchAll();

// Cards de contagem
$cards = $pdo->query("SELECT
    COUNT(*) AS total,
    SUM(status='pendente')  AS pendente,
    SUM(status='aprovado')  AS aprovado,
    SUM(status='rejeitado') AS rejeitado
    FROM planos_aprovacao WHERE ativo=1")->fetch();

$menuActive = 'planos_aprovacao';
$pageTitle  = 'Planos para Aprovação';
require_once __DIR__ . '/includes/layout_top.php';
?>
<style>
    .head-row{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:18px;}
    .btn-primary{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:12px;background:#111;color:#fff;font-weight:800;font-size:13px;border:0;cursor:pointer;}
    .btn-primary:hover{opacity:.9}
    .flash{margin-bottom:14px;padding:10px 14px;border-radius:12px;border:1px solid var(--line);background:#fff;font-size:13px;font-weight:700;}

    .cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:18px;}
    .card{border:1px solid var(--line);border-radius:16px;padding:14px;background:#fff;display:flex;justify-content:space-between;gap:8px;}
    .card .k{font-size:11px;color:var(--muted);font-weight:900;text-transform:uppercase;}
    .card .v{margin-top:8px;font-size:22px;font-weight:900;}
    .iconbox{width:34px;height:34px;border-radius:10px;border:1px solid var(--line);display:flex;align-items:center;justify-content:center;font-size:15px;background:#f8f8f8;flex-shrink:0;}

    .panel{background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden;}
    .panel-top{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid var(--line);flex-wrap:wrap;}
    .filters{display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
    .search{display:flex;align-items:center;gap:8px;border:1px solid var(--line);border-radius:12px;background:#fff;padding:9px 12px;min-width:220px;}
    .search input{border:0;outline:none;width:100%;font-size:13px;}
    select{border:1px solid var(--line);border-radius:12px;background:#fff;padding:9px 12px;font-size:13px;outline:none;}
    .btn{padding:9px 14px;border-radius:12px;border:1px solid var(--line);background:#fff;font-weight:800;font-size:13px;cursor:pointer;}
    .btn:hover{background:#f3f4f6;}
    .btn-dark{background:#111;color:#fff;border-color:#111;}

    table{width:100%;border-collapse:collapse;}
    th,td{padding:11px 14px;border-bottom:1px solid var(--line);font-size:13px;text-align:left;}
    th{color:var(--muted);font-size:11px;font-weight:900;text-transform:uppercase;}
    tbody tr:last-child td{border-bottom:none;}
    tbody tr:hover td{background:#fafafa;}

    .badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:900;border:1px solid transparent;}
    .badge-pendente {background:#fef9c3;color:#854d0e;border-color:#fde047;}
    .badge-aprovado {background:#dcfce7;color:#166534;border-color:#86efac;}
    .badge-rejeitado{background:#fee2e2;color:#991b1b;border-color:#fca5a5;}

    .file-link{font-size:12px;font-weight:800;color:#2563eb;display:inline-flex;align-items:center;gap:4px;}
    .file-link:hover{text-decoration:underline;}

    .dots{width:34px;height:34px;border-radius:10px;border:1px solid var(--line);background:#fff;cursor:pointer;font-weight:900;font-size:18px;}
    .row-menu{position:relative;text-align:right;}
    .dropdown{position:absolute;right:14px;top:40px;background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.1);min-width:170px;display:none;overflow:hidden;z-index:30;}
    .dropdown button{width:100%;text-align:left;border:0;background:#fff;padding:10px 12px;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;}
    .dropdown button:hover{background:#f3f4f6;}

    .empty{padding:48px;text-align:center;color:var(--muted);font-size:14px;font-weight:700;}

    /* Modal */
    .modal-backdrop{position:fixed;inset:0;background:rgba(17,17,17,.5);display:none;align-items:center;justify-content:center;z-index:1000;padding:18px;}
    .modal{width:560px;max-width:96vw;background:#fff;border-radius:16px;border:1px solid var(--line);box-shadow:0 24px 64px rgba(0,0,0,.2);max-height:90vh;overflow-y:auto;}
    .modal-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding:16px 18px 12px;border-bottom:1px solid var(--line);}
    .modal-head .t{font-weight:900;font-size:17px;margin:0;}
    .modal-close{width:34px;height:34px;border-radius:10px;border:1px solid var(--line);background:#fff;cursor:pointer;font-size:18px;flex-shrink:0;}
    .modal-body{padding:16px 18px;}
    .modal-foot{display:flex;justify-content:flex-end;gap:10px;padding:12px 18px 16px;border-top:1px solid var(--line);}
    label{display:block;font-size:12px;font-weight:900;margin:10px 0 5px;color:#374151;}
    input[type=text],textarea{width:100%;padding:10px 12px;border-radius:12px;border:1px solid var(--line);outline:none;font-size:13px;background:#fff;box-sizing:border-box;}
    textarea{min-height:80px;resize:vertical;}
    .drop-zone{border:2px dashed var(--line);border-radius:12px;padding:22px;text-align:center;cursor:pointer;position:relative;}
    .drop-zone:hover,.drop-zone.dragover{border-color:#111;}
    .drop-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
    .dz-label{font-size:13px;color:var(--muted);font-weight:700;pointer-events:none;}
</style>

<div class="head-row">
    <div>
        <h1 class="page-title">📄 Planos para Aprovação</h1>
        <div class="page-sub">Envie planos (PDF ou HTML) para revisão e aprovação da equipe</div>
        <?php if ($flash): ?><div class="flash"><?= h($flash) ?></div><?php endif; ?>
    </div>
    <button class="btn-primary" onclick="abrirModal()">＋ Novo Plano</button>
</div>

<!-- Cards -->
<div class="cards">
    <div class="card"><div><div class="k">Total</div><div class="v"><?= (int)$cards['total'] ?></div></div><div class="iconbox">📄</div></div>
    <div class="card"><div><div class="k">Pendentes</div><div class="v" style="color:#854d0e;"><?= (int)$cards['pendente'] ?></div></div><div class="iconbox">⏳</div></div>
    <div class="card"><div><div class="k">Aprovados</div><div class="v" style="color:#166534;"><?= (int)$cards['aprovado'] ?></div></div><div class="iconbox">✅</div></div>
    <div class="card"><div><div class="k">Rejeitados</div><div class="v" style="color:#991b1b;"><?= (int)$cards['rejeitado'] ?></div></div><div class="iconbox">❌</div></div>
</div>

<!-- Tabela -->
<div class="panel">
    <div class="panel-top">
        <div style="font-weight:900;">Lista de Planos</div>
        <form method="get" class="filters">
            <div class="search">
                <span style="color:#9ca3af;">🔍</span>
                <input type="text" name="q" value="<?= h($q) ?>" placeholder="Buscar...">
            </div>
            <select name="status">
                <option value="">Todos os Status</option>
                <option value="pendente"  <?= $fstatus==='pendente'  ?'selected':'' ?>>⏳ Pendente</option>
                <option value="aprovado"  <?= $fstatus==='aprovado'  ?'selected':'' ?>>✅ Aprovado</option>
                <option value="rejeitado" <?= $fstatus==='rejeitado' ?'selected':'' ?>>❌ Rejeitado</option>
            </select>
            <button class="btn" type="submit">Filtrar</button>
            <?php if ($fstatus || $q): ?>
                <a class="btn" href="planos_aprovacao.php">Limpar</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($planos)): ?>
        <div class="empty">Nenhum plano encontrado.</div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th style="width:30%;">Nome</th>
                <th style="width:20%;">Descrição</th>
                <th style="width:10%;">Arquivo</th>
                <th style="width:12%;">Criado por</th>
                <th style="width:11%;">Data</th>
                <th style="width:10%;">Status</th>
                <th style="width:7%;"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($planos as $p):
            $ext  = isset($p['tipo_arquivo']) ? $p['tipo_arquivo'] : '';
            $icon = $ext === 'pdf' ? '📕' : ($ext === 'html' ? '🌐' : '');
            $rowId = 'row-' . (int)$p['id'];
        ?>
        <tr id="<?= $rowId ?>">
            <td>
                <strong><?= h($p['nome']) ?></strong>
                <?php if ($p['status'] === 'aprovado' && $p['aprovador_nome']): ?>
                <div style="font-size:11px;color:var(--muted);margin-top:2px;">
                    Aprovado por <?= h($p['aprovador_nome']) ?> em <?= date('d/m/Y', strtotime($p['aprovado_em'])) ?>
                </div>
                <?php elseif ($p['status'] === 'rejeitado' && $p['aprovador_nome']): ?>
                <div style="font-size:11px;color:#dc2626;margin-top:2px;">
                    Rejeitado por <?= h($p['aprovador_nome']) ?> em <?= date('d/m/Y', strtotime($p['aprovado_em'])) ?>
                </div>
                <?php endif; ?>
            </td>
            <td style="color:var(--muted);font-size:12px;">
                <?= $p['descricao'] ? h(mb_strimwidth($p['descricao'], 0, 80, '…')) : '—' ?>
            </td>
            <td>
                <?php if ($p['arquivo']): ?>
                <a class="file-link" href="uploads/planos/<?= h($p['arquivo']) ?>" target="_blank">
                    <?= $icon ?> <?= strtoupper($ext) ?>
                </a>
                <?php else: ?>
                <span style="color:var(--muted);">—</span>
                <?php endif; ?>
            </td>
            <td style="font-size:12px;"><?= h($p['criador_nome']) ?></td>
            <td style="font-size:12px;color:var(--muted);"><?= date('d/m/Y H:i', strtotime($p['criado_em'])) ?></td>
            <td>
                <span class="badge badge-<?= $p['status'] ?>">
                    <?= $p['status'] === 'aprovado' ? '✅ Aprovado' : ($p['status'] === 'rejeitado' ? '❌ Rejeitado' : '⏳ Pendente') ?>
                </span>
            </td>
            <td class="row-menu">
                <button class="dots" onclick="toggleDrop(event,'drop-<?= (int)$p['id'] ?>')">⋯</button>
                <div class="dropdown" id="drop-<?= (int)$p['id'] ?>">
                    <?php if ($p['status'] === 'pendente'): ?>
                        <button onclick="aprovar(<?= (int)$p['id'] ?>, '<?= h($p['nome']) ?>')">✅ Aprovar</button>
                        <button onclick="rejeitar(<?= (int)$p['id'] ?>, '<?= h($p['nome']) ?>')">❌ Rejeitar</button>
                    <?php endif; ?>
                    <?php if ($p['arquivo']): ?>
                        <button onclick="window.open('uploads/planos/<?= h($p['arquivo']) ?>','_blank')">📎 Ver arquivo</button>
                    <?php endif; ?>
                    <?php if ((int)$p['criado_por'] === (int)$u['id'] || $u['tipo'] === 'desenvolvedor'): ?>
                        <button onclick="excluir(<?= (int)$p['id'] ?>)" style="color:#dc2626;">🗑 Excluir</button>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Modal Novo Plano -->
<div class="modal-backdrop" id="modalBackdrop">
    <div class="modal">
        <div class="modal-head">
            <p class="t">📄 Novo Plano</p>
            <button class="modal-close" onclick="fecharModal()">×</button>
        </div>
        <div class="modal-body">
            <label>Nome do plano <span style="color:#dc2626;">*</span></label>
            <input type="text" id="pNome" placeholder="Ex: Plano de Migração do Sistema">

            <label>Descrição</label>
            <textarea id="pDesc" placeholder="Descreva brevemente o conteúdo..."></textarea>

            <label>Arquivo (PDF ou HTML)</label>
            <div class="drop-zone" id="dropZone">
                <input type="file" id="pArquivo" accept=".pdf,.html,.htm" onchange="atualizarLabel(this)">
                <div class="dz-label" id="dzLabel">📎 Clique ou arraste um arquivo PDF ou HTML aqui</div>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn" onclick="fecharModal()">Cancelar</button>
            <button class="btn btn-dark" id="btnSalvar" onclick="salvarPlano()">Enviar Plano</button>
        </div>
    </div>
</div>

<script>
var CSRF = <?= json_encode($csrf) ?>;

// ── Dropdown ───────────────────────────────────────────────────────────
function toggleDrop(e, id) {
    e.stopPropagation();
    var dd = document.getElementById(id);
    var all = document.querySelectorAll('.dropdown');
    all.forEach(function(d){ if(d.id !== id) d.style.display='none'; });
    dd.style.display = dd.style.display === 'block' ? 'none' : 'block';
}
document.addEventListener('click', function(){ document.querySelectorAll('.dropdown').forEach(function(d){ d.style.display='none'; }); });

// ── Modal ──────────────────────────────────────────────────────────────
function abrirModal() {
    document.getElementById('pNome').value = '';
    document.getElementById('pDesc').value = '';
    document.getElementById('pArquivo').value = '';
    document.getElementById('dzLabel').textContent = '📎 Clique ou arraste um arquivo PDF ou HTML aqui';
    document.getElementById('modalBackdrop').style.display = 'flex';
    setTimeout(function(){ document.getElementById('pNome').focus(); }, 80);
}
function fecharModal() { document.getElementById('modalBackdrop').style.display = 'none'; }
function atualizarLabel(input) {
    document.getElementById('dzLabel').textContent = input.files.length ? '✅ ' + input.files[0].name : '📎 Clique ou arraste um arquivo PDF ou HTML aqui';
}
(function(){
    var dz = document.getElementById('dropZone');
    dz.addEventListener('dragover', function(e){ e.preventDefault(); dz.classList.add('dragover'); });
    dz.addEventListener('dragleave', function(){ dz.classList.remove('dragover'); });
    dz.addEventListener('drop', function(e){
        e.preventDefault(); dz.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
            document.getElementById('pArquivo').files = e.dataTransfer.files;
            atualizarLabel(document.getElementById('pArquivo'));
        }
    });
})();

function salvarPlano() {
    var nome = document.getElementById('pNome').value.trim();
    if (!nome) { alert('Informe o nome do plano.'); return; }
    var btn = document.getElementById('btnSalvar');
    btn.disabled = true; btn.textContent = 'Enviando...';
    var fd = new FormData();
    fd.append('csrf', CSRF); fd.append('action', 'create');
    fd.append('nome', nome);
    fd.append('descricao', document.getElementById('pDesc').value.trim());
    var arq = document.getElementById('pArquivo').files[0];
    if (arq) fd.append('arquivo', arq);
    fetch('planos_aprovacao_process.php', {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(res){
            btn.disabled = false; btn.textContent = 'Enviar Plano';
            if (!res.ok) { alert(res.msg); return; }
            fecharModal(); window.location.reload();
        })
        .catch(function(){ btn.disabled=false; btn.textContent='Enviar Plano'; alert('Erro de comunicação.'); });
}

// ── Ações ──────────────────────────────────────────────────────────────
function aprovar(id, nome) {
    if (!confirm('Aprovar o plano "' + nome + '"?')) return;
    var fd = new FormData();
    fd.append('csrf', CSRF); fd.append('action', 'aprovar'); fd.append('id', id);
    fetch('planos_aprovacao_process.php', {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(res){ if (!res.ok) alert(res.msg); else window.location.reload(); })
        .catch(function(){ alert('Erro de comunicação.'); });
}
function rejeitar(id, nome) {
    if (!confirm('Rejeitar o plano "' + nome + '"?')) return;
    var fd = new FormData();
    fd.append('csrf', CSRF); fd.append('action', 'rejeitar'); fd.append('id', id);
    fetch('planos_aprovacao_process.php', {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(res){ if (!res.ok) alert(res.msg); else window.location.reload(); })
        .catch(function(){ alert('Erro de comunicação.'); });
}
function excluir(id) {
    if (!confirm('Excluir este plano?')) return;
    var fd = new FormData();
    fd.append('csrf', CSRF); fd.append('action', 'excluir'); fd.append('id', id);
    fetch('planos_aprovacao_process.php', {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(res){ if (!res.ok) alert(res.msg); else window.location.reload(); })
        .catch(function(){ alert('Erro de comunicação.'); });
}

document.addEventListener('keydown', function(e){ if (e.key === 'Escape') fecharModal(); });
document.getElementById('modalBackdrop').addEventListener('click', function(e){ if (e.target===this) fecharModal(); });
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
