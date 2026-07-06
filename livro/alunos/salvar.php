<?php
require_once 'config.php';
require_once '../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cadastrar.php');
    exit;
}

$nome        = trim(isset($_POST['nome'])        ? $_POST['nome']        : '');
$responsavel = trim(isset($_POST['responsavel']) ? $_POST['responsavel'] : '') ?: null;
$cpf         = trim(isset($_POST['cpf'])         ? $_POST['cpf']         : '') ?: null;
$whatsapp    = trim(isset($_POST['whatsapp'])     ? $_POST['whatsapp']    : '') ?: null;
$turmas_sel  = isset($_POST['turmas']) ? array_map('intval', $_POST['turmas']) : array();

if ($nome === '') {
    header('Location: cadastrar.php');
    exit;
}

$stmt = $pdo->prepare("INSERT INTO alunos (nome, responsavel, cpf, whatsapp) VALUES (:nome, :resp, :cpf, :wh)");
$stmt->execute([
    ':nome' => $nome,
    ':resp' => $responsavel,
    ':cpf'  => $cpf,
    ':wh'   => $whatsapp,
]);
$aluno_id = (int)$pdo->lastInsertId();

if ($aluno_id && $turmas_sel) {
    $ins = $pdo->prepare("INSERT IGNORE INTO aluno_turmas (aluno_id, turma_id) VALUES (:aid, :tid)");
    foreach ($turmas_sel as $tid) {
        if ($tid > 0) {
            $ins->execute([':aid' => $aluno_id, ':tid' => $tid]);
        }
    }
}

header('Location: index.php?msg=' . urlencode('Aluno "' . $nome . '" cadastrado com sucesso!'));
exit;
