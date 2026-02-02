<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo = db();

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16));
$csrf = $_SESSION['csrf'];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: suportes.php?msg=id_invalido'); exit; }

$stmt = $pdo->prepare("
  SELECT s.*, 
         c.nome AS cliente_nome,
         u3.nome AS responsavel_nome
  FROM suportes s
  JOIN clientes c ON c.id = s.id_cliente
  LEFT JOIN usuarios u3 ON u3.id = s.id_usuario_responsavel
  WHERE s.id=? AND s.ativo=1
  LIMIT 1
");
$stmt->execute(array($id));
$r = $stmt->fetch();
if (!$r) { header('Location: suportes.php?msg=nao_encontrado'); exit; }

$clientes = $pdo->query("SELECT id, nome FROM clientes WHERE ativo=1 ORDER BY nome ASC")->fetchAll();
$usuarios = $pdo->query("SELECT id, nome, tipo FROM usuarios WHERE ativo=1 ORDER BY nome ASC")->fetchAll();

$menuActive = 'suportes';
$pageTitle  = 'Editar Suporte';
require_once __DIR__ . '/includes/layout_top.php';
?>

<style>
    .wrap{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:18px;}
    .title h1{margin:0;font-size:22px;font-weight:900;}
    .title .sub{color:var(--muted);font-size:13px;margin-top:4px;}
    .panel{padding:16px;margin-top:14px;}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    .grid-1{grid-template-columns:1fr;}
    label{display:block;font-size:12px;font-weight:900;margin:10px 0 6px;}
    input, textarea, select{
        width:100%;padding:11px 12px;border-radius:12px;border:1px solid var(--line);
        outline:none;font-size:13px;background:#fff;
    }
    textarea{min-height:90px;resize:vertical;}
    .btn{padding:10px 14px;border-radius:12px;border:1px solid var(--line);background:#fff;font-weight:900;font-size:13px;cursor:pointer;}
    .btn-dark{background:#111;color:#fff;border-color:#111;}
    .btn-dark:hover{opacity:.92}
    .checkline{display:flex;align-items:center;gap:10px;border:1px solid var(--line);border-radius:12px;padding:10px 12px;background:#fff;margin-top:10px;}
    .checkline input{width:auto;margin:0;}
</style>

<div class="wrap">
    <div class="title">
        <h1>Editar Suporte</h1>
        <div class="sub">Atualize os dados do atendimento</div>
    </div>

    <div style="display:flex;gap:10px;">
        <button class="btn" type="button" onclick="window.location.href='suportes.php'">← Voltar</button>
        <button class="btn btn-dark" type="submit" form="formEdit">Salvar</button>
    </div>
</div>

<form id="formEdit" method="post" action="suporte_process.php">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">

    <div class="panel">
        <div class="grid grid-1">
            <div>
                <label>Assunto *</label>
                <input type="text" name="assunto" required value="<?= h($r['assunto']) ?>">
            </div>
        </div>

        <div class="grid">
            <div>
                <label>Cliente *</label>
                <select name="id_cliente" required>
                    <option value="">Selecione o cliente</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= ((int)$r['id_cliente']===(int)$c['id'])?'selected':''; ?>>
                            <?= h($c['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Responsável *</label>
                <select name="id_usuario_responsavel" required>
                    <option value="">Selecione o responsável</option>
                    <?php foreach ($usuarios as $us): ?>
                        <option value="<?= (int)$us['id'] ?>"
                            <?= ((int)$r['id_usuario_responsavel'] === (int)$us['id']) ? 'selected' : ''; ?>>
                            <?= h($us['nome']) ?> (<?= h($us['tipo']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid">
            <div>
                <label>Tipo de Contato *</label>
                <select name="tipo_contato" required>
                    <option value="duvida" <?= ($r['tipo_contato']==='duvida')?'selected':''; ?>>Dúvida</option>
                    <option value="melhoria" <?= ($r['tipo_contato']==='melhoria')?'selected':''; ?>>Melhoria</option>
                    <option value="solicitacao" <?= ($r['tipo_contato']==='solicitacao')?'selected':''; ?>>Solicitação</option>
                    <option value="bug" <?= ($r['tipo_contato']==='bug')?'selected':''; ?>>Bug</option>
                    <option value="configuracao" <?= ($r['tipo_contato']==='configuracao')?'selected':''; ?>>Configuração</option>
                </select>
            </div>

            <div>
                <label>Status *</label>
                <select name="status" required>
                    <option value="em_andamento" <?= ($r['status']==='em_andamento')?'selected':''; ?>>Em andamento</option>
                    <option value="aguardando_resposta" <?= ($r['status']==='aguardando_resposta')?'selected':''; ?>>Aguardando resposta</option>
                    <option value="finalizado" <?= ($r['status']==='finalizado')?'selected':''; ?>>Finalizado</option>
                </select>
            </div>
        </div>

        <div class="grid">
            <div>
                <label>Criticidade *</label>
                <select name="criticidade" required>
                    <option value="media" <?= ($r['criticidade']==='media')?'selected':''; ?>>Média</option>
                    <option value="baixa" <?= ($r['criticidade']==='baixa')?'selected':''; ?>>Baixa</option>
                    <option value="alta" <?= ($r['criticidade']==='alta')?'selected':''; ?>>Alta</option>
                    <option value="urgente" <?= ($r['criticidade']==='urgente')?'selected':''; ?>>Urgente</option>
                </select>
            </div>
            <div></div>
        </div>

        <div class="grid grid-1">
            <div>
                <label>Descrição</label>
                <textarea name="descricao"><?= h($r['descricao']) ?></textarea>
            </div>
        </div>

        <div class="grid grid-1">
            <div>
                <label>Resolução</label>
                <textarea name="resolucao"><?= h($r['resolucao']) ?></textarea>
            </div>
        </div>

        <div class="grid">
            <div>
                <label>Duração (minutos)</label>
                <input type="number" name="duracao_min" min="0" step="1" value="<?= h($r['duracao_min']) ?>">
            </div>
            <div></div>
        </div>

        <?php if (empty($r['id_demanda'])): ?>
            <div class="checkline">
                <input type="checkbox" name="criar_demanda" value="1" id="criar_demanda">
                <label for="criar_demanda" style="margin:0;font-size:13px;font-weight:900;">Criar demanda a partir deste suporte (se ainda não existir)</label>
            </div>
        <?php else: ?>
            <div style="margin-top:10px;color:var(--muted);font-weight:900;">
                Demanda vinculada: <a href="demanda_view.php?id=<?= (int)$r['id_demanda'] ?>">#<?= (int)$r['id_demanda'] ?></a>
            </div>
        <?php endif; ?>

    </div>
</form>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
