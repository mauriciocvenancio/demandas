<?php
require_once 'config.php';

function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) die('ID inválido.');

$stmt = $pdo->prepare("SELECT * FROM livro_caixa WHERE id = :id");
$stmt->execute(array(':id' => $id));
$l = $stmt->fetch();
if (!$l) die('Lançamento não encontrado.');
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Editar lançamento</title>
<style>
body{font-family:Arial; margin:20px;}
.card{border:1px solid #ddd; padding:15px; border-radius:10px; max-width:980px;}
.row{display:flex; gap:10px; flex-wrap:wrap;}
label{display:block; font-size:12px; color:#444;}
input, select{padding:8px; border:1px solid #ccc; border-radius:8px; min-width:180px;}
button,a{padding:10px 14px; border:0; border-radius:10px; cursor:pointer; text-decoration:none; display:inline-block;}
.btn{background:#111; color:#fff;}
.btn2{background:#444; color:#fff;}
</style>
</head>
<body>

<div class="card">
  <h2>Editar lançamento #<?php echo (int)$l['id']; ?></h2>

  <form method="post" action="atualizar.php">
    <input type="hidden" name="id" value="<?php echo (int)$l['id']; ?>">

    <div class="row">
      <div>
        <label>Data</label>
        <input type="date" name="data_lancamento" required value="<?php echo h($l['data_lancamento']); ?>">
      </div>

      <div>
        <label>Tipo</label>
        <select name="tipo" required>
          <option value="E" <?php echo ($l['tipo']==='E'?'selected':''); ?>>Entrada</option>
          <option value="S" <?php echo ($l['tipo']==='S'?'selected':''); ?>>Saída</option>
        </select>
      </div>

      <div>
        <label>Categoria</label>
        <input type="text" name="categoria" value="<?php echo h($l['categoria']); ?>">
      </div>

      <div style="flex:1; min-width:260px;">
        <label>Descrição</label>
        <input type="text" name="descricao" required value="<?php echo h($l['descricao']); ?>">
      </div>

      <div>
        <label>Valor</label>
        <input type="number" step="0.01" min="0" name="valor" required value="<?php echo h(number_format((float)$l['valor'],2,'.','')); ?>">
      </div>
    </div>

    <div style="margin-top:14px;">
      <button class="btn" type="submit">Salvar alterações</button>
      <a class="btn2" href="index.php">Voltar</a>
    </div>
  </form>
</div>

</body>
</html>