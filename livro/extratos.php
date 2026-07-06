<?php
require_once 'config.php';
require_once 'auth.php';

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$uploadsDir = __DIR__ . '/uploads/';
$msg  = isset($_GET['msg'])  ? $_GET['msg']  : '';
$erro = isset($_GET['erro']) ? $_GET['erro'] : '';

// ── Excluir extrato ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir'])) {
    $nomeExcluir = basename($_POST['excluir']);
    if (preg_match('/^extrato_[\w]+\.pdf$/i', $nomeExcluir)) {
        $caminho = $uploadsDir . $nomeExcluir;
        if (is_file($caminho)) {
            @unlink($caminho);
            header('Location: extratos.php?msg=' . urlencode('Extrato excluído com sucesso.'));
        } else {
            header('Location: extratos.php?erro=' . urlencode('Arquivo não encontrado.'));
        }
    } else {
        header('Location: extratos.php?erro=' . urlencode('Arquivo inválido.'));
    }
    exit;
}

// ── Listar extratos ──────────────────────────────────────────────────────────
$extratos = array();
if (is_dir($uploadsDir)) {
    foreach (glob($uploadsDir . 'extrato_*.pdf') as $caminho) {
        $nome    = basename($caminho);
        $tamanho = filesize($caminho);
        // Extrair data/hora do nome: extrato_YYYYMMDD_HHiiss_hash.pdf
        $dataHora = '';
        if (preg_match('/^extrato_(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})_/', $nome, $m)) {
            $dataHora = $m[3] . '/' . $m[2] . '/' . $m[1] . ' ' . $m[4] . ':' . $m[5] . ':' . $m[6];
        }
        $extratos[] = array(
            'nome'     => $nome,
            'tamanho'  => $tamanho,
            'dataHora' => $dataHora,
            'mtime'    => filemtime($caminho),
        );
    }
    // Ordenar por data de modificação, mais recente primeiro
    usort($extratos, function($a, $b) { return $b['mtime'] - $a['mtime']; });
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Extratos – Livro Caixa</title>
    <style>
        body { font-family: Arial; margin: 20px; max-width: 1000px; }
        .card { border: 1px solid #ddd; padding: 20px; border-radius: 10px; margin-bottom: 16px; }
        h2 { margin-top: 0; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 14px; font-size: 14px; }
        .alert-ok  { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-err { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #eee; text-align: left; vertical-align: middle; }
        th { background: #f5f5f5; font-size: 13px; }
        .btn  { padding: 8px 14px; border: 0; border-radius: 8px; cursor: pointer; text-decoration: none;
                display: inline-block; font-size: 13px; color: #fff; }
        .btn-blue  { background: #1a6bbf; }
        .btn-blue:hover  { background: #155299; }
        .btn-gray  { background: #444; }
        .btn-gray:hover  { background: #222; }
        .btn-green { background: #0b7a3e; }
        .btn-green:hover { background: #085c2e; }
        .btn-del   { background: #b00020; }
        .btn-del:hover   { background: #880018; }
        .btn-view  { background: #0b5a8c; }
        .btn-view:hover  { background: #083f63; }
        .small { font-size: 12px; color: #666; }
        .row-btns { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
        .vazio { color: #888; font-size: 14px; padding: 20px 0; }

        /* Modal PDF */
        #modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.78);
                         z-index: 9999; align-items: center; justify-content: center; }
        #modal-overlay.open { display: flex; }
        #modal-box { background: #fff; border-radius: 12px; padding: 16px; width: 92vw; height: 92vh;
                     display: flex; flex-direction: column; gap: 10px; position: relative; }
        #modal-box iframe { flex: 1; border: none; border-radius: 6px; }
        #modal-header { display: flex; align-items: center; gap: 12px; }
        #modal-filename { font-size: 13px; color: #444; flex: 1; word-break: break-all; }
        #modal-close { background: #b00020; color: #fff; border: 0; border-radius: 8px;
                       padding: 7px 14px; cursor: pointer; font-size: 13px; white-space: nowrap; }
    </style>
</head>
<body>

<div class="card">
    <h2>📄 Extratos Bancários Salvos</h2>

    <?php if ($msg): ?>
        <div class="alert alert-ok"><?php echo h($msg); ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alert alert-err"><?php echo h($erro); ?></div>
    <?php endif; ?>

    <div class="row-btns">
        <a class="btn btn-green" href="importar_extrato.php">+ Importar Novo Extrato</a>
        <a class="btn btn-gray"  href="index.php">← Voltar ao Livro Caixa</a>
    </div>

    <?php if (!$extratos): ?>
        <p class="vazio">Nenhum extrato encontrado na pasta de uploads.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Data de envio</th>
                    <th>Arquivo</th>
                    <th>Tamanho</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($extratos as $i => $e): ?>
                <tr>
                    <td class="small"><?php echo ($i + 1); ?></td>
                    <td><?php echo h($e['dataHora']); ?></td>
                    <td class="small" title="<?php echo h($e['nome']); ?>">
                        <?php echo h(strlen($e['nome']) > 50 ? substr($e['nome'], 0, 50) . '...' : $e['nome']); ?>
                    </td>
                    <td class="small">
                        <?php echo $e['tamanho'] ? number_format($e['tamanho'] / 1024, 0, ',', '.') . ' KB' : '–'; ?>
                    </td>
                    <td style="white-space: nowrap;">
                        <button class="btn btn-view"
                                onclick="abrirModal('ver_extrato.php?f=<?php echo urlencode($e['nome']); ?>', '<?php echo addslashes(h($e['nome'])); ?>')"
                                title="Visualizar PDF">
                            👁 Visualizar
                        </button>

                        <a class="btn btn-blue"
                           href="ver_extrato.php?f=<?php echo urlencode($e['nome']); ?>"
                           target="_blank"
                           title="Abrir em nova aba">
                            ↗ Abrir
                        </a>

                        <form method="post" style="display:inline;"
                              onsubmit="return confirm('Excluir o extrato <?php echo addslashes(h($e['nome'])); ?>?');">
                            <input type="hidden" name="excluir" value="<?php echo h($e['nome']); ?>">
                            <button class="btn btn-del" type="submit" title="Excluir extrato">✕ Excluir</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="small" style="margin-top: 12px;">
            Total: <?php echo count($extratos); ?> extrato(s) —
            <?php
            $totalBytes = array_sum(array_column($extratos, 'tamanho'));
            echo number_format($totalBytes / 1024, 0, ',', '.') . ' KB no total';
            ?>
        </p>
    <?php endif; ?>
</div>

<!-- Modal de visualização -->
<div id="modal-overlay" onclick="fecharModal(event)">
    <div id="modal-box">
        <div id="modal-header">
            <span id="modal-filename"></span>
            <button id="modal-close" onclick="fecharModal(null, true)">Fechar ✕</button>
        </div>
        <iframe id="modal-frame" src=""></iframe>
    </div>
</div>

<script>
function abrirModal(url, nome) {
    document.getElementById('modal-filename').textContent = nome;
    document.getElementById('modal-frame').src = url;
    document.getElementById('modal-overlay').classList.add('open');
}
function fecharModal(e, force) {
    if (force || (e && e.target === document.getElementById('modal-overlay'))) {
        document.getElementById('modal-overlay').classList.remove('open');
        document.getElementById('modal-frame').src = '';
    }
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') fecharModal(null, true);
});
</script>

</body>
</html>
