<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo = db();
$u   = auth_user();

header('Content-Type: application/json');

set_exception_handler(function($e) {
    echo json_encode(['ok' => false, 'msg' => 'Erro interno: ' . $e->getMessage()]); exit;
});

function jsonOk($data = []) { echo json_encode(array_merge(['ok' => true], $data)); exit; }
function jsonErr($msg)       { echo json_encode(['ok' => false, 'msg' => $msg]); exit; }
function post($k)            { return isset($_POST[$k]) ? trim((string)$_POST[$k]) : ''; }

// CSRF
if (!isset($_SESSION['csrf']) || post('csrf') === '' || post('csrf') !== $_SESSION['csrf']) {
    jsonErr('Sessão expirada. Recarregue a página.');
}

$action = post('action');

/* ─── CRIAR ─────────────────────────────────────────────────────────── */
if ($action === 'create') {
    $nome      = post('nome');
    $descricao = post('descricao');
    if ($nome === '') jsonErr('Informe o nome do plano.');

    $arquivo     = null;
    $tipoArquivo = null;

    if (!empty($_FILES['arquivo']['name'])) {
        $ext  = strtolower(pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'html', 'htm'])) jsonErr('Formato inválido. Envie PDF ou HTML.');
        $maxSize = 10 * 1024 * 1024; // 10 MB
        if ($_FILES['arquivo']['size'] > $maxSize) jsonErr('Arquivo muito grande. Máximo 10 MB.');

        $novoNome = 'plano_' . time() . '_' . bin2hex(openssl_random_pseudo_bytes(4)) . '.' . $ext;
        $destino  = __DIR__ . '/uploads/planos/' . $novoNome;
        if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $destino)) jsonErr('Falha ao salvar o arquivo.');

        $arquivo     = $novoNome;
        $tipoArquivo = $ext === 'htm' ? 'html' : $ext;
    }

    $st = $pdo->prepare("
        INSERT INTO planos_aprovacao (nome, descricao, arquivo, tipo_arquivo, status, criado_por, criado_em, ativo)
        VALUES (?, ?, ?, ?, 'pendente', ?, NOW(), 1)
    ");
    $st->execute([$nome, $descricao ?: null, $arquivo, $tipoArquivo, (int)$u['id']]);
    jsonOk(['id' => (int)$pdo->lastInsertId()]);
}

/* ─── APROVAR ────────────────────────────────────────────────────────── */
if ($action === 'aprovar') {
    $id = (int)post('id');
    if ($id <= 0) jsonErr('ID inválido.');

    $chk = $pdo->prepare("SELECT id, nome, criado_por, status FROM planos_aprovacao WHERE id=? AND ativo=1 LIMIT 1");
    $chk->execute([$id]);
    $plano = $chk->fetch();
    if (!$plano) jsonErr('Plano não encontrado.');
    if ($plano['status'] === 'aprovado') jsonErr('Plano já aprovado.');

    $pdo->prepare("UPDATE planos_aprovacao SET status='aprovado', aprovado_por=?, aprovado_em=NOW() WHERE id=?")
        ->execute([(int)$u['id'], $id]);

    // Notificar o criador (se for diferente de quem aprovou)
    if ((int)$plano['criado_por'] !== (int)$u['id']) {
        $msg  = '✅ Seu plano "' . $plano['nome'] . '" foi aprovado por ' . $u['nome'] . '.';
        $link = '/planos_aprovacao.php';
        $pdo->prepare("INSERT INTO notificacoes (id_usuario, mensagem, link, criado_em) VALUES (?, ?, ?, NOW())")
            ->execute([(int)$plano['criado_por'], $msg, $link]);
    }

    jsonOk();
}

/* ─── REJEITAR ───────────────────────────────────────────────────────── */
if ($action === 'rejeitar') {
    $id = (int)post('id');
    if ($id <= 0) jsonErr('ID inválido.');

    $chk = $pdo->prepare("SELECT id, nome, criado_por FROM planos_aprovacao WHERE id=? AND ativo=1 LIMIT 1");
    $chk->execute([$id]);
    $plano = $chk->fetch();
    if (!$plano) jsonErr('Plano não encontrado.');

    $pdo->prepare("UPDATE planos_aprovacao SET status='rejeitado', aprovado_por=?, aprovado_em=NOW() WHERE id=?")
        ->execute([(int)$u['id'], $id]);

    if ((int)$plano['criado_por'] !== (int)$u['id']) {
        $msg  = '❌ Seu plano "' . $plano['nome'] . '" foi rejeitado por ' . $u['nome'] . '.';
        $link = '/planos_aprovacao.php';
        $pdo->prepare("INSERT INTO notificacoes (id_usuario, mensagem, link, criado_em) VALUES (?, ?, ?, NOW())")
            ->execute([(int)$plano['criado_por'], $msg, $link]);
    }

    jsonOk();
}

/* ─── MARCAR NOTIFICAÇÕES COMO LIDAS ────────────────────────────────── */
if ($action === 'marcar_lidas') {
    $pdo->prepare("UPDATE notificacoes SET lida=1 WHERE id_usuario=?")
        ->execute([(int)$u['id']]);
    jsonOk();
}

/* ─── EXCLUIR ────────────────────────────────────────────────────────── */
if ($action === 'excluir') {
    $id = (int)post('id');
    if ($id <= 0) jsonErr('ID inválido.');

    $chk = $pdo->prepare("SELECT arquivo, criado_por FROM planos_aprovacao WHERE id=? AND ativo=1 LIMIT 1");
    $chk->execute([$id]);
    $plano = $chk->fetch();
    if (!$plano) jsonErr('Plano não encontrado.');

    // Apenas o criador ou desenvolvedor pode excluir
    if ((int)$plano['criado_por'] !== (int)$u['id'] && $u['tipo'] !== 'desenvolvedor') {
        jsonErr('Sem permissão para excluir.');
    }

    if ($plano['arquivo']) {
        $caminho = __DIR__ . '/uploads/planos/' . $plano['arquivo'];
        if (file_exists($caminho)) unlink($caminho);
    }

    $pdo->prepare("UPDATE planos_aprovacao SET ativo=0 WHERE id=?")->execute([$id]);
    jsonOk();
}

jsonErr('Ação inválida.');
