<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo = db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: suportes.php?msg=id_invalido'); exit; }

$stmt = $pdo->prepare("
  SELECT s.*,
         c.nome AS cliente_nome,
         u.nome AS usuario_nome
  FROM suportes s
  JOIN clientes c ON c.id = s.id_cliente
  JOIN usuarios u ON u.id = s.id_usuario_registro
  WHERE s.id=? AND s.ativo=1
  LIMIT 1
");
$stmt->execute(array($id));
$r = $stmt->fetch();
if (!$r) { header('Location: suportes.php?msg=nao_encontrado'); exit; }

function labelTipo($t){
    $map = array('melhoria'=>'Melhoria','duvida'=>'Dúvida','solicitacao'=>'Solicitação','bug'=>'Bug','configuracao'=>'Configuração');
    return isset($map[$t]) ? $map[$t] : $t;
}
function labelStatusSup($s){
    $map = array('em_andamento'=>'Em andamento','finalizado'=>'Finalizado','aguardando_resposta'=>'Aguardando resposta');
    return isset($map[$s]) ? $map[$s] : $s;
}
function labelCrit($c){
    $map = array('baixa'=>'Baixa','media'=>'Média','alta'=>'Alta','urgente'=>'Urgente');
    return isset($map[$c]) ? $map[$c] : $c;
}

$menuActive = 'suportes';
$pageTitle  = 'Visualizar Suporte';
require_once __DIR__ . '/includes/layout_top.php';
?>

<style>
    .wrap{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:18px;}
    .title h1{margin:0;font-size:22px;font-weight:900;}
    .title .sub{color:var(--muted);font-size:13px;margin-top:4px;}
    .btn{padding:10px 14px;border-radius:12px;border:1px solid var(--line);background:#fff;font-weight:900;font-size:13px;cursor:pointer;}
    .btn-dark{background:#111;color:#fff;border-color:#111;}
    .panel{padding:16px;margin-top:14px;}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    .item{border:1px solid var(--line);border-radius:14px;padding:12px;background:#fff;}
    .k{font-size:12px;color:var(--muted);font-weight:900;}
    .v{margin-top:6px;font-size:14px;font-weight:900;color:#111;}
    .desc{white-space:pre-wrap;font-size:13px;color:#111;line-height:1.4;margin-top:8px}
    .badge{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;border:1px solid var(--line);font-size:12px;font-weight:900;background:#fff;}
    .badge-dark{background:#111;color:#fff;border-color:#111;}
</style>

<div class="wrap">
    <div class="title">
        <h1><?= h($r['assunto']) ?></h1>
        <div class="sub">Detalhes do suporte</div>
    </div>
    <div style="display:flex;gap:10px;">
        <button class="btn" type="button" onclick="window.location.href='suportes.php'">← Voltar</button>
        <button class="btn btn-dark" type="button" onclick="window.location.href='suporte_edit.php?id=<?= (int)$r['id'] ?>'">✏️ Editar</button>
    </div>
</div>

<div class="grid">
    <div class="item"><div class="k">Cliente</div><div class="v"><?= h($r['cliente_nome']) ?></div></div>
    <div class="item"><div class="k">Registrado por</div><div class="v"><?= h($r['usuario_nome']) ?></div></div>
    <div class="item"><div class="k">Tipo</div><div class="v"><span class="badge"><?= h(labelTipo($r['tipo_contato'])) ?></span></div></div>
    <div class="item"><div class="k">Status</div><div class="v"><span class="badge"><?= h(labelStatusSup($r['status'])) ?></span></div></div>
    <div class="item"><div class="k">Criticidade</div><div class="v"><span class="badge badge-dark"><?= h(labelCrit($r['criticidade'])) ?></span></div></div>
    <div class="item"><div class="k">Duração</div><div class="v"><?= $r['duracao_min']!==null ? ((int)$r['duracao_min'].' min') : '-' ?></div></div>
</div>

<?php if (!empty($r['id_demanda'])): ?>
    <div class="panel">
        <div style="font-weight:900;">Demanda vinculada</div>
        <div style="margin-top:8px;">
            <a class="btn" href="demanda_view.php?id=<?= (int)$r['id_demanda'] ?>">Abrir Demanda #<?= (int)$r['id_demanda'] ?></a>
        </div>
    </div>
<?php endif; ?>

<div class="panel">
    <div style="font-weight:900;">Descrição</div>
    <div class="desc"><?= h($r['descricao'] ? $r['descricao'] : '-') ?></div>
</div>

<div class="panel">
    <div style="font-weight:900;">Resolução</div>
    <div class="desc"><?= h($r['resolucao'] ? $r['resolucao'] : '-') ?></div>
</div>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
