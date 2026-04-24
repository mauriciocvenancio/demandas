<?php
require_once 'config.php';

$data = isset($_POST['data_lancamento']) ? $_POST['data_lancamento'] : '';
$tipo = isset($_POST['tipo']) ? $_POST['tipo'] : '';
$categoria = isset($_POST['categoria']) ? trim($_POST['categoria']) : '';
$descricao = isset($_POST['descricao']) ? trim($_POST['descricao']) : '';
$valor = isset($_POST['valor']) ? $_POST['valor'] : '';

if (!$data || ($tipo !== 'E' && $tipo !== 'S') || !$descricao || $valor === '') {
    die('Dados inválidos.');
}

$valor = str_replace(',', '.', $valor);
if (!is_numeric($valor) || $valor < 0) die('Valor inválido.');

$stmt = $pdo->prepare("
    INSERT INTO livro_caixa (data_lancamento, tipo, categoria, descricao, valor)
    VALUES (:data, :tipo, :categoria, :descricao, :valor)
");
$stmt->execute(array(
    ':data' => $data,
    ':tipo' => $tipo,
    ':categoria' => ($categoria !== '' ? $categoria : null),
    ':descricao' => $descricao,
    ':valor' => $valor
));

header("Location: index.php");
exit;