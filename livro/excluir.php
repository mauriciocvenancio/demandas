<?php
require_once 'config.php';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) die('ID inválido.');

$stmt = $pdo->prepare("DELETE FROM livro_caixa WHERE id = :id");
$stmt->execute(array(':id' => $id));

header("Location: index.php");
exit;