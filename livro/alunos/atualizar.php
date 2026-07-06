<?php
require_once 'config.php';
require_once '../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id          = (int)(isset($_POST['id'])          ? $_POST['id']          : 0);
$nome        = trim(isset($_POST['nome'])        ? $_POST['nome']        : '');
$responsavel = trim(isset($_POST['responsavel']) ? $_POST['responsavel'] : '') ?: null;
$cpf         = trim(isset($_POST['cpf'])         ? $_POST['cpf']         : '') ?: null;
$whatsapp    = trim(isset($_POST['whatsapp'])     ? $_POST['whatsapp']    : '') ?: null;
$turmas_sel  = isset($_POST['turmas']) ? array_map('intval', $_POST['turmas']) : array();

if ($id <= 0 || $nome === '') {
    header('Location: index.php?erro=' . urlencode('Dados inválidos.'));
    exit;
}

// Verificar se aluno existe
$chk = $pdo->prepare("SELECT id FROM alunos WHERE id = ?");
$chk->execute([$id]);
if (!$chk->fetch()) {
    header('Location: index.php?erro=' . urlencode('Aluno não encontrado.'));
    exit;
}

$upd = $pdo->prepare("UPDATE alunos SET nome=:nome, responsavel=:resp, cpf=:cpf, whatsapp=:wh WHERE id=:id");
$upd->execute([
    ':nome' => $nome,
    ':resp' => $responsavel,
    ':cpf'  => $cpf,
    ':wh'   => $whatsapp,
    ':id'   => $id,
]);

// Reescrever turmas
$pdo->prepare("DELETE FROM aluno_turmas WHERE aluno_id = ?")->execute([$id]);
if ($turmas_sel) {
    $ins = $pdo->prepare("INSERT IGNORE INTO aluno_turmas (aluno_id, turma_id) VALUES (:aid, :tid)");
    foreach ($turmas_sel as $tid) {
        if ($tid > 0) {
            $ins->execute([':aid' => $id, ':tid' => $tid]);
        }
    }
}

header('Location: index.php?msg=' . urlencode('Aluno "' . $nome . '" atualizado com sucesso!'));
exit;
