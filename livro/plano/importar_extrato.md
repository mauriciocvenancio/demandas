# Plano: Importação de Extrato PDF

**Data:** 27/04/2026  
**Módulo:** Livro Caixa (`c:\xampp\htdocs\demandas\livro`)

---

## Contexto do PDF (Sicredi)

Cada linha de transação segue o padrão:

```
DD/MM/YYYY  DESCRIÇÃO  DOCUMENTO  VALOR(R$)  SALDO(R$)
```

### Regras de classificação

| Condição | tipo | categoria | descricao |
|---|---|---|---|
| Valor **positivo** (ex: `100,00`) | `E` (Entrada) | `Mensalidade` | Descrição completa da linha do PDF |
| Valor **negativo** (ex: `-217,72`) | `S` (Saída) | *(vazio)* | *(vazio)* |

### Linhas ignoradas no parsing

- Linha de saldo inicial (`SALDO   7.323,50`)
- Cabeçalhos de tabela (`Data  Descrição  Documento...`)
- Rodapés de página (`Sicredi Fone`, `https://ibpj...`, `-- N of N --`)
- Linha de data de impressão (`Impresso em ...`)

---

## Arquivos a criar / modificar

| Arquivo | Ação |
|---|---|
| `index.php` | Adicionar botão "Importar Extrato" |
| `importar_extrato.php` | Formulário de upload + radio (salvar / importar dados) *(novo)* |
| `processar_extrato.php` | Parsing do PDF e inserção no banco *(novo)* |
| `composer.json` | Dependência `smalot/pdfparser ^2.0` *(novo)* |
| `vendor/` | Gerado via `composer install` |

---

## Fluxo completo

```
index.php
  └── botão "Importar Extrato"
        └── importar_extrato.php

importar_extrato.php
  ├── Campo: upload de arquivo PDF
  └── Pergunta (radio):
        ○ Apenas salvar o PDF
        ○ Importar os dados do PDF
  └── POST → processar_extrato.php

processar_extrato.php
  ├── Opção: "apenas salvar"
  │     └── Salva o PDF em uploads/
  │         Redireciona para index.php com mensagem de sucesso
  │
  └── Opção: "importar dados"
        ├── Extrai texto do PDF com smalot/pdfparser
        ├── Parse linha a linha com regex
        ├── Monta array de lançamentos
        ├── Exibe tabela de PREVIEW (antes de confirmar)
        └── Confirmação → INSERT em massa em livro_caixa
                          Redireciona para index.php com contador de registros importados
```

---

## Regex de parsing por linha

```regex
/^(\d{2}\/\d{2}\/\d{4})\s+(.+?)\s{2,}(\S+)\s+([-\d.,]+)\s+([\d.,]+)/
```

| Grupo | Conteúdo |
|---|---|
| `$1` | Data (`DD/MM/YYYY`) → converter para `YYYY-MM-DD` |
| `$2` | Descrição da transação |
| `$3` | Código do documento (`PIX_CRED`, `PIX_DEB`, `CX123456`, etc.) |
| `$4` | Valor (`100,00` ou `-217,72`) → normalizar vírgula para ponto |
| `$5` | Saldo após (ignorado no insert) |

---

## Tabela do banco (`livro_caixa`)

Campos utilizados no INSERT (sem alteração de schema):

| Campo | Valor |
|---|---|
| `data_lancamento` | Data convertida (`YYYY-MM-DD`) |
| `tipo` | `'E'` ou `'S'` |
| `categoria` | `'Mensalidade'` (entradas) ou `''` (saídas) |
| `descricao` | Descrição do PDF (entradas) ou `''` (saídas) |
| `valor` | Valor absoluto (sem sinal negativo) |

---

## Dependência: smalot/pdfparser

O projeto não possui Composer. Passos necessários antes de implementar:

```bash
# Na pasta do projeto
composer require smalot/pdfparser
```

Isso gera `composer.json` e a pasta `vendor/` com autoload.

**Alternativa sem Composer:** usar `exec('pdftotext arquivo.pdf -')` — requer instalação do Poppler/Xpdf no Windows.  
**Recomendado:** Composer + smalot/pdfparser.

---

## O que NÃO será alterado

- Schema da tabela `livro_caixa` (mesmos campos)
- Fluxo de lançamento manual existente
- Lógica de autenticação (`includes/auth.php`)
- Demais arquivos do módulo (`editar.php`, `excluir.php`, etc.)

---

## Exemplos de linhas do PDF (Sicredi)

```
02/03/2026 RECEBIMENTO PIX 02308742070 Vivian Viviane Mallo    PIX_CRED    100,00 7.423,50
03/03/2026 PAGAMENTO PIX 02432180950 JOSEMARI ALVES            PIX_DEB    -217,72 7.475,78
10/03/2026 INTEGR.CAPITAL SUBSCRITO                            1            -20,00 5.759,78
```
