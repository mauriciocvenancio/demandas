<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo = db();
date_default_timezone_set('America/Sao_Paulo');

$menuActive = 'dashboard';
$pageTitle  = 'Dashboard';

/* ====== Contadores ======
Ajuste aqui se quiser mudar o que é "pendente" / "concluída"
- Pendentes: nao_iniciado + aguardando_resposta_cliente
- Em andamento: em_andamento
- Concluídas: finalizado + publicado
*/
$sqlCards = "
SELECT
  (SELECT COUNT(*) FROM clientes  WHERE ativo=1) AS total_clientes,

  (SELECT COUNT(*) FROM demandas WHERE ativo=1 AND status IN ('nao_iniciado','aguardando_resposta_cliente')) AS demandas_pendentes,

  (SELECT COUNT(*) FROM demandas WHERE ativo=1 AND status='em_andamento') AS demandas_andamento,

  (SELECT COUNT(*) FROM demandas WHERE ativo=1 AND status IN ('finalizado','publicado')) AS demandas_concluidas,

  (SELECT COUNT(*) FROM suportes WHERE ativo=1) AS total_suportes,

  (SELECT COUNT(*) FROM suportes WHERE ativo=1 AND DATE(criado_em)=CURDATE()) AS suportes_hoje
";
$cards = $pdo->query($sqlCards)->fetch();

/* ====== Demandas Pendentes (lista) ====== */
$sqlPendentes = "
SELECT
  d.id, d.titulo, d.status, d.criticidade, d.prazo, d.criado_em,
  c.nome AS cliente_nome,
  u.nome AS responsavel_nome
FROM demandas d
JOIN clientes c ON c.id=d.id_cliente
JOIN usuarios u ON u.id=d.id_responsavel
WHERE d.ativo=1
  AND d.status IN ('nao_iniciado','aguardando_resposta_cliente')
ORDER BY
  CASE d.criticidade
    WHEN 'urgente' THEN 1
    WHEN 'alta'    THEN 2
    WHEN 'media'   THEN 3
    WHEN 'baixa'   THEN 4
    ELSE 5
  END,
  COALESCE(d.prazo, '9999-12-31') ASC,
  d.criado_em DESC
LIMIT 8
";
$pendentes = $pdo->query($sqlPendentes)->fetchAll();

/* ====== Suportes Recentes (lista) ====== */
$sqlSuportes = "
SELECT s.id,
       s.assunto,
       s.tipo_contato,
       s.status,
       s.criticidade,
       s.criado_em,
       c.nome AS cliente_nome,
       u.nome AS responsavel_nome
FROM suportes s
JOIN clientes c ON c.id = s.id_cliente
LEFT JOIN usuarios u ON u.id = s.id_usuario_responsavel
WHERE s.ativo=1
ORDER BY s.id DESC
LIMIT 5
";
$suportes = $pdo->query($sqlSuportes)->fetchAll();

function labelStatusDemanda($s){
    $map = array(
        'nao_iniciado' => 'Não iniciado',
        'em_andamento' => 'Em andamento',
        'aguardando_resposta_cliente' => 'Aguardando resposta cliente',
        'finalizado' => 'Finalizado',
        'publicado' => 'Publicado',
    );
    return isset($map[$s]) ? $map[$s] : $s;
}
function labelStatusSuporte($s){
    $map = array(
        'em_andamento' => 'Em andamento',
        'aguardando_resposta' => 'Aguardando resposta',
        'finalizado' => 'Finalizado',
    );
    return isset($map[$s]) ? $map[$s] : $s;
}
function labelTipoSuporte($t){
    $map = array(
        'melhoria'=>'Melhoria','duvida'=>'Dúvida','solicitacao'=>'Solicitação','bug'=>'Bug','configuracao'=>'Configuração'
    );
    return isset($map[$t]) ? $map[$t] : $t;
}
function labelCrit($c){
    $map = array('baixa'=>'Baixa','media'=>'Média','alta'=>'Alta','urgente'=>'Urgente');
    return isset($map[$c]) ? $map[$c] : $c;
}

require_once __DIR__ . '/includes/layout_top.php';
?>

<style>
    .toprow{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:18px;}
    .title h1{margin:0;font-size:22px;font-weight:900;}
    .title .sub{color:var(--muted);font-size:13px;margin-top:4px;}

    .cards{display:grid;grid-template-columns:repeat(6, minmax(0,1fr));gap:10px;margin-bottom:14px;}
    .card{border:1px solid var(--line);border-radius:16px;padding:14px;background:#fff;display:flex;justify-content:space-between;gap:10px;}
    .card .k{font-size:12px;color:var(--muted);font-weight:900;}
    .card .v{margin-top:10px;font-size:22px;font-weight:900;}
    .iconbox{
        width:36px;height:36px;border-radius:12px;border:1px solid var(--line);
        display:flex;align-items:center;justify-content:center;font-size:16px;background:#f8f8f8;flex-shrink:0;
    }

    .grid2{display:grid;grid-template-columns: 1fr 1fr; gap:14px;}
    .panel{border:1px solid var(--line);border-radius:16px;background:#fff;padding:14px;}
    .panel-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;}
    .panel-title{font-weight:900;}
    .panel-sub{color:var(--muted);font-size:12px;font-weight:800;}
    .link{font-weight:900;text-decoration:none;color:#111;display:inline-flex;gap:8px;align-items:center;}
    .link:hover{opacity:.85}

    .list{margin:0;padding:0;list-style:none;}
    .item{padding:12px 10px;border-top:1px solid var(--line);display:flex;justify-content:space-between;gap:12px;}
    .item:first-child{border-top:0;}
    .item .left{min-width:0;}
    .item .t{font-weight:900;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:520px;}
    .item .m{margin-top:3px;color:var(--muted);font-size:12px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .item .right{text-align:right;flex-shrink:0;}
    .badge{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;border:1px solid var(--line);font-size:12px;font-weight:900;background:#fff;}
    .badge-dark{background:#111;color:#fff;border-color:#111;}
    .muted{color:var(--muted);font-weight:800;}

    @media(max-width: 1200px){
        .cards{grid-template-columns:repeat(2, minmax(0,1fr));}
        .grid2{grid-template-columns:1fr;}
        .item .t{max-width:340px;}
    }
</style>

<div class="toprow">
    <div class="title">
        <h1>Dashboard</h1>
        <div class="sub">Visão geral do sistema de demandas e suportes</div>
    </div>
</div>

<div class="cards">
    <div class="card">
        <div>
            <div class="k">Total de Clientes</div>
            <div class="v"><?= (int)$cards['total_clientes'] ?></div>
        </div>
        <div class="iconbox">👥</div>
    </div>

    <div class="card">
        <div>
            <div class="k">Demandas Pendentes</div>
            <div class="v"><?= (int)$cards['demandas_pendentes'] ?></div>
        </div>
        <div class="iconbox">⏳</div>
    </div>

    <div class="card">
        <div>
            <div class="k">Em Andamento</div>
            <div class="v"><?= (int)$cards['demandas_andamento'] ?></div>
        </div>
        <div class="iconbox">🕘</div>
    </div>

    <div class="card">
        <div>
            <div class="k">Concluídas</div>
            <div class="v"><?= (int)$cards['demandas_concluidas'] ?></div>
        </div>
        <div class="iconbox">✅</div>
    </div>

    <div class="card">
        <div>
            <div class="k">Total de Suportes</div>
            <div class="v"><?= (int)$cards['total_suportes'] ?></div>
        </div>
        <div class="iconbox">🎧</div>
    </div>

    <div class="card">
        <div>
            <div class="k">Suportes Hoje</div>
            <div class="v"><?= (int)$cards['suportes_hoje'] ?></div>
        </div>
        <div class="iconbox">📅</div>
    </div>
</div>

<div class="grid2">
    <!-- Demandas Pendentes -->
    <div class="panel">
        <div class="panel-head">
            <div>
                <div class="panel-title">Demandas Pendentes</div>
                <div class="panel-sub">Tarefas que precisam de atenção</div>
            </div>
            <a class="link" href="demandas.php">Ver todas →</a>
        </div>

        <?php if (!empty($pendentes)): ?>
            <ul class="list">
                <?php foreach ($pendentes as $d): ?>
                    <li class="item">
                        <div class="left">
                            <div class="t">
                                <a class="link" href="demanda_view.php?id=<?= (int)$d['id'] ?>" style="gap:0;">
                                    <?= h($d['titulo']) ?>
                                </a>
                            </div>
                            <div class="m">
                                <?= h($d['cliente_nome']) ?> • <?= h($d['responsavel_nome']) ?>
                                <?php if (!empty($d['prazo'])): ?>
                                    • Prazo: <?= h(date('d/m/Y', strtotime($d['prazo']))) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="right">
                            <div><span class="badge badge-dark"><?= h(labelCrit($d['criticidade'])) ?></span></div>
                            <div style="margin-top:6px;"><span class="badge"><?= h(labelStatusDemanda($d['status'])) ?></span></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="muted" style="padding:22px 10px;text-align:center;">Nenhuma demanda pendente no momento.</div>
        <?php endif; ?>
    </div>

    <!-- Suportes Recentes -->
    <div class="panel">
        <div class="panel-head">
            <div>
                <div class="panel-title">Suportes Recentes</div>
                <div class="panel-sub">Últimos atendimentos realizados</div>
            </div>
            <a class="link" href="suportes.php">Ver todos →</a>
        </div>

        <?php if (!empty($suportes)): ?>
            <ul class="list">
                <?php foreach ($suportes as $s): ?>
                    <li class="item">
                        <div class="left">
                            <div class="t">
                                <a class="link" href="suportes.php?edit=<?= (int)$s['id'] ?>" style="gap:0;">
                                    <?= h($s['assunto']) ?>
                                </a>
                            </div>
                            <div class="m">
                                <?= h($s['cliente_nome']) ?>
                                • <b><?= h(!empty($s['responsavel_nome']) ? $s['responsavel_nome'] : '-') ?></b>
                                • <?= h(labelTipoSuporte($s['tipo_contato'])) ?>
                                • <?= h(date('d/m/Y H:i', strtotime($s['criado_em']))) ?>
                            </div>

                        </div>
                        <div class="right">
                            <div><span class="badge badge-dark"><?= h(labelCrit($s['criticidade'])) ?></span></div>
                            <div style="margin-top:6px;"><span class="badge"><?= h(labelStatusSuporte($s['status'])) ?></span></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="muted" style="padding:22px 10px;text-align:center;">Nenhum suporte registrado ainda.</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
