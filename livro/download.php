<?php
require_once 'config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) die('ID inválido.');

$stmt = $pdo->prepare("SELECT * FROM livro_caixa_arquivos WHERE id = :id");
$stmt->execute(array(':id' => $id));
$arq = $stmt->fetch();
if (!$arq) die('Arquivo não encontrado.');

$uploadDir = __DIR__ . '/uploads/';
$caminho = $uploadDir . $arq['nome_arquivo'];

if (!is_file($caminho)) die('Arquivo não existe no servidor.');

$nome = $arq['nome_original'];
$mime = $arq['mime'] ? $arq['mime'] : 'application/octet-stream';

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($nome) . '"');
header('Content-Length: ' . filesize($caminho));
header('Pragma: public');

readfile($caminho);
exit;