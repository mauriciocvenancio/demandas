<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo = db();
$u   = auth_user();

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16));
$csrf = $_SESSION['csrf'];

$q    = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$tipo = isset($_GET['tipo']) ? trim((string)$_GET['tipo']) : 'todos';
$resp = isset($_GET['resp']) ? (int)$_GET['resp'] : 0; // 0 = todos

$where = array("s.ativo=1");
$params = array();

if ($q !== '') {
    $where[] = "(s.assunto LIKE ? OR c.nome LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$allowedTipos = array('melhoria','duvida','solicitacao','bug','configuracao');
if ($tipo !== 'todos' && in_array($tipo, $allowedTipos, true)) {
    $where[] = "s.tipo_contato = ?";
    $params[] = $tipo;
}

if ($resp > 0) {
    $where[]  = "s.id_usuario_responsavel = ?";
    $params[] = $resp;
}

$sql = "
SELECT s.*,
       c.nome AS cliente_nome,
       u2.nome AS usuario_nome,
       u3.nome AS responsavel_nome
FROM suportes s
JOIN clientes c ON c.id = s.id_cliente
JOIN usuarios u2 ON u2.id = s.id_usuario_registro
LEFT JOIN usuarios u3 ON u3.id = s.id_usuario_responsavel
WHERE ".implode(" AND ", $where)."
ORDER BY s.id DESC
LIMIT 200
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$linhas = $stmt->fetchAll();

$clientes = $pdo->query("SELECT id, nome FROM clientes WHERE ativo=1 ORDER BY nome ASC")->fetchAll();
$usuarios = $pdo->query("SELECT id, nome, tipo FROM usuarios WHERE ativo=1 ORDER BY nome ASC")->fetchAll();

function labelTipo($t){
    $map = array(
        'melhoria'=>'Melhoria',
        'duvida'=>'Dúvida',
        'solicitacao'=>'Solicitação',
        'bug'=>'Bug',
        'configuracao'=>'Configuração'
    );
    return isset($map[$t]) ? $map[$t] : $t;
}
function labelStatusSup($s){
    $map = array(
        'em_andamento'=>'Em andamento',
        'finalizado'=>'Finalizado',
        'aguardando_resposta'=>'Aguardando resposta'
    );
    return isset($map[$s]) ? $map[$s] : $s;
}
function labelCrit($c){
    $map = array('baixa'=>'Baixa','media'=>'Média','alta'=>'Alta','urgente'=>'Urgente');
    return isset($map[$c]) ? $map[$c] : $c;
}

$menuActive = 'suportes';
$pageTitle  = 'Suportes';
require_once __DIR__ . '/includes/layout_top.php';
?>

<style>
    .toprow{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:18px;}
    .title h1{margin:0;font-size:22px;font-weight:900;}
    .title .sub{color:var(--muted);font-size:13px;margin-top:4px;}

    .btn-dark{
        display:inline-flex;gap:10px;align-items:center;
        padding:10px 14px;border-radius:12px;border:1px solid #111;
        background:#111;color:#fff;font-weight:900;font-size:13px;cursor:pointer;
    }
    .btn-dark:hover{opacity:.92}
    .panel{padding:16px;}
    .toolbar{display:flex;justify-content:flex-end;gap:10px;margin-bottom:10px;}
    .input, .select{
        height:40px;border-radius:12px;border:1px solid var(--line);
        padding:0 12px;font-size:13px;outline:none;background:#fff;
    }
    .input{width:260px;}
    .select{width:170px;}

    table{width:100%;border-collapse:separate;border-spacing:0;}
    thead th{font-size:12px;color:var(--muted);text-align:left;font-weight:900;padding:12px 10px;border-bottom:1px solid var(--line);}
    tbody td{padding:12px 10px;border-bottom:1px solid var(--line);font-size:13px;font-weight:700;}
    .badge{
        display:inline-flex;align-items:center;gap:8px;
        padding:4px 10px;border-radius:999px;border:1px solid var(--line);
        font-size:12px;font-weight:900;background:#fff;
    }
    .badge-dark{background:#111;color:#fff;border-color:#111;}
    .right{display:flex;justify-content:flex-end;}
    .kebab{cursor:pointer;border:1px solid transparent;background:transparent;font-size:18px;line-height:1;padding:6px 10px;border-radius:10px;}
    .kebab:hover{border-color:var(--line);background:#fff;}
    .menu{position:relative;}
    .menu-panel{
        position:absolute;right:0;top:38px;z-index:50;
        border:1px solid var(--line);background:#fff;border-radius:12px;
        min-width:160px;box-shadow:0 10px 30px rgba(0,0,0,.08);
        overflow:hidden;display:none;
    }
    .menu-panel button{
        width:100%;text-align:left;border:0;background:#fff;padding:10px 12px;
        font-weight:800;font-size:13px;cursor:pointer;
    }
    .menu-panel button:hover{background:#f6f6f6;}
    .empty{padding:46px 10px;text-align:center;color:var(--muted);font-weight:800;}

    /* Modal */
    .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);display:none;align-items:center;justify-content:center;z-index:100;}
    .modal{width:720px;max-width:calc(100% - 20px);background:#fff;border-radius:16px;border:1px solid var(--line);box-shadow:0 20px 50px rgba(0,0,0,.2);}
    .modal-head{display:flex;align-items:flex-start;justify-content:space-between;padding:16px 18px;border-bottom:1px solid var(--line);}
    .modal-head h3{margin:0;font-size:18px;font-weight:900;}
    .modal-head .sub{margin-top:4px;color:var(--muted);font-size:13px;font-weight:700;}
    .xbtn{border:0;background:transparent;font-size:18px;cursor:pointer;padding:6px 10px;border-radius:10px;}
    .xbtn:hover{background:#f6f6f6;}
    .modal-body{padding:16px 18px;}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
    .grid-1{grid-template-columns:1fr;}
    label{display:block;font-size:12px;font-weight:900;margin:10px 0 6px;}
    input, textarea, select{
        width:100%;padding:11px 12px;border-radius:12px;border:1px solid var(--line);outline:none;font-size:13px;background:#fff;
    }
    textarea{min-height:88px;resize:vertical;}
    .modal-foot{display:flex;justify-content:flex-end;gap:10px;padding:14px 18px;border-top:1px solid var(--line);}
    .btn{padding:10px 14px;border-radius:12px;border:1px solid var(--line);background:#fff;font-weight:900;font-size:13px;cursor:pointer;}
    .btn:hover{background:#f6f6f6;}
    .btn-primary{background:#111;color:#fff;border-color:#111;}
    .btn-primary:hover{opacity:.92}
    .checkline{display:flex;align-items:center;gap:10px;border:1px solid var(--line);border-radius:12px;padding:10px 12px;background:#fff;margin-top:10px;}
    .checkline input{width:auto;margin:0;}
</style>

<div class="toprow">
    <div class="title">
        <h1>Suportes</h1>
        <div class="sub">Registre e acompanhe os atendimentos realizados</div>
    </div>

    <button class="btn-dark" type="button" onclick="openModal()">
        <span style="font-size:16px;line-height:0;">＋</span> Novo Suporte
    </button>
</div>

<div class="panel">
    <div class="toolbar">
        <form method="get" style="display:flex;gap:10px;">
            <input class="input" type="text" name="q" value="<?= h($q) ?>" placeholder="Buscar suportes...">
            <select class="select" name="tipo">
                <option value="todos" <?= ($tipo==='todos')?'selected':''; ?>>Todos Tipos</option>
                <option value="melhoria" <?= ($tipo==='melhoria')?'selected':''; ?>>Melhoria</option>
                <option value="duvida" <?= ($tipo==='duvida')?'selected':''; ?>>Dúvida</option>
                <option value="solicitacao" <?= ($tipo==='solicitacao')?'selected':''; ?>>Solicitação</option>
                <option value="bug" <?= ($tipo==='bug')?'selected':''; ?>>Bug</option>
                <option value="configuracao" <?= ($tipo==='configuracao')?'selected':''; ?>>Configuração</option>
            </select>

            <select class="select" name="resp">
                <option value="0" <?= ($resp===0)?'selected':''; ?>>Todos Responsáveis</option>
                <?php foreach ($usuarios as $us): ?>
                    <option value="<?= (int)$us['id'] ?>" <?= ($resp===(int)$us['id'])?'selected':''; ?>>
                        <?= h($us['nome']) ?> (<?= h($us['tipo']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn" type="submit">Filtrar</button>
        </form>
    </div>

    <div style="font-weight:900;font-size:15px;margin-bottom:10px;">Lista de Suportes</div>

    <?php if (!empty($linhas)): ?>
        <table>
            <thead>
            <tr>
                <th>Assunto</th>
                <th>Cliente</th>
                <th>Responsável</th>
                <th>Tipo</th>
                <th>Status</th>
                <th>Criticidade</th>
                <th>Demanda</th>
                <th class="right"></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($linhas as $r): ?>
                <tr>
                    <td><?= h($r['assunto']) ?></td>
                    <td><?= h($r['cliente_nome']) ?></td>
                    <td><?= h($r['responsavel_nome'] ? $r['responsavel_nome'] : '-') ?></td>
                    <td><span class="badge"><?= h(labelTipo($r['tipo_contato'])) ?></span></td>
                    <td><span class="badge"><?= h(labelStatusSup($r['status'])) ?></span></td>
                    <td><span class="badge badge-dark"><?= h(labelCrit($r['criticidade'])) ?></span></td>
                    <td><?= $r['id_demanda'] ? ('#'.(int)$r['id_demanda']) : '-' ?></td>
                    <td class="right">
                        <div class="menu">
                            <button class="kebab" type="button" onclick="toggleMenu(<?= (int)$r['id'] ?>)">…</button>
                            <div class="menu-panel" id="menu-<?= (int)$r['id'] ?>">
                                <button type="button" onclick="window.location.href='suporte_view.php?id=<?= (int)$r['id'] ?>'">👁 Visualizar</button>
                                <button type="button" onclick="window.location.href='suporte_edit.php?id=<?= (int)$r['id'] ?>'">✏️ Editar</button>
                                <?php if (!empty($r['id_demanda'])): ?>
                                    <button type="button" onclick="window.location.href='demanda_view.php?id=<?= (int)$r['id_demanda'] ?>'">↗ Abrir Demanda</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="empty">Nenhum suporte encontrado</div>
    <?php endif; ?>
</div>

<!-- Modal: Novo Suporte -->
<div class="modal-overlay" id="modal">
    <div class="modal">
        <div class="modal-head">
            <div>
                <h3>Novo Suporte</h3>
                <div class="sub">Registre um novo atendimento de suporte</div>
            </div>
            <button class="xbtn" type="button" onclick="closeModal()">×</button>
        </div>

        <form method="post" action="suporte_process.php" class="modal-body" id="formCreate">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="create">

            <div class="grid grid-1">
                <div>
                    <label>Assunto *</label>
                    <input type="text" name="assunto" required placeholder="Assunto do atendimento">
                </div>
            </div>

            <div class="grid">
                <div>
                    <label>Cliente *</label>
                    <select name="id_cliente" required>
                        <option value="">Selecione o cliente</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= h($c['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Responsável *</label>
                    <select name="id_usuario_responsavel" required>
                        <option value="">Selecione o responsável</option>
                        <?php foreach ($usuarios as $us): ?>
                            <option value="<?= (int)$us['id'] ?>" <?= ((int)$us['id'] == (int)$u['id']) ? 'selected' : ''; ?>>
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
                        <option value="duvida">Dúvida</option>
                        <option value="melhoria">Melhoria</option>
                        <option value="solicitacao">Solicitação</option>
                        <option value="bug">Bug</option>
                        <option value="configuracao">Configuração</option>
                    </select>
                </div>

                <div>
                    <label>Status *</label>
                    <select name="status" required>
                        <option value="em_andamento">Em andamento</option>
                        <option value="aguardando_resposta">Aguardando resposta</option>
                        <option value="finalizado">Finalizado</option>
                    </select>
                </div>
            </div>

            <div class="grid">
                <div>
                    <label>Criticidade *</label>
                    <select name="criticidade" required>
                        <option value="media">Média</option>
                        <option value="baixa">Baixa</option>
                        <option value="alta">Alta</option>
                        <option value="urgente">Urgente</option>
                    </select>
                </div>
                <div></div>
            </div>

            <div class="grid grid-1">
                <div>
                    <label>Descrição</label>
                    <textarea name="descricao" placeholder="Descreva o motivo do contato"></textarea>
                </div>
            </div>

            <div class="grid grid-1">
                <div>
                    <label>Resolução</label>
                    <textarea name="resolucao" placeholder="Como foi resolvido o problema?"></textarea>
                </div>
            </div>

            <div class="grid">
                <div>
                    <label>Duração (minutos)</label>
                    <input type="number" name="duracao_min" min="0" step="1" placeholder="Tempo de atendimento">
                </div>
                <div></div>
            </div>

            <div class="checkline">
                <input type="checkbox" name="criar_demanda" value="1" id="criar_demanda">
                <label for="criar_demanda" style="margin:0;font-size:13px;font-weight:900;">Criar demanda a partir deste suporte</label>
            </div>
        </form>

        <div class="modal-foot">
            <button class="btn" type="button" onclick="closeModal()">Cancelar</button>
            <button class="btn btn-primary" type="submit" form="formCreate">Registrar Suporte</button>
        </div>
    </div>
</div>

<script>
    function openModal(){ document.getElementById('modal').style.display='flex'; }
    function closeModal(){ document.getElementById('modal').style.display='none'; }

    function toggleMenu(id){
        closeAllMenus();
        var el = document.getElementById('menu-'+id);
        if (!el) return;
        el.style.display = (el.style.display === 'block') ? 'none' : 'block';
    }
    function closeAllMenus(){
        var menus = document.querySelectorAll('.menu-panel');
        for (var i=0;i<menus.length;i++) menus[i].style.display='none';
    }
    document.addEventListener('click', function(e){
        if (!e.target.classList.contains('kebab')) closeAllMenus();
    });
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
