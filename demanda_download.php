<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo = db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(404); exit; }

$stmt = $pdo->prepare("
    SELECT a.*, d.id AS demanda_id
    FROM demandas_arquivos a
    JOIN demandas d ON d.id = a.id_demanda
    WHERE a.id = ? AND d.ativo = 1
    LIMIT 1
");
$stmt->execute(array($id));
$arq = $stmt->fetch();

if (!$arq) { http_response_code(404); exit; }

$path = __DIR__ . '/uploads/demandas/' . (int)$arq['id_demanda'] . '/' . $arq['arquivo'];
if (!file_exists($path)) { http_response_code(404); exit; }

$mime = $arq['mime'] ? $arq['mime'] : 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
