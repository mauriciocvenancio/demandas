<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';
require_login();

$u = auth_user();

// define qual item do menu fica ativo
$menuActive = isset($menuActive) ? $menuActive : 'dashboard';
$pageTitle  = isset($pageTitle) ? $pageTitle : 'Dashboard';

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
                menuItem('graficos',      'Gráficos',         'graficos.php',        '📈', $menuActive);
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
            <div class="right">
                <button class="iconbtn" title="Notificações">🔔</button>
                <button class="iconbtn" title="Ajuda">❔</button>
            </div>
        </header>

        <section class="content">
