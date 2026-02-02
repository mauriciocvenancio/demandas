<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo = db();
date_default_timezone_set('America/Sao_Paulo');

/* filtros iguais ao relatório */
$data_ini   = isset($_GET['data_ini']) ? trim((string)$_GET['data_ini']) : date('Y-m-01');
$data_fim   = isset($_GET['data_fim']) ? trim((string)$_GET['data_fim']) : date('Y-m-d');
$id_cliente = isset($_GET['id_cliente']) ? (int)$_GET['id_cliente'] : 0;
$tipo       = isset($_GET['tipo']) ? trim((string)$_GET['tipo']) : 'todos';
$status     = isset($_GET['status']) ? trim((string)$_GET['status']) : 'todos';

function validDate($d){ return (bool)preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $d); }
if (!validDate($data_ini)) $data_ini = date('Y-m-01');
if (!validDate($data_fim)) $data_fim = date('Y-m-d');

$allowedTipos = array('melhoria','duvida','solicitacao','bug','configuracao');
$allowedStatus = array('em_andamento','finalizado','aguardando_resposta');

$where  = array("s.ativo=1", "DATE(s.criado_em) >= ?", "DATE(s.criado_em) <= ?");
$params = array($data_ini, $data_fim);

if ($id_cliente > 0) { $where[]="s.id_cliente=?"; $params[]=$id_cliente; }
if ($tipo !== 'todos' && in_array($tipo, $allowedTipos, true)) { $where[]="s.tipo_contato=?"; $params[]=$tipo; }
if ($status !== 'todos' && in_array($status, $allowedStatus, true)) { $where[]="s.status=?"; $params[]=$status; }

$sql = "
SELECT
  s.id,
  s.criado_em,
  s.assunto,
  c.nome AS cliente,
  s.tipo_contato,
  s.status,
  s.criticidade,
  s.duracao_min,
  u.nome AS registrado_por,
  s.id_demanda
FROM suportes s
JOIN clientes c ON c.id=s.id_cliente
JOIN usuarios u ON u.id=s.id_usuario_registro
WHERE ".implode(" AND ", $where)."
ORDER BY s.criado_em DESC
";
$st = $pdo->prepare($sql);
$st->execute($params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=relatorio_suportes.csv');

$out = fopen('php://output', 'w');
fputcsv($out, array(
    'ID','Data','Assunto','Cliente','Tipo','Status','Criticidade','Duracao(min)','Registrado por','Demanda'
), ';');

while ($r = $st->fetch()) {
    fputcsv($out, array(
        (int)$r['id'],
        date('d/m/Y H:i', strtotime($r['criado_em'])),
        $r['assunto'],
        $r['cliente'],
        $r['tipo_contato'],
        $r['status'],
        $r['criticidade'],
        ($r['duracao_min']!==null ? (int)$r['duracao_min'] : ''),
        $r['registrado_por'],
        ($r['id_demanda'] ? '#'.$r['id_demanda'] : '')
    ), ';');
}
fclose($out);
exit;
