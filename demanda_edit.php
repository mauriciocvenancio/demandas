<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo = db();
$u = auth_user();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) redirect('/demandas.php?msg=id_invalido');

// CSRF
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16));
$csrf = $_SESSION['csrf'];

$stmt = $pdo->prepare("SELECT * FROM demandas WHERE id=? AND ativo=1 LIMIT 1");
$stmt->execute(array($id));
$demanda = $stmt->fetch();
if (!$demanda) redirect('/demandas.php?msg=nao_encontrada');

$clientes = $pdo->query("SELECT id, nome FROM clientes WHERE ativo=1 ORDER BY nome ASC")->fetchAll();
$usuarios = $pdo->query("SELECT id, nome, tipo FROM usuarios WHERE ativo=1 ORDER BY nome ASC")->fetchAll();

$stmtA = $pdo->prepare("SELECT * FROM demandas_arquivos WHERE id_demanda=? ORDER BY id DESC");
$stmtA->execute(array($id));
$arquivos = $stmtA->fetchAll();

$menuActive = 'demandas';
$pageTitle  = 'Editar Demanda';
require_once __DIR__ . '/includes/layout_top.php';

function isImageExt($filename){
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, array('jpg','jpeg','png','gif','webp'));
}
?>

<style>
    .wrap{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:18px;}
    .title h1{margin:0;font-size:22px;font-weight:900;}
    .title .sub{color:var(--muted);font-size:13px;margin-top:4px;}

    .panel{padding:16px;margin-top:14px;}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    .grid-1{grid-template-columns:1fr;}
    label{display:block;font-size:12px;font-weight:800;margin:10px 0 6px;}
    input, textarea, select{
        width:100%;padding:11px 12px;border-radius:12px;border:1px solid var(--line);
        outline:none;font-size:13px;background:#fff;
    }
    textarea{min-height:120px;resize:vertical;}

    .btn{
        padding:10px 14px;border-radius:12px;border:1px solid var(--line);
        background:#fff;font-weight:800;font-size:13px;cursor:pointer;
    }
    .btn-dark{background:#111;color:#fff;border-color:#111;}
    .btn-dark:hover{opacity:.92}

    .files{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;}
    .file{
        border:1px solid var(--line);border-radius:14px;padding:12px;background:#fff;
        display:flex;justify-content:space-between;gap:12px;align-items:center;
    }
    .file .name{font-size:13px;font-weight:800;max-width:360px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
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
        <h1>Editar Demanda</h1>
        <div class="sub">Atualize os dados e adicione novos anexos</div>
    </div>

    <div style="display:flex;gap:10px;">
        <button class="btn" type="button" onclick="window.location.href='demanda_view.php?id=<?= (int)$demanda['id'] ?>'">← Voltar</button>
        <button class="btn btn-dark" type="submit" form="formEdit">Salvar Alterações</button>
    </div>
</div>

<form id="formEdit" method="post" action="demandas_process.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" value="<?= (int)$demanda['id'] ?>">

    <div class="panel">
        <div class="grid grid-1">
            <div>
                <label>Título *</label>
                <input type="text" name="titulo" value="<?= h($demanda['titulo']) ?>" required>
            </div>
        </div>

        <div class="grid">
            <div>
                <label>Cliente *</label>
                <select name="id_cliente" required>
                    <option value="">Selecione o cliente</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= ((int)$demanda['id_cliente'] === (int)$c['id']) ? 'selected' : '' ?>>
                            <?= h($c['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Responsável *</label>
                <select name="id_responsavel" required>
                    <?php foreach ($usuarios as $us): ?>
                        <option value="<?= (int)$us['id'] ?>" <?= ((int)$demanda['id_responsavel'] === (int)$us['id']) ? 'selected' : '' ?>>
                            <?= h($us['nome']) ?> (<?= h($us['tipo']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid">
            <div>
                <label>Status</label>
                <select name="status">
                    <option value="nao_iniciado" <?= ($demanda['status']==='nao_iniciado')?'selected':''; ?>>Não Iniciado</option>
                    <option value="em_andamento" <?= ($demanda['status']==='em_andamento')?'selected':''; ?>>Em Andamento</option>
                    <option value="aguardando_cliente" <?= ($demanda['status']==='aguardando_cliente')?'selected':''; ?>>Aguardando Cliente</option>
                    <option value="finalizado" <?= ($demanda['status']==='finalizado')?'selected':''; ?>>Finalizado</option>
                    <option value="publicado" <?= ($demanda['status']==='publicado')?'selected':''; ?>>Publicado</option>
                </select>
            </div>

            <div>
                <label>Criticidade</label>
                <select name="criticidade">
                    <option value="baixa" <?= ($demanda['criticidade']==='baixa')?'selected':''; ?>>Baixa</option>
                    <option value="media" <?= ($demanda['criticidade']==='media')?'selected':''; ?>>Média</option>
                    <option value="alta" <?= ($demanda['criticidade']==='alta')?'selected':''; ?>>Alta</option>
                    <option value="urgente" <?= ($demanda['criticidade']==='urgente')?'selected':''; ?>>Urgente</option>
                </select>
            </div>
        </div>

        <div class="grid">
            <div>
                <label>Prazo</label>
                <input type="date" name="prazo" value="<?= h($demanda['prazo']) ?>">
            </div>
            <div></div>
        </div>

        <div class="grid grid-1">
            <div>
                <label>Descrição</label>
                <textarea name="descricao"><?= h($demanda['descricao']) ?></textarea>
            </div>
        </div>

        <div class="grid grid-1">
            <div>
                <label>Adicionar novos anexos</label>
                <input type="file" name="anexos[]" multiple>
            </div>
        </div>
    </div>

    <div class="panel">
        <div style="font-weight:900;font-size:16px;">Anexos atuais</div>

        <?php if (!empty($arquivos)): ?>
            <div class="files">
                <?php foreach ($arquivos as $a): ?>
                    <div class="file">
                        <div class="name"><?= h($a['nome_original']) ?></div>
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
</form>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
