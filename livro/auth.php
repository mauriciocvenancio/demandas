<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Garante que $pdo está disponível
if (!isset($pdo)) {
    require_once __DIR__ . '/config.php';
}

// Setup automático: cria tabela e usuário admin na primeira execução
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

// Verifica se está autenticado; redireciona para login caso contrário
if (empty($_SESSION['usuario_id'])) {
    $docRoot  = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    $appPath  = str_replace($docRoot, '', str_replace('\\', '/', __DIR__));
    header('Location: ' . $appPath . '/login.php');
    exit;
}
