<?php
require_once 'config.php';
require_once 'auth.php';

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: importar_extrato.php');
    exit;
}

$acao = isset($_POST['acao']) ? $_POST['acao'] : '';

// ─────────────────────────────────────────────────────────────────────────────
// FLUXO 2: Confirmação final — insere lançamentos vindos do preview (sem PDF)
// ─────────────────────────────────────────────────────────────────────────────
if ($acao === 'importar' && isset($_POST['confirmar']) && $_POST['confirmar'] === '1') {

    $transacoes = (isset($_POST['t']) && is_array($_POST['t'])) ? $_POST['t'] : array();

    if (empty($transacoes)) {
        header('Location: importar_extrato.php?erro=' . urlencode('Nenhum dado para importar.'));
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO livro_caixa (data_lancamento, tipo, categoria, descricao, valor)
        VALUES (:data, :tipo, :categoria, :descricao, :valor)
    ");

    $count = 0;
    foreach ($transacoes as $t) {
        $tData     = isset($t['data'])      ? $t['data']      : '';
        $tTipo     = isset($t['tipo'])      ? $t['tipo']      : '';
        $categoria = isset($t['categoria']) ? $t['categoria'] : '';
        $descricao = isset($t['descricao']) ? $t['descricao'] : '';
        $valor     = isset($t['valor'])     ? abs((float) $t['valor']) : null;

        $data = preg_match('/^\d{4}-\d{2}-\d{2}$/', $tData) ? $tData : null;
        $tipo = in_array($tTipo, array('E', 'S'))            ? $tTipo : null;

        if (!$data || !$tipo || $valor === null) continue;

        $stmt->execute(array(
            ':data'      => $data,
            ':tipo'      => $tipo,
            ':categoria' => $categoria,
            ':descricao' => $descricao,
            ':valor'     => $valor,
        ));
        $count++;
    }

    header('Location: index.php?msg=' . urlencode($count . ' lancamento(s) importado(s) com sucesso.'));
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// FLUXO 1: Upload do PDF para salvar em disco
// ─────────────────────────────────────────────────────────────────────────────
if ($acao !== 'salvar') {
    header('Location: importar_extrato.php?erro=' . urlencode('Acao invalida.'));
    exit;
}

if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    header('Location: importar_extrato.php?erro=' . urlencode('Erro ao receber o arquivo. Tente novamente.'));
    exit;
}

$arquivo = $_FILES['pdf'];
$ext     = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));

if ($ext !== 'pdf') {
    header('Location: importar_extrato.php?erro=' . urlencode('Apenas arquivos PDF sao aceitos.'));
    exit;
}

if ($arquivo['size'] > 20 * 1024 * 1024) {
    header('Location: importar_extrato.php?erro=' . urlencode('Arquivo muito grande. Limite: 20 MB.'));
    exit;
}

$uploadsDir = __DIR__ . '/uploads/';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

$nomeArquivo = 'extrato_' . date('Ymd_His') . '_' . substr(sha1_file($arquivo['tmp_name']), 0, 10) . '.pdf';
$destino     = $uploadsDir . $nomeArquivo;

if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
    header('Location: importar_extrato.php?erro=' . urlencode('Falha ao salvar o arquivo no servidor.'));
    exit;
}

header('Location: importar_extrato.php?msg=' . urlencode('PDF salvo com sucesso: uploads/' . $nomeArquivo));
exit;
