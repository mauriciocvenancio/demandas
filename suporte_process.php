<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo = db();
$u   = auth_user();

if (session_status() === PHP_SESSION_NONE) session_start();

function post($k, $default=''){
    return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $default;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: suportes.php");
    exit;
}

$csrf = post('csrf');
if (!$csrf || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
    die('CSRF inválido');
}

$action = post('action','');

if ($action === 'create') {

    $assunto   = post('assunto');
    $id_cliente = (int)post('id_cliente', 0);
    $tipo_contato = post('tipo_contato');
    $status    = post('status', 'em_andamento');
    $criticidade = post('criticidade', 'media');
    $descricao = post('descricao');
    $resolucao = post('resolucao');
    $duracao_min = (int)post('duracao_min', 0);

    $id_usuario_responsavel = (int)post('id_usuario_responsavel', 0);
    if ($id_usuario_responsavel <= 0) $id_usuario_responsavel = (int)$u['id'];

    $criar_demanda = isset($_POST['criar_demanda']) && $_POST['criar_demanda'] == '1';

    if ($assunto === '' || $id_cliente <= 0) {
        header("Location: suportes.php?err=1");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1) cria suporte
        $sqlSup = "INSERT INTO suportes
      (assunto, id_cliente, tipo_contato, status, criticidade, descricao, resolucao, duracao_min,
       id_usuario_registro, id_usuario_responsavel, criado_em, ativo)
      VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)";
        $st = $pdo->prepare($sqlSup);
        $st->execute(array(
            $assunto, $id_cliente, $tipo_contato, $status, $criticidade, $descricao, $resolucao, $duracao_min,
            (int)$u['id'], $id_usuario_responsavel
        ));

        $id_suporte = (int)$pdo->lastInsertId();
        $id_demanda = 0;

        // 2) se marcou "criar demanda", cria demanda para DEV
        if ($criar_demanda) {

            // pega 1 dev ativo
            $stDev = $pdo->query("SELECT id FROM usuarios WHERE ativo=1 AND tipo='desenvolvedor' ORDER BY nome ASC LIMIT 1");
            $dev = $stDev->fetch();

            $id_resp_demanda = 0;

            if ($dev && !empty($dev['id'])) {
                $id_resp_demanda = (int)$dev['id'];
            } else {
                // fallback: responsável escolhido, senão usuário logado
                $id_resp_demanda = $id_usuario_responsavel > 0 ? $id_usuario_responsavel : (int)$u['id'];
            }

            $tituloDem = "Suporte: ".$assunto;
            $descDem   = $descricao;
            if ($descDem === '') $descDem = "Demanda criada a partir do suporte #".$id_suporte;

            $sqlDem = "INSERT INTO demandas
        (titulo, descricao, id_cliente, id_responsavel, status, criticidade, prazo, criado_em, ativo)
        VALUES
        (?, ?, ?, ?, 'nao_iniciado', ?, NULL, NOW(), 1)";
            $stD = $pdo->prepare($sqlDem);
            $stD->execute(array($tituloDem, $descDem, $id_cliente, $id_resp_demanda, $criticidade));

            $id_demanda = (int)$pdo->lastInsertId();

            // vincula demanda no suporte
            $stU = $pdo->prepare("UPDATE suportes SET id_demanda=? WHERE id=?");
            $stU->execute(array($id_demanda, $id_suporte));
        }

        $pdo->commit();

        header("Location: suportes.php?ok=1");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        // em produção: logar erro em arquivo
        die("Erro ao salvar: ".$e->getMessage());
    }
}

/* update básico (se você tiver suporte_edit.php) */
if ($action === 'update') {

    $id = (int)post('id', 0);
    if ($id <= 0) { header("Location: suportes.php"); exit; }

    $assunto   = post('assunto');
    $id_cliente = (int)post('id_cliente', 0);
    $tipo_contato = post('tipo_contato');
    $status    = post('status');
    $criticidade = post('criticidade');
    $descricao = post('descricao');
    $resolucao = post('resolucao');
    $duracao_min = (int)post('duracao_min', 0);

    $id_usuario_responsavel = (int)post('id_usuario_responsavel', 0);
    if ($id_usuario_responsavel <= 0) $id_usuario_responsavel = (int)$u['id'];

    $sql = "UPDATE suportes SET
    assunto=?,
    id_cliente=?,
    tipo_contato=?,
    status=?,
    criticidade=?,
    descricao=?,
    resolucao=?,
    duracao_min=?,
    id_usuario_responsavel=?
    WHERE id=?";

    $st = $pdo->prepare($sql);
    $st->execute(array(
        $assunto, $id_cliente, $tipo_contato, $status, $criticidade, $descricao, $resolucao, $duracao_min,
        $id_usuario_responsavel, $id
    ));

    header("Location: suportes.php?ok=2");
    exit;
}

header("Location: suportes.php");
exit;
