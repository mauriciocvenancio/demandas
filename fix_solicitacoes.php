<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$u = auth_user();
if ($u['tipo'] !== 'desenvolvedor') die('Acesso negado.');

$pdo = db();

echo '<pre style="font-family:monospace;background:#1e1e1e;color:#d4d4d4;padding:20px;font-size:13px;">';
echo "=== Diagnóstico: tabela solicitacoes ===\n\n";

// Verifica colunas existentes
$stmt = $pdo->query("SHOW COLUMNS FROM solicitacoes");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Colunas existentes:\n  " . implode(", ", $cols) . "\n\n";

$needed = array(
    'descricao'          => "TEXT NULL",
    'email_solicitante'  => "VARCHAR(255) NULL",
    'telefone_solicitante' => "VARCHAR(50) NULL",
);

$missing = array();
foreach ($needed as $col => $def) {
    if (!in_array($col, $cols)) {
        $missing[] = $col;
    }
}

if (empty($missing)) {
    echo "✅ Todas as colunas necessárias existem. O erro pode ser outro.\n\n";
} else {
    echo "❌ Colunas faltando: " . implode(", ", $missing) . "\n\n";
    echo "Adicionando colunas...\n";
    foreach ($missing as $col) {
        try {
            $pdo->exec("ALTER TABLE solicitacoes ADD COLUMN `$col` {$needed[$col]}");
            echo "  + Coluna '$col' adicionada com sucesso.\n";
        } catch (Exception $e) {
            echo "  ✗ Erro ao adicionar '$col': " . $e->getMessage() . "\n";
        }
    }
    echo "\n✅ Migração concluída. Você pode apagar este arquivo agora.\n";
}

// Verifica também solicitacoes_historico
echo "\n=== Verificando solicitacoes_historico ===\n";
try {
    $stmt2 = $pdo->query("SHOW COLUMNS FROM solicitacoes_historico");
    $cols2 = $stmt2->fetchAll(PDO::FETCH_COLUMN);
    echo "Colunas: " . implode(", ", $cols2) . "\n";
} catch (Exception $e) {
    echo "❌ Tabela solicitacoes_historico não encontrada: " . $e->getMessage() . "\n";
    echo "Criando tabela...\n";
    try {
        $pdo->exec("CREATE TABLE solicitacoes_historico (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            id_solicitacao  INT UNSIGNED NOT NULL,
            status_anterior VARCHAR(50) NULL,
            status_novo     VARCHAR(50) NOT NULL,
            observacao      TEXT NULL,
            nome_usuario    VARCHAR(100) NULL,
            criado_em       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sol (id_solicitacao)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "  + Tabela criada com sucesso.\n";
    } catch (Exception $e2) {
        echo "  ✗ Erro: " . $e2->getMessage() . "\n";
    }
}

echo "\n=== Fim do diagnóstico ===\n";
echo '</pre>';
echo '<p style="font-family:sans-serif;margin:20px;"><strong>Após corrigir, apague este arquivo:</strong> fix_solicitacoes.php</p>';
