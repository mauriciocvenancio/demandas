<?php
require_once 'config.php';
require_once 'auth.php';

$msg  = isset($_GET['msg'])  ? $_GET['msg']  : '';
$erro = isset($_GET['erro']) ? $_GET['erro'] : '';
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Importar Extrato – Livro Caixa</title>
    <style>
        body { font-family: Arial; margin: 20px; max-width: 900px; }
        .card { border: 1px solid #ddd; padding: 20px; border-radius: 10px; margin-bottom: 16px; }
        h2 { margin-top: 0; }
        label { display: block; font-size: 13px; color: #444; margin-bottom: 4px; }
        input[type="file"] { padding: 8px; border: 1px solid #ccc; border-radius: 8px; width: 100%; box-sizing: border-box; margin-bottom: 16px; }
        .opcoes { display: flex; flex-direction: column; gap: 10px; margin: 16px 0; }
        .opcao { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border: 2px solid #ddd; border-radius: 10px; cursor: pointer; }
        .opcao input[type="radio"] { margin-top: 3px; width: 18px; height: 18px; }
        .opcao-titulo { font-weight: bold; font-size: 14px; }
        .opcao-desc { font-size: 12px; color: #666; margin-top: 2px; }
        .btn  { padding: 11px 22px; background: #1a6bbf; color: #fff; border: 0; border-radius: 10px; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-block; }
        .btn:hover { background: #155299; }
        .btn-green { background: #0b7a3e; }
        .btn-green:hover { background: #085c2e; }
        .btn-gray { background: #444; }
        .btn-gray:hover { background: #222; }
        .row-btns { display: flex; gap: 10px; margin-top: 16px; flex-wrap: wrap; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 14px; font-size: 14px; }
        .alert-ok  { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-err { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        #preview { display: none; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 8px 10px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #f5f5f5; font-size: 12px; }
        .badge { padding: 2px 8px; border-radius: 999px; font-size: 11px; color: #fff; display: inline-block; }
        .badge-e { background: #0b7a3e; }
        .badge-s { background: #a11; }
        .valor-pos { color: #0b7a3e; font-weight: bold; }
        .valor-neg { color: #a11; font-weight: bold; }
        .resumo { display: flex; gap: 24px; flex-wrap: wrap; font-size: 14px; margin-bottom: 18px; padding: 14px; background: #f9f9f9; border-radius: 8px; }
        .resumo strong { font-size: 16px; }
        #loading { display: none; color: #555; font-size: 14px; padding: 10px 0; }
        #erro-js { display: none; }
    </style>
</head>
<body>

<div class="card">
    <h2>Importar Extrato Bancário</h2>

    <?php if ($msg): ?>
        <div class="alert alert-ok"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alert alert-err"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div id="erro-js" class="alert alert-err"></div>

    <!-- Formulário de upload (usado para "apenas salvar") -->
    <form id="form-upload" method="post" action="processar_extrato.php" enctype="multipart/form-data">
        <input type="hidden" name="acao" id="input-acao" value="salvar">

        <label>Selecione o arquivo PDF do extrato</label>
        <input type="file" name="pdf" id="campo-pdf" accept=".pdf" required>

        <p style="font-weight:bold; font-size:14px; margin-bottom:8px;">O que deseja fazer com o PDF?</p>

        <div class="opcoes">
            <label class="opcao" id="opcao-salvar">
                <input type="radio" name="acao_escolha" value="salvar" checked>
                <div>
                    <div class="opcao-titulo">Apenas salvar o PDF</div>
                    <div class="opcao-desc">O arquivo será salvo na pasta de uploads sem criar lançamentos.</div>
                </div>
            </label>

            <label class="opcao" id="opcao-importar">
                <input type="radio" name="acao_escolha" value="importar">
                <div>
                    <div class="opcao-titulo">Importar os dados do PDF</div>
                    <div class="opcao-desc">
                        O sistema lê as transações e exibe um preview antes de confirmar.
                        <br>Entradas → tipo <strong>Entrada</strong>, categoria <strong>Mensalidade</strong>.
                        <br>Saídas → tipo <strong>Saída</strong>, sem categoria/descrição.
                    </div>
                </div>
            </label>
        </div>

        <div id="loading">Lendo PDF, aguarde…</div>

        <div class="row-btns">
            <button class="btn" type="submit" id="btn-continuar">Continuar</button>
            <a class="btn btn-gray" href="extratos.php">📄 Ver Extratos Salvos</a>
            <a class="btn btn-gray" href="index.php">← Voltar</a>
        </div>
    </form>
</div>

<!-- Preview gerado pelo JavaScript -->
<div class="card" id="preview">
    <h2>Preview da Importação</h2>
    <p style="color:#555; font-size:13px;">Confira os lançamentos abaixo. Clique em <strong>Confirmar</strong> para importar.</p>

    <div class="resumo" id="resumo"></div>

    <table>
        <thead>
        <tr>
            <th>Data</th>
            <th>Tipo</th>
            <th>Categoria</th>
            <th>Descrição</th>
            <th>Valor (R$)</th>
        </tr>
        </thead>
        <tbody id="tabela-preview"></tbody>
    </table>

    <!-- Formulário de confirmação (sem arquivo — só dados parseados) -->
    <form method="post" action="processar_extrato.php" id="form-confirmar">
        <input type="hidden" name="acao" value="importar">
        <input type="hidden" name="confirmar" value="1">
        <div id="hidden-fields"></div>
        <div class="row-btns">
            <button class="btn btn-green" type="submit" id="btn-confirmar">Confirmar e Importar</button>
            <a class="btn btn-gray" href="importar_extrato.php">Cancelar</a>
        </div>
    </form>
</div>

<!-- PDF.js via CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc =
    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';

var form       = document.getElementById('form-upload');
var campoAcao  = document.getElementById('input-acao');
var campoPdf   = document.getElementById('campo-pdf');
var btnCont    = document.getElementById('btn-continuar');
var loading    = document.getElementById('loading');
var erroJs     = document.getElementById('erro-js');
var preview    = document.getElementById('preview');

function mostrarErro(msg) {
    erroJs.style.display = 'block';
    erroJs.textContent   = msg;
    loading.style.display = 'none';
    btnCont.disabled = false;
}

function formatarBR(val) {
    return val.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function parsearLinhas(texto) {
    var linhas = texto.split(/\r?\n/);
    var resultado = [];

    // padrões de linhas a ignorar
    var ignorar = [
        /^Data\s+Descri/i,
        /^SALDO\s/i,
        /^Sicredi\s+(Fone|Internet)/i,
        /^Internet Banking/i,
        /^0800\s/i,
        /^SAC\s/i,
        /^Ouvidoria/i,
        /^https?:\/\//i,
        /^--\s*\d+\s*of\s*\d+\s*--/i,
        /^Cooperativa:/i,
        /^Associado:/i,
        /^Impresso em/i,
        /^Extrato$/i,
        /^Dados referentes/i,
        /^\d{2}\/\d{2}\/\d{4},\s*\d{2}:\d{2}/
    ];

    // DD/MM/YYYY  DESCRIÇÃO (2+ espaços) DOCUMENTO  VALOR  SALDO
    var pat = /^(\d{2}\/\d{2}\/\d{4})\s+(.+?)\s{2,}(\S+)\s+([-\d.]+,\d{2})\s+([\d.]+,\d{2})\s*$/;

    for (var i = 0; i < linhas.length; i++) {
        var linha = linhas[i].trim();
        if (!linha) continue;

        var skip = false;
        for (var j = 0; j < ignorar.length; j++) {
            if (ignorar[j].test(linha)) { skip = true; break; }
        }
        if (skip) continue;

        var m = linha.match(pat);
        if (!m) continue;

        var dataRaw  = m[1];   // DD/MM/YYYY
        var descricao = m[2].trim();
        // m[3] = documento (PIX_CRED, PIX_DEB, CXnnnnnn) — não armazenamos
        var valorRaw = m[4];   // pode ter sinal negativo

        // Converter data para YYYY-MM-DD
        var partes = dataRaw.split('/');
        var data   = partes[2] + '-' + partes[1] + '-' + partes[0];

        // Converter valor BR para float
        var valorStr = valorRaw.replace(/\./g, '').replace(',', '.');
        var valor    = parseFloat(valorStr);

        var tipo, categoria, desc;
        if (valor > 0) {
            tipo      = 'E';
            categoria = 'Mensalidade';
            desc      = descricao;
        } else {
            tipo      = 'S';
            categoria = '';
            desc      = descricao;
            valor     = Math.abs(valor);
        }

        resultado.push({
            data:      data,
            tipo:      tipo,
            categoria: categoria,
            descricao: desc,
            valor:     valor
        });
    }

    return resultado;
}

function exibirPreview(transacoes) {
    var totalE = 0, totalS = 0;
    transacoes.forEach(function(t) {
        if (t.tipo === 'E') totalE += t.valor;
        else                totalS += t.valor;
    });

    document.getElementById('resumo').innerHTML =
        '<div>Registros encontrados: <strong>' + transacoes.length + '</strong></div>' +
        '<div>Entradas: <strong class="valor-pos">R$ ' + formatarBR(totalE) + '</strong></div>' +
        '<div>Saídas: <strong class="valor-neg">R$ ' + formatarBR(totalS) + '</strong></div>';

    var tbody  = document.getElementById('tabela-preview');
    var hidden = document.getElementById('hidden-fields');
    tbody.innerHTML  = '';
    hidden.innerHTML = '';

    transacoes.forEach(function(t, i) {
        // linha da tabela
        var dataBR = t.data.split('-').reverse().join('/');
        var badge  = t.tipo === 'E'
            ? '<span class="badge badge-e">Entrada</span>'
            : '<span class="badge badge-s">Saída</span>';
        var cls    = t.tipo === 'E' ? 'valor-pos' : 'valor-neg';
        var tr     = document.createElement('tr');
        tr.innerHTML =
            '<td>' + dataBR + '</td>' +
            '<td>' + badge + '</td>' +
            '<td>' + escHtml(t.categoria) + '</td>' +
            '<td>' + escHtml(t.descricao) + '</td>' +
            '<td class="' + cls + '">' + formatarBR(t.valor) + '</td>';
        tbody.appendChild(tr);

        // campos hidden para o POST de confirmação
        function addHidden(name, val) {
            var inp = document.createElement('input');
            inp.type  = 'hidden';
            inp.name  = name;
            inp.value = val;
            hidden.appendChild(inp);
        }
        addHidden('t[' + i + '][data]',      t.data);
        addHidden('t[' + i + '][tipo]',      t.tipo);
        addHidden('t[' + i + '][categoria]', t.categoria);
        addHidden('t[' + i + '][descricao]', t.descricao);
        addHidden('t[' + i + '][valor]',     t.valor.toFixed(2));
    });

    document.getElementById('btn-confirmar').textContent =
        'Confirmar e Importar ' + transacoes.length + ' lançamentos';

    preview.style.display = 'block';
    preview.scrollIntoView({ behavior: 'smooth' });

    loading.style.display = 'none';
    btnCont.disabled = false;
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

form.addEventListener('submit', function(e) {
    var acaoEscolhida = document.querySelector('input[name="acao_escolha"]:checked');
    if (!acaoEscolhida || acaoEscolhida.value !== 'importar') {
        // Apenas salvar — deixa o formulário submeter normalmente
        campoAcao.value = 'salvar';
        return;
    }

    // Importar dados — interceptar e processar via PDF.js
    e.preventDefault();
    erroJs.style.display  = 'none';
    preview.style.display = 'none';

    var arquivo = campoPdf.files[0];
    if (!arquivo) {
        mostrarErro('Selecione um arquivo PDF.');
        return;
    }

    btnCont.disabled      = true;
    loading.style.display = 'block';

    var reader = new FileReader();
    reader.onload = function(ev) {
        var typedArray = new Uint8Array(ev.target.result);

        pdfjsLib.getDocument({ data: typedArray }).promise.then(function(pdfDoc) {
            var numPages = pdfDoc.numPages;
            var textoTotal = '';
            var paginas = [];

            for (var p = 1; p <= numPages; p++) {
                paginas.push(pdfDoc.getPage(p));
            }

            Promise.all(paginas).then(function(pages) {
                var textPromises = pages.map(function(page) {
                    return page.getTextContent().then(function(content) {
                        // Agrupar itens por linha (coordenada Y, tolerância de 3px)
                        var grupos = [];
                        content.items.forEach(function(item) {
                            if (!item.str.trim()) return;
                            var y = item.transform[5];
                            var x = item.transform[4];
                            var encontrou = false;
                            for (var g = 0; g < grupos.length; g++) {
                                if (Math.abs(grupos[g].y - y) <= 3) {
                                    grupos[g].itens.push({ x: x, str: item.str });
                                    encontrou = true;
                                    break;
                                }
                            }
                            if (!encontrou) {
                                grupos.push({ y: y, itens: [{ x: x, str: item.str }] });
                            }
                        });

                        // Ordenar grupos: Y descendente (PDF começa de baixo)
                        grupos.sort(function(a, b) { return b.y - a.y; });

                        // Montar linhas: itens ordenados por X, separados por 2 espaços
                        return grupos.map(function(g) {
                            g.itens.sort(function(a, b) { return a.x - b.x; });
                            return g.itens.map(function(i) { return i.str.trim(); }).join('  ');
                        }).join('\n');
                    });
                });

                return Promise.all(textPromises);
            }).then(function(textosPaginas) {
                textoTotal = textosPaginas.join('\n');

                // Normalizar múltiplos espaços para facilitar o regex
                // Mantém \n mas colapsa múltiplos espaços em 2+ espaços onde for separador
                var transacoes = parsearLinhas(textoTotal);

                if (transacoes.length === 0) {
                    mostrarErro('Nenhuma transação encontrada no PDF. Verifique se o arquivo é o extrato correto.');
                    return;
                }

                exibirPreview(transacoes);

            }).catch(function(err) {
                mostrarErro('Erro ao ler páginas do PDF: ' + err.message);
            });

        }).catch(function(err) {
            mostrarErro('Erro ao abrir o PDF: ' + err.message);
        });
    };

    reader.onerror = function() {
        mostrarErro('Erro ao ler o arquivo no navegador.');
    };

    reader.readAsArrayBuffer(arquivo);
});
</script>

</body>
</html>
