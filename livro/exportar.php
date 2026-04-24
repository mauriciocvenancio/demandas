<?php
require_once 'config.php';

$data_ini = isset($_GET['data_ini']) && $_GET['data_ini'] ? $_GET['data_ini'] : date('Y-m-01');
$data_fim = isset($_GET['data_fim']) && $_GET['data_fim'] ? $_GET['data_fim'] : date('Y-m-d');
$tipo_filtro = isset($_GET['tipo']) ? $_GET['tipo'] : '';

$saldo_inicial = isset($_GET['saldo_inicial']) && $_GET['saldo_inicial'] !== ''
    ? (float) str_replace(',', '.', $_GET['saldo_inicial'])
    : 0.00;

// saldo anterior ao período
$stmtPrev = $pdo->prepare("
    SELECT
      COALESCE(SUM(CASE WHEN tipo='E' THEN valor ELSE 0 END),0) as ent,
      COALESCE(SUM(CASE WHEN tipo='S' THEN valor ELSE 0 END),0) as sai
    FROM livro_caixa
    WHERE data_lancamento < :ini
");
$stmtPrev->execute(array(':ini' => $data_ini));
$prev = $stmtPrev->fetch();

$saldo_anterior = (float)$prev['ent'] - (float)$prev['sai'];
$saldo_base = $saldo_inicial + $saldo_anterior;

// lançamentos ASC
$sql = "SELECT * FROM livro_caixa WHERE data_lancamento BETWEEN :ini AND :fim";
$params = array(':ini' => $data_ini, ':fim' => $data_fim);

if ($tipo_filtro === 'E' || $tipo_filtro === 'S') {
    $sql .= " AND tipo = :tipo";
    $params[':tipo'] = $tipo_filtro;
}

$sql .= " ORDER BY data_lancamento ASC, id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lanc = $stmt->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=livro_caixa.csv');

$out = fopen('php://output', 'w');

// cabeçalho
fputcsv($out, array('Data','Tipo','Categoria','Descricao','Valor','Saldo_apos'));

// saldo após
$saldo_corrente = $saldo_base;
foreach ($lanc as $l) {
    $valor = (float)$l['valor'];

    if ($l['tipo'] === 'E') $saldo_corrente += $valor;
    else $saldo_corrente -= $valor;

    fputcsv($out, array(
        $l['data_lancamento'],
        ($l['tipo'] === 'E' ? 'Entrada' : 'Saida'),
        $l['categoria'],
        $l['descricao'],
        number_format($valor, 2, '.', ''),
        number_format($saldo_corrente, 2, '.', '')
    ));
}

fclose($out);
exit;