<?php
require_once 'config.php';
require_once '../auth.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: index.php'); exit; }

$aluno = $pdo->prepare("SELECT * FROM alunos WHERE id = ?");
$aluno->execute([$id]);
$aluno = $aluno->fetch();
if (!$aluno) { header('Location: index.php?erro=' . urlencode('Aluno não encontrado.')); exit; }

// Turmas do aluno
$stmtSel = $pdo->prepare("SELECT turma_id FROM aluno_turmas WHERE aluno_id = ?");
$stmtSel->execute([$id]);
$turmas_aluno = array_column($stmtSel->fetchAll(), 'turma_id');
$turmas_aluno = array_map('intval', $turmas_aluno);

// Todas as turmas
$turmas_raw = $pdo->query("SELECT * FROM turmas ORDER BY FIELD(dia_semana,'Segunda','Terça','Quarta','Quinta'), horario, id")->fetchAll();
$turmas_por_dia = array();
foreach ($turmas_raw as $t) {
    $turmas_por_dia[$t['dia_semana']][] = $t;
}
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Editar Aluno</title>
<style>
*{box-sizing:border-box;}
body{font-family:Arial,sans-serif;margin:0;background:#f4f6f9;color:#222;}
.topbar{background:#555;color:#fff;padding:0 24px;display:flex;align-items:center;gap:4px;height:48px;}
.topbar h1{margin:0;font-size:18px;margin-right:12px;}
.topbar a{color:#fff;text-decoration:none;font-size:14px;padding:7px 12px;border-radius:6px;opacity:.85;}
.topbar a:hover{background:rgba(255,255,255,.18);opacity:1;}
.topbar-sep{flex:1;}
.topbar-user{font-size:13px;opacity:.75;white-space:nowrap;}
.topbar a.btn-sair{background:#b00020;opacity:1;}
.topbar a.btn-sair:hover{background:#8c0019;}
.container{max-width:720px;margin:24px auto;padding:0 16px;}
.card{background:#fff;border:1px solid #dde;border-radius:12px;padding:24px;margin-bottom:20px;}
.card h2{margin:0 0 20px;font-size:17px;color:#555;border-bottom:1px solid #eee;padding-bottom:10px;}
.field{margin-bottom:18px;}
label{display:block;font-size:13px;font-weight:bold;color:#444;margin-bottom:5px;}
label .req{color:#b00020;}
input[type=text]{width:100%;padding:9px 12px;border:1px solid #ccc;border-radius:8px;font-size:14px;}
input[type=text]:focus{outline:none;border-color:#555;box-shadow:0 0 0 2px rgba(80,80,80,.12);}
.alert-warn{background:#fff3cd;color:#856404;border:1px solid #ffc107;padding:10px 14px;border-radius:8px;margin-top:8px;font-size:13px;display:none;}
.turmas-grupo{margin-bottom:14px;}
.turmas-grupo h3{font-size:13px;font-weight:bold;color:#555;margin:0 0 8px;text-transform:uppercase;letter-spacing:.5px;}
.turmas-checks{display:flex;flex-wrap:wrap;gap:8px;}
.turma-check{display:flex;align-items:center;gap:6px;background:#f0f4fa;border:1px solid #d0daf0;border-radius:8px;padding:6px 12px;cursor:pointer;font-size:13px;transition:background .15s;}
.turma-check:hover{background:#dce7fa;}
.turma-check input{cursor:pointer;width:15px;height:15px;}
.turma-check.marcado{background:#555;color:#fff;border-color:#333;}
.actions{display:flex;gap:10px;margin-top:24px;}
.btn{display:inline-block;padding:10px 20px;border:0;border-radius:8px;cursor:pointer;font-size:14px;text-decoration:none;}
.btn-edit{background:#555;color:#fff;}
.btn-edit:hover{background:#333;}
.btn-clear{background:#eee;color:#333;}
.btn-clear:hover{background:#ddd;}
.info-criado{font-size:12px;color:#aaa;margin-top:6px;}
</style>
</head>
<body>

<div class="topbar">
    <h1>Editar Aluno</h1>
    <a href="index.php">Alunos</a>
    <a href="painel_turmas.php">Painel de Turmas</a>
    <span class="topbar-sep"></span>
    <span class="topbar-user">👤 <?= htmlspecialchars(isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : '', ENT_QUOTES, 'UTF-8') ?></span>
    <a href="../logout.php" class="btn-sair">Sair</a>
</div>

<div class="container">

<form method="post" action="atualizar.php">
    <input type="hidden" name="id" value="<?= $aluno['id'] ?>">

    <div class="card">
        <h2>Dados do Aluno</h2>
        <p class="info-criado">Cadastrado em: <?= h($aluno['criado_em']) ?> &nbsp;|&nbsp; Atualizado em: <?= h($aluno['atualizado_em']) ?></p>

        <div class="field">
            <label>Nome do aluno <span class="req">*</span></label>
            <input type="text" name="nome" value="<?= h($aluno['nome']) ?>" required placeholder="Nome completo">
        </div>

        <div class="field">
            <label>Responsável <small style="font-weight:normal;color:#777;">(preencha se o aluno tiver responsável)</small></label>
            <input type="text" name="responsavel" id="responsavel" value="<?= h(isset($aluno['responsavel']) ? $aluno['responsavel'] : '') ?>" placeholder="Nome do responsável">
            <div class="alert-warn" id="aviso-resp">
                ⚠️ <strong>Atenção:</strong> Este aluno possui responsável — verifique se é menor de idade ou dependente.
            </div>
        </div>

        <div class="field">
            <label>CPF</label>
            <input type="text" name="cpf" id="cpf" value="<?= h(isset($aluno['cpf']) ? $aluno['cpf'] : '') ?>" placeholder="000.000.000-00" maxlength="14">
        </div>

        <div class="field">
            <label>WhatsApp</label>
            <input type="text" name="whatsapp" id="whatsapp" value="<?= h(isset($aluno['whatsapp']) ? $aluno['whatsapp'] : '') ?>" placeholder="(00) 00000-0000" maxlength="16">
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
                    <?php $marcado = in_array((int)$t['id'], $turmas_aluno); ?>
                    <label class="turma-check <?= $marcado ? 'marcado' : '' ?>">
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
        <button type="submit" class="btn btn-edit">Salvar Alterações</button>
        <a href="index.php" class="btn btn-clear">Cancelar</a>
    </div>

</form>
</div>

<script>
var campResp  = document.getElementById('responsavel');
var avisoResp = document.getElementById('aviso-resp');
function checkResp() {
    avisoResp.style.display = campResp.value.trim() !== '' ? 'block' : 'none';
}
campResp.addEventListener('input', checkResp);
checkResp();

document.getElementById('cpf').addEventListener('input', function() {
    var v = this.value.replace(/\D/g, '').substring(0, 11);
    v = v.replace(/(\d{3})(\d)/, '$1.$2');
    v = v.replace(/(\d{3})(\d)/, '$1.$2');
    v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    this.value = v;
});

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

function toggleLabel(el) {
    var lbl = el.closest('.turma-check');
    if (el.checked) lbl.classList.add('marcado');
    else lbl.classList.remove('marcado');
}
</script>

</body>
</html>
