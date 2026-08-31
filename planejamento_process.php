<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

header('Content-Type: application/json');

set_exception_handler(function($e) {
    echo json_encode(array('ok' => false, 'msg' => 'Erro interno: ' . $e->getMessage()));
    exit;
});
set_error_handler(function($errno, $errstr) {
    echo json_encode(array('ok' => false, 'msg' => 'Erro PHP: ' . $errstr));
    exit;
}, E_ERROR | E_USER_ERROR);

function jsonOk($data = array()) {
    echo json_encode(array_merge(array('ok' => true), $data));
    exit;
}
function jsonErr($msg) {
    echo json_encode(array('ok' => false, 'msg' => $msg));
    exit;
}
function post($k) {
    return isset($_POST[$k]) ? trim((string)$_POST[$k]) : '';
}

$pdo = db();
$u   = auth_user();

// CSRF
$csrf = post('csrf');
if (!isset($_SESSION['csrf']) || $csrf === '' || $csrf !== $_SESSION['csrf']) {
    jsonErr('Sessão expirada. Recarregue a página.');
}

// Retorna próximo dia útil a partir de uma data (timestamp), máx 365 dias
function proximoDiaUtil($ts) {
    for ($i = 0; $i < 365; $i++) {
        $ts += 86400;
        $dow = (int)date('N', $ts); // 1=seg ... 7=dom
        if ($dow <= 5) return $ts;
    }
    return $ts;
}

// Retorna próximo dia útil disponível para o dev a partir de amanhã (respeita limite de 8h)
function calcularDataAlocacao($pdo, $id_dev, $media) {
    $ts = mktime(0, 0, 0, (int)date('m'), (int)date('d'), (int)date('Y')); // hoje
    for ($tentativa = 0; $tentativa < 365; $tentativa++) {
        $ts  = proximoDiaUtil($ts);
        $dia = date('Y-m-d', $ts);
        $st  = $pdo->prepare("
            SELECT COALESCE(SUM(pi.estimativa_media), 0) AS horas
            FROM planejamento_calendario pc
            JOIN planejamento_itens pi ON pi.id = pc.id_item
            WHERE pc.id_desenvolvedor = ?
              AND pc.data_alocada = ?
              AND pi.ativo = 1
              AND pi.status <> 'finalizado'
        ");
        $st->execute(array((int)$id_dev, $dia));
        $horas = (float)$st->fetchColumn();
        if ($horas + $media <= 8.0) {
            return $dia;
        }
    }
    return date('Y-m-d', $ts);
}

function proximaOrdem($pdo, $id_dev, $data) {
    $st = $pdo->prepare("SELECT COALESCE(MAX(ordem),0)+1 FROM planejamento_calendario WHERE id_desenvolvedor=? AND data_alocada=?");
    $st->execute(array((int)$id_dev, $data));
    return (int)$st->fetchColumn();
}

$action = post('action');

try {

/* ───────────────────────────── CREATE ───────────────────────────── */
if ($action === 'create') {
    $titulo    = post('titulo');
    $descricao = post('descricao');
    $id_dev    = (int)post('id_desenvolvedor');
    $est_min   = (float)post('estimativa_min');
    $est_max   = (float)post('estimativa_max');

    if ($titulo === '')   jsonErr('Informe o título.');
    if ($id_dev <= 0)     jsonErr('Selecione o desenvolvedor.');
    if ($est_min < 0 || $est_max < 0) jsonErr('Estimativas inválidas.');
    if ($est_max < $est_min) { $tmp = $est_min; $est_min = $est_max; $est_max = $tmp; }
    if ($est_min == 0 && $est_max == 0) jsonErr('Informe pelo menos um valor de estimativa.');

    $media = round(($est_min + $est_max) / 2, 2);

    $st = $pdo->prepare("
        INSERT INTO planejamento_itens
        (titulo, descricao, id_desenvolvedor, estimativa_min, estimativa_max, estimativa_media, status, criado_em, ativo)
        VALUES (?, ?, ?, ?, ?, ?, 'pendente', NOW(), 1)
    ");
    $st->execute(array($titulo, $descricao !== '' ? $descricao : null, $id_dev, $est_min, $est_max, $media));
    $id_item = (int)$pdo->lastInsertId();

    $data_alocada = calcularDataAlocacao($pdo, $id_dev, $media);
    $ordem        = proximaOrdem($pdo, $id_dev, $data_alocada);

    $stc = $pdo->prepare("
        INSERT INTO planejamento_calendario (id_item, id_desenvolvedor, data_alocada, ordem, criado_em)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stc->execute(array($id_item, $id_dev, $data_alocada, $ordem));

    jsonOk(array('id_item' => $id_item, 'data_alocada' => $data_alocada));
}

/* ───────────────────────────── EDIT ───────────────────────────── */
if ($action === 'edit') {
    $id_item   = (int)post('id_item');
    $titulo    = post('titulo');
    $descricao = post('descricao');
    $id_dev    = (int)post('id_desenvolvedor');
    $est_min   = (float)post('estimativa_min');
    $est_max   = (float)post('estimativa_max');

    if ($id_item <= 0) jsonErr('Item inválido.');
    if ($titulo === '') jsonErr('Informe o título.');
    if ($id_dev <= 0)   jsonErr('Selecione o desenvolvedor.');
    if ($est_max < $est_min) { $tmp = $est_min; $est_min = $est_max; $est_max = $tmp; }
    if ($est_min <= 0 && $est_max <= 0) jsonErr('Informe pelo menos um valor de estimativa.');

    $media = round(($est_min + $est_max) / 2, 2);

    // Bloquear edição se já finalizado
    $chk = $pdo->prepare("SELECT status FROM planejamento_itens WHERE id=? AND ativo=1 LIMIT 1");
    $chk->execute(array($id_item));
    $row = $chk->fetch();
    if (!$row) jsonErr('Item não encontrado.');
    if ($row['status'] === 'finalizado') jsonErr('Não é possível editar um item já finalizado.');

    $st = $pdo->prepare("
        UPDATE planejamento_itens
        SET titulo=?, descricao=?, id_desenvolvedor=?, estimativa_min=?, estimativa_max=?, estimativa_media=?
        WHERE id=? AND ativo=1
    ");
    $st->execute(array(
        $titulo,
        $descricao !== '' ? $descricao : null,
        $id_dev,
        $est_min,
        $est_max,
        $media,
        $id_item
    ));

    // Atualizar também o dev no calendário
    $pdo->prepare("UPDATE planejamento_calendario SET id_desenvolvedor=? WHERE id_item=?")
        ->execute(array($id_dev, $id_item));

    jsonOk();
}

/* ───────────────────────────── MOVE ───────────────────────────── */
if ($action === 'move') {
    $id_item   = (int)post('id_item');
    $nova_data = post('nova_data');

    if ($id_item <= 0) jsonErr('Item inválido.');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $nova_data)) jsonErr('Data inválida.');

    // garantir que não é fim de semana
    $dow = (int)date('N', strtotime($nova_data));
    if ($dow >= 6) jsonErr('Não é permitido alocar em fim de semana.');

    $chk = $pdo->prepare("SELECT id_desenvolvedor FROM planejamento_calendario WHERE id_item=? LIMIT 1");
    $chk->execute(array($id_item));
    $row = $chk->fetch();
    if (!$row) jsonErr('Item não encontrado no calendário.');

    $id_dev = (int)$row['id_desenvolvedor'];
    $ordem  = proximaOrdem($pdo, $id_dev, $nova_data);

    $st = $pdo->prepare("UPDATE planejamento_calendario SET data_alocada=?, ordem=? WHERE id_item=?");
    $st->execute(array($nova_data, $ordem, $id_item));

    jsonOk();
}

/* ───────────────────────────── FINALIZE ───────────────────────────── */
if ($action === 'finalize') {
    $id_item    = (int)post('id_item');
    $tempo_real = (float)post('tempo_real');

    if ($id_item <= 0)    jsonErr('Item inválido.');
    if ($tempo_real <= 0) jsonErr('Informe o tempo real gasto (maior que zero).');

    $st = $pdo->prepare("
        UPDATE planejamento_itens
        SET status='finalizado', tempo_real=?, finalizado_em=NOW()
        WHERE id=? AND ativo=1
    ");
    $st->execute(array($tempo_real, $id_item));

    jsonOk();
}

/* ───────────────────────────── DELETE ───────────────────────────── */
if ($action === 'delete') {
    $id_item = (int)post('id_item');
    if ($id_item <= 0) jsonErr('Item inválido.');

    $pdo->prepare("UPDATE planejamento_itens SET ativo=0 WHERE id=?")->execute(array($id_item));
    $pdo->prepare("DELETE FROM planejamento_calendario WHERE id_item=?")->execute(array($id_item));

    jsonOk();
}

jsonErr('Ação inválida.');

} catch (Exception $e) {
    jsonErr('Erro interno: ' . $e->getMessage());
} catch (Error $e) {
    jsonErr('Erro interno: ' . $e->getMessage());
}
