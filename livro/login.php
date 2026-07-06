<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// Setup: garante tabela e admin existem
$pdo->exec("CREATE TABLE IF NOT EXISTS livro_usuarios (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    usuario   VARCHAR(100) NOT NULL UNIQUE,
    senha     VARCHAR(255) NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

$chk = $pdo->prepare("SELECT id FROM livro_usuarios WHERE usuario = 'admin'");
$chk->execute();
if (!$chk->fetch()) {
    $hash = password_hash('ace2026', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO livro_usuarios (usuario, senha) VALUES ('admin', ?)")->execute([$hash]);
}

// Já autenticado → redireciona para o sistema
if (!empty($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim(isset($_POST['usuario']) ? $_POST['usuario'] : '');
    $senha   = isset($_POST['senha']) ? $_POST['senha'] : '';

    if ($usuario !== '' && $senha !== '') {
        $stmt = $pdo->prepare("SELECT id, usuario, senha FROM livro_usuarios WHERE usuario = ?");
        $stmt->execute([$usuario]);
        $row = $stmt->fetch();

        if ($row && password_verify($senha, $row['senha'])) {
            session_regenerate_id(true);
            $_SESSION['usuario_id']   = $row['id'];
            $_SESSION['usuario_nome'] = $row['usuario'];
            header('Location: index.php');
            exit;
        }
    }
    $erro = 'Usuário ou senha inválidos.';
}
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login – Sistema</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:Arial,sans-serif;background:linear-gradient(135deg,#1a3a6b 0%,#1a6bbf 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;}
.login-box{background:#fff;border-radius:16px;padding:40px 36px;width:100%;max-width:380px;box-shadow:0 8px 32px rgba(0,0,0,.22);}
.login-logo{text-align:center;margin-bottom:28px;}
.login-logo h1{font-size:22px;color:#1a3a6b;letter-spacing:.5px;}
.login-logo p{font-size:13px;color:#888;margin-top:4px;}
.field{margin-bottom:18px;}
label{display:block;font-size:13px;font-weight:bold;color:#444;margin-bottom:6px;}
input[type=text],input[type=password]{width:100%;padding:11px 14px;border:1px solid #ccc;border-radius:8px;font-size:15px;transition:border-color .2s,box-shadow .2s;}
input[type=text]:focus,input[type=password]:focus{outline:none;border-color:#1a6bbf;box-shadow:0 0 0 3px rgba(26,107,191,.15);}
.btn-login{width:100%;padding:12px;background:#1a6bbf;color:#fff;border:0;border-radius:8px;font-size:16px;font-weight:bold;cursor:pointer;transition:background .2s;}
.btn-login:hover{background:#1558a0;}
.btn-login:active{background:#0f3f7a;}
.alert-err{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;padding:11px 14px;border-radius:8px;margin-bottom:18px;font-size:14px;text-align:center;}
.footer-txt{text-align:center;margin-top:20px;font-size:12px;color:#aaa;}
</style>
</head>
<body>

<div class="login-box">
    <div class="login-logo">
        <h1>Sistema de Gestão</h1>
        <p>Livro Caixa &amp; Alunos</p>
    </div>

    <?php if ($erro): ?>
        <div class="alert-err">⚠ <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <div class="field">
            <label for="usuario">Usuário</label>
            <input type="text" id="usuario" name="usuario"
                   value="<?= htmlspecialchars(isset($_POST['usuario']) ? $_POST['usuario'] : '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Digite seu usuário" autofocus required>
        </div>

        <div class="field">
            <label for="senha">Senha</label>
            <input type="password" id="senha" name="senha" placeholder="Digite sua senha" required>
        </div>

        <button type="submit" class="btn-login">Entrar</button>
    </form>

    <p class="footer-txt">Acesso restrito a usuários autorizados.</p>
</div>

</body>
</html>
