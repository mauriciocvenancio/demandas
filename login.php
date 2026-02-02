<?php
require_once __DIR__ . '/includes/auth.php';

if (auth_user()) {
    redirect('/dashboard.php');
}

$erro = isset($_GET['erro']) ? $_GET['erro'] : '';
$ok   = isset($_GET['ok']) ? $_GET['ok'] : '';
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Sistema de Demandas - Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#fff;}
        .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
        .card{width:420px;max-width:92vw;border:1px solid #e6e6e6;border-radius:12px;
            box-shadow:0 6px 18px rgba(0,0,0,.06);padding:28px;background:#fff;}
        .icon{width:56px;height:56px;border-radius:14px;background:#111;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;}
        .icon span{color:#fff;font-size:22px;font-weight:bold}
        h1{margin:0;text-align:center;font-size:26px;}
        .sub{margin:6px 0 22px;text-align:center;color:#666;font-size:14px;}
        label{display:block;font-size:13px;margin:12px 0 6px;color:#111;font-weight:bold;}
        input{width:100%;padding:12px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px;outline:none;}
        input:focus{border-color:#bbb;}
        button{width:100%;margin-top:14px;padding:12px;border:0;border-radius:8px;background:#111;color:#fff;font-weight:bold;font-size:14px;cursor:pointer;}
        button:hover{opacity:.92;}
        .msg{padding:10px 12px;border-radius:8px;margin:0 0 12px;font-size:13px;}
        .err{background:#ffecec;border:1px solid #ffb7b7;color:#7a1b1b;}
        .ok{background:#ecfff2;border:1px solid #a9f0bf;color:#1b7a3a;}
        .foot{margin-top:16px;text-align:center;color:#666;font-size:13px;}
        .foot a{color:#111;text-decoration:none;font-weight:bold;}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="icon"><span>🧾</span></div>
        <h1>Sistema de Demandas</h1>
        <div class="sub">Entre com suas credenciais para acessar</div>

        <?php if ($erro === 'credenciais'): ?>
            <div class="msg err">E-mail ou senha inválidos.</div>
        <?php elseif ($erro === 'inativo'): ?>
            <div class="msg err">Usuário inativo. Contate o administrador.</div>
        <?php endif; ?>

        <?php if ($ok === 'cadastrado'): ?>
            <div class="msg ok">Usuário cadastrado com sucesso! Faça login.</div>
        <?php endif; ?>

        <form method="post" action="login_process.php" autocomplete="off">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" placeholder="seu@email.com" required>

            <label for="senha">Senha</label>
            <input type="password" name="senha" id="senha" placeholder="Sua senha" required>

            <button type="submit">Entrar</button>
        </form>

        <div class="foot">
            Não tem uma conta? <a href="cadastrar.php">Cadastre-se</a>
        </div>
    </div>
</div>
</body>
</html>
