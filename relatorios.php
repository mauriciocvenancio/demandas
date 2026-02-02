<?php
$menuActive = 'relatorios';
$pageTitle  = 'Relatórios';
require_once __DIR__ . '/includes/layout_top.php';
?>

<style>
    .reports-grid{
        display:grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap:14px;
        margin-top:18px;
    }

    .report-card{
        border:1px solid var(--line);
        background:#fff;
        border-radius:16px;
        padding:18px;
        display:flex;
        gap:14px;
        align-items:flex-start;
        cursor:pointer;
        transition:.15s ease;
    }

    .report-card:hover{
        transform: translateY(-2px);
        box-shadow: 0 14px 30px rgba(0,0,0,.07);
    }

    .report-icon{
        width:48px;
        height:48px;
        border-radius:14px;
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:22px;
        font-weight:900;
        flex-shrink:0;
        border:1px solid var(--line);
        background:#f8f8f8;
    }

    .report-title{
        margin:0;
        font-size:16px;
        font-weight:900;
    }

    .report-sub{
        margin-top:4px;
        color:var(--muted);
        font-size:13px;
        font-weight:700;
    }

    .report-actions{
        margin-top:12px;
        display:flex;
        gap:10px;
        flex-wrap:wrap;
    }

    .btn{
        padding:10px 14px;
        border-radius:12px;
        border:1px solid var(--line);
        background:#fff;
        font-weight:900;
        font-size:13px;
        cursor:pointer;
        text-decoration:none;
        display:inline-flex;
        align-items:center;
        gap:8px;
        color:#111;
    }

    .btn:hover{ background:#f6f6f6; }

    .btn-dark{
        background:#111;
        color:#fff;
        border-color:#111;
    }

    .btn-dark:hover{ opacity:.92; }

    @media(max-width: 980px){
        .reports-grid{
            grid-template-columns:1fr;
        }
    }
</style>

<h1 class="page-title">Relatórios</h1>
<div class="page-sub">Relatórios e indicadores</div>

<div class="reports-grid">

    <!-- Demandas -->
    <div class="report-card" onclick="window.location.href='relatorio_demandas.php'">
        <div class="report-icon">📋</div>
        <div style="flex:1;">
            <h3 class="report-title">Relatório de Demandas</h3>
            <div class="report-sub">
                Filtre por cliente, status, criticidade e período.
            </div>

            <div class="report-actions">
                <a class="btn btn-dark" href="relatorio_demandas.php" onclick="event.stopPropagation();">
                    Abrir relatório →
                </a>
            </div>
        </div>
    </div>

    <!-- Suportes -->
    <div class="report-card" onclick="window.location.href='relatorio_suportes.php'">
        <div class="report-icon">🎧</div>
        <div style="flex:1;">
            <h3 class="report-title">Relatório de Suportes</h3>
            <div class="report-sub">
                Filtre por tipo, status, período e cliente. Exporta CSV.
            </div>

            <div class="report-actions">
                <a class="btn btn-dark" href="relatorio_suportes.php" onclick="event.stopPropagation();">
                    Abrir relatório →
                </a>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
