<?php
require_once 'config.php';

function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$hoje = date('Y-m-d');
$data_ini = isset($_GET['data_ini']) && $_GET['data_ini'] ? $_GET['data_ini'] : date('Y-m-01');
$data_fim = isset($_GET['data_fim']) && $_GET['data_fim'] ? $_GET['data_fim'] : $hoje;
$tipo_filtro = isset($_GET['tipo']) ? $_GET['tipo'] : ''; // '', 'E', 'S'

$saldo_inicial = isset($_GET['saldo_inicial']) && $_GET['saldo_inicial'] !== ''
    ? (float) str_replace(',', '.', $_GET['saldo_inicial'])
    : 0.00;

// saldo anterior ao período (antes de data_ini)
$stmtPrev = $pdo->prepare("
    SELECT
      COALESCE(SUM(CASE WHEN tipo='E' THEN valor ELSE 0 END),0) as ent,
      COALESCE(SUM(CASE WHEN tipo='S' THEN valor ELSE 0 END),0) as sai
    FROM livro_caixa
    WHERE data_lancamento < :ini
");
$stmtPrev->execute(array(':ini' => $data_ini));
$prev = $stmtPrev->fetch();

$saldo_anterior = (float)$prev['ent'] - (float)$prev['sai'];
$saldo_base = $saldo_inicial + $saldo_anterior; // saldo que “entra” no período

// lançamentos ASC (para calcular saldo após)
$sqlAsc = "SELECT * FROM livro_caixa
           WHERE data_lancamento BETWEEN :ini AND :fim";
$params = array(':ini' => $data_ini, ':fim' => $data_fim);

if ($tipo_filtro === 'E' || $tipo_filtro === 'S') {
    $sqlAsc .= " AND tipo = :tipo";
    $params[':tipo'] = $tipo_filtro;
}

$sqlAsc .= " ORDER BY data_lancamento ASC, id ASC";
$stmt = $pdo->prepare($sqlAsc);
$stmt->execute($params);
$lancAsc = $stmt->fetchAll();

// totais + saldo após por lançamento
$total_entradas = 0.00;
$total_saidas   = 0.00;

$saldo_corrente = $saldo_base;
$saldoPosPorId = array();

foreach ($lancAsc as $l) {
    $v = (float)$l['valor'];
    if ($l['tipo'] === 'E') {
        $total_entradas += $v;
        $saldo_corrente += $v;
    } else {
        $total_saidas += $v;
        $saldo_corrente -= $v;
    }
    $saldoPosPorId[(int)$l['id']] = $saldo_corrente;
}

$saldo_final = $saldo_base + ($total_entradas - $total_saidas);

// exibir em DESC (mais recente primeiro)
$lancDesc = array_reverse($lancAsc);

// saldo acumulado por dia (sempre do período todo, sem filtro de tipo)
$stmtDia = $pdo->prepare("
    SELECT data_lancamento,
           SUM(CASE WHEN tipo='E' THEN valor ELSE 0 END) as entradas,
           SUM(CASE WHEN tipo='S' THEN valor ELSE 0 END) as saidas
    FROM livro_caixa
    WHERE data_lancamento BETWEEN :ini AND :fim
    GROUP BY data_lancamento
    ORDER BY data_lancamento ASC
");
$stmtDia->execute(array(':ini' => $data_ini, ':fim' => $data_fim));
$dias = $stmtDia->fetchAll();

$saldoDia = $saldo_base;
foreach ($dias as &$d) {
    $saldoDia += (float)$d['entradas'] - (float)$d['saidas'];
    $d['saldo_acumulado'] = $saldoDia;
}
unset($d);

// ---------- Anexos: carregar de uma vez para os lançamentos exibidos ----------
$ids = array();
foreach ($lancAsc as $l) { $ids[] = (int)$l['id']; }

$anexosPorLanc = array();

if (count($ids) > 0) {
    $in = implode(',', $ids);
    $stmtA = $pdo->query("SELECT * FROM livro_caixa_arquivos WHERE id_livro_caixa IN ($in) ORDER BY criado_em DESC, id DESC");
    $rowsA = $stmtA->fetchAll();

    foreach ($rowsA as $a) {
        $idl = (int)$a['id_livro_caixa'];
        if (!isset($anexosPorLanc[$idl])) $anexosPorLanc[$idl] = array();
        $anexosPorLanc[$idl][] = $a;
    }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Livro Caixa</title>
    <style>
        body{font-family:Arial; margin:20px;}
        .card{border:1px solid #ddd; padding:15px; border-radius:10px; margin-bottom:16px;}
        .row{display:flex; gap:10px; flex-wrap:wrap;}
        label{display:block; font-size:12px; color:#444;}
        input, select{padding:8px; border:1px solid #ccc; border-radius:8px; min-width:180px;}
        button,a{padding:10px 14px; border:0; border-radius:10px; cursor:pointer; text-decoration:none; display:inline-block;}
        .btn{background:#111; color:#fff;}
        .btn2{background:#444; color:#fff;}
        .btn-del{background:#b00020; color:#fff;}
        table{width:100%; border-collapse:collapse;}
        th, td{padding:10px; border-bottom:1px solid #eee; text-align:left; font-size:14px; vertical-align:top;}
        .badge{padding:3px 8px; border-radius:999px; font-size:12px; color:#fff; display:inline-block;}
        .e{background:#0b7a3e;}
        .s{background:#a11;}
        .totais{display:flex; gap:18px; flex-wrap:wrap; font-size:14px; margin-top:12px;}
        strong{font-size:16px;}
        .small{font-size:12px; color:#666;}
        .fileinput{min-width:200px;}
    </style>
</head>
<body>

<div class="card">
    <h2>Novo lançamento</h2>
    <form method="post" action="salvar.php">
        <div class="row">
            <div>
                <label>Data</label>
                <input type="date" name="data_lancamento" required value="<?php echo h($hoje); ?>">
            </div>

            <div>
                <label>Tipo</label>
                <select name="tipo" required>
                    <option value="E">Entrada</option>
                    <option value="S">Saída</option>
                </select>
            </div>

            <div>
                <label>Categoria</label>
                <input type="text" name="categoria" placeholder="Ex.: Alimentação">
            </div>

            <div style="flex:1; min-width:260px;">
                <label>Descrição</label>
                <input type="text" name="descricao" required placeholder="Ex.: Compra de material">
            </div>

            <div>
                <label>Valor</label>
                <input type="number" step="0.01" min="0" name="valor" required placeholder="0,00">
            </div>

            <div style="align-self:flex-end;">
                <button class="btn" type="submit">Salvar</button>
            </div>
        </div>
        <div class="small" style="margin-top:8px;">
            Dica: use os filtros para ver períodos e exportar CSV.
        </div>
    </form>
</div>

<div class="card">
    <h2>Filtros</h2>
    <form method="get">
        <div class="row">
            <div>
                <label>De</label>
                <input type="date" name="data_ini" value="<?php echo h($data_ini); ?>">
            </div>

            <div>
                <label>Até</label>
                <input type="date" name="data_fim" value="<?php echo h($data_fim); ?>">
            </div>

            <div>
                <label>Tipo</label>
                <select name="tipo">
                    <option value="" <?php echo ($tipo_filtro===''?'selected':''); ?>>Todos</option>
                    <option value="E" <?php echo ($tipo_filtro==='E'?'selected':''); ?>>Entradas</option>
                    <option value="S" <?php echo ($tipo_filtro==='S'?'selected':''); ?>>Saídas</option>
                </select>
            </div>

            <div>
                <label>Saldo inicial (opcional)</label>
                <input type="text" name="saldo_inicial" value="<?php echo h(number_format($saldo_inicial, 2, ',', '.')); ?>" placeholder="0,00">
            </div>

            <div style="align-self:flex-end;">
                <button class="btn" type="submit">Aplicar</button>
            </div>

            <div style="align-self:flex-end;">
                <a class="btn2"
                   href="exportar.php?data_ini=<?php echo h($data_ini); ?>&data_fim=<?php echo h($data_fim); ?>&tipo=<?php echo h($tipo_filtro); ?>&saldo_inicial=<?php echo h($saldo_inicial); ?>">
                    Exportar CSV
                </a>
            </div>
        </div>
    </form>

    <div class="totais">
        <div>Saldo inicial: <strong>R$ <?php echo number_format($saldo_inicial, 2, ',', '.'); ?></strong></div>
        <div>Saldo anterior: <strong>R$ <?php echo number_format($saldo_anterior, 2, ',', '.'); ?></strong></div>
        <div>Saldo base: <strong>R$ <?php echo number_format($saldo_base, 2, ',', '.'); ?></strong></div>
        <div>Entradas: <strong>R$ <?php echo number_format($total_entradas, 2, ',', '.'); ?></strong></div>
        <div>Saídas: <strong>R$ <?php echo number_format($total_saidas, 2, ',', '.'); ?></strong></div>
        <div>Saldo final: <strong>R$ <?php echo number_format($saldo_final, 2, ',', '.'); ?></strong></div>
    </div>
</div>

<div class="card">
    <h2>Saldo acumulado por dia</h2>
    <table>
        <thead>
        <tr>
            <th>Data</th>
            <th>Entradas</th>
            <th>Saídas</th>
            <th>Saldo acumulado</th>
        </tr>
        </thead>
        <tbody>
        <?php if(!$dias){ ?>
            <tr><td colspan="4">Sem dados no período.</td></tr>
        <?php } ?>
        <?php foreach($dias as $d){ ?>
            <tr>
                <td><?php echo h($d['data_lancamento']); ?></td>
                <td>R$ <?php echo number_format((float)$d['entradas'], 2, ',', '.'); ?></td>
                <td>R$ <?php echo number_format((float)$d['saidas'], 2, ',', '.'); ?></td>
                <td>R$ <?php echo number_format((float)$d['saldo_acumulado'], 2, ',', '.'); ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Lançamentos</h2>
    <table>
        <thead>
        <tr>
            <th>Data</th>
            <th>Tipo</th>
            <th>Categoria</th>
            <th>Descrição</th>
            <th>Valor</th>
            <th>Saldo após</th>
            <th>Arquivos</th>
            <th>Ações</th>
        </tr>
        </thead>
        <tbody>
        <?php if(!$lancDesc){ ?>
            <tr><td colspan="8">Sem lançamentos no período.</td></tr>
        <?php } ?>
        <?php foreach($lancDesc as $l){ $id=(int)$l['id']; ?>
            <tr>
                <td><?php echo h($l['data_lancamento']); ?></td>

                <td>
                    <?php if($l['tipo']==='E'){ ?>
                        <span class="badge e">Entrada</span>
                    <?php } else { ?>
                        <span class="badge s">Saída</span>
                    <?php } ?>
                </td>

                <td><?php echo h($l['categoria']); ?></td>
                <td><?php echo h($l['descricao']); ?></td>
                <td>R$ <?php echo number_format((float)$l['valor'], 2, ',', '.'); ?></td>
                <td>R$ <?php echo number_format((float)$saldoPosPorId[$id], 2, ',', '.'); ?></td>

                <td style="min-width:340px;">
                    <form method="post" action="upload_arquivo.php" enctype="multipart/form-data"
                          style="display:flex; gap:6px; align-items:center; margin-bottom:8px;">
                        <input type="hidden" name="id_livro_caixa" value="<?php echo $id; ?>">
                        <input class="fileinput" type="file" name="arquivo" required>
                        <button class="btn2" type="submit">Anexar</button>
                    </form>

                    <?php
                    $lista = isset($anexosPorLanc[$id]) ? $anexosPorLanc[$id] : array();
                    if (!$lista) {
                        echo '<span class="small">Sem anexos</span>';
                    } else {
                        foreach ($lista as $a) {
                            $arqId = (int)$a['id'];
                            $nome = h($a['nome_original']);
                            $tam = $a['tamanho'] ? number_format(((float)$a['tamanho']/1024), 0, ',', '.') . ' KB' : '';

                            echo '<div style="display:flex; gap:8px; align-items:center; margin:4px 0;">';
                            echo '<a class="btn2" href="download.php?id='.$arqId.'" style="padding:6px 10px;">Baixar</a>';
                            echo '<span class="small" title="'.$nome.'">'.(strlen($nome)>30 ? substr($nome,0,30).'...' : $nome).' '.$tam.'</span>';
                            echo '<form method="post" action="excluir_arquivo.php" style="display:inline;" onsubmit="return confirm(\'Excluir este anexo?\');">';
                            echo '<input type="hidden" name="id" value="'.$arqId.'">';
                            echo '<button class="btn-del" type="submit" style="padding:6px 10px;">X</button>';
                            echo '</form>';
                            echo '</div>';
                        }
                    }
                    ?>
                </td>

                <td>
                    <a class="btn2" href="editar.php?id=<?php echo $id; ?>">Editar</a>

                    <form method="post" action="excluir.php" style="display:inline;" onsubmit="return confirm('Excluir este lançamento?');">
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                        <button class="btn-del" type="submit">Excluir</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div>

</body>
</html>