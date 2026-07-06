<?php
require_once 'config.php';
require_once 'auth.php';

$nome = isset($_GET['f']) ? basename($_GET['f']) : '';

// Aceita apenas arquivos com prefixo extrato_ e extensão .pdf
if (!$nome || !preg_match('/^extrato_[\w]+\.pdf$/i', $nome)) {
    die('Arquivo inválido.');
}

$caminho = __DIR__ . '/uploads/' . $nome;

if (!is_file($caminho)) {
    die('Arquivo não encontrado.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $nome . '"');
header('Content-Length: ' . filesize($caminho));
header('Cache-Control: private, max-age=3600');

readfile($caminho);
exit;
