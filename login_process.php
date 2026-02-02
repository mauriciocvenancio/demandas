<?php
require_once __DIR__ . '/includes/auth.php';

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$senha = isset($_POST['senha']) ? (string)$_POST['senha'] : '';

if ($email === '' || $senha === '') {
    redirect('/login.php?erro=credenciais');
}

$pdo = db();
$stmt = $pdo->prepare("SELECT id, nome, email, senha_hash, tipo, ativo FROM usuarios WHERE email = ? LIMIT 1");
$stmt->execute(array($email));
$user = $stmt->fetch();

if (!$user) {
    redirect('/login.php?erro=credenciais');
}

if ((int)$user['ativo'] !== 1) {
    redirect('/login.php?erro=inativo');
}

if (!password_verify($senha, $user['senha_hash'])) {
    redirect('/login.php?erro=credenciais');
}

// atualiza ultimo login
$upd = $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
$upd->execute(array($user['id']));

login_user($user);
redirect('/dashboard.php');
