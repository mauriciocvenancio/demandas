<?php
require_once 'config.php';
require_once 'auth.php';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) die('ID inválido.');

$data_ini     = isset($_POST['data_ini'])     ? $_POST['data_ini']     : '';
$data_fim     = isset($_POST['data_fim'])     ? $_POST['data_fim']     : '';
$tipo_filtro  = isset($_POST['tipo_filtro'])  ? $_POST['tipo_filtro']  : '';
$saldo_ini    = isset($_POST['saldo_inicial'])? $_POST['saldo_inicial']: '';

function buildRedirect($params = array()) {
    $base = 'index.php';
    $qs = array();
    foreach ($params as $k => $v) {
        if ($v !== '') $qs[] = urlencode($k) . '=' . urlencode($v);
    }
    return $qs ? $base . '?' . implode('&', $qs) : $base;
}

$stmt = $pdo->prepare("SELECT * FROM livro_caixa_arquivos WHERE id = :id");
$stmt->execute(array(':id' => $id));
$arq = $stmt->fetch();
if (!$arq) {
    header("Location: " . buildRedirect(array(
        'data_ini'      => $data_ini,
        'data_fim'      => $data_fim,
        'tipo'          => $tipo_filtro,
        'saldo_inicial' => $saldo_ini,
    )));
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

header("Location: " . buildRedirect(array(
    'data_ini'      => $data_ini,
    'data_fim'      => $data_fim,
    'tipo'          => $tipo_filtro,
    'saldo_inicial' => $saldo_ini,
)));
exit;