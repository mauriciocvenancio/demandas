<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_login();

$pdo = db();
$u   = auth_user();

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(16));
$csrf = $_SESSION['csrf'];

date_default_timezone_set('America/Sao_Paulo');

// ── Semana exibida ──────────────────────────────────────────────────
$semanaOffset = isset($_GET['s']) ? (int)$_GET['s'] : 0;

// Segunda-feira da semana atual + offset
$hoje       = mktime(0,0,0,(int)date('m'),(int)date('d'),(int)date('Y'));
$diaSemana  = (int)date('N', $hoje); // 1=seg 7=dom
$segunda    = $hoje - ($diaSemana - 1) * 86400 + $semanaOffset * 7 * 86400;

$diasSemana = array();
for ($i = 0; $i < 5; $i++) {
    $ts = $segunda + $i * 86400;
    $diasSemana[] = array(
        'ts'    => $ts,
        'date'  => date('Y-m-d', $ts),
        'label' => array('Seg','Ter','Qua','Qui','Sex')[$i] . ' ' . date('d/m', $ts),
        'isHoje'=> date('Y-m-d', $ts) === date('Y-m-d', $hoje),
    );
}
$dataIni = $diasSemana[0]['date'];
$dataFim = $diasSemana[4]['date'];

// ── Desenvolvedores ──────────────────────────────────────────────────
$devs = $pdo->query("SELECT id, nome FROM usuarios WHERE ativo=1 AND tipo='desenvolvedor' ORDER BY nome ASC")->fetchAll();

// ── Itens do calendário na semana ────────────────────────────────────
$stmtCal = $pdo->prepare("
    SELECT
        pi.id, pi.titulo, pi.descricao,
        pi.estimativa_min, pi.estimativa_max, pi.estimativa_media,
        pi.tempo_real, pi.status,
        pc.data_alocada, pc.id_desenvolvedor, pc.ordem
    FROM planejamento_calendario pc
    JOIN planejamento_itens pi ON pi.id = pc.id_item
    WHERE pi.ativo = 1
      AND pc.data_alocada BETWEEN ? AND ?
    ORDER BY pc.id_desenvolvedor, pc.data_alocada, pc.ordem
");
$stmtCal->execute(array($dataIni, $dataFim));
$itensRaw = $stmtCal->fetchAll();

// Organizar por dev → data
$calendario = array(); // [id_dev][data] = [itens]
foreach ($devs as $dev) {
    $calendario[$dev['id']] = array();
    foreach ($diasSemana as $dia) {
        $calendario[$dev['id']][$dia['date']] = array();
    }
}
foreach ($itensRaw as $item) {
    $did  = (int)$item['id_desenvolvedor'];
    $data = $item['data_alocada'];
    if (isset($calendario[$did][$data])) {
        $calendario[$did][$data][] = $item;
    }
}

// ── Horas alocadas por dev/dia (todos os itens, inclusive finalizados) ──
$horasDia = array(); // [id_dev][data] = float
foreach ($devs as $dev) {
    $horasDia[$dev['id']] = array();
    foreach ($diasSemana as $dia) {
        $soma = 0;
        foreach ($calendario[$dev['id']][$dia['date']] as $it) {
            // finalizados usam tempo_real; demais usam estimativa_media
            if ($it['status'] === 'finalizado' && !empty($it['tempo_real'])) {
                $soma += (float)$it['tempo_real'];
            } else {
                $soma += (float)$it['estimativa_media'];
            }
        }
        $horasDia[$dev['id']][$dia['date']] = $soma;
    }
}

// ── Itens não planejados: suportes finalizados ────────────────────────
$naoPlanejados = array(); // [id_dev][data] = [itens]

$stmtSup = $pdo->prepare("
    SELECT
        DATE(s.criado_em)          AS data_np,
        s.id_usuario_responsavel   AS id_dev,
        s.assunto                  AS titulo,
        COALESCE(s.duracao_min, 30) AS duracao_min
    FROM suportes s
    WHERE s.ativo = 1
      AND s.status = 'finalizado'
      AND DATE(s.criado_em) BETWEEN ? AND ?
    ORDER BY s.criado_em
");
$stmtSup->execute(array($dataIni, $dataFim));
foreach ($stmtSup->fetchAll() as $s) {
    $did  = (int)$s['id_dev'];
    $data = $s['data_np'];
    if (!isset($naoPlanejados[$did]))       $naoPlanejados[$did] = array();
    if (!isset($naoPlanejados[$did][$data])) $naoPlanejados[$did][$data] = array();
    $naoPlanejados[$did][$data][] = array(
        'titulo'     => $s['titulo'],
        'duracao_min'=> (int)$s['duracao_min'],
        'origem'     => 'suporte',
    );
}

// ── Itens não planejados: demandas finalizadas ────────────────────────
$stmtDem = $pdo->prepare("
    SELECT
        DATE(d.atualizado_em)           AS data_np,
        d.id_responsavel                AS id_dev,
        d.titulo,
        COALESCE(d.duracao_min, 30)     AS duracao_min
    FROM demandas d
    WHERE d.ativo = 1
      AND d.status = 'finalizado'
      AND d.atualizado_em IS NOT NULL
      AND DATE(d.atualizado_em) BETWEEN ? AND ?
    ORDER BY d.atualizado_em
");
$stmtDem->execute(array($dataIni, $dataFim));
foreach ($stmtDem->fetchAll() as $dem) {
    $did  = (int)$dem['id_dev'];
    $data = $dem['data_np'];
    if (!isset($naoPlanejados[$did]))       $naoPlanejados[$did] = array();
    if (!isset($naoPlanejados[$did][$data])) $naoPlanejados[$did][$data] = array();
    $naoPlanejados[$did][$data][] = array(
        'titulo'     => $dem['titulo'],
        'duracao_min'=> (int)$dem['duracao_min'],
        'origem'     => 'demanda',
    );
}

// Todos os usuários (para selects)
$todosDevs = $pdo->query("SELECT id, nome FROM usuarios WHERE ativo=1 ORDER BY nome ASC")->fetchAll();

$menuActive = 'planejamento';
$pageTitle  = 'Planejamento';
require_once __DIR__ . '/includes/layout_top.php';
?>

<style>
    /* ── Topo ── */
    .toprow{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:18px;flex-wrap:wrap;}
    .title h1{margin:0;font-size:22px;font-weight:900;}
    .title .sub{color:var(--muted);font-size:13px;margin-top:4px;}
    .btn{height:38px;padding:0 16px;border-radius:12px;border:1px solid var(--line);background:#fff;font-weight:800;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;text-decoration:none;color:inherit;}
    .btn:hover{background:#f3f4f6;}
    .btn-dark{background:#111;color:#fff;border-color:#111;}
    .btn-dark:hover{opacity:.92;}

    /* ── Navegação de semana ── */
    .week-nav{display:flex;align-items:center;gap:10px;}
    .week-nav .label{font-weight:900;font-size:14px;white-space:nowrap;}

    /* ── Calendário ── */
    .cal-wrap{overflow-x:auto;}
    .dev-section{margin-bottom:22px;}
    .dev-header{
        font-weight:900;font-size:14px;padding:10px 14px;
        background:#111;color:#fff;border-radius:12px 12px 0 0;
        display:flex;align-items:center;gap:8px;
    }
    .cal-grid{
        display:grid;
        grid-template-columns:repeat(5, 1fr);
        border:1px solid var(--line);
        border-top:none;
        border-radius:0 0 12px 12px;
        overflow:hidden;
        background:#fff;
        min-width:900px;
    }
    .cal-col{border-right:1px solid var(--line);min-height:180px;display:flex;flex-direction:column;}
    .cal-col:last-child{border-right:none;}
    .col-head{
        padding:10px 12px;background:#f9fafb;border-bottom:1px solid var(--line);
        font-size:12px;font-weight:900;display:flex;flex-direction:column;gap:3px;
    }
    .col-head.hoje{background:#111;color:#fff;}
    .col-head .ch-date{font-size:13px;font-weight:900;}
    .col-head .ch-horas{font-size:11px;font-weight:700;opacity:.65;}
    .col-head.hoje .ch-horas{opacity:.8;}
    .col-head .ch-alert{font-size:11px;font-weight:800;color:#dc2626;}
    .col-head.hoje .ch-alert{color:#fca5a5;}
    .col-body{padding:8px;flex:1;display:flex;flex-direction:column;gap:6px;}
    .col-body.drag-over{background:#f0f9ff;outline:2px dashed #60a5fa;outline-offset:-4px;border-radius:4px;}

    /* ── Cards ── */
    .plan-card{
        border:1px solid var(--line);border-radius:10px;padding:9px 10px;background:#fff;
        font-size:12px;cursor:grab;user-select:none;position:relative;
    }
    .plan-card:active{cursor:grabbing;}
    .plan-card.finalizado{background:#f0fdf4;border-color:#86efac;}
    .plan-card .pc-title{font-weight:900;font-size:13px;margin-bottom:4px;padding-right:22px;}
    .plan-card .pc-est{color:var(--muted);font-weight:700;}
    .plan-card .pc-real{color:#16a34a;font-weight:800;margin-top:3px;}
    .plan-card .pc-status{
        display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:900;
        border:1px solid var(--line);margin-top:4px;background:#f9fafb;
    }
    .plan-card .pc-status.finalizado{background:#dcfce7;border-color:#86efac;color:#166534;}
    .plan-card .pc-status.em_andamento{background:#fef9c3;border-color:#fde047;color:#854d0e;}
    .plan-card .pc-menu-btn{
        position:absolute;top:7px;right:7px;border:none;background:transparent;
        cursor:pointer;font-size:15px;line-height:1;padding:2px 4px;border-radius:6px;
    }
    .plan-card .pc-menu-btn:hover{background:#f3f4f6;}
    .pc-menu{
        position:absolute;right:4px;top:28px;z-index:200;
        background:#fff;border:1px solid var(--line);border-radius:10px;
        min-width:160px;box-shadow:0 8px 24px rgba(0,0,0,.1);display:none;overflow:hidden;
    }
    .pc-menu button{
        display:block;width:100%;text-align:left;border:none;background:#fff;
        padding:9px 12px;font-size:12px;font-weight:800;cursor:pointer;
    }
    .pc-menu button:hover{background:#f6f6f6;}

    /* ── Suporte não planejado ── */
    .nao-plan-card{
        border:1px solid #fde68a;border-radius:10px;padding:8px 10px;
        background:#fffbeb;font-size:12px;
    }
    .nao-plan-card .np-label{font-size:10px;font-weight:900;color:#92400e;text-transform:uppercase;letter-spacing:.05em;}
    .nao-plan-card .np-title{font-weight:800;margin-top:2px;}
    .nao-plan-card .np-horas{color:#b45309;font-weight:900;margin-top:2px;}

    /* ── Modal ── */
    .modal-bd{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9000;align-items:center;justify-content:center;}
    .modal-box{background:#fff;border-radius:20px;padding:24px;width:520px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.18);display:flex;flex-direction:column;gap:16px;}
    .modal-head{display:flex;justify-content:space-between;align-items:flex-start;}
    .modal-title{font-size:17px;font-weight:900;}
    .modal-sub{font-size:12px;color:var(--muted);margin-top:3px;}
    .modal-close{border:1px solid var(--line);background:#fff;border-radius:10px;padding:6px 12px;cursor:pointer;font-size:18px;font-weight:900;}
    .field label{display:block;font-size:12px;font-weight:800;margin-bottom:5px;}
    .field input, .field select, .field textarea{
        width:100%;padding:10px 12px;border-radius:12px;border:1px solid var(--line);
        font-size:13px;outline:none;background:#fff;font-family:inherit;
    }
    .field textarea{min-height:80px;resize:vertical;}
    .est-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;}
    .est-media{
        background:#f9fafb;border:1px solid var(--line);border-radius:12px;
        padding:10px 12px;font-size:13px;font-weight:900;display:flex;align-items:center;
    }
    .modal-foot{display:flex;justify-content:flex-end;gap:10px;}

    /* ── Modal finalizar ── */
    .modal-fin-box{background:#fff;border-radius:20px;padding:24px;width:380px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.18);display:flex;flex-direction:column;gap:16px;}
</style>

<div class="toprow">
    <div class="title">
        <h1>Planejamento</h1>
        <div class="sub">Calendário semanal de desenvolvimento — máx 8h/dev/dia</div>
    </div>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <div class="week-nav">
            <a class="btn" href="?s=<?= $semanaOffset - 1 ?>">← Anterior</a>
            <span class="week-label" style="font-weight:900;font-size:13px;white-space:nowrap;">
                <?= date('d/m', $segunda) ?> – <?= date('d/m', $segunda + 4*86400) ?>
            </span>
            <a class="btn" href="?s=<?= $semanaOffset + 1 ?>">Próxima →</a>
            <?php if ($semanaOffset !== 0): ?>
                <a class="btn" href="?s=0">Hoje</a>
            <?php endif; ?>
        </div>
        <button class="btn btn-dark" onclick="abrirModal()">+ Novo Item</button>
        <a class="btn" href="planejamento_relatorio.php">Relatório</a>
    </div>
</div>

<div class="cal-wrap">
<?php foreach ($devs as $dev):
    $did = (int)$dev['id'];
    $totalSemana = 0;
    foreach ($diasSemana as $dia) { $totalSemana += $horasDia[$did][$dia['date']]; }
?>
<div class="dev-section">
    <div class="dev-header">
        <span>👤 <?= h($dev['nome']) ?></span>
        <span style="font-size:12px;font-weight:700;opacity:.7;">
            <?= number_format($totalSemana, 1) ?>h na semana
        </span>
    </div>
    <div class="cal-grid">
        <?php foreach ($diasSemana as $dia):
            $data  = $dia['date'];
            $horas = $horasDia[$did][$data];
            $alerta = $horas > 8;
        ?>
        <div class="cal-col">
            <div class="col-head <?= $dia['isHoje'] ? 'hoje' : '' ?>">
                <div class="ch-date"><?= $dia['label'] ?></div>
                <div class="ch-horas"><?= number_format($horas, 1) ?>h alocadas</div>
                <?php if ($alerta): ?>
                    <div class="ch-alert">⚠ Acima de 8h</div>
                <?php endif; ?>
            </div>
            <div class="col-body"
                 data-dev="<?= $did ?>"
                 data-date="<?= $data ?>"
                 ondragover="event.preventDefault(); this.classList.add('drag-over')"
                 ondragleave="this.classList.remove('drag-over')"
                 ondrop="onDrop(event, <?= $did ?>, '<?= $data ?>')">

                <?php foreach ($calendario[$did][$data] as $item):
                    $statusCls = $item['status'] === 'finalizado' ? 'finalizado' :
                                 ($item['status'] === 'em_andamento' ? 'em_andamento' : '');
                    $cardId = 'card-'.(int)$item['id'];
                ?>
                <div class="plan-card <?= $statusCls ?>"
                     id="<?= $cardId ?>"
                     draggable="true"
                     ondragstart="onDragStart(event, <?= (int)$item['id'] ?>)">

                    <div class="pc-title"><?= h($item['titulo']) ?></div>
                    <div class="pc-est">
                        Est: <?= number_format((float)$item['estimativa_min'],1) ?>h ~
                             <?= number_format((float)$item['estimativa_max'],1) ?>h
                        (média <?= number_format((float)$item['estimativa_media'],1) ?>h)
                    </div>
                    <?php if (!empty($item['tempo_real'])): ?>
                        <div class="pc-real">✓ Real: <?= number_format((float)$item['tempo_real'],1) ?>h</div>
                    <?php endif; ?>
                    <div class="pc-status <?= $statusCls ?>">
                        <?= $item['status'] === 'finalizado' ? 'Finalizado' :
                            ($item['status'] === 'em_andamento' ? 'Em andamento' : 'Pendente') ?>
                    </div>

                    <button class="pc-menu-btn" type="button"
                            onclick="toggleCardMenu(event, '<?= $cardId ?>')">⋯</button>

                    <div class="pc-menu" id="menu-<?= $cardId ?>">
                        <?php if ($item['status'] !== 'finalizado'): ?>
                            <button onclick="abrirEditar(<?= (int)$item['id'] ?>, <?= h(json_encode($item['titulo'])) ?>, <?= h(json_encode((string)$item['descricao'])) ?>, <?= (int)$item['id_desenvolvedor'] ?>, <?= (float)$item['estimativa_min'] ?>, <?= (float)$item['estimativa_max'] ?>)">✏️ Editar</button>
                            <button onclick="abrirFinalizar(<?= (int)$item['id'] ?>, '<?= h($item['titulo']) ?>')">✅ Finalizar</button>
                        <?php endif; ?>
                        <button onclick="excluirItem(<?= (int)$item['id'] ?>)">🗑 Remover</button>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php
                if (!empty($naoPlanejados[$did][$data])):
                    foreach ($naoPlanejados[$did][$data] as $np):
                        $hNp  = round($np['duracao_min'] / 60, 1);
                        $icon = $np['origem'] === 'demanda' ? '📋' : '🎧';
                ?>
                <div class="nao-plan-card">
                    <div class="np-label"><?= $icon ?> Não planejado · <?= $np['origem'] === 'demanda' ? 'Demanda' : 'Suporte' ?></div>
                    <div class="np-title"><?= h($np['titulo']) ?></div>
                    <div class="np-horas"><?= $hNp ?>h</div>
                </div>
                <?php   endforeach;
                endif; ?>

            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($devs)): ?>
    <div style="padding:40px;text-align:center;color:var(--muted);font-weight:800;">
        Nenhum desenvolvedor cadastrado. Cadastre usuários com tipo "desenvolvedor".
    </div>
<?php endif; ?>
</div>

<!-- ── Modal: Novo Item ──────────────────────────────────────────── -->
<div class="modal-bd" id="modalNovo" onclick="if(event.target===this)fecharModal()">
    <div class="modal-box">
        <div class="modal-head">
            <div>
                <div class="modal-title">Novo Item de Planejamento</div>
                <div class="modal-sub">O sistema agenda automaticamente no primeiro dia disponível</div>
            </div>
            <button class="modal-close" onclick="fecharModal()">×</button>
        </div>

        <div class="field">
            <label>Título *</label>
            <input type="text" id="f_titulo" placeholder="O que precisa ser feito?">
        </div>
        <div class="field">
            <label>Descrição</label>
            <textarea id="f_descricao" placeholder="Detalhe o que deve ser desenvolvido..."></textarea>
        </div>
        <div class="field">
            <label>Desenvolvedor *</label>
            <select id="f_dev">
                <option value="">Selecione</option>
                <?php foreach ($devs as $dev): ?>
                    <option value="<?= (int)$dev['id'] ?>"><?= h($dev['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="est-row">
            <div class="field">
                <label>Estimativa mín (h) *</label>
                <input type="number" id="f_est_min" min="0.5" step="0.5" placeholder="ex: 2">
            </div>
            <div class="field">
                <label>Estimativa máx (h) *</label>
                <input type="number" id="f_est_max" min="0.5" step="0.5" placeholder="ex: 4">
            </div>
            <div class="field">
                <label>Média calculada</label>
                <div class="est-media" id="f_media">—</div>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn" onclick="fecharModal()">Cancelar</button>
            <button class="btn btn-dark" onclick="salvarItem()">Agendar Item</button>
        </div>
    </div>
</div>

<!-- ── Modal: Editar Item ────────────────────────────────────────── -->
<div class="modal-bd" id="modalEditar" onclick="if(event.target===this)fecharEditar()">
    <div class="modal-box">
        <div class="modal-head">
            <div>
                <div class="modal-title">Editar Item</div>
                <div class="modal-sub">Disponível apenas antes de finalizar</div>
            </div>
            <button class="modal-close" onclick="fecharEditar()">×</button>
        </div>

        <input type="hidden" id="e_id">
        <div class="field">
            <label>Título *</label>
            <input type="text" id="e_titulo">
        </div>
        <div class="field">
            <label>Descrição</label>
            <textarea id="e_descricao"></textarea>
        </div>
        <div class="field">
            <label>Desenvolvedor *</label>
            <select id="e_dev">
                <option value="">Selecione</option>
                <?php foreach ($devs as $dev): ?>
                    <option value="<?= (int)$dev['id'] ?>"><?= h($dev['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="est-row">
            <div class="field">
                <label>Estimativa mín (h) *</label>
                <input type="number" id="e_est_min" min="0.5" step="0.5">
            </div>
            <div class="field">
                <label>Estimativa máx (h) *</label>
                <input type="number" id="e_est_max" min="0.5" step="0.5">
            </div>
            <div class="field">
                <label>Média calculada</label>
                <div class="est-media" id="e_media">—</div>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn" onclick="fecharEditar()">Cancelar</button>
            <button class="btn btn-dark" id="btnSalvarEditar" onclick="salvarEditar()">Salvar Alterações</button>
        </div>
    </div>
</div>

<!-- ── Modal: Finalizar Item ─────────────────────────────────────── -->
<div class="modal-bd" id="modalFin" onclick="if(event.target===this)fecharFinalizar()">
    <div class="modal-fin-box">
        <div class="modal-head">
            <div>
                <div class="modal-title">Finalizar Item</div>
                <div class="modal-sub" id="finTitulo" style="font-weight:700;color:#111;margin-top:4px;"></div>
            </div>
            <button class="modal-close" onclick="fecharFinalizar()">×</button>
        </div>
        <div class="field">
            <label>Tempo real gasto (horas) *</label>
            <input type="number" id="f_tempo_real" min="0.1" step="0.5" placeholder="ex: 3.5">
        </div>
        <div class="modal-foot">
            <button class="btn" onclick="fecharFinalizar()">Cancelar</button>
            <button class="btn btn-dark" onclick="confirmarFinalizar()">✅ Confirmar</button>
        </div>
    </div>
</div>

<script>
var CSRF = <?= json_encode($csrf) ?>;
var finItemId = null;

// ── Drag & Drop ──────────────────────────────────────────────────────
var dragItemId = null;
var dragCardEl = null;

function onDragStart(e, id) {
    dragItemId = id;
    dragCardEl = e.currentTarget;
    e.dataTransfer.effectAllowed = 'move';
}

function onDrop(e, devId, data) {
    e.preventDefault();
    var col = e.currentTarget;
    col.classList.remove('drag-over');
    if (!dragItemId) return;

    var fd = new FormData();
    fd.append('csrf', CSRF);
    fd.append('action', 'move');
    fd.append('id_item', dragItemId);
    fd.append('nova_data', data);

    // Feedback visual imediato: move o card na tela enquanto aguarda servidor
    var destBody = col.querySelector('.col-body');
    if (dragCardEl && destBody) {
        var npCards = destBody.querySelectorAll('.nao-plan-card');
        if (npCards.length > 0) {
            destBody.insertBefore(dragCardEl, npCards[0]);
        } else {
            destBody.appendChild(dragCardEl);
        }
        dragCardEl.style.opacity = '0.5';
    }

    fetch('planejamento_process.php', {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(res) {
            if (!res.ok) { alert(res.msg); window.location.reload(); return; }
            window.location.reload();
        })
        .catch(function(){ alert('Erro de comunicação.'); window.location.reload(); });
}

function recalcHoras() {
    // Recalcula os totais de hora nos cabeçalhos via reload simples
    // (DOM-only seria complexo; recarga silenciosa a cada drop)
    // Mantido simples: atualiza via reload na próxima interação
}

// ── Menus dos cards ──────────────────────────────────────────────────
function toggleCardMenu(e, cardId) {
    e.stopPropagation();
    closeAllCardMenus();
    var m = document.getElementById('menu-' + cardId);
    if (m) m.style.display = m.style.display === 'block' ? 'none' : 'block';
}
function closeAllCardMenus() {
    var ms = document.querySelectorAll('.pc-menu');
    for (var i=0;i<ms.length;i++) ms[i].style.display='none';
}
document.addEventListener('click', closeAllCardMenus);
document.addEventListener('keydown', function(e){ if(e.key==='Escape'){ fecharModal(); fecharFinalizar(); closeAllCardMenus(); }});

// ── Modal Novo Item ──────────────────────────────────────────────────
function abrirModal() {
    document.getElementById('f_titulo').value = '';
    document.getElementById('f_descricao').value = '';
    document.getElementById('f_dev').value = '';
    document.getElementById('f_est_min').value = '';
    document.getElementById('f_est_max').value = '';
    document.getElementById('f_media').textContent = '—';
    document.getElementById('modalNovo').style.display = 'flex';
    setTimeout(function(){ document.getElementById('f_titulo').focus(); }, 80);
}
function fecharModal() { document.getElementById('modalNovo').style.display = 'none'; }

function calcMedia() {
    var mn = parseFloat(document.getElementById('f_est_min').value) || 0;
    var mx = parseFloat(document.getElementById('f_est_max').value) || 0;
    if (mn > 0 || mx > 0) {
        document.getElementById('f_media').textContent = ((mn+mx)/2).toFixed(1) + 'h';
    } else {
        document.getElementById('f_media').textContent = '—';
    }
}
document.getElementById('f_est_min').addEventListener('input', calcMedia);
document.getElementById('f_est_max').addEventListener('input', calcMedia);

function salvarItem() {
    var titulo  = document.getElementById('f_titulo').value.trim();
    var descr   = document.getElementById('f_descricao').value.trim();
    var devId   = document.getElementById('f_dev').value;
    var estMin  = document.getElementById('f_est_min').value;
    var estMax  = document.getElementById('f_est_max').value;

    if (!titulo)            { alert('Informe o título.'); return; }
    if (!devId)             { alert('Selecione o desenvolvedor.'); return; }
    if (!estMin || !estMax) { alert('Informe as estimativas.'); return; }

    var btn = document.querySelector('#modalNovo .btn-dark');
    btn.disabled = true; btn.textContent = 'Agendando...';

    var fd = new FormData();
    fd.append('csrf', CSRF);
    fd.append('action', 'create');
    fd.append('titulo', titulo);
    fd.append('descricao', descr);
    fd.append('id_desenvolvedor', devId);
    fd.append('estimativa_min', estMin);
    fd.append('estimativa_max', estMax);

    fetch('planejamento_process.php', {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(res) {
            btn.disabled = false; btn.textContent = 'Agendar Item';
            if (!res.ok) { alert(res.msg); return; }
            fecharModal();
            window.location.reload();
        })
        .catch(function(){ btn.disabled=false; btn.textContent='Agendar Item'; alert('Erro de comunicação.'); });
}

// ── Modal Editar ─────────────────────────────────────────────────────
function abrirEditar(id, titulo, descricao, devId, estMin, estMax) {
    closeAllCardMenus();
    document.getElementById('e_id').value       = id;
    document.getElementById('e_titulo').value   = titulo;
    document.getElementById('e_descricao').value= descricao || '';
    document.getElementById('e_dev').value      = devId;
    document.getElementById('e_est_min').value  = estMin;
    document.getElementById('e_est_max').value  = estMax;
    calcMediaEditar();
    document.getElementById('modalEditar').style.display = 'flex';
    setTimeout(function(){ document.getElementById('e_titulo').focus(); }, 80);
}
function fecharEditar() { document.getElementById('modalEditar').style.display = 'none'; }

function calcMediaEditar() {
    var mn = parseFloat(document.getElementById('e_est_min').value) || 0;
    var mx = parseFloat(document.getElementById('e_est_max').value) || 0;
    document.getElementById('e_media').textContent = (mn > 0 || mx > 0) ? ((mn+mx)/2).toFixed(1)+'h' : '—';
}
document.getElementById('e_est_min').addEventListener('input', calcMediaEditar);
document.getElementById('e_est_max').addEventListener('input', calcMediaEditar);

function salvarEditar() {
    var id     = document.getElementById('e_id').value;
    var titulo = document.getElementById('e_titulo').value.trim();
    var descr  = document.getElementById('e_descricao').value.trim();
    var devId  = document.getElementById('e_dev').value;
    var estMin = document.getElementById('e_est_min').value;
    var estMax = document.getElementById('e_est_max').value;

    if (!titulo)            { alert('Informe o título.'); return; }
    if (!devId)             { alert('Selecione o desenvolvedor.'); return; }
    if (!estMin || !estMax) { alert('Informe as estimativas.'); return; }

    var btn = document.getElementById('btnSalvarEditar');
    btn.disabled = true; btn.textContent = 'Salvando...';

    var fd = new FormData();
    fd.append('csrf', CSRF);
    fd.append('action', 'edit');
    fd.append('id_item', id);
    fd.append('titulo', titulo);
    fd.append('descricao', descr);
    fd.append('id_desenvolvedor', devId);
    fd.append('estimativa_min', estMin);
    fd.append('estimativa_max', estMax);

    fetch('planejamento_process.php', {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(res) {
            btn.disabled = false; btn.textContent = 'Salvar Alterações';
            if (!res.ok) { alert(res.msg); return; }
            fecharEditar();
            window.location.reload();
        })
        .catch(function(){ btn.disabled=false; btn.textContent='Salvar Alterações'; alert('Erro de comunicação.'); });
}

// ── Modal Finalizar ──────────────────────────────────────────────────
function abrirFinalizar(id, titulo) {
    finItemId = id;
    document.getElementById('finTitulo').textContent = titulo;
    document.getElementById('f_tempo_real').value = '';
    document.getElementById('modalFin').style.display = 'flex';
    setTimeout(function(){ document.getElementById('f_tempo_real').focus(); }, 80);
}
function fecharFinalizar() { document.getElementById('modalFin').style.display = 'none'; finItemId = null; }

function confirmarFinalizar() {
    var tempo = parseFloat(document.getElementById('f_tempo_real').value);
    if (!tempo || tempo <= 0) { alert('Informe o tempo real gasto.'); return; }

    var btn = document.querySelector('#modalFin .btn-dark');
    btn.disabled = true;

    var fd = new FormData();
    fd.append('csrf', CSRF);
    fd.append('action', 'finalize');
    fd.append('id_item', finItemId);
    fd.append('tempo_real', tempo);

    fetch('planejamento_process.php', {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(res) {
            btn.disabled = false;
            if (!res.ok) { alert(res.msg); return; }
            fecharFinalizar();
            window.location.reload();
        })
        .catch(function(){ btn.disabled=false; alert('Erro de comunicação.'); });
}

// ── Excluir ──────────────────────────────────────────────────────────
function excluirItem(id) {
    if (!confirm('Remover este item do planejamento?')) return;

    var fd = new FormData();
    fd.append('csrf', CSRF);
    fd.append('action', 'delete');
    fd.append('id_item', id);

    fetch('planejamento_process.php', {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(res) {
            if (!res.ok) { alert(res.msg); return; }
            var card = document.getElementById('card-'+id);
            if (card) card.remove();
        })
        .catch(function(){ alert('Erro de comunicação.'); });
}
</script>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
