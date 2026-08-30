# Plano — Módulo Planejamento

**Data:** 30/08/2026  
**Status:** Planejado — aguardando implementação

---

## Visão geral

Novo menu **Planejamento** para distribuir itens de trabalho entre desenvolvedores numa visão de calendário semanal, com estimativa de horas, drag & drop entre dias, registro de tempo real e relatório de horas planejadas × realizadas.

---

## 1. Banco de dados — SQL

### Tabela `planejamento_itens`

```sql
CREATE TABLE planejamento_itens (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    titulo           VARCHAR(300) NOT NULL,
    descricao        TEXT NULL,
    id_desenvolvedor INT NOT NULL,
    estimativa_min   DECIMAL(5,2) NOT NULL DEFAULT 0,
    estimativa_max   DECIMAL(5,2) NOT NULL DEFAULT 0,
    estimativa_media DECIMAL(5,2) NOT NULL DEFAULT 0,
    tempo_real       DECIMAL(5,2) NULL DEFAULT NULL,
    status           VARCHAR(20)  NOT NULL DEFAULT 'pendente',
    criado_em        DATETIME     NOT NULL,
    finalizado_em    DATETIME     NULL,
    ativo            TINYINT      NOT NULL DEFAULT 1
);
-- status: pendente | em_andamento | finalizado
```

### Tabela `planejamento_calendario`

```sql
CREATE TABLE planejamento_calendario (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    id_item          INT  NOT NULL,
    id_desenvolvedor INT  NOT NULL,
    data_alocada     DATE NOT NULL,
    ordem            INT  NOT NULL DEFAULT 0,
    criado_em        DATETIME NOT NULL
);
```

> Ao mover um item no calendário, apenas `data_alocada` e `ordem` são atualizados em `planejamento_calendario`.

---

## 2. Arquivos a criar/modificar

| Arquivo | Ação | Descrição |
|---------|------|-----------|
| `planejamento.php` | Criar | Calendário semanal — tela principal |
| `planejamento_process.php` | Criar | Handler de ações: create, move, finalize, delete |
| `planejamento_relatorio.php` | Criar | Relatório planejado × realizado + horas não planejadas |
| `includes/layout_top.php` | Modificar | Adicionar item **Planejamento** no menu lateral |

---

## 3. Lógica de agendamento automático

Ao cadastrar um item:

1. Pegar o **próximo dia útil** (a partir de amanhã; pular sábado/domingo)
2. Verificar quantas horas já alocadas para o dev nesse dia somando `estimativa_media` dos itens desse dia
3. Se `horas_alocadas + estimativa_media ≤ 8h` → alocar nesse dia
4. Senão → avançar para o próximo dia útil e repetir
5. Inserir em `planejamento_calendario` com a `data_alocada` encontrada

```
Exemplo:
- Dev tem 6h alocadas na terça
- Novo item com estimativa 2h e 4h → média = 3h
- 6h + 3h = 9h > 8h → passa para quarta
- Quarta tem 4h → 4h + 3h = 7h ≤ 8h → aloca na quarta
```

---

## 4. `planejamento.php` — Calendário semanal

### Layout geral

```
[ ← Semana anterior ]  Semana de 01/09 a 05/09/2026  [ Semana seguinte → ]   [ + Novo Item ]
(o calendário exibe sempre e somente as 5 colunas: SEG, TER, QUA, QUI, SEX — sem sábado e domingo)

┌─ Dev: João ─────────────────────────────────────────────────────────────────────────────┐
│ SEG 01/09   TER 02/09   QUA 03/09   QUI 04/09   SEX 05/09                              │
│ 3h total    5h total    8h total    2h total     0h total                               │
│ [card item] [card item] [card item] [card item]  (vazio)                                │
│ [card item] [card item]             [Não plan.]                                          │
└──────────────────────────────────────────────────────────────────────────────────────────┘
┌─ Dev: Maria ──────────────────────────────────────────────────────────────────────────────┐
│ ...                                                                                       │
└───────────────────────────────────────────────────────────────────────────────────────────┘
```

### Cards de item

Cada card mostra:
- Título
- Estimativa: `2h ~ 4h (média: 3h)`
- Badge de status (pendente / em andamento / finalizado)
- Botão ⚙ → opções: Finalizar (abre campo de tempo real), Editar, Remover do calendário

Cards finalizados mostram também: `Real: Xh`

### Horas não planejadas (suportes)

Em cada coluna de dia, abaixo dos cards planejados, buscar:

```sql
SELECT s.id, s.assunto, s.duracao_min, u.nome
FROM suportes s
JOIN usuarios u ON u.id = s.id_usuario_responsavel
WHERE s.ativo = 1
  AND s.status = 'finalizado'
  AND DATE(s.criado_em) = :data
ORDER BY s.criado_em
```

Exibir como bloco diferenciado (fundo amarelo/laranja) com rótulo **"Não planejado"** e horas = `duracao_min / 60`.

### Drag & Drop

- Cada card tem `draggable="true"` e evento `ondragstart`
- Cada célula de dia tem `ondragover` e `ondrop`
- Ao soltar: POST AJAX para `planejamento_process.php?action=move` com `id_item` e `nova_data`
- O servidor atualiza `planejamento_calendario.data_alocada` e recalcula `ordem`
- Frontend atualiza o card via JS (move o elemento DOM) sem reload

---

## 5. `planejamento_process.php` — Ações

| `action` | Campos POST | O que faz |
|----------|------------|-----------|
| `create` | titulo, descricao, id_desenvolvedor, estimativa_min, estimativa_max | INSERT em `planejamento_itens` + lógica de agendamento automático + INSERT em `planejamento_calendario` |
| `move` | id_item, nova_data | UPDATE `data_alocada` em `planejamento_calendario` |
| `finalize` | id_item, tempo_real | UPDATE `planejamento_itens` SET status=finalizado, tempo_real=?, finalizado_em=NOW() |
| `delete` | id_item | Soft delete `planejamento_itens.ativo=0` + DELETE de `planejamento_calendario` |

Todas as ações retornam JSON `{ok: true}` ou `{ok: false, msg: "..."}` para o frontend AJAX.

---

## 6. `planejamento_relatorio.php` — Relatório

### Filtros
- Período (data_ini / data_fim)
- Desenvolvedor

### Cards de resumo
- Total de itens planejados
- Total horas estimadas (soma de `estimativa_media`)
- Total horas realizadas (soma de `tempo_real` onde finalizado)
- Total horas não planejadas (soma de `duracao_min/60` dos suportes finalizados no período)

### Tabela — Itens planejados

| Data | Desenvolvedor | Título | Est. mín | Est. máx | Média | Tempo real | Diferença | Status |
|------|--------------|--------|----------|----------|-------|------------|-----------|--------|

Diferença = `tempo_real - estimativa_media` (negativo = foi mais rápido, positivo = estourou)

### Tabela — Horas não planejadas (suportes)

| Data | Desenvolvedor | Assunto suporte | Duração |
|------|--------------|-----------------|---------|

---

## 7. Menu lateral — `includes/layout_top.php`

Adicionar após o item "Fila de Demandas":

```php
menuItem('planejamento', 'Planejamento', 'planejamento.php', '📅', $menuActive);
```

---

## 8. Ordem de implementação

1. Executar o SQL (criar as 2 tabelas)
2. Criar `planejamento_process.php`
3. Criar `planejamento.php` (calendário + modal de cadastro)
4. Adicionar item no menu em `includes/layout_top.php`
5. Criar `planejamento_relatorio.php`

---

## 9. Observações

- Estimativa média = `(estimativa_min + estimativa_max) / 2` — calculada no PHP e salva na coluna para facilitar queries
- Dias úteis = **segunda a sexta apenas** — sábado e domingo **nunca aparecem** no calendário e nunca recebem itens (nem no agendamento automático, nem via drag & drop)
- O limite de 8h/dev/dia é soft: o sistema alerta mas o usuário pode mover manualmente para um dia já com 8h via drag & drop (sem bloqueio)
- Suportes sem `duracao_min` preenchido são ignorados nas horas não planejadas
- Devs disponíveis para seleção = `usuarios WHERE ativo=1 AND tipo='desenvolvedor'`
