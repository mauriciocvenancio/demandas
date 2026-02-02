<?php
require_once __DIR__ . '/includes/auth.php';

$nome  = isset($_POST['nome']) ? trim($_POST['nome']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$tipo  = isset($_POST['tipo']) ? trim($_POST['tipo']) : 'suporte';
$senha = isset($_POST['senha']) ? (string)$_POST['senha'] : '';

$tiposValidos = array('analista','desenvolvedor','suporte');
if (!in_array($tipo, $tiposValidos, true)) $tipo = 'suporte';

if (strlen($senha) < 6) {
    redirect('/cadastrar.php?erro=senha');
}

$pdo = db();

// verifica email
$chk = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
$chk->execute(array($email));
if ($chk->fetch()) {
    redirect('/cadastrar.php?erro=email');
}

$hash = password_hash($senha, PASSWORD_BCRYPT);

$ins = $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, tipo, ativo, criado_em) VALUES (?, ?, ?, ?, 1, NOW())");
$ins->execute(array($nome, $email, $hash, $tipo));

redirect('/login.php?ok=cadastrado');
