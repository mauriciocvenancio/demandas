<?php
require_once 'config.php';
require_once 'auth.php';

$id = isset($_POST['id_livro_caixa']) ? (int)$_POST['id_livro_caixa'] : 0;
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

if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
    header("Location: " . buildRedirect(array(
        'data_ini'      => $data_ini,
        'data_fim'      => $data_fim,
        'tipo'          => $tipo_filtro,
        'saldo_inicial' => $saldo_ini,
    )));
    exit;
}

$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}
if (!is_writable($uploadDir)) {
    die('Pasta uploads sem permissão de escrita.');
}

$nomeOriginal = $_FILES['arquivo']['name'];
$tmp = $_FILES['arquivo']['tmp_name'];
$tamanho = (int)$_FILES['arquivo']['size'];

if ($tamanho <= 0) die('Arquivo inválido.');
if ($tamanho > 10 * 1024 * 1024) die('Arquivo maior que 10MB.'); // limite 10MB

// extensões permitidas
$ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
$permitidas = array('pdf','jpg','jpeg','png','doc','docx','xls','xlsx');
if (!in_array($ext, $permitidas)) {
    die('Extensão não permitida.');
}

// mime (best-effort)
$mime = '';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mime = finfo_file($finfo, $tmp);
        finfo_close($finfo);
    }
}

// nome novo (evita conflito)
$novoNome = 'lc_'.$id.'_'.date('Ymd_His').'_' . substr(sha1(uniqid('', true)), 0, 12) . '.' . $ext;
$dest = $uploadDir . $novoNome;

if (!move_uploaded_file($tmp, $dest)) {
    die('Falha ao salvar arquivo.');
}

// grava no banco
$stmt = $pdo->prepare("
    INSERT INTO livro_caixa_arquivos
    (id_livro_caixa, nome_original, nome_arquivo, mime, tamanho)
    VALUES (:id, :no, :na, :mime, :tam)
");
$stmt->execute(array(
    ':id' => $id,
    ':no' => $nomeOriginal,
    ':na' => $novoNome,
    ':mime' => ($mime ? $mime : null),
    ':tam' => ($tamanho ? $tamanho : null)
));

header("Location: " . buildRedirect(array(
    'data_ini'      => $data_ini,
    'data_fim'      => $data_fim,
    'tipo'          => $tipo_filtro,
    'saldo_inicial' => $saldo_ini,
    'msg'           => 'Anexo enviado com sucesso.',
)));
exit;