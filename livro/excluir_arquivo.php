<?php
require_once 'config.php';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) die('ID inválido.');

$stmt = $pdo->prepare("SELECT * FROM livro_caixa_arquivos WHERE id = :id");
$stmt->execute(array(':id' => $id));
$arq = $stmt->fetch();
if (!$arq) {
    header("Location: index.php");
    exit;
}

$uploadDir = __DIR__ . '/uploads/';
$caminho = $uploadDir . $arq['nome_arquivo'];

// deleta do banco
$stmtDel = $pdo->prepare("DELETE FROM livro_caixa_arquivos WHERE id = :id");
$stmtDel->execute(array(':id' => $id));

// deleta do disco
if (is_file($caminho)) {
    @unlink($caminho);
}

header("Location: index.php");
exit;