<?php
/**
 * API REST — Solicitações
 * Autenticação: header X-Api-Token: <token>
 *
 * GET    /api/solicitacoes.php              → lista (filtros: ?cidade=&status=&tipo=&limit=)
 * GET    /api/solicitacoes.php?id=X         → detalhe de uma solicitação
 * POST   /api/solicitacoes.php              → criar solicitação
 * PATCH  /api/solicitacoes.php?id=X         → mudar status
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-Api-Token, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

function json_ok($data, $code = 200) {
    http_response_code($code);
    echo json_encode(array('ok' => true) + (array)$data, JSON_UNESCAPED_UNICODE);
    exit;
}
function json_err($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(array('ok' => false, 'erro' => $msg), JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── Autenticação por token ── */
$headers = function_exists('getallheaders') ? getallheaders() : array();
$token   = '';
foreach ($headers as $k => $v) {
    if (strtolower($k) === 'x-api-token') { $token = trim($v); break; }
}
if ($token === '') $token = trim((string)($_SERVER['HTTP_X_API_TOKEN'] ?? ''));
if ($token === '') json_err('Token não informado. Use o header X-Api-Token.', 401);

$pdo = db();
$tkRow = $pdo->prepare("SELECT id, nome FROM api_tokens WHERE token=? AND ativo=1");
$tkRow->execute(array($token));
$tk = $tkRow->fetch();
if (!$tk) json_err('Token inválido ou inativo.', 401);

/* ── Listas de valores válidos ── */
$allowedStatus = array('nova','em_analise','aprovada','em_desenvolvimento','aguardando_homologacao','implantada','rejeitada','cancelada');
$allowedTipo   = array('novo_item','melhoria');
$allowedPrio   = array('baixa','media','alta','urgente');

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* ══════════════════════════════════
   GET — listar, detalhar ou cidades
══════════════════════════════════ */
if ($method === 'GET') {

    // GET ?cidades=1 — lista todas as cidades com contagens por status
    if (!empty($_GET['cidades'])) {
        $rows = $pdo->query("
            SELECT
                cidade,
                COUNT(*)                                 AS total,
                SUM(status = 'nova')                     AS novas,
                SUM(status = 'em_analise')               AS em_analise,
                SUM(status = 'aprovada')                 AS aprovadas,
                SUM(status = 'em_desenvolvimento')       AS em_desenvolvimento,
                SUM(status = 'aguardando_homologacao')   AS aguardando_homologacao,
                SUM(status = 'implantada')               AS implantadas,
                SUM(status = 'rejeitada')                AS rejeitadas,
                SUM(status = 'cancelada')                AS canceladas
            FROM solicitacoes
            WHERE ativo = 1
            GROUP BY cidade
            ORDER BY cidade ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        // converter campos numéricos
        foreach ($rows as &$r) {
            foreach ($r as $k => $v) {
                if ($k !== 'cidade') $r[$k] = (int)$v;
            }
        }
        unset($r);

        json_ok(array('total_cidades' => count($rows), 'cidades' => $rows));
    }

    // GET ?cidade=X&ref_externa=Y — busca por ref_externa dentro de uma cidade
    if (!empty($_GET['ref_externa'])) {
        $where  = array("s.ativo=1", "s.ref_externa = ?");
        $params = array(trim((string)$_GET['ref_externa']));
        if (!empty($_GET['cidade'])) {
            $where[]  = "s.cidade = ?";
            $params[] = trim((string)$_GET['cidade']);
        }
        $stmt = $pdo->prepare("
            SELECT s.id, s.tipo, s.titulo, s.cidade, s.nome_solicitante,
                   s.status, s.prioridade, s.origem, s.ref_externa, s.criado_em
            FROM solicitacoes s
            WHERE " . implode(' AND ', $where) . "
            ORDER BY s.criado_em DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        json_ok(array('total' => count($rows), 'solicitacoes' => $rows));
    }

    if ($id > 0) {
        // detalhe
        $stmt = $pdo->prepare("
            SELECT s.*,
                   u.nome AS responsavel_nome
            FROM solicitacoes s
            LEFT JOIN usuarios u ON u.id = s.id_responsavel
            WHERE s.id=? AND s.ativo=1
        ");
        $stmt->execute(array($id));
        $sol = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sol) json_err('Solicitação não encontrada.', 404);

        $hist = $pdo->prepare("SELECT * FROM solicitacoes_historico WHERE id_solicitacao=? ORDER BY criado_em DESC");
        $hist->execute(array($id));
        $sol['historico'] = $hist->fetchAll(PDO::FETCH_ASSOC);

        json_ok(array('solicitacao' => $sol));
    }

    // listagem com filtros
    $where  = array("s.ativo=1");
    $params = array();

    if (!empty($_GET['cidade'])) {
        $where[]  = "s.cidade LIKE ?";
        $params[] = '%' . $_GET['cidade'] . '%';
    }
    if (!empty($_GET['status']) && in_array($_GET['status'], $allowedStatus, true)) {
        $where[]  = "s.status = ?";
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['tipo']) && in_array($_GET['tipo'], $allowedTipo, true)) {
        $where[]  = "s.tipo = ?";
        $params[] = $_GET['tipo'];
    }
    $limit = min((int)($_GET['limit'] ?? 100), 500);

    $sql  = "SELECT s.id, s.tipo, s.titulo, s.cidade, s.nome_solicitante, s.status, s.prioridade, s.origem, s.criado_em
             FROM solicitacoes s WHERE " . implode(' AND ', $where) . " ORDER BY s.criado_em DESC LIMIT " . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    json_ok(array('total' => count($rows), 'solicitacoes' => $rows));
}

/* ══════════════════════════════════
   POST — criar solicitação
══════════════════════════════════ */
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) json_err('Body JSON inválido ou vazio.');

    $titulo    = trim((string)($body['titulo'] ?? ''));
    $cidade    = trim((string)($body['cidade'] ?? ''));
    $nome      = trim((string)($body['nome_solicitante'] ?? ''));
    $tipo      = in_array($body['tipo'] ?? '', $allowedTipo, true) ? $body['tipo'] : 'novo_item';
    $descricao = trim((string)($body['descricao'] ?? ''));
    $email     = trim((string)($body['email_solicitante'] ?? ''));
    $telefone  = trim((string)($body['telefone_solicitante'] ?? ''));
    $cargo     = trim((string)($body['cargo_solicitante'] ?? ''));
    $prioridade= in_array($body['prioridade'] ?? '', $allowedPrio, true) ? $body['prioridade'] : 'media';
    $refExt    = trim((string)($body['ref_externa'] ?? ''));

    if ($titulo === '') json_err('Campo obrigatório: titulo.');
    if ($cidade === '') json_err('Campo obrigatório: cidade.');
    if ($nome === '')   json_err('Campo obrigatório: nome_solicitante.');

    $stmt = $pdo->prepare("
        INSERT INTO solicitacoes
          (tipo, titulo, descricao, cidade, nome_solicitante, email_solicitante,
           telefone_solicitante, cargo_solicitante, prioridade, origem, ref_externa, status, criado_em)
        VALUES (?,?,?,?,?,?,?,?,?,'api',?,'nova',NOW())
    ");
    $stmt->execute(array($tipo,$titulo,$descricao,$cidade,$nome,$email,$telefone,$cargo,$prioridade,$refExt));
    $newId = (int)$pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO solicitacoes_historico (id_solicitacao, status_anterior, status_novo, observacao, nome_usuario, criado_em)
        VALUES (?, NULL, 'nova', ?, ?, NOW())
    ")->execute(array($newId, 'Criada via API', $tk['nome']));

    json_ok(array('id' => $newId, 'status' => 'nova'), 201);
}

/* ══════════════════════════════════
   PATCH — mudar status
══════════════════════════════════ */
if ($method === 'PATCH') {
    if (!$id) json_err('Informe ?id= na URL.');

    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body) json_err('Body JSON inválido ou vazio.');

    $statusNovo = trim((string)($body['status'] ?? ''));
    $observacao = trim((string)($body['observacao'] ?? ''));

    if (!in_array($statusNovo, $allowedStatus, true)) {
        json_err('Status inválido. Valores aceitos: ' . implode(', ', $allowedStatus));
    }

    $sol = $pdo->prepare("SELECT status FROM solicitacoes WHERE id=? AND ativo=1");
    $sol->execute(array($id));
    $row = $sol->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_err('Solicitação não encontrada.', 404);

    $pdo->prepare("UPDATE solicitacoes SET status=?, atualizado_em=NOW() WHERE id=?")->execute(array($statusNovo, $id));

    $pdo->prepare("
        INSERT INTO solicitacoes_historico (id_solicitacao, status_anterior, status_novo, observacao, nome_usuario, criado_em)
        VALUES (?,?,?,?,?,NOW())
    ")->execute(array($id, $row['status'], $statusNovo, $observacao, $tk['nome']));

    json_ok(array('id' => $id, 'status_anterior' => $row['status'], 'status_novo' => $statusNovo));
}

json_err('Método não suportado.', 405);
