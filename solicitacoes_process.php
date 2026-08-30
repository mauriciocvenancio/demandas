<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = db();
$u   = auth_user();

// CSRF
if (!isset($_POST['csrf']) || $_POST['csrf'] !== ($_SESSION['csrf'] ?? '')) {
    redirect('/solicitacoes.php?msg=csrf');
}

$action = isset($_POST['action']) ? trim((string)$_POST['action']) : '';

$allowedStatus = array('nova','em_analise','aprovada','em_desenvolvimento','aguardando_homologacao','implantada','rejeitada','cancelada');
$allowedTipo   = array('novo_item','melhoria');
$allowedPrio   = array('baixa','media','alta','urgente');

/* ──────────────────────────────────────────
   CRIAR
────────────────────────────────────────── */
if ($action === 'create') {
    $titulo    = trim((string)($_POST['titulo'] ?? ''));
    $tipo      = in_array($_POST['tipo'] ?? '', $allowedTipo, true) ? $_POST['tipo'] : 'novo_item';
    $cidade    = trim((string)($_POST['cidade'] ?? ''));
    $descricao = trim((string)($_POST['descricao'] ?? ''));
    $nome      = trim((string)($_POST['nome_solicitante'] ?? ''));
    $email     = trim((string)($_POST['email_solicitante'] ?? ''));
    $telefone  = trim((string)($_POST['telefone_solicitante'] ?? ''));
    $cargo     = trim((string)($_POST['cargo_solicitante'] ?? ''));
    $prioridade= in_array($_POST['prioridade'] ?? '', $allowedPrio, true) ? $_POST['prioridade'] : 'media';
    $responsavel = !empty($_POST['id_responsavel']) ? (int)$_POST['id_responsavel'] : null;

    if ($titulo === '' || $cidade === '' || $nome === '') {
        redirect('/solicitacoes.php?msg=campos_obrigatorios');
    }

    $stmt = $pdo->prepare("
        INSERT INTO solicitacoes
          (tipo, titulo, descricao, cidade, nome_solicitante, email_solicitante,
           telefone_solicitante, cargo_solicitante, prioridade, id_responsavel, origem, status, criado_em)
        VALUES (?,?,?,?,?,?,?,?,?,?,'interno','nova',NOW())
    ");
    $stmt->execute(array($tipo,$titulo,$descricao,$cidade,$nome,$email,$telefone,$cargo,$prioridade,$responsavel));
    $id = $pdo->lastInsertId();

    // registrar histórico
    $pdo->prepare("
        INSERT INTO solicitacoes_historico (id_solicitacao, status_anterior, status_novo, observacao, nome_usuario, criado_em)
        VALUES (?, NULL, 'nova', 'Solicitação criada', ?, NOW())
    ")->execute(array($id, $u['nome']));

    redirect('/solicitacao_view.php?id=' . $id . '&msg=criada');
}

/* ──────────────────────────────────────────
   MUDAR STATUS
────────────────────────────────────────── */
if ($action === 'status') {
    $id          = (int)($_POST['id'] ?? 0);
    $statusNovo  = in_array($_POST['status_novo'] ?? '', $allowedStatus, true) ? $_POST['status_novo'] : '';
    $observacao  = trim((string)($_POST['observacao'] ?? ''));

    if (!$id || $statusNovo === '') {
        redirect('/solicitacoes.php?msg=dados_invalidos');
    }

    $sol = $pdo->prepare("SELECT status FROM solicitacoes WHERE id=? AND ativo=1");
    $sol->execute(array($id));
    $row = $sol->fetch();
    if (!$row) redirect('/solicitacoes.php');

    $statusAnterior = $row['status'];

    $pdo->prepare("UPDATE solicitacoes SET status=?, atualizado_em=NOW() WHERE id=?")->execute(array($statusNovo, $id));

    $pdo->prepare("
        INSERT INTO solicitacoes_historico (id_solicitacao, status_anterior, status_novo, observacao, nome_usuario, criado_em)
        VALUES (?,?,?,?,?,NOW())
    ")->execute(array($id, $statusAnterior, $statusNovo, $observacao, $u['nome']));

    redirect('/solicitacao_view.php?id=' . $id . '&msg=status_atualizado');
}

/* ──────────────────────────────────────────
   EDITAR
────────────────────────────────────────── */
if ($action === 'edit') {
    $id        = (int)($_POST['id'] ?? 0);
    $titulo    = trim((string)($_POST['titulo'] ?? ''));
    $tipo      = in_array($_POST['tipo'] ?? '', $allowedTipo, true) ? $_POST['tipo'] : 'novo_item';
    $cidade    = trim((string)($_POST['cidade'] ?? ''));
    $descricao = trim((string)($_POST['descricao'] ?? ''));
    $nome      = trim((string)($_POST['nome_solicitante'] ?? ''));
    $email     = trim((string)($_POST['email_solicitante'] ?? ''));
    $telefone  = trim((string)($_POST['telefone_solicitante'] ?? ''));
    $cargo     = trim((string)($_POST['cargo_solicitante'] ?? ''));
    $prioridade= in_array($_POST['prioridade'] ?? '', $allowedPrio, true) ? $_POST['prioridade'] : 'media';
    $responsavel = !empty($_POST['id_responsavel']) ? (int)$_POST['id_responsavel'] : null;

    if (!$id || $titulo === '' || $cidade === '' || $nome === '') {
        redirect('/solicitacoes.php?msg=campos_obrigatorios');
    }

    $pdo->prepare("
        UPDATE solicitacoes SET tipo=?,titulo=?,descricao=?,cidade=?,nome_solicitante=?,
          email_solicitante=?,telefone_solicitante=?,cargo_solicitante=?,prioridade=?,
          id_responsavel=?,atualizado_em=NOW()
        WHERE id=? AND ativo=1
    ")->execute(array($tipo,$titulo,$descricao,$cidade,$nome,$email,$telefone,$cargo,$prioridade,$responsavel,$id));

    redirect('/solicitacao_view.php?id=' . $id . '&msg=editada');
}

/* ──────────────────────────────────────────
   EXCLUIR (soft-delete)
────────────────────────────────────────── */
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $pdo->prepare("UPDATE solicitacoes SET ativo=0, atualizado_em=NOW() WHERE id=?")->execute(array($id));
    }
    redirect('/solicitacoes.php?msg=excluida');
}

redirect('/solicitacoes.php');
