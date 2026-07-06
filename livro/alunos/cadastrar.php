<?php
require_once 'config.php';
require_once '../auth.php';

// Carregar turmas agrupadas por dia
$turmas_raw = $pdo->query("SELECT * FROM turmas ORDER BY FIELD(dia_semana,'Segunda','Terça','Quarta','Quinta'), horario, id")->fetchAll();
$turmas_por_dia = array();
foreach ($turmas_raw as $t) {
    $turmas_por_dia[$t['dia_semana']][] = $t;
}

$erros = array();
$old   = array('nome'=>'','responsavel'=>'','cpf'=>'','whatsapp'=>'','turmas'=>array());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome        = trim(isset($_POST['nome'])        ? $_POST['nome']        : '');
    $responsavel = trim(isset($_POST['responsavel']) ? $_POST['responsavel'] : '');
    $cpf         = trim(isset($_POST['cpf'])         ? $_POST['cpf']         : '');
    $whatsapp    = trim(isset($_POST['whatsapp'])     ? $_POST['whatsapp']    : '');
    $turmas_sel  = isset($_POST['turmas']) ? array_map('intval', $_POST['turmas']) : array();

    if ($nome === '') $erros[] = 'O nome do aluno é obrigatório.';

    $old = compact('nome','responsavel','cpf','whatsapp') + array('turmas' => $turmas_sel);

    if (!$erros) {
        // salvar via salvar.php
        header('Location: salvar.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Novo Aluno</title>
<style>
*{box-sizing:border-box;}
body{font-family:Arial,sans-serif;margin:0;background:#f4f6f9;color:#222;}
.topbar{background:#1a6bbf;color:#fff;padding:0 24px;display:flex;align-items:center;gap:4px;height:48px;}
.topbar h1{margin:0;font-size:18px;margin-right:12px;}
.topbar a{color:#fff;text-decoration:none;font-size:14px;padding:7px 12px;border-radius:6px;opacity:.85;}
.topbar a:hover{background:rgba(255,255,255,.18);opacity:1;}
.topbar-sep{flex:1;}
.topbar-user{font-size:13px;opacity:.75;white-space:nowrap;}
.topbar a.btn-sair{background:#b00020;opacity:1;}
.topbar a.btn-sair:hover{background:#8c0019;}
.container{max-width:720px;margin:24px auto;padding:0 16px;}
.card{background:#fff;border:1px solid #dde;border-radius:12px;padding:24px;margin-bottom:20px;}
.card h2{margin:0 0 20px;font-size:17px;color:#1a6bbf;border-bottom:1px solid #eee;padding-bottom:10px;}
.field{margin-bottom:18px;}
label{display:block;font-size:13px;font-weight:bold;color:#444;margin-bottom:5px;}
label .req{color:#b00020;}
input[type=text]{width:100%;padding:9px 12px;border:1px solid #ccc;border-radius:8px;font-size:14px;}
input[type=text]:focus{outline:none;border-color:#1a6bbf;box-shadow:0 0 0 2px rgba(26,107,191,.15);}
.alert-err{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;padding:12px 16px;border-radius:8px;margin-bottom:18px;font-size:14px;}
.alert-warn{background:#fff3cd;color:#856404;border:1px solid #ffc107;padding:10px 14px;border-radius:8px;margin-top:8px;font-size:13px;display:none;}
.turmas-grupo{margin-bottom:14px;}
.turmas-grupo h3{font-size:13px;font-weight:bold;color:#1a6bbf;margin:0 0 8px;text-transform:uppercase;letter-spacing:.5px;}
.turmas-checks{display:flex;flex-wrap:wrap;gap:8px;}
.turma-check{display:flex;align-items:center;gap:6px;background:#f0f4fa;border:1px solid #d0daf0;border-radius:8px;padding:6px 12px;cursor:pointer;font-size:13px;transition:background .15s;}
.turma-check:hover{background:#dce7fa;}
.turma-check input{cursor:pointer;width:15px;height:15px;}
.turma-check.marcado{background:#1a6bbf;color:#fff;border-color:#1558a0;}
.actions{display:flex;gap:10px;margin-top:24px;}
.btn{display:inline-block;padding:10px 20px;border:0;border-radius:8px;cursor:pointer;font-size:14px;text-decoration:none;}
.btn-primary{background:#1a6bbf;color:#fff;}
.btn-primary:hover{background:#1558a0;}
.btn-clear{background:#eee;color:#333;}
.btn-clear:hover{background:#ddd;}
</style>
</head>
<body>

<div class="topbar">
    <h1>Novo Aluno</h1>
    <a href="index.php">Alunos</a>
    <a href="painel_turmas.php">Painel de Turmas</a>
    <span class="topbar-sep"></span>
    <span class="topbar-user">👤 <?= htmlspecialchars(isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : '', ENT_QUOTES, 'UTF-8') ?></span>
    <a href="../logout.php" class="btn-sair">Sair</a>
</div>

<div class="container">

<?php if ($erros): ?>
    <div class="alert-err">
        <?php foreach ($erros as $e) echo '<div>• ' . h($e) . '</div>'; ?>
    </div>
<?php endif; ?>

<form method="post" action="salvar.php">

    <div class="card">
        <h2>Dados do Aluno</h2>

        <div class="field">
            <label>Nome do aluno <span class="req">*</span></label>
            <input type="text" name="nome" value="<?= h($old['nome']) ?>" required placeholder="Nome completo">
        </div>

        <div class="field">
            <label>Responsável <small style="font-weight:normal;color:#777;">(preencha se o aluno tiver responsável)</small></label>
            <input type="text" name="responsavel" id="responsavel" value="<?= h($old['responsavel']) ?>" placeholder="Nome do responsável">
            <div class="alert-warn" id="aviso-resp">
                ⚠️ <strong>Atenção:</strong> Este aluno possui responsável — verifique se é menor de idade ou dependente.
            </div>
        </div>

        <div class="field">
            <label>CPF</label>
            <input type="text" name="cpf" id="cpf" value="<?= h($old['cpf']) ?>" placeholder="000.000.000-00" maxlength="14">
        </div>

        <div class="field">
            <label>WhatsApp</label>
            <input type="text" name="whatsapp" id="whatsapp" value="<?= h($old['whatsapp']) ?>" placeholder="(00) 00000-0000" maxlength="16">
        </div>
    </div>

    <div class="card">
        <h2>Turmas</h2>
        <p style="font-size:13px;color:#666;margin-top:0;">Selecione uma ou mais turmas que o aluno treina.</p>

        <?php foreach ($turmas_por_dia as $dia => $lista): ?>
        <div class="turmas-grupo">
            <h3><?= h($dia) ?></h3>
            <div class="turmas-checks">
                <?php foreach ($lista as $t): ?>
                    <?php $marcado = in_array((int)$t['id'], $old['turmas']); ?>
                    <label class="turma-check <?= $marcado ? 'marcado' : '' ?>" id="label-<?= $t['id'] ?>">
                        <input type="checkbox" name="turmas[]" value="<?= $t['id'] ?>"
                               <?= $marcado ? 'checked' : '' ?>
                               onchange="toggleLabel(this)">
                        <?= h($t['horario'] . ' — ' . $t['descricao']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="actions">
        <button type="submit" class="btn btn-primary">Salvar Aluno</button>
        <a href="index.php" class="btn btn-clear">Cancelar</a>
    </div>

</form>
</div>

<script>
// Aviso de responsável
var campResp = document.getElementById('responsavel');
var avisoResp = document.getElementById('aviso-resp');
function checkResp() {
    avisoResp.style.display = campResp.value.trim() !== '' ? 'block' : 'none';
}
campResp.addEventListener('input', checkResp);
checkResp(); // executar ao carregar (re-POST com dados)

// Máscara CPF
document.getElementById('cpf').addEventListener('input', function() {
    var v = this.value.replace(/\D/g, '').substring(0, 11);
    v = v.replace(/(\d{3})(\d)/, '$1.$2');
    v = v.replace(/(\d{3})(\d)/, '$1.$2');
    v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    this.value = v;
});

// Máscara WhatsApp
document.getElementById('whatsapp').addEventListener('input', function() {
    var v = this.value.replace(/\D/g, '').substring(0, 11);
    if (v.length > 6) {
        v = '(' + v.substring(0,2) + ') ' + v.substring(2,7) + '-' + v.substring(7);
    } else if (v.length > 2) {
        v = '(' + v.substring(0,2) + ') ' + v.substring(2);
    } else if (v.length > 0) {
        v = '(' + v;
    }
    this.value = v;
});

// Toggle estilo checkbox turma
function toggleLabel(el) {
    var lbl = el.closest('.turma-check');
    if (el.checked) lbl.classList.add('marcado');
    else lbl.classList.remove('marcado');
}
</script>

</body>
</html>
