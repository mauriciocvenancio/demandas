# Plano de Atualização — Campo Tipo de Demanda

**Data:** 06/07/2026  
**Status:** Concluído (ambiente local) — pendente aplicação em produção

---

## Descrição da mudança

Adição de dois novos campos no cadastro e edição de demandas:

- **Tipo** — select com as opções: Bug, Solicitação novo item ou melhoria, Uso incorreto do Cliente, Orientações e Dúvidas
- **Nome do Solicitante** — campo de texto que aparece somente quando o tipo selecionado for "Solicitação novo item ou melhoria"

---

## 1. SQL — Executar no banco de dados

```sql
ALTER TABLE demandas
    ADD COLUMN tipo_demanda VARCHAR(50) NULL DEFAULT NULL AFTER criticidade;

ALTER TABLE demandas
    ADD COLUMN nome_solicitante VARCHAR(200) NULL DEFAULT NULL AFTER tipo_demanda;
```

> Executar no banco: **pitfallcom_demanda**

---

## 2. Arquivos a atualizar

### `demandas_process.php`

Adicionar após a linha com `$allowedCrit`:

```php
$allowedTipo = array('bug','solicitacao_novo_item','uso_incorreto','orientacoes_duvidas');
```

No bloco `action === 'create'`, após `$prazoDb`:

```php
$tipo_demanda     = allowedOrDefault(post('tipo_demanda'), $allowedTipo, null);
if (!in_array($tipo_demanda, $allowedTipo, true)) $tipo_demanda = null;
$nome_solicitante = ($tipo_demanda === 'solicitacao_novo_item' && post('nome_solicitante') !== '')
                    ? post('nome_solicitante') : null;
```

Alterar o INSERT para incluir os novos campos:

```php
$stmt = $pdo->prepare("
    INSERT INTO demandas
    (titulo, descricao, id_cliente, id_responsavel, status, criticidade, prazo, tipo_demanda, nome_solicitante, ativo, criado_em)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
");
$stmt->execute(array(
    $titulo,
    ($descricao !== '' ? $descricao : null),
    $id_cliente,
    $id_resp,
    $status,
    $criticidade,
    $prazoDb,
    $tipo_demanda,
    $nome_solicitante
));
```

No bloco `action === 'update'`, mesmo trecho de leitura dos campos acima, e alterar o UPDATE:

```php
$st = $pdo->prepare("
    UPDATE demandas
    SET titulo=?, descricao=?, id_cliente=?, id_responsavel=?, status=?, criticidade=?, prazo=?,
        tipo_demanda=?, nome_solicitante=?, atualizado_em=NOW()
    WHERE id=?
    LIMIT 1
");
$st->execute(array(
    $titulo,
    ($descricao !== '' ? $descricao : null),
    $id_cliente,
    $id_resp,
    $status,
    $criticidade,
    $prazoDb,
    $tipo_demanda,
    $nome_solicitante,
    $id
));
```

---

### `demandas.php` (modal de criação)

Adicionar após o bloco de Prazo e antes da Descrição:

```html
<div class="grid">
    <div>
        <label>Tipo</label>
        <select name="tipo_demanda" id="tipo_demanda_create" onchange="toggleSolicitante(this,'solicitante_create')">
            <option value="">Selecione o tipo</option>
            <option value="bug">Bug</option>
            <option value="solicitacao_novo_item">Solicitação novo item ou melhoria</option>
            <option value="uso_incorreto">Uso incorreto do Cliente</option>
            <option value="orientacoes_duvidas">Orientações e Dúvidas</option>
        </select>
    </div>
    <div id="solicitante_create" style="display:none;">
        <label>Nome do Solicitante</label>
        <input type="text" name="nome_solicitante" placeholder="Nome de quem solicitou">
    </div>
</div>
```

Adicionar no bloco `<script>` existente:

```javascript
function toggleSolicitante(sel, boxId){
    var box = document.getElementById(boxId);
    box.style.display = (sel.value === 'solicitacao_novo_item') ? 'block' : 'none';
}
```

---

### `demanda_edit.php` (formulário de edição)

Adicionar após o bloco de Prazo e antes da Descrição:

```html
<div class="grid">
    <div>
        <label>Tipo</label>
        <select name="tipo_demanda" id="tipo_demanda_edit" onchange="toggleSolicitante(this,'solicitante_edit')">
            <option value="">Selecione o tipo</option>
            <option value="bug" <?= ($demanda['tipo_demanda']==='bug')?'selected':''; ?>>Bug</option>
            <option value="solicitacao_novo_item" <?= ($demanda['tipo_demanda']==='solicitacao_novo_item')?'selected':''; ?>>Solicitação novo item ou melhoria</option>
            <option value="uso_incorreto" <?= ($demanda['tipo_demanda']==='uso_incorreto')?'selected':''; ?>>Uso incorreto do Cliente</option>
            <option value="orientacoes_duvidas" <?= ($demanda['tipo_demanda']==='orientacoes_duvidas')?'selected':''; ?>>Orientações e Dúvidas</option>
        </select>
    </div>
    <div id="solicitante_edit" style="display:<?= ($demanda['tipo_demanda']==='solicitacao_novo_item')?'block':'none'; ?>;">
        <label>Nome do Solicitante</label>
        <input type="text" name="nome_solicitante" value="<?= h($demanda['nome_solicitante']) ?>" placeholder="Nome de quem solicitou">
    </div>
</div>
```

Adicionar antes do `require layout_bottom.php`:

```html
<script>
    function toggleSolicitante(sel, boxId){
        var box = document.getElementById(boxId);
        box.style.display = (sel.value === 'solicitacao_novo_item') ? 'block' : 'none';
    }
</script>
```

---

## Ordem de aplicação em produção

1. Executar o SQL no banco de dados
2. Substituir `demandas_process.php`
3. Substituir `demandas.php`
4. Substituir `demanda_edit.php`

---

## Observações

- Campos existentes no banco não são afetados (colunas adicionadas como `NULL DEFAULT NULL`)
- Demandas antigas terão `tipo_demanda = NULL` e `nome_solicitante = NULL` — sem quebra de dados
- O campo Nome do Solicitante é apagado automaticamente no processo se o tipo for alterado para outro que não "Solicitação novo item ou melhoria"
