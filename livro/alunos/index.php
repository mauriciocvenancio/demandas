<?php
require_once 'config.php';
require_once '../auth.php';

$busca    = isset($_GET['busca'])    ? trim($_GET['busca'])    : '';
$filtro_t = isset($_GET['turma_id']) ? (int)$_GET['turma_id'] : 0;

// Carregar turmas para o filtro
$turmas = $pdo->query("SELECT * FROM turmas ORDER BY FIELD(dia_semana,'Segunda','Terça','Quarta','Quinta'), horario, id")->fetchAll();

// Buscar alunos
$sql = "
    SELECT a.*,
           GROUP_CONCAT(CONCAT(t.dia_semana,' ',t.horario,' ',t.descricao) ORDER BY t.id SEPARATOR ' | ') AS turmas_txt
    FROM alunos a
    LEFT JOIN aluno_turmas at2 ON at2.aluno_id = a.id
    LEFT JOIN turmas t ON t.id = at2.turma_id
";
$params = array();
$where  = array();

if ($busca !== '') {
    $where[] = "(a.nome LIKE :busca OR a.responsavel LIKE :busca OR a.whatsapp LIKE :busca)";
    $params[':busca'] = '%' . $busca . '%';
}
if ($filtro_t > 0) {
    $where[] = "a.id IN (SELECT aluno_id FROM aluno_turmas WHERE turma_id = :tid)";
    $params[':tid'] = $filtro_t;
}
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " GROUP BY a.id ORDER BY a.nome ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$alunos = $stmt->fetchAll();
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Alunos</title>
<style>
*{box-sizing:border-box;}
body{font-family:Arial,sans-serif;margin:0;background:#f4f6f9;color:#222;}
.topbar{background:#1a6bbf;color:#fff;padding:0 24px;display:flex;align-items:center;gap:4px;height:48px;}
.topbar h1{margin:0;font-size:18px;margin-right:12px;}
.topbar a{color:#fff;text-decoration:none;font-size:14px;padding:7px 12px;border-radius:6px;opacity:.85;white-space:nowrap;}
.topbar a:hover{background:rgba(255,255,255,.18);opacity:1;}
.topbar a.ativo{background:rgba(255,255,255,.22);opacity:1;}
.topbar-sep{flex:1;}
.topbar-user{font-size:13px;opacity:.75;white-space:nowrap;}
.topbar a.btn-sair{background:#b00020;opacity:1;}
.topbar a.btn-sair:hover{background:#8c0019;}
.container{max-width:1100px;margin:24px auto;padding:0 16px;}
.card{background:#fff;border:1px solid #dde;border-radius:12px;padding:20px;margin-bottom:20px;}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;}
label{display:block;font-size:12px;color:#555;margin-bottom:4px;}
input,select{padding:8px 10px;border:1px solid #ccc;border-radius:8px;font-size:14px;}
input[type=text]{min-width:220px;}
.btn{display:inline-block;padding:9px 16px;border:0;border-radius:8px;cursor:pointer;font-size:14px;text-decoration:none;}
.btn-primary{background:#1a6bbf;color:#fff;}
.btn-primary:hover{background:#1558a0;}
.btn-success{background:#0b7a3e;color:#fff;}
.btn-success:hover{background:#085f2f;}
.btn-edit{background:#555;color:#fff;}
.btn-edit:hover{background:#333;}
.btn-del{background:#b00020;color:#fff;}
.btn-del:hover{background:#8c0019;}
.btn-clear{background:#eee;color:#333;}
.btn-clear:hover{background:#ddd;}
.alert-ok{background:#d4edda;color:#155724;border:1px solid #c3e6cb;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px;}
.alert-err{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px;}
table{width:100%;border-collapse:collapse;font-size:14px;}
th{background:#f0f4fa;padding:10px 12px;text-align:left;border-bottom:2px solid #dde;font-size:13px;color:#444;}
td{padding:10px 12px;border-bottom:1px solid #eef;vertical-align:top;}
tr:hover td{background:#fafbff;}
.badge-resp{background:#fff3cd;color:#856404;border:1px solid #ffc107;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:bold;}
.turmas-lista{font-size:12px;color:#555;line-height:1.7;}
.turma-tag{display:inline-block;background:#e8f0fb;color:#1a4d9e;border-radius:6px;padding:2px 8px;margin:2px 2px 2px 0;font-size:11px;}
.total{font-size:13px;color:#666;margin-bottom:10px;}
.acoes{display:flex;gap:6px;flex-wrap:wrap;}
</style>
</head>
<body>

<div class="topbar">
    <h1>Sistema</h1>
    <a href="../index.php">Livro Caixa</a>
    <a href="index.php" class="ativo">Alunos</a>
    <a href="painel_turmas.php">Painel de Turmas</a>
    <span class="topbar-sep"></span>
    <span class="topbar-user">👤 <?= htmlspecialchars(isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : '', ENT_QUOTES, 'UTF-8') ?></span>
    <a href="../logout.php" class="btn-sair">Sair</a>
</div>

<div class="container">

<?php if (!empty($_GET['msg'])): ?>
    <div class="alert-ok"><?= h($_GET['msg']) ?></div>
<?php endif; ?>
<?php if (!empty($_GET['erro'])): ?>
    <div class="alert-err"><?= h($_GET['erro']) ?></div>
<?php endif; ?>

<!-- Filtros -->
<div class="card">
    <form method="get" class="row">
        <div>
            <label>Buscar (nome, responsável, WhatsApp)</label>
            <input type="text" name="busca" value="<?= h($busca) ?>" placeholder="Digite para buscar...">
        </div>
        <div>
            <label>Filtrar por turma</label>
            <select name="turma_id">
                <option value="0">Todas as turmas</option>
                <?php
                $diaAtual = '';
                foreach ($turmas as $t):
                    if ($t['dia_semana'] !== $diaAtual) {
                        if ($diaAtual !== '') echo '</optgroup>';
                        echo '<optgroup label="' . h($t['dia_semana']) . '">';
                        $diaAtual = $t['dia_semana'];
                    }
                ?>
                    <option value="<?= $t['id'] ?>" <?= $filtro_t == $t['id'] ? 'selected' : '' ?>>
                        <?= h($t['horario'] . ' — ' . $t['descricao']) ?>
                    </option>
                <?php endforeach; if ($diaAtual !== '') echo '</optgroup>'; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="index.php" class="btn btn-clear" style="margin-left:4px;">Limpar</a>
        </div>
        <div style="margin-left:auto;">
            <a href="cadastrar.php" class="btn btn-success">+ Novo Aluno</a>
        </div>
    </form>
</div>

<!-- Listagem -->
<div class="card">
    <p class="total"><?= count($alunos) ?> aluno(s) encontrado(s)</p>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Nome</th>
                <th>Responsável</th>
                <th>CPF</th>
                <th>WhatsApp</th>
                <th>Turmas</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$alunos): ?>
            <tr><td colspan="7" style="color:#999;text-align:center;padding:24px;">Nenhum aluno encontrado.</td></tr>
        <?php endif; ?>
        <?php foreach ($alunos as $a): ?>
            <tr>
                <td style="color:#aaa;font-size:12px;"><?= $a['id'] ?></td>
                <td><strong><?= h($a['nome']) ?></strong></td>
                <td>
                    <?php if ($a['responsavel']): ?>
                        <?= h($a['responsavel']) ?>
                        <br><span class="badge-resp">⚠ Possui responsável</span>
                    <?php else: ?>
                        <span style="color:#bbb;font-size:12px;">—</span>
                    <?php endif; ?>
                </td>
                <td><?= $a['cpf'] ? h($a['cpf']) : '<span style="color:#bbb;font-size:12px;">—</span>' ?></td>
                <td><?= $a['whatsapp'] ? h($a['whatsapp']) : '<span style="color:#bbb;font-size:12px;">—</span>' ?></td>
                <td class="turmas-lista">
                    <?php
                    $stT = $pdo->prepare("SELECT t.dia_semana, t.horario, t.descricao FROM turmas t JOIN aluno_turmas at2 ON at2.turma_id=t.id WHERE at2.aluno_id=? ORDER BY t.id");
                    $stT->execute([$a['id']]);
                    $tlist = $stT->fetchAll();
                    if ($tlist):
                        foreach ($tlist as $tt):
                            echo '<span class="turma-tag">' . h($tt['dia_semana'].' '.$tt['horario'].' '.$tt['descricao']) . '</span>';
                        endforeach;
                    else:
                        echo '<span style="color:#bbb;font-size:12px;">Sem turma</span>';
                    endif;
                    ?>
                </td>
                <td>
                    <div class="acoes">
                        <a href="editar.php?id=<?= $a['id'] ?>" class="btn btn-edit">Editar</a>
                        <form method="post" action="excluir.php" onsubmit="return confirm('Excluir o aluno <?= h(addslashes($a['nome'])) ?>?');">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button type="submit" class="btn btn-del">Excluir</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

</div><!-- /container -->
</body>
</html>
