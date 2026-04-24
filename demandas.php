<?php
$menuActive = 'demandas';
$pageTitle  = 'Demandas';
require_once __DIR__ . '/includes/layout_top.php';

$pdo = db();
$u = auth_user();


// CSRF
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16));
$csrf = $_SESSION['csrf'];

$msg = isset($_GET['msg']) ? trim((string)$_GET['msg']) : '';
$q   = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$fs  = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$fc  = isset($_GET['crit']) ? trim((string)$_GET['crit']) : '';
$fr  = isset($_GET['responsavel']) ? trim((string)$_GET['responsavel']) : '';

function msgText($m){
    $map = array(
        'criada' => 'Demanda criada com sucesso.',
        'csrf' => 'Sessão expirada. Recarregue a página e tente novamente.',
        'titulo_obrigatorio' => 'Informe o título da demanda.',
        'cliente_obrigatorio' => 'Selecione o cliente.',
        'acao_invalida' => 'Ação inválida.'
    );
    return isset($map[$m]) ? $map[$m] : '';
}
$flash = msgText($msg);

// combos
if (!empty($u['id_cliente'])) {
    $stmtClientes = $pdo->prepare("SELECT id, nome FROM clientes WHERE ativo=1 AND id=? ORDER BY nome ASC");
    $stmtClientes->execute(array((int)$u['id_cliente']));
    $clientes = $stmtClientes->fetchAll();
} else {
    $clientes = $pdo->query("SELECT id, nome FROM clientes WHERE ativo=1 ORDER BY nome ASC")->fetchAll();
}
$usuarios = $pdo->query("SELECT id, nome, tipo FROM usuarios WHERE ativo=1 ORDER BY nome ASC")->fetchAll();

// filtros listagem
$where = " d.ativo=1 ";
$params = array();

if (!empty($u['id_cliente'])) {
    $where .= " AND d.id_cliente = ? AND d.id_responsavel = ? ";
    $params[] = (int)$u['id_cliente'];
    $params[] = (int)$u['id'];
}

if ($q !== '') {
    $where .= " AND (d.titulo LIKE ? OR c.nome LIKE ? OR u.nome LIKE ?) ";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($fs !== '' && $fs !== 'all') {
    $where .= " AND d.status = ? ";
    $params[] = $fs;
}
if ($fc !== '' && $fc !== 'all') {
    $where .= " AND d.criticidade = ? ";
    $params[] = $fc;
}
if ($fr !== '' && $fr !== 'all') {
    $where .= " AND d.id_responsavel = ? ";
    $params[] = $fr;
}

$sql = "
  SELECT d.id, d.titulo, d.status, d.criticidade, d.prazo, d.criado_em,
         c.nome AS cliente_nome,
         u.nome AS responsavel_nome
  FROM demandas d
  JOIN clientes c ON c.id = d.id_cliente
  JOIN usuarios u ON u.id = d.id_responsavel
  WHERE $where
  ORDER BY d.criado_em DESC
  LIMIT 200
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$demandas = $stmt->fetchAll();

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

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
function brDatetime($d){
    if (!$d) return '-';
    $t = strtotime($d);
    if (!$t) return '-';
    return date('d/m/Y H:i', $t);
}
function diasPrazo($prazo, $criado_em, $status = ''){
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
function diasCriacao($criado_em, $prazo){
    if (empty($criado_em) || empty($prazo)) return null;
    $tc = strtotime(date('Y-m-d', strtotime($criado_em)));
    $tp = strtotime(date('Y-m-d', strtotime($prazo)));
    if (!$tc || !$tp) return null;
    $dias = (int)round(($tp - $tc) / 86400);
    if ($dias === 0) return array('txt' => 'Mesmo dia', 'cor' => '#16a34a');
    if ($dias <= 5)  return array('txt' => $dias . 'd',  'cor' => '#d97706');
    return              array('txt' => $dias . 'd',      'cor' => '#dc2626');
}
?>
<style>
    .head-row{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;}
    .btn-primary{
        display:inline-flex;align-items:center;gap:10px;
        padding:10px 14px;border-radius:12px;
        background:#111;color:#fff;font-weight:800;font-size:13px;
        border:0; cursor:pointer;
    }
    .btn-primary:hover{opacity:.92}
    .panel{padding:0}
    .panel-inner{padding:16px}
    .panel-top{
        display:flex;align-items:center;justify-content:space-between;gap:12px;
        padding:16px;border-bottom:1px solid var(--line);
    }
    .filters{display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
    .search{
        display:flex;align-items:center;gap:10px;
        border:1px solid var(--line);border-radius:12px;background:#fff;
        padding:10px 12px; min-width:220px;
    }
    .search input{border:0;outline:none;width:100%;font-size:13px;}
    select{
        border:1px solid var(--line);border-radius:12px;background:#fff;
        padding:10px 12px;font-size:13px;outline:none;
    }

    table{width:100%;border-collapse:collapse;}
    th,td{padding:12px 14px;border-bottom:1px solid var(--line);font-size:13px;}
    th{color:var(--muted);font-size:12px;text-align:left;font-weight:800;}
    .td-muted{color:var(--muted)}
    .row-actions{position:relative;text-align:right;width:60px;}
    .dots{
        width:34px;height:34px;border-radius:12px;border:1px solid var(--line);
        background:#fff;cursor:pointer;font-weight:900;
    }
    .menu{
        position:absolute;right:14px;top:44px;
        background:#fff;border:1px solid var(--line);border-radius:12px;
        box-shadow:var(--shadow);min-width:170px;display:none;overflow:hidden;
        z-index:20;
    }
    .menu button{
        width:100%;display:flex;align-items:center;gap:10px;
        padding:10px 12px;background:#fff;border:0;cursor:pointer;
        font-size:13px;font-weight:700;text-align:left;
    }
    .menu button:hover{background:#f3f4f6}

    .flash{
        margin-top:14px;
        padding:10px 12px;border-radius:12px;
        border:1px solid var(--line);background:#fff;
        font-size:13px;color:#111;
    }

    .badge{
        display:inline-flex;align-items:center;gap:8px;
        padding:4px 10px;border-radius:999px;
        border:1px solid var(--line);
        font-size:12px;font-weight:800;background:#fff;
    }
    .badge-dark{background:#111;color:#fff;border-color:#111;}

    /* modal */
    .modal-backdrop{
        position:fixed;inset:0;background:rgba(17,17,17,.55);
        display:none;align-items:center;justify-content:center;z-index:1000;
        padding:18px;
    }
    .modal{
        width:640px;max-width:96vw;background:#fff;border-radius:16px;
        border:1px solid var(--line);box-shadow:0 20px 60px rgba(0,0,0,.25);
    }
    .modal-head{
        display:flex;align-items:flex-start;justify-content:space-between;gap:10px;
        padding:16px 16px 10px;border-bottom:1px solid var(--line);
    }
    .modal-head .t{font-weight:900;font-size:18px;margin:0}
    .modal-head .s{color:var(--muted);font-size:12px;margin-top:4px}
    .modal-close{
        width:36px;height:36px;border-radius:12px;border:1px solid var(--line);
        background:#fff;cursor:pointer;font-size:18px;
    }
    .modal-body{padding:14px 16px 16px;}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    .grid-1{grid-template-columns:1fr;}
    label{display:block;font-size:12px;font-weight:800;margin:10px 0 6px;}
    input, textarea{
        width:100%;padding:11px 12px;border-radius:12px;border:1px solid var(--line);
        outline:none;font-size:13px;background:#fff;
    }
    textarea{min-height:92px;resize:vertical;}
    .drop{
        border:1px dashed #d1d5db;border-radius:14px;
        padding:18px;background:#fafafa;
        display:flex;align-items:center;justify-content:center;
        flex-direction:column;gap:8px;
        cursor:pointer;
    }
    .drop .ic{font-size:28px;color:#6b7280;}
    .drop .tx{font-size:13px;color:#6b7280;font-weight:700;}
    .modal-foot{
        display:flex;justify-content:flex-end;gap:10px;
        padding:12px 16px 16px;border-top:1px solid var(--line);
    }
    .btn{
        padding:10px 14px;border-radius:12px;border:1px solid var(--line);
        background:#fff;font-weight:800;font-size:13px;cursor:pointer;
    }
    .btn-dark{
        background:#111;color:#fff;border-color:#111;
    }
    .btn-dark:hover{opacity:.92}
    .req{color:#111;font-weight:900}
</style>

<div class="head-row">
    <div>
        <h1 class="page-title">Demandas</h1>
        <div class="page-sub">Gerencie todas as demandas e tarefas do sistema</div>
        <?php if ($flash !== ''): ?><div class="flash"><?= h($flash) ?></div><?php endif; ?>
    </div>

    <button class="btn-primary" type="button" onclick="openModal('modalNovaDemanda')">
        <span style="font-size:16px;">＋</span> Nova Demanda
    </button>
</div>

<div class="panel" style="margin-top:18px;">
    <div class="panel-top">
        <div style="font-weight:900;">Lista de Demandas</div>

        <form method="get" class="filters" action="demandas.php">
            <div class="search">
                <span style="color:#9ca3af;">🔍</span>
                <input type="text" name="q" value="<?= h($q) ?>" placeholder="Buscar demandas...">
            </div>

            <select name="status">
                <option value="all" <?= ($fs===''||$fs==='all')?'selected':''; ?>>Todos Status</option>
                <option value="nao_iniciado" <?= ($fs==='nao_iniciado')?'selected':''; ?>>Não Iniciado</option>
                <option value="em_andamento" <?= ($fs==='em_andamento')?'selected':''; ?>>Em Andamento</option>
                <option value="aguardando_cliente" <?= ($fs==='aguardando_cliente')?'selected':''; ?>>Aguardando Cliente</option>
                <option value="finalizado" <?= ($fs==='finalizado')?'selected':''; ?>>Finalizado</option>
                <option value="publicado" <?= ($fs==='publicado')?'selected':''; ?>>Publicado</option>
            </select>

            <select name="crit">
                <option value="all" <?= ($fc===''||$fc==='all')?'selected':''; ?>>Todas Criticidade</option>
                <option value="baixa" <?= ($fc==='baixa')?'selected':''; ?>>Baixa</option>
                <option value="media" <?= ($fc==='media')?'selected':''; ?>>Média</option>
                <option value="alta" <?= ($fc==='alta')?'selected':''; ?>>Alta</option>
                <option value="urgente" <?= ($fc==='urgente')?'selected':''; ?>>Urgente</option>
            </select>

            <?php if (empty($u['id_cliente'])): ?>
                <select name="responsavel" onchange="this.form.submit()">
                    <option value="all">Responsável</option>
                    <?php foreach($usuarios as $us): ?>
                        <option value="<?= h($us['id']) ?>" <?= $fr == $us['id'] ? 'selected' : '' ?>>
                            <?= h($us['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <button class="btn" type="submit">Filtrar</button>
        </form>
    </div>

    <div class="panel-inner">
        <table>
            <thead>
            <tr>
                <th style="width:24%;">Título</th>
                <th style="width:16%;">Cliente</th>
                <th style="width:16%;">Responsável</th>
                <th style="width:12%;">Criticidade</th>
                <th style="width:12%;">Status</th>
                <th style="width:10%;">Prazo</th>
                <th style="width:10%;">Criação</th>
                <th style="width:6%;"></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($demandas)): ?>
                <?php foreach ($demandas as $d): ?>
                    <tr>
                        <td><b><?= h($d['titulo']) ?></b></td>
                        <td class="td-muted"><?= h($d['cliente_nome']) ?></td>
                        <td class="td-muted"><?= h($d['responsavel_nome']) ?></td>
                        <td>
                            <span class="badge badge-dark">👁 <?= h(labelCrit($d['criticidade'])) ?></span>
                        </td>
                        <td><span class="badge"><?= h(labelStatus($d['status'])) ?></span></td>
                        <td class="td-muted">
                            <?= h(brData($d['prazo'])) ?>
                        </td>
                        <td class="td-muted" style="font-size:12px;">
                            <?= h(brDatetime($d['criado_em'])) ?>
                            <?php $dc = diasCriacao($d['criado_em'], $d['prazo']); if ($dc): ?>
                                <div style="font-size:11px;margin-top:3px;font-weight:900;color:<?= $dc['cor'] ?>;"><?= $dc['txt'] ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="row-actions">
                            <button class="dots" type="button" onclick="toggleRowMenu(<?= (int)$d['id'] ?>)">…</button>
                            <div class="menu" id="menu-<?= (int)$d['id'] ?>">
                                <button type="button" onclick="window.location.href='demanda_view.php?id=<?= (int)$d['id'] ?>'">👁 Visualizar</button>
                                <button type="button" onclick="window.location.href='demanda_edit.php?id=<?= (int)$d['id'] ?>'">✏️ Editar</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="8" class="td-muted" style="padding:18px;">Nenhuma demanda encontrada.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===== Modal: Nova Demanda ===== -->
<div class="modal-backdrop" id="modalNovaDemanda" onclick="backdropClose(event, 'modalNovaDemanda')">
    <div class="modal">
        <div class="modal-head">
            <div>
                <div class="t">Nova Demanda</div>
                <div class="s">Preencha os dados para criar uma nova demanda</div>
            </div>
            <button class="modal-close" type="button" onclick="closeModal('modalNovaDemanda')">×</button>
        </div>

        <form method="post" action="demandas_process.php" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="create">

                <div class="grid grid-1">
                    <div>
                        <label>Título <span class="req">*</span></label>
                        <input type="text" name="titulo" placeholder="Título da demanda" required>
                    </div>
                </div>

                <div class="grid">
                    <div>
                        <label>Cliente <span class="req">*</span></label>

                        <?php if (!empty($u['id_cliente']) && !empty($clientes)): ?>
                            <input type="hidden" name="id_cliente" value="<?= (int)$u['id_cliente'] ?>">
                            <input type="text" value="<?= h($clientes[0]['nome']) ?>" disabled>
                        <?php else: ?>
                            <select name="id_cliente" required>
                                <option value="">Selecione o cliente</option>
                                <?php foreach ($clientes as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"><?= h($c['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label>Responsável <span class="req">*</span></label>

                        <?php if (!empty($u['id_cliente'])): ?>
                            <input type="hidden" name="resp_mode" value="me">
                            <input type="hidden" name="id_responsavel" value="<?= (int)$u['id'] ?>">
                            <input type="text" value="<?= h($u['nome']) ?>" disabled>
                        <?php else: ?>
                            <select name="resp_mode" onchange="toggleResp(this.value)">
                                <option value="me" selected>Eu mesmo</option>
                                <option value="user">Selecionar usuário</option>
                            </select>

                            <div style="margin-top:10px; display:none;" id="resp_user_box">
                                <select name="id_responsavel">
                                    <option value="">Selecione o usuário</option>
                                    <?php foreach ($usuarios as $us): ?>
                                        <option value="<?= (int)$us['id'] ?>">
                                            <?= h($us['nome']) ?> (<?= h($us['tipo']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="grid">
                    <div>
                        <label>Status</label>
                        <select name="status">
                            <option value="nao_iniciado" selected>Não Iniciado</option>
                            <option value="em_andamento">Em Andamento</option>
                            <option value="aguardando_cliente">Aguardando Cliente</option>
                            <option value="finalizado">Finalizado</option>
                            <option value="publicado">Publicado</option>
                        </select>
                    </div>

                    <div>
                        <label>Criticidade</label>
                        <select name="criticidade">
                            <option value="baixa">Baixa</option>
                            <option value="media" selected>Média</option>
                            <option value="alta">Alta</option>
                            <option value="urgente">Urgente</option>
                        </select>
                    </div>
                </div>

                <div class="grid">
                    <div>
                        <label>Prazo</label>
                        <input type="date" name="prazo">
                    </div>
                    <div></div>
                </div>

                <div class="grid grid-1">
                    <div>
                        <label>Descrição</label>
                        <textarea name="descricao" placeholder="Descreva os detalhes da demanda"></textarea>
                    </div>
                </div>

                <div class="grid grid-1">
                    <div>
                        <label>Anexos</label>
                        <label class="drop" for="anexos_input">
                            <div class="ic">⤴</div>
                            <div class="tx">Clique para selecionar arquivos</div>
                            <div class="tx" id="files_info" style="font-weight:600;"></div>
                        </label>
                        <input type="file" id="anexos_input" name="anexos[]" multiple style="display:none;">
                    </div>
                </div>

            </div>

            <div class="modal-foot">
                <button class="btn" type="button" onclick="closeModal('modalNovaDemanda')">Cancelar</button>
                <button class="btn btn-dark" type="submit">Criar Demanda</button>
            </div>
        </form>
    </div>
</div>
<script>
    function openModal(id){
        document.getElementById(id).style.display = 'flex';
        closeAllMenus();
    }
    function closeModal(id){
        document.getElementById(id).style.display = 'none';
    }
    function backdropClose(e, id){
        if (e.target && e.target.id === id) closeModal(id);
    }
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape'){
            closeModal('modalNovaDemanda');
            closeAllMenus();
        }
    });

    function toggleRowMenu(id){
        var el = document.getElementById('menu-'+id);
        var isOpen = (el.style.display === 'block');
        closeAllMenus();
        el.style.display = isOpen ? 'none' : 'block';
    }
    function closeAllMenus(){
        var menus = document.querySelectorAll('.menu');
        for (var i=0;i<menus.length;i++) menus[i].style.display = 'none';
    }
    document.addEventListener('click', function(e){
        var isDots = e.target && e.target.classList && e.target.classList.contains('dots');
        var isMenu = e.target && (e.target.closest && e.target.closest('.menu'));
        if (!isDots && !isMenu) closeAllMenus();
    });

    function toggleResp(v){
        var box = document.getElementById('resp_user_box');
        box.style.display = (v === 'user') ? 'block' : 'none';
    }

    // exibir nomes dos arquivos selecionados
    document.getElementById('anexos_input').addEventListener('change', function(){
        var info = document.getElementById('files_info');
        if (!this.files || this.files.length === 0){
            info.textContent = '';
            return;
        }
        info.textContent = this.files.length + ' arquivo(s) selecionado(s)';
    });
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
