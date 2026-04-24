<?php
require_once 'config.php';

$id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$data = isset($_POST['data_lancamento']) ? $_POST['data_lancamento'] : '';
$tipo = isset($_POST['tipo']) ? $_POST['tipo'] : '';
$categoria = isset($_POST['categoria']) ? trim($_POST['categoria']) : '';
$descricao = isset($_POST['descricao']) ? trim($_POST['descricao']) : '';
$valor = isset($_POST['valor']) ? $_POST['valor'] : '';

if ($id <= 0 || !$data || ($tipo !== 'E' && $tipo !== 'S') || !$descricao || $valor === '') {
    die('Dados inválidos.');
}

$valor = str_replace(',', '.', $valor);
if (!is_numeric($valor) || $valor < 0) die('Valor inválido.');

$stmt = $pdo->prepare("
  UPDATE livro_caixa
  SET data_lancamento = :data,
      tipo = :tipo,
      categoria = :categoria,
      descricao = :descricao,
      valor = :valor
  WHERE id = :id
");
$stmt->execute(array(
    ':data' => $data,
    ':tipo' => $tipo,
    ':categoria' => ($categoria !== '' ? $categoria : null),
    ':descricao' => $descricao,
    ':valor' => $valor,
    ':id' => $id
));

header("Location: index.php");
exit;