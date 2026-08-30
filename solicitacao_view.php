<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo = db();
$u   = auth_user();

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16));
$csrf = $_SESSION['csrf'];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) redirect('/solicitacoes.php');

$stmt = $pdo->prepare("
    SELECT s.*, u.nome AS responsavel_nome
    FROM solicitacoes s
    LEFT JOIN usuarios u ON u.id = s.id_responsavel
    WHERE s.id=? AND s.ativo=1
");
$stmt->execute(array($id));
$sol = $stmt->fetch();
if (!$sol) redirect('/solicitacoes.php');

$historico = $pdo->prepare("SELECT * FROM solicitacoes_historico WHERE id_solicitacao=? ORDER BY criado_em DESC");
$historico->execute(array($id));
$hist = $historico->fetchAll();

$usuarios = $pdo->query("SELECT id, nome FROM usuarios WHERE ativo=1 ORDER BY nome ASC")->fetchAll();

$msg = isset($_GET['msg']) ? trim((string)$_GET['msg']) : '';
$editMode = isset($_GET['editar']) && $_GET['editar'] == '1';

function lSt($s){
    $m=array('nova'=>'Nova','em_analise'=>'Em Análise','aprovada'=>'Aprovada',
        'em_desenvolvimento'=>'Em Desenvolvimento','aguardando_homologacao'=>'Ag. Homologação',
        'implantada'=>'Implantada','rejeitada'=>'Rejeitada','cancelada'=>'Cancelada');
    return isset($m[$s])?$m[$s]:$s;
}
function cSt($s){
    $m=array('nova'=>'#6b7280','em_analise'=>'#2563eb','aprovada'=>'#16a34a',
        'em_desenvolvimento'=>'#d97706','aguardando_homologacao'=>'#ea580c',
        'implantada'=>'#15803d','rejeitada'=>'#dc2626','cancelada'=>'#991b1b');
    return isset($m[$s])?$m[$s]:'#6b7280';
}
function lTp($t){ return $t==='melhoria'?'Melhoria':'Novo Item'; }
function lPr($p){ $m=array('baixa'=>'Baixa','media'=>'Média','alta'=>'Alta','urgente'=>'Urgente'); return isset($m[$p])?$m[$p]:$p; }

// status disponíveis para transição
$allStatus = array('nova','em_analise','aprovada','em_desenvolvimento','aguardando_homologacao','implantada','rejeitada','cancelada');
$nextStatus = array_filter($allStatus, function($s) use ($sol){ return $s !== $sol['status']; });

$menuActive = 'solicitacoes';
$pageTitle  = 'Solicitação #' . $id;
require_once __DIR__ . '/includes/layout_top.php';
?>

<style>
    .back-link{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:800;color:var(--muted);margin-bottom:14px;text-decoration:none;}
    .back-link:hover{color:#111;}
    .page-header{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:18px;flex-wrap:wrap;}
    .page-header h1{font-size:20px;font-weight:900;margin:0 0 8px;}
    .badges{display:flex;gap:8px;flex-wrap:wrap;}
    .badge{display:inline-flex;align-items:center;padding:4px 12px;border-radius:999px;font-size:12px;font-weight:900;border:1px solid transparent;}
    .badge-tipo{background:#f3f4f6;color:#374151;border-color:#e5e7eb;}
    .badge-prio-urgente{background:#fef2f2;color:#dc2626;}
    .badge-prio-alta{background:#fff7ed;color:#ea580c;}
    .badge-prio-media{background:#fefce8;color:#ca8a04;}
    .badge-prio-baixa{background:#f0fdf4;color:#16a34a;}
    .badge-api{background:#eff6ff;color:#2563eb;border-color:#bfdbfe;}

    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;}
    .card-box{background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px;}
    .card-box h3{font-size:13px;font-weight:900;color:var(--muted);text-transform:uppercase;margin:0 0 14px;letter-spacing:.05em;}
    .field-row{margin-bottom:12px;}
    .field-row .lbl{font-size:11px;font-weight:900;color:var(--muted);text-transform:uppercase;margin-bottom:2px;}
    .field-row .val{font-size:13px;font-weight:700;}

    .status-section{background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px;margin-bottom:16px;}
    .status-section h3{font-size:13px;font-weight:900;color:var(--muted);text-transform:uppercase;margin:0 0 14px;}
    .status-current{display:flex;align-items:center;gap:12px;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--line);}
    .status-dot{width:12px;height:12px;border-radius:50%;flex-shrink:0;}
    .status-label{font-size:16px;font-weight:900;}
    select.field,textarea.field,input.field{width:100%;padding:10px 12px;border-radius:12px;border:1px solid var(--line);outline:none;font-size:13px;background:#fff;box-sizing:border-box;}
    textarea.field{min-height:72px;resize:vertical;}
    label.lbl2{display:block;font-size:12px;font-weight:900;margin:0 0 5px;color:#374151;}
    .btn{padding:10px 16px;border-radius:12px;border:1px solid var(--line);background:#fff;font-weight:800;font-size:13px;cursor:pointer;}
    .btn-dark{background:#111;color:#fff;border-color:#111;}
    .btn-dark:hover{opacity:.9}
    .btn-row{display:flex;gap:10px;margin-top:12px;justify-content:flex-end;}

    /* timeline */
    .timeline{background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px;}
    .timeline h3{font-size:13px;font-weight:900;color:var(--muted);text-transform:uppercase;margin:0 0 14px;}
    .tl-item{display:flex;gap:14px;position:relative;padding-bottom:20px;}
    .tl-item:last-child{padding-bottom:0;}
    .tl-item:not(:last-child)::before{content:'';position:absolute;left:15px;top:32px;bottom:0;width:2px;background:var(--line);}
    .tl-dot{width:32px;height:32px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:14px;border:2px solid;}
    .tl-body{flex:1;min-width:0;}
    .tl-title{font-weight:900;font-size:13px;}
    .tl-sub{font-size:12px;color:var(--muted);margin-top:2px;}
    .tl-obs{font-size:12px;margin-top:6px;padding:8px 10px;background:#f9fafb;border-radius:10px;border:1px solid var(--line);}
    .empty{padding:24px;text-align:center;color:var(--muted);font-size:13px;}

    /* edit form */
    .edit-section{background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px;margin-bottom:16px;}
    .edit-section h3{font-size:13px;font-weight:900;color:var(--muted);text-transform:uppercase;margin:0 0 14px;}
    .flash{padding:10px 14px;border-radius:12px;border:1px solid var(--line);background:#fff;font-size:13px;margin-bottom:16px;}

    @media(max-width:860px){.grid2{grid-template-columns:1fr;}}
</style>

<a class="back-link" href="solicitacoes.php">← Voltar para Solicitações</a>

<?php if ($msg): ?>
<div class="flash">
    <?php
    $msgs = array('criada'=>'Solicitação criada.','editada'=>'Solicitação atualizada.','status_atualizado'=>'Status atualizado com sucesso.');
    echo h(isset($msgs[$msg]) ? $msgs[$msg] : '');
    ?>
</div>
<?php endif; ?>

<div class="page-header">
    <div style="flex:1;min-width:0;">
        <h1><?= h($sol['titulo']) ?></h1>
        <div class="badges">
            <span class="badge badge-tipo"><?= h(lTp($sol['tipo'])) ?></span>
            <span class="badge badge-prio-<?= h($sol['prioridade']) ?>"><?= h(lPr($sol['prioridade'])) ?></span>
            <span class="badge" style="background:<?= cSt($sol['status']) ?>1a;color:<?= cSt($sol['status']) ?>;border-color:<?= cSt($sol['status']) ?>44;"><?= h(lSt($sol['status'])) ?></span>
            <?php if ($sol['origem']==='api'): ?><span class="badge badge-api">Via API</span><?php endif; ?>
        </div>
    </div>
    <div style="display:flex;gap:10px;flex-shrink:0;">
        <?php if (!$editMode): ?>
            <a class="btn" href="solicitacao_view.php?id=<?= $id ?>&editar=1">✏️ Editar</a>
        <?php endif; ?>
        <form method="post" action="solicitacoes_process.php" onsubmit="return confirm('Excluir esta solicitação?')">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $id ?>">
            <button type="submit" class="btn" style="color:#dc2626;border-color:#fca5a5;">🗑 Excluir</button>
        </form>
    </div>
</div>

<!-- Editar (modo edit) -->
<?php if ($editMode): ?>
<div class="edit-section">
    <h3>Editar Solicitação</h3>
    <form method="post" action="solicitacoes_process.php">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div style="margin-bottom:10px;">
            <label class="lbl2">Título *</label>
            <input type="text" name="titulo" class="field" value="<?= h($sol['titulo']) ?>" required>
        </div>
        <div class="grid2">
            <div>
                <label class="lbl2">Tipo</label>
                <select name="tipo" class="field">
                    <option value="novo_item" <?= $sol['tipo']==='novo_item'?'selected':'' ?>>Novo Item</option>
                    <option value="melhoria"  <?= $sol['tipo']==='melhoria'?'selected':'' ?>>Melhoria</option>
                </select>
            </div>
            <div>
                <label class="lbl2">Prioridade</label>
                <select name="prioridade" class="field">
                    <?php foreach (array('baixa','media','alta','urgente') as $p): ?>
                        <option value="<?= $p ?>" <?= $sol['prioridade']===$p?'selected':'' ?>><?= h(lPr($p)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="grid2">
            <div>
                <label class="lbl2">Cidade *</label>
                <input type="text" name="cidade" class="field" value="<?= h($sol['cidade']) ?>" required>
            </div>
            <div>
                <label class="lbl2">Responsável Interno</label>
                <select name="id_responsavel" class="field">
                    <option value="">— Sem responsável —</option>
                    <?php foreach ($usuarios as $us): ?>
                        <option value="<?= (int)$us['id'] ?>" <?= $sol['id_responsavel']==$us['id']?'selected':'' ?>><?= h($us['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div style="margin-bottom:10px;">
            <label class="lbl2">Descrição</label>
            <textarea name="descricao" class="field"><?= h($sol['descricao']) ?></textarea>
        </div>
        <div style="padding-top:12px;border-top:1px solid var(--line);margin-top:4px;">
            <div style="font-size:11px;font-weight:900;color:var(--muted);text-transform:uppercase;margin-bottom:10px;">Dados do Solicitante</div>
            <div class="grid2">
                <div>
                    <label class="lbl2">Nome *</label>
                    <input type="text" name="nome_solicitante" class="field" value="<?= h($sol['nome_solicitante']) ?>" required>
                </div>
                <div>
                    <label class="lbl2">Cargo / Função</label>
                    <input type="text" name="cargo_solicitante" class="field" value="<?= h($sol['cargo_solicitante']) ?>">
                </div>
            </div>
            <div class="grid2">
                <div>
                    <label class="lbl2">E-mail</label>
                    <input type="email" name="email_solicitante" class="field" value="<?= h($sol['email_solicitante']) ?>">
                </div>
                <div>
                    <label class="lbl2">Telefone</label>
                    <input type="tel" name="telefone_solicitante" class="field" value="<?= h($sol['telefone_solicitante']) ?>">
                </div>
            </div>
        </div>
        <div class="btn-row">
            <a class="btn" href="solicitacao_view.php?id=<?= $id ?>">Cancelar</a>
            <button type="submit" class="btn btn-dark">Salvar Alterações</button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Dados + Solicitante -->
<div class="grid2">
    <div class="card-box">
        <h3>Dados da Solicitação</h3>
        <div class="field-row"><div class="lbl">Cidade</div><div class="val">📍 <?= h($sol['cidade']) ?></div></div>
        <div class="field-row"><div class="lbl">Origem</div><div class="val"><?= $sol['origem']==='api'?'Via API':'Interno' ?></div></div>
        <?php if (!empty($sol['ref_externa'])): ?>
            <div class="field-row"><div class="lbl">Ref. Externa</div><div class="val"><?= h($sol['ref_externa']) ?></div></div>
        <?php endif; ?>
        <div class="field-row"><div class="lbl">Responsável</div><div class="val"><?= h($sol['responsavel_nome'] ?: '—') ?></div></div>
        <div class="field-row"><div class="lbl">Criado em</div><div class="val"><?= date('d/m/Y H:i', strtotime($sol['criado_em'])) ?></div></div>
        <?php if (!empty($sol['descricao'])): ?>
            <div class="field-row"><div class="lbl">Descrição</div><div class="val" style="white-space:pre-wrap;font-weight:400;"><?= h($sol['descricao']) ?></div></div>
        <?php endif; ?>
    </div>
    <div class="card-box">
        <h3>Dados do Solicitante</h3>
        <div class="field-row"><div class="lbl">Nome</div><div class="val"><?= h($sol['nome_solicitante']) ?></div></div>
        <?php if (!empty($sol['cargo_solicitante'])): ?>
            <div class="field-row"><div class="lbl">Cargo</div><div class="val"><?= h($sol['cargo_solicitante']) ?></div></div>
        <?php endif; ?>
        <?php if (!empty($sol['email_solicitante'])): ?>
            <div class="field-row"><div class="lbl">E-mail</div><div class="val"><a href="mailto:<?= h($sol['email_solicitante']) ?>" style="color:#2563eb;"><?= h($sol['email_solicitante']) ?></a></div></div>
        <?php endif; ?>
        <?php if (!empty($sol['telefone_solicitante'])): ?>
            <div class="field-row"><div class="lbl">Telefone</div><div class="val"><?= h($sol['telefone_solicitante']) ?></div></div>
        <?php endif; ?>
    </div>
</div>

<!-- Mudar Status -->
<div class="status-section">
    <h3>Status Atual</h3>
    <div class="status-current">
        <div class="status-dot" style="background:<?= cSt($sol['status']) ?>;border-color:<?= cSt($sol['status']) ?>;"></div>
        <div class="status-label"><?= h(lSt($sol['status'])) ?></div>
    </div>
    <form method="post" action="solicitacoes_process.php">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="status">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="grid2">
            <div>
                <label class="lbl2">Novo Status</label>
                <select name="status_novo" class="field" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($nextStatus as $st): ?>
                        <option value="<?= $st ?>" style="color:<?= cSt($st) ?>;"><?= h(lSt($st)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="lbl2">Observação</label>
                <input type="text" name="observacao" class="field" placeholder="Motivo, comentário... (opcional)">
            </div>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn btn-dark">Atualizar Status</button>
        </div>
    </form>
</div>

<!-- Timeline de histórico -->
<div class="timeline">
    <h3>Histórico de Status</h3>
    <?php if (!empty($hist)): ?>
        <?php foreach ($hist as $h_item): ?>
        <div class="tl-item">
            <div class="tl-dot" style="background:<?= cSt($h_item['status_novo']) ?>1a;border-color:<?= cSt($h_item['status_novo']) ?>;color:<?= cSt($h_item['status_novo']) ?>;">●</div>
            <div class="tl-body">
                <div class="tl-title">
                    <?php if ($h_item['status_anterior']): ?>
                        <?= h(lSt($h_item['status_anterior'])) ?> → <strong><?= h(lSt($h_item['status_novo'])) ?></strong>
                    <?php else: ?>
                        <strong><?= h(lSt($h_item['status_novo'])) ?></strong>
                    <?php endif; ?>
                </div>
                <div class="tl-sub">
                    <?= h(date('d/m/Y H:i', strtotime($h_item['criado_em']))) ?>
                    <?php if (!empty($h_item['nome_usuario'])): ?> • <?= h($h_item['nome_usuario']) ?><?php endif; ?>
                </div>
                <?php if (!empty($h_item['observacao'])): ?>
                    <div class="tl-obs"><?= h($h_item['observacao']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty">Nenhum histórico registrado.</div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
