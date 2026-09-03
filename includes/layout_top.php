<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_login();

$u = auth_user();

// define qual item do menu fica ativo
$menuActive = isset($menuActive) ? $menuActive : 'dashboard';
$pageTitle  = isset($pageTitle) ? $pageTitle : 'Dashboard';

// Garante conexão disponível (algumas páginas incluem layout_top antes de definir $pdo)
if (!isset($pdo)) { $pdo = db(); }

// Notificações não lidas do usuário logado
$_notifCount = 0;
$_notifs     = array();
$_pendPlanos = 0;
if ($u) {
    try {
        $stmtN = $pdo->prepare("SELECT id, mensagem, link, criado_em FROM notificacoes WHERE id_usuario=? AND lida=0 ORDER BY criado_em DESC LIMIT 10");
        $stmtN->execute(array((int)$u['id']));
        $_notifs     = $stmtN->fetchAll();
        $_notifCount = count($_notifs);
    } catch (Exception $e) { $_notifs = array(); }
    // Planos pendentes de aprovação (apenas para desenvolvedores)
    if ($u['tipo'] === 'desenvolvedor') {
        try {
            $stmtP = $pdo->query("SELECT COUNT(*) FROM planos_aprovacao WHERE status='pendente' AND ativo=1");
            $_pendPlanos  = (int)$stmtP->fetchColumn();
            $_notifCount += $_pendPlanos;
        } catch (Exception $e) { $_pendPlanos = 0; }
    }
}

function menuItem($key, $label, $href, $icon, $activeKey){
    $active = ($key === $activeKey) ? 'active' : '';
    echo '<a class="nav-item '.$active.'" href="'.$href.'">
            <span class="nav-ic">'.$icon.'</span>
            <span class="nav-tx">'.h($label).'</span>
          </a>';
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title><?= h($pageTitle) ?> - Demandas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root{
            --bg:#f6f7fb;
            --card:#ffffff;
            --text:#111827;
            --muted:#6b7280;
            --line:#e5e7eb;
            --shadow:0 8px 24px rgba(17,24,39,.06);
            --radius:14px;
            --sidebar:280px;
            --primary:#111;
        }
        *{box-sizing:border-box}
        body{margin:0;font-family:Arial,Helvetica,sans-serif;background:var(--bg);color:var(--text);}
        a{text-decoration:none;color:inherit}
        .app{display:flex;min-height:100vh;}

        /* ===== Sidebar ===== */
        .sidebar{
            width:var(--sidebar);
            background:#fff;
            border-right:1px solid var(--line);
            display:flex;
            flex-direction:column;
        }
        .brand{
            padding:18px 18px 14px;
            display:flex;
            align-items:center;
            gap:12px;
            border-bottom:1px solid var(--line);
        }
        .brand .logo{
            width:42px;height:42px;border-radius:12px;
            background:#111;display:flex;align-items:center;justify-content:center;
            color:#fff;font-size:18px;
        }
        .brand .txt .t{font-weight:700;line-height:1.1}
        .brand .txt .s{font-size:12px;color:var(--muted);margin-top:2px}

        .nav{
            padding:14px 10px;
        }
        .nav .nav-title{
            font-size:11px;
            color:var(--muted);
            padding:10px 12px;
            letter-spacing:.08em;
            text-transform:uppercase;
        }
        .nav-item{
            display:flex;align-items:center;gap:12px;
            padding:11px 12px;
            margin:4px 6px;
            border-radius:12px;
            color:#111827;
            transition:.15s;
        }
        .nav-item:hover{background:#f3f4f6}
        .nav-item.active{
            background:#111;
            color:#fff;
        }
        .nav-ic{
            width:28px;height:28px;border-radius:10px;
            display:flex;align-items:center;justify-content:center;
            font-size:15px;
            background:rgba(17,17,17,.06);
        }
        .nav-item.active .nav-ic{background:rgba(255,255,255,.14)}
        .nav-tx{font-weight:600;font-size:14px}

        .sidebar-footer{
            margin-top:auto;
            padding:12px;
            border-top:1px solid var(--line);
            background:#fff;
        }
        .userbox{
            border:1px solid var(--line);
            border-radius:14px;
            padding:12px;
            background:#fafafa;
        }
        .userbox .lbl{font-size:12px;color:var(--muted)}
        .userbox .nm{margin-top:3px;font-weight:700}
        .userbox .em{margin-top:2px;font-size:12px;color:var(--muted)}
        .userbox .actions{margin-top:10px;display:flex;gap:10px}
        .btn-link{
            display:inline-flex;align-items:center;justify-content:center;
            border:1px solid var(--line);background:#fff;color:#111;
            border-radius:10px;padding:8px 10px;font-weight:700;font-size:12px;
        }

        /* ===== Main ===== */
        .main{flex:1;display:flex;flex-direction:column;}
        .topbar{
            height:64px;
            background:#fff;
            border-bottom:1px solid var(--line);
            display:flex;
            align-items:center;
            justify-content:space-between;
            padding:0 18px;
        }
        .topbar .right{
            display:flex;align-items:center;gap:10px;
        }
        .iconbtn{
            width:38px;height:38px;border-radius:12px;
            border:1px solid var(--line);background:#fff;
            display:flex;align-items:center;justify-content:center;
            cursor:pointer;
        }
        .content{
            padding:22px 22px 40px;
        }

        /* ===== Headings ===== */
        .page-title{font-size:26px;font-weight:800;margin:0}
        .page-sub{margin-top:6px;color:var(--muted)}

        /* ===== Cards stats ===== */
        .grid-stats{
            margin-top:18px;
            display:grid;
            grid-template-columns: repeat(6, minmax(160px, 1fr));
            gap:14px;
        }
        .stat{
            background:var(--card);
            border:1px solid var(--line);
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            padding:14px 16px;
            min-height:92px;
            display:flex;
            justify-content:space-between;
            gap:10px;
        }
        .stat .k{font-size:13px;color:var(--muted);font-weight:700}
        .stat .v{font-size:24px;font-weight:900;margin-top:10px}
        .badge-ic{
            width:34px;height:34px;border-radius:12px;
            display:flex;align-items:center;justify-content:center;
            border:1px solid var(--line);
            font-size:16px;
            background:#f9fafb;
        }

        /* ===== Panels ===== */
        .grid-panels{
            margin-top:16px;
            display:grid;
            grid-template-columns: 1fr 1fr;
            gap:14px;
        }
        .panel{
            background:var(--card);
            border:1px solid var(--line);
            border-radius:var(--radius);
            box-shadow:var(--shadow);
            padding:16px;
            min-height:170px;
        }
        .panel-head{
            display:flex;align-items:flex-start;justify-content:space-between;gap:10px;
            margin-bottom:10px;
        }
        .panel-head .t{font-weight:900}
        .panel-head .s{font-size:12px;color:var(--muted);margin-top:3px}
        .panel-head .link{
            font-weight:800;
            color:#111;
            display:flex;align-items:center;gap:6px;
            padding:8px 10px;border-radius:10px;border:1px solid var(--line);
            background:#fff;
            font-size:12px;
        }
        .empty{
            height:110px;
            display:flex;align-items:center;justify-content:center;
            color:var(--muted);
            border-radius:12px;
            background:#fafafa;
            border:1px dashed #e5e7eb;
            font-size:13px;
        }

        /* ===== Responsive ===== */
        @media (max-width: 1200px){
            .grid-stats{grid-template-columns: repeat(3, minmax(160px, 1fr));}
            .grid-panels{grid-template-columns: 1fr;}
        }
        @media (max-width: 860px){
            .sidebar{display:none;}
            .content{padding:16px}
            .grid-stats{grid-template-columns: repeat(2, minmax(140px, 1fr));}
        }
    </style>
</head>
<body>
<div class="app">

    <!-- ===== Sidebar ===== -->
    <aside class="sidebar">
        <div class="brand">
            <div class="logo">🧾</div>
            <div class="txt">
                <div class="t">Demandas</div>
                <div class="s">Sistema de Gestão</div>
            </div>
        </div>

        <nav class="nav">
            <div class="nav-title">Menu</div>
            <?php
            if (!empty($u['id_cliente'])) {
                // usuário vinculado a um cliente: vê apenas demandas
                menuItem('demandas',      'Demandas',        'demandas.php',       '📋', $menuActive);
            } else {
                // usuário interno/admin: vê tudo
                menuItem('dashboard',     'Dashboard',       'dashboard.php',      '▦',  $menuActive);
                menuItem('clientes',      'Clientes',        'clientes.php',       '👥', $menuActive);
                menuItem('demandas',      'Demandas',        'demandas.php',       '📋', $menuActive);
                menuItem('suportes',      'Suportes',        'suportes.php',       '🎧', $menuActive);
                menuItem('relatorios',    'Relatórios',      'relatorios.php',     '📊', $menuActive);
                menuItem('fila_demandas', 'Fila de Demandas','fila_demandas.php',  '📌', $menuActive);
                menuItem('graficos',       'Gráficos',          'graficos.php',        '📈', $menuActive);
                menuItem('planejamento',      'Planejamento',       'planejamento.php',       '📅', $menuActive);
                menuItem('planos_aprovacao',  'Planos p/ Aprovação','planos_aprovacao.php',   '📄', $menuActive);
                menuItem('solicitacoes',  'Solicitações',      'solicitacoes.php',    '💡', $menuActive);
            }
            ?>
        </nav>

        <div class="sidebar-footer">
            <div class="userbox">
                <div class="lbl">Logado como</div>
                <div class="nm"><?= h($u['nome']) ?></div>
                <div class="em"><?= h($u['email']) ?> • <?= h($u['tipo']) ?></div>
                <div class="actions">
                    <a class="btn-link" href="logout.php">Sair</a>
                </div>
            </div>
        </div>
    </aside>

    <!-- ===== Main ===== -->
    <main class="main">
        <header class="topbar">
            <div></div>
            <div class="right" style="position:relative;">
                <!-- Sino de notificações -->
                <button class="iconbtn" title="Notificações" id="bellBtn" onclick="toggleBell()" style="position:relative;">
                    🔔
                    <?php if ($_notifCount > 0): ?>
                    <span id="bellBadge" style="position:absolute;top:2px;right:2px;background:#ef4444;color:#fff;font-size:10px;font-weight:900;border-radius:999px;min-width:16px;height:16px;display:flex;align-items:center;justify-content:center;padding:0 3px;line-height:1;"><?= $_notifCount ?></span>
                    <?php endif; ?>
                </button>
                <!-- Dropdown notificações -->
                <div id="bellDropdown" style="display:none;position:absolute;right:0;top:44px;background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 8px 32px rgba(0,0,0,.14);width:320px;z-index:9999;overflow:hidden;">
                    <div style="padding:12px 16px;font-weight:900;font-size:13px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;">
                        <span>🔔 Notificações</span>
                        <?php if ($_notifCount > 0): ?>
                        <button onclick="marcarLidas()" style="font-size:11px;font-weight:700;color:var(--primary);background:none;border:none;cursor:pointer;">Marcar como lidas</button>
                        <?php endif; ?>
                    </div>
                    <?php if ($u['tipo'] === 'desenvolvedor' && $_pendPlanos > 0):
                        $pendPlanos = $_pendPlanos;
                        if (true): ?>
                    <a href="planos_aprovacao.php" style="display:flex;gap:10px;padding:12px 16px;text-decoration:none;color:inherit;border-bottom:1px solid var(--line);background:#fffbeb;">
                        <span style="font-size:20px;">📄</span>
                        <div>
                            <div style="font-weight:800;font-size:13px;"><?= $pendPlanos ?> plano(s) aguardando aprovação</div>
                            <div style="font-size:11px;color:var(--muted);">Clique para revisar</div>
                        </div>
                    </a>
                    <?php endif; endif; ?>
                    <?php if (empty($_notifs) && $_pendPlanos === 0): ?>
                    <div style="padding:24px;text-align:center;color:var(--muted);font-size:13px;font-weight:700;">Nenhuma notificação</div>
                    <?php else: ?>
                    <?php foreach ($_notifs as $nf): ?>
                    <a href="<?= h(isset($nf['link']) ? $nf['link'] : '#') ?>" style="display:flex;gap:10px;padding:12px 16px;text-decoration:none;color:inherit;border-bottom:1px solid var(--line);background:#f0f9ff;">
                        <span style="font-size:18px;margin-top:2px;">🔔</span>
                        <div>
                            <div style="font-size:13px;font-weight:700;line-height:1.4;"><?= h($nf['mensagem']) ?></div>
                            <div style="font-size:11px;color:var(--muted);margin-top:2px;"><?= date('d/m H:i', strtotime($nf['criado_em'])) ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button class="iconbtn" title="Ajuda">❔</button>
            </div>
        </header>
<script>
function toggleBell(){
    var d = document.getElementById('bellDropdown');
    d.style.display = d.style.display === 'none' ? 'block' : 'none';
}
function marcarLidas(){
    var CSRF = '<?= isset($_SESSION['csrf']) ? $_SESSION['csrf'] : '' ?>';
    var fd = new FormData();
    fd.append('csrf', CSRF); fd.append('action','marcar_lidas');
    fetch('planos_aprovacao_process.php',{method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(){ document.getElementById('bellBadge') && (document.getElementById('bellBadge').style.display='none'); document.getElementById('bellDropdown').style.display='none'; window.location.reload(); });
}
document.addEventListener('click', function(e){
    var btn = document.getElementById('bellBtn');
    var dd  = document.getElementById('bellDropdown');
    if (btn && dd && !btn.contains(e.target) && !dd.contains(e.target)) dd.style.display = 'none';
});
</script>

        <section class="content">
