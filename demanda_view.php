<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo = db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    redirect('/demandas.php?msg=id_invalido');
}

$sql = "
    SELECT d.*,
           c.nome AS cliente_nome,
           u.nome AS responsavel_nome
    FROM demandas d
    JOIN clientes c ON c.id = d.id_cliente
    JOIN usuarios u ON u.id = d.id_responsavel
    WHERE d.id = ? AND d.ativo = 1
    LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute(array($id));
$demanda = $stmt->fetch();

if (!$demanda) {
    redirect('/demandas.php?msg=nao_encontrada');
}

$stmtA = $pdo->prepare("SELECT * FROM demandas_arquivos WHERE id_demanda=? ORDER BY id DESC");
$stmtA->execute(array($id));
$arquivos = $stmtA->fetchAll();

function labelStatus($s){
    $map = array(
        'nao_iniciado' => 'Não Iniciado',
        'em_andamento' => 'Em Andamento',
        'aguardando_cliente' => 'Aguardando Cliente',
        'finalizado' => 'Finalizado',
        'publicado' => 'Publicado'
    );
    return isset($map[$s]) ? $map[$s] : $s;
}
function labelCrit($c){
    $map = array('baixa'=>'Baixa','media'=>'Média','alta'=>'Alta','urgente'=>'Urgente');
    return isset($map[$c]) ? $map[$c] : $c;
}
function brData($d){
    if (!$d) return '-';
    $t = strtotime($d);
    if (!$t) return '-';
    return date('d/m/Y', $t);
}
function isImageExt($filename){
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, array('jpg','jpeg','png','gif','webp'));
}

$menuActive = 'demandas';
$pageTitle  = 'Visualizar Demanda';
require_once __DIR__ . '/includes/layout_top.php';
?>

<style>
    .wrap{
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:14px;
        margin-bottom:18px;
    }
    .title h1{margin:0;font-size:22px;font-weight:900;}
    .title .sub{color:var(--muted);font-size:13px;margin-top:4px;}

    .btn{
        padding:10px 14px;border-radius:12px;border:1px solid var(--line);
        background:#fff;font-weight:800;font-size:13px;cursor:pointer;
    }
    .btn-dark{background:#111;color:#fff;border-color:#111;}
    .btn-dark:hover{opacity:.92}

    .panel{padding:16px;margin-top:14px;}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
    .item{border:1px solid var(--line);border-radius:14px;padding:12px;background:#fff;}
    .item .k{font-size:12px;color:var(--muted);font-weight:800;}
    .item .v{margin-top:6px;font-size:14px;font-weight:800;color:#111;}
    .desc{white-space:pre-wrap;font-size:13px;color:#111;line-height:1.4;margin-top:8px}

    .badge{
        display:inline-flex;align-items:center;gap:8px;
        padding:4px 10px;border-radius:999px;
        border:1px solid var(--line);
        font-size:12px;font-weight:800;background:#fff;
    }
    .badge-dark{background:#111;color:#fff;border-color:#111;}

    .files{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;}
    .file{
        border:1px solid var(--line);border-radius:14px;padding:12px;background:#fff;
        display:flex;justify-content:space-between;gap:12px;align-items:center;
    }
    .file .name{font-size:13px;font-weight:800;max-width:360px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .file .meta{font-size:12px;color:var(--muted);margin-top:4px;}
    .file a{
        text-decoration:none;
        padding:8px 10px;border-radius:12px;border:1px solid var(--line);
        font-size:12px;font-weight:900;color:#111;background:#fff;
    }
    .images{
        display:grid;
        grid-template-columns:repeat(4, 1fr);
        gap:12px;
        margin-top:12px;
    }
    .imgbox{
        border:1px solid var(--line);
        border-radius:14px;
        overflow:hidden;
        background:#fff;
    }
    .imgbox img{
        width:100%;
        height:150px;
        object-fit:cover;
        display:block;
    }
    .imgbox .cap{
        padding:8px 10px;
        font-size:12px;
        color:var(--muted);
        font-weight:700;
    }
</style>

<div class="wrap">
    <div class="title">
        <h1><?= h($demanda['titulo']) ?></h1>
        <div class="sub">Visualização completa da demanda</div>
    </div>

    <div style="display:flex;gap:10px;">
        <button class="btn" type="button" onclick="window.location.href='demandas.php'">← Voltar</button>
        <button class="btn btn-dark" type="button" onclick="window.location.href='demanda_edit.php?id=<?= (int)$demanda['id'] ?>'">✏️ Editar</button>
    </div>
</div>

<div class="grid">
    <div class="item">
        <div class="k">Cliente</div>
        <div class="v"><?= h($demanda['cliente_nome']) ?></div>
    </div>

    <div class="item">
        <div class="k">Responsável</div>
        <div class="v"><?= h($demanda['responsavel_nome']) ?></div>
    </div>

    <div class="item">
        <div class="k">Status</div>
        <div class="v"><span class="badge"><?= h(labelStatus($demanda['status'])) ?></span></div>
    </div>

    <div class="item">
        <div class="k">Criticidade</div>
        <div class="v"><span class="badge badge-dark"><?= h(labelCrit($demanda['criticidade'])) ?></span></div>
    </div>

    <div class="item">
        <div class="k">Prazo</div>
        <div class="v"><?= h(brData($demanda['prazo'])) ?></div>
    </div>

    <div class="item">
        <div class="k">Criado em</div>
        <div class="v"><?= h(date('d/m/Y H:i', strtotime($demanda['criado_em']))) ?></div>
    </div>
</div>

<div class="panel">
    <div style="font-weight:900;font-size:16px;">Descrição</div>
    <div class="desc"><?= h($demanda['descricao'] ? $demanda['descricao'] : '-') ?></div>
</div>

<div class="panel">
    <div style="font-weight:900;font-size:16px;">Anexos</div>

    <?php if (!empty($arquivos)): ?>

        <div class="files">
            <?php foreach ($arquivos as $a): ?>
                <div class="file">
                    <div>
                        <div class="name"><?= h($a['nome_original']) ?></div>
                        <div class="meta">
                            <?= h($a['mime'] ? $a['mime'] : '-') ?> · <?= h($a['tamanho'] ? number_format($a['tamanho']/1024, 1) . ' KB' : '-') ?>
                        </div>
                    </div>
                    <a href="demanda_download.php?id=<?= (int)$a['id'] ?>" target="_blank">Baixar</a>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top:18px;font-weight:900;">Prévia das imagens</div>
        <div class="images">
            <?php foreach ($arquivos as $a): ?>
                <?php if (isImageExt($a['nome_original'])): ?>
                    <div class="imgbox">
                        <img src="demanda_file.php?id=<?= (int)$a['id'] ?>" alt="">
                        <div class="cap"><?= h($a['nome_original']) ?></div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
        <div style="margin-top:10px;color:var(--muted);font-weight:700;">Nenhum anexo enviado ainda.</div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
