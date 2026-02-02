<?php
require_once __DIR__ . '/includes/auth.php';

$erro = isset($_GET['erro']) ? $_GET['erro'] : '';
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Cadastrar Usuário</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#fff;}
        .wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
        .card{width:460px;max-width:92vw;border:1px solid #e6e6e6;border-radius:12px;
            box-shadow:0 6px 18px rgba(0,0,0,.06);padding:28px;background:#fff;}
        h1{margin:0 0 6px;text-align:center;font-size:22px;}
        .sub{margin:0 0 18px;text-align:center;color:#666;font-size:13px;}
        label{display:block;font-size:13px;margin:12px 0 6px;color:#111;font-weight:bold;}
        input,select{width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:14px;outline:none;}
        button{width:100%;margin-top:14px;padding:12px;border:0;border-radius:8px;background:#111;color:#fff;font-weight:bold;font-size:14px;cursor:pointer;}
        .msg{padding:10px 12px;border-radius:8px;margin:0 0 12px;font-size:13px;}
        .err{background:#ffecec;border:1px solid #ffb7b7;color:#7a1b1b;}
        .foot{margin-top:14px;text-align:center;font-size:13px;}
        .foot a{color:#111;text-decoration:none;font-weight:bold;}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Cadastrar Usuário</h1>
        <div class="sub">Crie sua conta para acessar o sistema</div>

        <?php if ($erro === 'email'): ?>
            <div class="msg err">Este e-mail já está cadastrado.</div>
        <?php elseif ($erro === 'senha'): ?>
            <div class="msg err">A senha deve ter pelo menos 6 caracteres.</div>
        <?php endif; ?>

        <form method="post" action="cadastrar_process.php" autocomplete="off">
            <label for="nome">Nome</label>
            <input type="text" name="nome" id="nome" placeholder="Seu nome" required>

            <label for="email">Email</label>
            <input type="email" name="email" id="email" placeholder="seu@email.com" required>

            <label for="tipo">Tipo</label>
            <select name="tipo" id="tipo" required>
                <option value="analista">Analista</option>
                <option value="desenvolvedor">Desenvolvedor</option>
                <option value="suporte" selected>Suporte</option>
            </select>

            <label for="senha">Senha</label>
            <input type="password" name="senha" id="senha" placeholder="Crie uma senha" required>

            <button type="submit">Cadastrar</button>
        </form>

        <div class="foot">
            Já tem conta? <a href="login.php">Voltar para login</a>
        </div>
    </div>
</div>
</body>
</html>
