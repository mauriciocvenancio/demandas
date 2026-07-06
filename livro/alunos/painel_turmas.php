<?php
require_once 'config.php';
require_once '../auth.php';

// Busca todas as turmas na ordem correta
$turmas = $pdo->query("
    SELECT * FROM turmas
    ORDER BY FIELD(dia_semana,'Segunda','Terca','Quarta','Quinta','Sexta','Sabado','Domingo'), horario, id
")->fetchAll();

// Para cada turma, busca os alunos
$alunos_por_turma = array();
foreach ($turmas as $t) {
    $stmt = $pdo->prepare("
        SELECT a.id, a.nome, a.responsavel
        FROM alunos a
        INNER JOIN aluno_turmas at2 ON at2.aluno_id = a.id
        WHERE at2.turma_id = ?
        ORDER BY a.nome ASC
    ");
    $stmt->execute(array($t['id']));
    $alunos_por_turma[$t['id']] = $stmt->fetchAll();
}

// Agrupa turmas por dia da semana
$por_dia = array();
foreach ($turmas as $t) {
    $por_dia[$t['dia_semana']][] = $t;
}
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Painel de Turmas</title>
<style>
*{box-sizing:border-box;}
body{font-family:Arial,sans-serif;margin:0;background:#f0f4f9;color:#222;}

/* ── Topbar ── */
.topbar{background:#1a3a6b;color:#fff;padding:0 24px;display:flex;align-items:center;gap:4px;height:48px;}
.topbar-brand{font-weight:bold;font-size:16px;margin-right:12px;white-space:nowrap;}
.topbar a{color:#fff;text-decoration:none;font-size:14px;padding:7px 12px;border-radius:6px;opacity:.85;white-space:nowrap;}
.topbar a:hover{background:rgba(255,255,255,.15);opacity:1;}
.topbar a.ativo{background:rgba(255,255,255,.22);opacity:1;}
.topbar-sep{flex:1;}
.topbar-user{font-size:13px;opacity:.75;white-space:nowrap;}
.topbar a.btn-sair{background:#b00020;opacity:1;}
.topbar a.btn-sair:hover{background:#8c0019;}

/* ── Conteúdo ── */
.container{max-width:1400px;margin:0 auto;padding:24px 20px;}

.page-header{margin-bottom:28px;}
.page-header h1{margin:0 0 4px;font-size:22px;color:#1a3a6b;}
.page-header p{margin:0;font-size:14px;color:#666;}

/* ── Seção por dia ── */
.dia-section{margin-bottom:36px;}
.dia-titulo{font-size:16px;font-weight:bold;color:#1a3a6b;letter-spacing:.5px;text-transform:uppercase;
            border-left:4px solid #1a6bbf;padding-left:10px;margin:0 0 14px;}

/* ── Grid de turmas ── */
.turmas-grid{display:flex;flex-wrap:wrap;gap:16px;}

/* ── Card de turma ── */
.turma-card{background:#fff;border:1px solid #dde;border-radius:12px;
            min-width:200px;max-width:260px;flex:1;
            display:flex;flex-direction:column;overflow:hidden;
            box-shadow:0 2px 6px rgba(0,0,0,.06);}
.card-header{background:#1a6bbf;color:#fff;padding:12px 14px;}
.card-horario{font-size:20px;font-weight:bold;line-height:1;}
.card-desc{font-size:12px;opacity:.88;margin-top:3px;}
.card-body{padding:12px 14px;flex:1;}
.card-footer{padding:6px 14px 10px;font-size:11px;color:#999;border-top:1px solid #f0f0f0;}

/* ── Lista de alunos ── */
.aluno-item{display:flex;align-items:baseline;gap:6px;padding:5px 0;
            border-bottom:1px solid #f3f3f3;font-size:13px;}
.aluno-item:last-child{border-bottom:none;}
.aluno-num{font-size:11px;color:#bbb;min-width:18px;text-align:right;}
.aluno-nome{flex:1;color:#222;}
.aluno-nome.menor{color:#777;}
.badge-resp{font-size:10px;background:#fff3cd;color:#856404;
            border:1px solid #ffc107;border-radius:4px;padding:1px 5px;white-space:nowrap;}
.sem-alunos{font-size:13px;color:#bbb;padding:8px 0;font-style:italic;}

/* cores por dia */
.dia-Segunda .card-header{background:#1a6bbf;}
.dia-Terca   .card-header{background:#0b7a3e;}
.dia-Quarta  .card-header{background:#6a1a9a;}
.dia-Quinta  .card-header{background:#b05a00;}
.dia-Sexta   .card-header{background:#8c0019;}

.dia-Segunda .dia-titulo{border-color:#1a6bbf;color:#1a6bbf;}
.dia-Terca   .dia-titulo{border-color:#0b7a3e;color:#0b7a3e;}
.dia-Quarta  .dia-titulo{border-color:#6a1a9a;color:#6a1a9a;}
.dia-Quinta  .dia-titulo{border-color:#b05a00;color:#b05a00;}
.dia-Sexta   .dia-titulo{border-color:#8c0019;color:#8c0019;}
</style>
</head>
<body>

<div class="topbar">
    <span class="topbar-brand">Sistema</span>
    <a href="../index.php">Livro Caixa</a>
    <a href="index.php">Alunos</a>
    <a href="painel_turmas.php" class="ativo">Painel de Turmas</a>
    <span class="topbar-sep"></span>
    <span class="topbar-user">&#128100; <?php echo htmlspecialchars(isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : '', ENT_QUOTES, 'UTF-8'); ?></span>
    <a href="../logout.php" class="btn-sair">Sair</a>
</div>

<div class="container">

    <div class="page-header">
        <h1>Painel de Turmas</h1>
        <p>Alunos agrupados por turma e dia da semana.</p>
    </div>

    <?php if (empty($por_dia)): ?>
        <p style="color:#888;">Nenhuma turma cadastrada.</p>
    <?php endif; ?>

    <?php foreach ($por_dia as $dia => $lista_turmas):
        $diaSlug = str_replace(array('ç','ã','á','é'), array('c','a','a','e'), $dia);
    ?>
    <div class="dia-section dia-<?php echo htmlspecialchars($diaSlug, ENT_QUOTES, 'UTF-8'); ?>">
        <h2 class="dia-titulo"><?php echo htmlspecialchars($dia, ENT_QUOTES, 'UTF-8'); ?>-feira</h2>

        <div class="turmas-grid">
        <?php foreach ($lista_turmas as $t):
            $alunos = isset($alunos_por_turma[$t['id']]) ? $alunos_por_turma[$t['id']] : array();
            $total  = count($alunos);
        ?>
            <div class="turma-card">
                <div class="card-header">
                    <div class="card-horario"><?php echo htmlspecialchars($t['horario'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="card-desc"><?php echo htmlspecialchars($t['descricao'], ENT_QUOTES, 'UTF-8'); ?></div>
                </div>

                <div class="card-body">
                    <?php if (!$alunos): ?>
                        <p class="sem-alunos">Sem alunos cadastrados</p>
                    <?php else: ?>
                        <?php foreach ($alunos as $i => $a): ?>
                            <div class="aluno-item">
                                <span class="aluno-num"><?php echo ($i + 1); ?></span>
                                <span class="aluno-nome<?php echo $a['responsavel'] ? ' menor' : ''; ?>">
                                    <?php echo htmlspecialchars($a['nome'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <?php if ($a['responsavel']): ?>
                                    <span class="badge-resp">menor</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="card-footer">
                    <?php echo $total; ?> aluno<?php echo $total !== 1 ? 's' : ''; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

</div><!-- /container -->
</body>
</html>
