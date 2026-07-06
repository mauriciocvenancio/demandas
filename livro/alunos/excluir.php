<?php
require_once 'config.php';
require_once '../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
if ($id <= 0) {
    header('Location: index.php?erro=' . urlencode('ID inválido.'));
    exit;
}

$chk = $pdo->prepare("SELECT nome FROM alunos WHERE id = ?");
$chk->execute([$id]);
$aluno = $chk->fetch();

if (!$aluno) {
    header('Location: index.php?erro=' . urlencode('Aluno não encontrado.'));
    exit;
}

// aluno_turmas é excluído via ON DELETE CASCADE
$pdo->prepare("DELETE FROM alunos WHERE id = ?")->execute([$id]);

header('Location: index.php?msg=' . urlencode('Aluno "' . $aluno['nome'] . '" excluído.'));
exit;
