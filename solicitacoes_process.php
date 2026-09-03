<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = db();
$u   = auth_user();

function _post56($k){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : ''; }
function _posti56($k){ return isset($_POST[$k]) ? (int)$_POST[$k] : 0; }

// CSRF
$csrf_sess = isset($_SESSION['csrf']) ? $_SESSION['csrf'] : '';
if (!isset($_POST['csrf']) || $_POST['csrf'] !== $csrf_sess) {
    redirect('/solicitacoes.php?msg=csrf');
}

$action = _post56('action');

$allowedStatus = array('nova','em_analise','aprovada','em_desenvolvimento','aguardando_homologacao','implantada','rejeitada','cancelada');
$allowedTipo   = array('novo_item','melhoria');
$allowedPrio   = array('baixa','media','alta','urgente');

/* ── CRIAR ─────────────────────────────────────────────────────────── */
if ($action === 'create') {
    $titulo     = _post56('titulo');
    $tipo_raw   = _post56('tipo');
    $tipo       = in_array($tipo_raw, $allowedTipo, true) ? $tipo_raw : 'novo_item';
    $cidade     = _post56('cidade');
    $descricao  = _post56('descricao');
    $nome       = _post56('nome_solicitante');
    $email      = _post56('email_solicitante');
    $telefone   = _post56('telefone_solicitante');
    $cargo      = _post56('cargo_solicitante');
    $prio_raw   = _post56('prioridade');
    $prioridade = in_array($prio_raw, $allowedPrio, true) ? $prio_raw : 'media';
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

    $pdo->prepare("
        INSERT INTO solicitacoes_historico (id_solicitacao, status_anterior, status_novo, observacao, nome_usuario, criado_em)
        VALUES (?, NULL, 'nova', 'Solicitação criada', ?, NOW())
    ")->execute(array($id, $u['nome']));

    redirect('/solicitacao_view.php?id=' . $id . '&msg=criada');
}

/* ── MUDAR STATUS ───────────────────────────────────────────────────── */
if ($action === 'status') {
    $id         = _posti56('id');
    $sn_raw     = _post56('status_novo');
    $statusNovo = in_array($sn_raw, $allowedStatus, true) ? $sn_raw : '';
    $observacao = _post56('observacao');

    if (!$id || $statusNovo === '') redirect('/solicitacoes.php?msg=dados_invalidos');

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

/* ── EDITAR ─────────────────────────────────────────────────────────── */
if ($action === 'edit') {
    $id         = _posti56('id');
    $titulo     = _post56('titulo');
    $tipo_raw   = _post56('tipo');
    $tipo       = in_array($tipo_raw, $allowedTipo, true) ? $tipo_raw : 'novo_item';
    $cidade     = _post56('cidade');
    $descricao  = _post56('descricao');
    $nome       = _post56('nome_solicitante');
    $email      = _post56('email_solicitante');
    $telefone   = _post56('telefone_solicitante');
    $cargo      = _post56('cargo_solicitante');
    $prio_raw   = _post56('prioridade');
    $prioridade = in_array($prio_raw, $allowedPrio, true) ? $prio_raw : 'media';
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

/* ── EXCLUIR ─────────────────────────────────────────────────────────── */
if ($action === 'delete') {
    $id = _posti56('id');
    if ($id) {
        $pdo->prepare("UPDATE solicitacoes SET ativo=0, atualizado_em=NOW() WHERE id=?")->execute(array($id));
    }
    redirect('/solicitacoes.php?msg=excluida');
}

redirect('/solicitacoes.php');
