<?php
require_once 'config.php';

$sucesso = false;
$erros   = array();
$old     = array('nome'=>'','responsavel'=>'','cpf'=>'','whatsapp'=>'','turmas'=>array());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome        = trim(isset($_POST['nome'])        ? $_POST['nome']        : '');
    $responsavel = trim(isset($_POST['responsavel']) ? $_POST['responsavel'] : '');
    $cpf         = trim(isset($_POST['cpf'])         ? $_POST['cpf']         : '');
    $whatsapp    = trim(isset($_POST['whatsapp'])     ? $_POST['whatsapp']    : '');
    $turmas_sel  = isset($_POST['turmas']) ? array_map('intval', $_POST['turmas']) : array();

    if ($nome === '')     $erros[] = 'Por favor, informe o nome do aluno.';
    if ($whatsapp === '') $erros[] = 'Por favor, informe o WhatsApp.';
    if (empty($turmas_sel)) $erros[] = 'Selecione ao menos uma turma.';

    $old = array(
        'nome'        => $nome,
        'responsavel' => $responsavel,
        'cpf'         => $cpf,
        'whatsapp'    => $whatsapp,
        'turmas'      => $turmas_sel,
    );

    if (!$erros) {
        $stmt = $pdo->prepare("INSERT INTO alunos (nome, responsavel, cpf, whatsapp) VALUES (:nome, :resp, :cpf, :wh)");
        $stmt->execute(array(
            ':nome' => $nome,
            ':resp' => $responsavel !== '' ? $responsavel : null,
            ':cpf'  => $cpf       !== '' ? $cpf       : null,
            ':wh'   => $whatsapp  !== '' ? $whatsapp  : null,
        ));
        $aluno_id = (int)$pdo->lastInsertId();

        if ($aluno_id && $turmas_sel) {
            $ins = $pdo->prepare("INSERT IGNORE INTO aluno_turmas (aluno_id, turma_id) VALUES (:aid, :tid)");
            foreach ($turmas_sel as $tid) {
                if ($tid > 0) $ins->execute(array(':aid' => $aluno_id, ':tid' => $tid));
            }
        }

        $sucesso = true;
    }
}

// Carregar turmas agrupadas por dia
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
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cadastro de Aluno</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:Arial,sans-serif;background:#f0f4f8;color:#222;min-height:100vh;}

.header{background:#1a6bbf;color:#fff;padding:20px 16px 28px;text-align:center;}
.header h1{font-size:22px;font-weight:bold;margin-bottom:4px;}
.header p{font-size:14px;opacity:.85;}

.container{max-width:560px;margin:-14px auto 40px;padding:0 14px;}

.card{background:#fff;border-radius:14px;padding:22px 20px;margin-bottom:16px;box-shadow:0 2px 8px rgba(0,0,0,.08);}
.card h2{font-size:16px;color:#1a6bbf;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid #eee;}

.field{margin-bottom:18px;}
.field:last-child{margin-bottom:0;}
label.lbl{display:block;font-size:13px;font-weight:bold;color:#444;margin-bottom:6px;}
label.lbl .req{color:#b00020;}
label.lbl .opt{font-weight:normal;color:#888;font-size:12px;}

input[type=text]{
    width:100%;padding:11px 13px;border:1.5px solid #ccc;border-radius:10px;
    font-size:15px;transition:border .2s;-webkit-appearance:none;
}
input[type=text]:focus{outline:none;border-color:#1a6bbf;box-shadow:0 0 0 3px rgba(26,107,191,.12);}

.alert-err{background:#fde8ea;color:#7a1020;border:1.5px solid #f5b8be;padding:13px 15px;border-radius:10px;margin-bottom:16px;font-size:14px;line-height:1.6;}
.alert-warn{background:#fff8e1;color:#7a5800;border:1.5px solid #ffd54f;padding:11px 14px;border-radius:10px;margin-top:10px;font-size:13px;display:none;}

/* Turmas */
.dia-titulo{font-size:12px;font-weight:bold;color:#1a6bbf;text-transform:uppercase;letter-spacing:.6px;margin:0 0 8px;}
.turmas-grupo{margin-bottom:18px;}
.turmas-grupo:last-child{margin-bottom:0;}
.turmas-checks{display:flex;flex-direction:column;gap:8px;}
.turma-item{display:flex;align-items:center;gap:10px;background:#f5f8ff;border:1.5px solid #d4e0f7;border-radius:10px;padding:11px 13px;cursor:pointer;transition:background .15s,border .15s;}
.turma-item:active{background:#dce7fa;}
.turma-item.marcado{background:#1a6bbf;border-color:#1558a0;color:#fff;}
.turma-item input[type=checkbox]{width:18px;height:18px;cursor:pointer;flex-shrink:0;accent-color:#fff;}
.turma-item span{font-size:14px;line-height:1.3;}

/* Botão */
.btn-enviar{
    display:block;width:100%;padding:14px;background:#1a6bbf;color:#fff;
    border:0;border-radius:12px;font-size:16px;font-weight:bold;cursor:pointer;
    letter-spacing:.3px;margin-top:4px;-webkit-appearance:none;
}
.btn-enviar:active{background:#1558a0;}

/* Sucesso */
.sucesso{text-align:center;padding:40px 20px;}
.sucesso .ico{font-size:64px;margin-bottom:16px;}
.sucesso h2{font-size:22px;color:#0b7a3e;margin-bottom:10px;}
.sucesso p{font-size:15px;color:#555;line-height:1.6;}
.sucesso .turmas-ok{margin:18px 0 0;text-align:left;}
.sucesso .turmas-ok h3{font-size:13px;color:#555;font-weight:bold;margin-bottom:8px;}
.tag{display:inline-block;background:#e8f0fb;color:#1a4d9e;border-radius:6px;padding:4px 10px;margin:3px;font-size:13px;}

/* Rodapé */
.footer{text-align:center;font-size:12px;color:#aaa;padding:0 0 24px;}
</style>
</head>
<body>

<div class="header">
    <h1>Cadastro de Aluno</h1>
    <p>Preencha os dados abaixo para se cadastrar</p>
</div>

<div class="container">

<?php if ($sucesso):
    // Buscar turmas selecionadas para exibir no sucesso
    $stmtT = $pdo->prepare("
        SELECT t.dia_semana, t.horario, t.descricao
        FROM turmas t
        JOIN aluno_turmas at2 ON at2.turma_id = t.id
        WHERE at2.aluno_id = :id
        ORDER BY t.id
    ");
    $stmtT->execute(array(':id' => $aluno_id));
    $turmas_ok = $stmtT->fetchAll();
?>
<div class="card sucesso">
    <div class="ico">✅</div>
    <h2>Cadastro realizado!</h2>
    <p>Obrigado, <strong><?= h($nome) ?></strong>!<br>
    Seu cadastro foi recebido com sucesso.<br>Em breve entraremos em contato.</p>

    <?php if ($turmas_ok): ?>
    <div class="turmas-ok">
        <h3>Turmas selecionadas:</h3>
        <?php foreach ($turmas_ok as $tt): ?>
            <span class="tag"><?= h($tt['dia_semana'] . ' ' . $tt['horario'] . ' — ' . $tt['descricao']) ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>

<?php if ($erros): ?>
<div class="alert-err">
    <?php foreach ($erros as $e) echo '<div>• ' . h($e) . '</div>'; ?>
</div>
<?php endif; ?>

<form method="post" action="formulario.php" novalidate>

    <div class="card">
        <h2>Dados do Aluno</h2>

        <div class="field">
            <label class="lbl">Nome completo <span class="req">*</span></label>
            <input type="text" name="nome" value="<?= h($old['nome']) ?>"
                   placeholder="Ex.: João da Silva" autocomplete="name" required>
        </div>

        <div class="field">
            <label class="lbl">Responsável <span class="opt">(caso o aluno seja menor)</span></label>
            <input type="text" name="responsavel" id="responsavel" value="<?= h($old['responsavel']) ?>"
                   placeholder="Nome do responsável" autocomplete="off">
            <div class="alert-warn" id="aviso-resp">
                ⚠️ Atenção: verifique se o aluno é menor de idade — o responsável deverá assinar a ficha presencialmente.
            </div>
        </div>

        <div class="field">
            <label class="lbl">CPF <span class="opt">(opcional)</span></label>
            <input type="text" name="cpf" id="cpf" value="<?= h($old['cpf']) ?>"
                   placeholder="000.000.000-00" maxlength="14" inputmode="numeric">
        </div>

        <div class="field">
            <label class="lbl">WhatsApp <span class="req">*</span></label>
            <input type="text" name="whatsapp" id="whatsapp" value="<?= h($old['whatsapp']) ?>"
                   placeholder="(00) 00000-0000" maxlength="16" inputmode="numeric">
        </div>
    </div>

    <div class="card">
        <h2>Turmas <span style="color:#b00020;font-size:13px;">*</span></h2>
        <p style="font-size:13px;color:#666;margin-bottom:16px;">Selecione as turmas em que vai treinar. Pode marcar mais de uma.</p>

        <?php foreach ($turmas_por_dia as $dia => $lista): ?>
        <div class="turmas-grupo">
            <p class="dia-titulo"><?= h($dia) ?></p>
            <div class="turmas-checks">
                <?php foreach ($lista as $t): ?>
                    <?php $marcado = in_array((int)$t['id'], $old['turmas']); ?>
                    <label class="turma-item <?= $marcado ? 'marcado' : '' ?>">
                        <input type="checkbox" name="turmas[]" value="<?= $t['id'] ?>"
                               <?= $marcado ? 'checked' : '' ?>
                               onchange="toggleTurma(this)">
                        <span><?= h($t['horario'] . ' — ' . $t['descricao']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <button type="submit" class="btn-enviar">Enviar Cadastro</button>

</form>

<?php endif; ?>

<div class="footer" style="margin-top:20px;">Seus dados são utilizados apenas para fins de cadastro.</div>

</div><!-- /container -->

<script>
// Aviso responsável
var campResp  = document.getElementById('responsavel');
var avisoResp = document.getElementById('aviso-resp');
if (campResp) {
    function checkResp() {
        avisoResp.style.display = campResp.value.trim() !== '' ? 'block' : 'none';
    }
    campResp.addEventListener('input', checkResp);
    checkResp();
}

// Máscara CPF
var campCpf = document.getElementById('cpf');
if (campCpf) {
    campCpf.addEventListener('input', function() {
        var v = this.value.replace(/\D/g, '').substring(0, 11);
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        this.value = v;
    });
}

// Máscara WhatsApp
var campWh = document.getElementById('whatsapp');
if (campWh) {
    campWh.addEventListener('input', function() {
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
}

// Toggle visual turma
function toggleTurma(el) {
    var item = el.parentElement;
    if (el.checked) item.className = 'turma-item marcado';
    else item.className = 'turma-item';
}
</script>

</body>
</html>
