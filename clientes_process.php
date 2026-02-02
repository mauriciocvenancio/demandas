<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo = db();

$action = isset($_POST['action']) ? trim($_POST['action']) : '';

function post($k){
    return isset($_POST[$k]) ? trim((string)$_POST[$k]) : '';
}

// CSRF simples
if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = isset($_POST['csrf']) ? $_POST['csrf'] : '';
if (!isset($_SESSION['csrf']) || $csrf !== $_SESSION['csrf']) {
    redirect('/clientes.php?msg=csrf');
}

if ($action === 'create') {

    $nome        = post('nome');
    $email       = post('email');
    $telefone    = post('telefone');
    $empresa     = post('empresa');
    $observacoes = post('observacoes');

    if ($nome === '') {
        redirect('/clientes.php?msg=nome_obrigatorio');
    }

    $stmt = $pdo->prepare("INSERT INTO clientes (nome, email, telefone, empresa, observacoes, ativo, criado_em)
                           VALUES (?, ?, ?, ?, ?, 1, NOW())");
    $stmt->execute(array(
        $nome,
        $email !== '' ? $email : null,
        $telefone !== '' ? $telefone : null,
        $empresa !== '' ? $empresa : null,
        $observacoes !== '' ? $observacoes : null
    ));

    redirect('/clientes.php?msg=criado');

} elseif ($action === 'update') {

    $id          = (int)post('id');
    $nome        = post('nome');
    $email       = post('email');
    $telefone    = post('telefone');
    $empresa     = post('empresa');
    $observacoes = post('observacoes');

    if ($id <= 0) redirect('/clientes.php?msg=id_invalido');
    if ($nome === '') redirect('/clientes.php?msg=nome_obrigatorio');

    $stmt = $pdo->prepare("UPDATE clientes
                           SET nome=?, email=?, telefone=?, empresa=?, observacoes=?, atualizado_em=NOW()
                           WHERE id=?");
    $stmt->execute(array(
        $nome,
        $email !== '' ? $email : null,
        $telefone !== '' ? $telefone : null,
        $empresa !== '' ? $empresa : null,
        $observacoes !== '' ? $observacoes : null,
        $id
    ));

    redirect('/clientes.php?msg=atualizado');

} elseif ($action === 'delete') {

    $id = (int)post('id');
    if ($id <= 0) redirect('/clientes.php?msg=id_invalido');

    // delete lógico
    $stmt = $pdo->prepare("UPDATE clientes SET ativo=0, atualizado_em=NOW() WHERE id=?");
    $stmt->execute(array($id));

    redirect('/clientes.php?msg=removido');

} else {
    redirect('/clientes.php?msg=acao_invalida');
}
