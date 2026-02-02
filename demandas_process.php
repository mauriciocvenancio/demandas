<?php
/**
 * demandas_process.php (PHP 5.6)
 * Ações:
 *  - create: cria demanda + upload anexos
 *  - update: atualiza demanda + upload anexos adicionais
 *
 * Requisitos:
 *  - includes/auth.php: require_login(), auth_user(), db()
 *  - includes/helpers.php: h() (opcional), redirect() (se não tiver, tem fallback abaixo)
 *  - Pasta uploads/demandas/ com permissão de escrita
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

require_login();
$pdo = db();
$u   = auth_user();

if (session_status() === PHP_SESSION_NONE) session_start();

/* ===== fallback redirect (caso não exista no helpers.php) ===== */
if (!function_exists('redirect')) {
    function redirect($url){
        header('Location: ' . $url);
        exit;
    }
}

/* ===== CSRF ===== */
$csrf = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
if (!isset($_SESSION['csrf']) || $csrf === '' || $csrf !== $_SESSION['csrf']) {
    redirect('/demandas.php?msg=csrf');
}

/* ===== Helpers ===== */
function post($k){
    return isset($_POST[$k]) ? trim((string)$_POST[$k]) : '';
}

function slugClean($name){
    $name = (string)$name;
    $name = preg_replace('/[^a-zA-Z0-9\.\-_ ]/', '', $name);
    $name = preg_replace('/\s+/', '_', trim($name));
    if ($name === '') $name = 'arquivo';
    return $name;
}

function normalizeDate($d){
    $d = trim((string)$d);
    if ($d === '') return null;
    if (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $d)) return $d;
    return null;
}

function allowedOrDefault($value, $allowed, $default){
    return in_array($value, $allowed, true) ? $value : $default;
}

function ensureDir($path){
    if (!is_dir($path)) {
        @mkdir($path, 0777, true);
    }
}

/**
 * Faz upload de múltiplos arquivos e grava em demandas_arquivos
 */
function handleUploads($pdo, $id_demanda){
    if (!isset($_FILES['anexos']) || !isset($_FILES['anexos']['name']) || !is_array($_FILES['anexos']['name'])) {
        return;
    }

    $uploadBase = __DIR__ . '/uploads/demandas/' . (int)$id_demanda . '/';
    ensureDir($uploadBase);

    $names = $_FILES['anexos']['name'];
    $tmp   = $_FILES['anexos']['tmp_name'];
    $err   = $_FILES['anexos']['error'];
    $size  = $_FILES['anexos']['size'];
    $type  = $_FILES['anexos']['type'];

    $total = count($names);

    for ($i = 0; $i < $total; $i++) {
        if (!isset($err[$i]) || $err[$i] !== UPLOAD_ERR_OK) continue;
        if (!isset($tmp[$i]) || !is_uploaded_file($tmp[$i])) continue;

        $orig = (string)$names[$i];
        $origSafe = slugClean($orig);

        $ext = '';
        $p = strrpos($origSafe, '.');
        if ($p !== false) $ext = strtolower(substr($origSafe, $p));

        // nome físico
        $rand = bin2hex(openssl_random_pseudo_bytes(6));
        $fname = date('Ymd_His') . '_' . $rand . $ext;

        $dest = $uploadBase . $fname;

        if (@move_uploaded_file($tmp[$i], $dest)) {

            $mime = isset($type[$i]) ? (string)$type[$i] : null;
            $tam  = isset($size[$i]) ? (int)$size[$i] : null;

            $st = $pdo->prepare("
                INSERT INTO demandas_arquivos
                (id_demanda, nome_original, arquivo, mime, tamanho, criado_em)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $st->execute(array(
                (int)$id_demanda,
                $orig,
                $fname,
                $mime,
                $tam
            ));
        }
    }
}

/* ===== parâmetros ===== */
$action = post('action');

$allowedStatus = array('nao_iniciado','em_andamento','aguardando_cliente','finalizado','publicado');
$allowedCrit   = array('baixa','media','alta','urgente');

if ($action === 'create') {

    $titulo      = post('titulo');
    $descricao   = post('descricao');
    $id_cliente  = (int)post('id_cliente');

    // responsável
    $resp_mode   = post('resp_mode'); // "me" ou "user"
    $id_resp     = (int)post('id_responsavel');
    if ($resp_mode === 'me' || $id_resp <= 0) {
        $id_resp = (int)$u['id'];
    }

    $status      = allowedOrDefault(post('status'), $allowedStatus, 'nao_iniciado');
    $criticidade = allowedOrDefault(post('criticidade'), $allowedCrit, 'media');
    $prazoDb     = normalizeDate(post('prazo'));

    if ($titulo === '') redirect('/demandas.php?msg=titulo_obrigatorio');
    if ($id_cliente <= 0) redirect('/demandas.php?msg=cliente_obrigatorio');

    $stmt = $pdo->prepare("
        INSERT INTO demandas
        (titulo, descricao, id_cliente, id_responsavel, status, criticidade, prazo, ativo, criado_em)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
    ");
    $stmt->execute(array(
        $titulo,
        ($descricao !== '' ? $descricao : null),
        $id_cliente,
        $id_resp,
        $status,
        $criticidade,
        $prazoDb
    ));

    $id_demanda = (int)$pdo->lastInsertId();

    // anexos
    handleUploads($pdo, $id_demanda);

    redirect('/demandas.php?msg=criada');

} elseif ($action === 'update') {

    $id          = (int)post('id');
    $titulo      = post('titulo');
    $descricao   = post('descricao');
    $id_cliente  = (int)post('id_cliente');
    $id_resp     = (int)post('id_responsavel');

    $status      = allowedOrDefault(post('status'), $allowedStatus, 'nao_iniciado');
    $criticidade = allowedOrDefault(post('criticidade'), $allowedCrit, 'media');
    $prazoDb     = normalizeDate(post('prazo'));

    if ($id <= 0) redirect('/demandas.php?msg=id_invalido');
    if ($titulo === '') redirect('/demanda_edit.php?id='.$id.'&msg=titulo_obrigatorio');
    if ($id_cliente <= 0) redirect('/demanda_edit.php?id='.$id.'&msg=cliente_obrigatorio');
    if ($id_resp <= 0) $id_resp = (int)$u['id'];

    // confere se existe e está ativo
    $chk = $pdo->prepare("SELECT id FROM demandas WHERE id=? AND ativo=1 LIMIT 1");
    $chk->execute(array($id));
    if (!$chk->fetch()) {
        redirect('/demandas.php?msg=nao_encontrada');
    }

    $st = $pdo->prepare("
        UPDATE demandas
        SET titulo=?, descricao=?, id_cliente=?, id_responsavel=?, status=?, criticidade=?, prazo=?, atualizado_em=NOW()
        WHERE id=?
        LIMIT 1
    ");
    $st->execute(array(
        $titulo,
        ($descricao !== '' ? $descricao : null),
        $id_cliente,
        $id_resp,
        $status,
        $criticidade,
        $prazoDb,
        $id
    ));

    // anexos adicionais
    handleUploads($pdo, $id);

    redirect('/demanda_view.php?id=' . $id);

} else {
    redirect('/demandas.php?msg=acao_invalida');
}
