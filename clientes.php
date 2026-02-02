<?php
$menuActive = 'clientes';
$pageTitle  = 'Clientes';
require_once __DIR__ . '/includes/layout_top.php';

$pdo = db();

// CSRF simples
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16));
$csrf = $_SESSION['csrf'];

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$msg = isset($_GET['msg']) ? trim((string)$_GET['msg']) : '';

$params = array();
$where = " WHERE ativo = 1 ";

if ($q !== '') {
    $where .= " AND (nome LIKE ? OR empresa LIKE ? OR email LIKE ? OR telefone LIKE ?) ";
    $like = '%' . $q . '%';
    $params = array($like, $like, $like, $like);
}

$sql = "SELECT id, nome, empresa, email, telefone, observacoes
        FROM clientes
        $where
        ORDER BY nome ASC
        LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll();

// mensagens simples
function msgText($m){
    $map = array(
        'criado' => 'Cliente cadastrado com sucesso.',
        'removido' => 'Cliente removido.',
        'atualizado' => 'Cliente atualizado com sucesso.',
        'nome_obrigatorio' => 'Informe o nome do cliente.',
        'csrf' => 'Sessão expirada. Recarregue a página e tente novamente.',
        'acao_invalida' => 'Ação inválida.',
        'id_invalido' => 'ID inválido.',
    );
    return isset($map[$m]) ? $map[$m] : '';
}
$flash = msgText($msg);
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
    .search{
        display:flex;align-items:center;gap:10px;
        border:1px solid var(--line);border-radius:12px;background:#fff;
        padding:10px 12px; min-width:260px;
    }
    .search input{border:0;outline:none;width:100%;font-size:13px;}
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
    .menu a, .menu button{
        width:100%;display:flex;align-items:center;gap:10px;
        padding:10px 12px;background:#fff;border:0;cursor:pointer;
        font-size:13px;font-weight:700;text-align:left;
    }
    .menu a:hover, .menu button:hover{background:#f3f4f6}
    .flash{
        margin-top:14px;
        padding:10px 12px;border-radius:12px;
        border:1px solid var(--line);background:#fff;
        font-size:13px;color:#111;
    }

    /* modal */
    .modal-backdrop{
        position:fixed;inset:0;background:rgba(17,17,17,.55);
        display:none;align-items:center;justify-content:center;z-index:1000;
        padding:18px;
    }
    .modal{
        width:560px;max-width:96vw;background:#fff;border-radius:16px;
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
        <h1 class="page-title">Clientes</h1>
        <div class="page-sub">Gerencie os clientes cadastrados no sistema</div>
        <?php if ($flash !== ''): ?>
            <div class="flash"><?= h($flash) ?></div>
        <?php endif; ?>
    </div>

    <button class="btn-primary" type="button" onclick="openModal('modalNovoCliente')">
        <span style="font-size:16px;">＋</span> Novo Cliente
    </button>
</div>

<div class="panel" style="margin-top:18px;">
    <div class="panel-top">
        <div style="font-weight:900;">Lista de Clientes</div>

        <form method="get" class="search" action="clientes.php">
            <span style="color:#9ca3af;">🔍</span>
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="Buscar clientes...">
        </form>
    </div>

    <div class="panel-inner">
        <table>
            <thead>
            <tr>
                <th style="width:28%;">Nome</th>
                <th style="width:28%;">Empresa</th>
                <th style="width:22%;">Email</th>
                <th style="width:16%;">Telefone</th>
                <th style="width:6%;"></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($clientes)): ?>
                <?php foreach ($clientes as $c): ?>
                    <tr>
                        <td><b><?= h($c['nome']) ?></b></td>
                        <td class="td-muted"><?= h($c['empresa'] !== null && $c['empresa'] !== '' ? $c['empresa'] : '-') ?></td>
                        <td class="td-muted"><?= h($c['email'] !== null && $c['email'] !== '' ? $c['email'] : '-') ?></td>
                        <td class="td-muted"><?= h($c['telefone'] !== null && $c['telefone'] !== '' ? $c['telefone'] : '-') ?></td>

                        <td class="row-actions">
                            <button class="dots" type="button" onclick="toggleRowMenu(<?= (int)$c['id'] ?>)">…</button>

                            <div class="menu" id="menu-<?= (int)$c['id'] ?>">
                                <button type="button"
                                        onclick='openEditClient(<?= (int)$c["id"] ?>, <?= json_encode($c["nome"]) ?>, <?= json_encode($c["email"]) ?>, <?= json_encode($c["telefone"]) ?>, <?= json_encode($c["empresa"]) ?>, <?= json_encode($c["observacoes"]) ?>)'>
                                    ✏️ Editar
                                </button>

                                <form method="post" action="clientes_process.php" onsubmit="return confirm('Remover este cliente?');" style="margin:0;">
                                    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <button type="submit">🗑️ Remover</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="td-muted" style="padding:18px;">
                        Nenhum cliente encontrado.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===== Modal: Novo Cliente ===== -->
<div class="modal-backdrop" id="modalNovoCliente" onclick="backdropClose(event, 'modalNovoCliente')">
    <div class="modal">
        <div class="modal-head">
            <div>
                <div class="t">Novo Cliente</div>
                <div class="s">Preencha os dados para cadastrar um novo cliente</div>
            </div>
            <button class="modal-close" type="button" onclick="closeModal('modalNovoCliente')">×</button>
        </div>

        <form method="post" action="clientes_process.php">
            <div class="modal-body">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="create">

                <div class="grid grid-1">
                    <div>
                        <label>Nome <span class="req">*</span></label>
                        <input type="text" name="nome" placeholder="Nome do cliente" required>
                    </div>
                </div>

                <div class="grid">
                    <div>
                        <label>Email</label>
                        <input type="email" name="email" placeholder="email@exemplo.com">
                    </div>
                    <div>
                        <label>Telefone</label>
                        <input type="text" name="telefone" class="js-phone" placeholder="(00) 00000-0000" maxlength="15">
                    </div>
                </div>

                <div class="grid grid-1">
                    <div>
                        <label>Empresa</label>
                        <input type="text" name="empresa" placeholder="Nome da empresa">
                    </div>
                </div>

                <div class="grid grid-1">
                    <div>
                        <label>Observações</label>
                        <textarea name="observacoes" placeholder="Informações adicionais sobre o cliente"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-foot">
                <button class="btn" type="button" onclick="closeModal('modalNovoCliente')">Cancelar</button>
                <button class="btn btn-dark" type="submit">Cadastrar</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== Modal: Editar Cliente ===== -->
<div class="modal-backdrop" id="modalEditarCliente" onclick="backdropClose(event, 'modalEditarCliente')">
    <div class="modal">
        <div class="modal-head">
            <div>
                <div class="t">Editar Cliente</div>
                <div class="s">Atualize os dados do cliente</div>
            </div>
            <button class="modal-close" type="button" onclick="closeModal('modalEditarCliente')">×</button>
        </div>

        <form method="post" action="clientes_process.php">
            <div class="modal-body">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id" value="">

                <div class="grid grid-1">
                    <div>
                        <label>Nome <span class="req">*</span></label>
                        <input type="text" name="nome" id="edit_nome" placeholder="Nome do cliente" required>
                    </div>
                </div>

                <div class="grid">
                    <div>
                        <label>Email</label>
                        <input type="email" name="email" id="edit_email" placeholder="email@exemplo.com">
                    </div>
                    <div>
                        <label>Telefone</label>
                        <input type="text" name="telefone" id="edit_telefone" class="js-phone" placeholder="(00) 00000-0000" maxlength="15">
                    </div>
                </div>

                <div class="grid grid-1">
                    <div>
                        <label>Empresa</label>
                        <input type="text" name="empresa" id="edit_empresa" placeholder="Nome da empresa">
                    </div>
                </div>

                <div class="grid grid-1">
                    <div>
                        <label>Observações</label>
                        <textarea name="observacoes" id="edit_observacoes" placeholder="Informações adicionais sobre o cliente"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-foot">
                <button class="btn" type="button" onclick="closeModal('modalEditarCliente')">Cancelar</button>
                <button class="btn btn-dark" type="submit">Salvar</button>
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
            closeModal('modalNovoCliente');
            closeModal('modalEditarCliente');
            closeAllMenus();
        }
    });

    function toggleRowMenu(id){
        const el = document.getElementById('menu-'+id);
        const isOpen = (el.style.display === 'block');
        closeAllMenus();
        el.style.display = isOpen ? 'none' : 'block';
    }
    function closeAllMenus(){
        const menus = document.querySelectorAll('.menu');
        for (let i=0;i<menus.length;i++) menus[i].style.display = 'none';
    }
    document.addEventListener('click', function(e){
        const isDots = e.target && e.target.classList && e.target.classList.contains('dots');
        const isMenu = e.target && (e.target.closest && e.target.closest('.menu'));
        if (!isDots && !isMenu) closeAllMenus();
    });

    // abre modal editar preenchendo campos
    function openEditClient(id, nome, email, telefone, empresa, observacoes){
        closeAllMenus();

        document.getElementById('edit_id').value = id || '';
        document.getElementById('edit_nome').value = nome || '';
        document.getElementById('edit_email').value = email || '';
        document.getElementById('edit_telefone').value = telefone || '';
        document.getElementById('edit_empresa').value = empresa || '';
        document.getElementById('edit_observacoes').value = observacoes || '';

        openModal('modalEditarCliente');
    }
</script>
<script>
    // ===== Máscara de telefone (BR) - (00) 0000-0000 ou (00) 00000-0000 =====
    function onlyDigits(v){
        return (v || '').replace(/\D/g, '');
    }

    function formatPhoneBR(value){
        var v = onlyDigits(value);

        // limita em 11 dígitos (DDD + 9)
        if (v.length > 11) v = v.substring(0, 11);

        // DDD
        if (v.length <= 2) {
            return v.length ? '(' + v : '';
        }

        var ddd = v.substring(0, 2);
        var rest = v.substring(2);

        // 8 dígitos (fixo) => 4-4
        // 9 dígitos (celular) => 5-4
        if (rest.length <= 4) {
            return '(' + ddd + ') ' + rest;
        }

        if (rest.length <= 8) {
            return '(' + ddd + ') ' + rest.substring(0, 4) + '-' + rest.substring(4);
        }

        // 9 dígitos
        return '(' + ddd + ') ' + rest.substring(0, 5) + '-' + rest.substring(5);
    }

    function applyPhoneMask(el){
        el.value = formatPhoneBR(el.value);
    }

    function bindPhoneMask(el){
        // ao digitar
        el.addEventListener('input', function(){
            var before = el.value;
            el.value = formatPhoneBR(before);
        });

        // ao colar
        el.addEventListener('paste', function(){
            setTimeout(function(){ applyPhoneMask(el); }, 0);
        });

        // ao sair do campo
        el.addEventListener('blur', function(){
            applyPhoneMask(el);
        });

        // ao entrar no campo já corrige
        el.addEventListener('focus', function(){
            applyPhoneMask(el);
        });

        // formata valor inicial (ex.: vindo do banco)
        applyPhoneMask(el);
    }

    // aplica em todos os inputs com class js-phone
    (function(){
        var els = document.querySelectorAll('.js-phone');
        for (var i = 0; i < els.length; i++){
            bindPhoneMask(els[i]);
        }
    })();

    // IMPORTANTE: quando abrir o modal de edição e preencher via JS,
    // a máscara já vai rodar no focus/blur, mas podemos forçar:
    // (opcional) chame applyPhoneMask(document.getElementById('edit_telefone')) após preencher.
</script>


<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
