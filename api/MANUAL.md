# Manual da API — Solicitações

**Base URL:** `https://seudominio.com.br/demandas/api/solicitacoes.php`  
**Formato:** JSON (`Content-Type: application/json`)  
**Autenticação:** Header `X-Api-Token: <token>`

---

## Autenticação

Todas as requisições precisam enviar o token no header HTTP:

```
X-Api-Token: SEU_TOKEN_AQUI
```

O token é gerado no banco de dados na tabela `api_tokens`. Caso o token seja inválido ou esteja ausente, a API retorna:

```json
HTTP 401
{
  "ok": false,
  "erro": "Token inválido ou inativo."
}
```

---

## Valores aceitos

### `tipo`
| Valor | Descrição |
|---|---|
| `novo_item` | Solicitação de nova funcionalidade |
| `melhoria` | Melhoria em algo existente |

### `prioridade`
| Valor | Descrição |
|---|---|
| `baixa` | Baixa prioridade |
| `media` | Média prioridade *(padrão)* |
| `alta` | Alta prioridade |
| `urgente` | Urgente |

### `status`
| Valor | Descrição |
|---|---|
| `nova` | Recém recebida, aguardando análise |
| `em_analise` | Em análise pela equipe |
| `aprovada` | Aprovada para desenvolvimento |
| `em_desenvolvimento` | Em desenvolvimento |
| `aguardando_homologacao` | Aguardando validação do solicitante |
| `implantada` | Entregue em produção |
| `rejeitada` | Não será implementada |
| `cancelada` | Cancelada |

---

## Endpoints

---

### `POST /` — Criar solicitação

Cria uma nova solicitação no sistema. A solicitação entra com status `nova` e origem `api`.

**Campos do body:**

| Campo | Tipo | Obrigatório | Descrição |
|---|---|---|---|
| `titulo` | string | ✅ | Título da solicitação (máx. 200 caracteres) |
| `cidade` | string | ✅ | Cidade do solicitante |
| `nome_solicitante` | string | ✅ | Nome completo do solicitante |
| `tipo` | string | — | `novo_item` ou `melhoria` *(padrão: `novo_item`)* |
| `descricao` | string | — | Descrição detalhada |
| `email_solicitante` | string | — | E-mail do solicitante |
| `telefone_solicitante` | string | — | Telefone do solicitante |
| `cargo_solicitante` | string | — | Cargo ou função |
| `prioridade` | string | — | Ver tabela acima *(padrão: `media`)* |
| `ref_externa` | string | — | ID ou referência no sistema externo |

**Requisição:**

```http
POST /demandas/api/solicitacoes.php
X-Api-Token: SEU_TOKEN_AQUI
Content-Type: application/json

{
  "tipo": "melhoria",
  "titulo": "Adicionar campo CPF no cadastro de participantes",
  "descricao": "Precisamos registrar o CPF dos participantes para emissão de relatórios obrigatórios.",
  "cidade": "Bituruna",
  "nome_solicitante": "Maria Souza",
  "email_solicitante": "maria@bituruna.pr.gov.br",
  "telefone_solicitante": "(42) 99812-3456",
  "cargo_solicitante": "Assistente Social",
  "prioridade": "alta",
  "ref_externa": "TICKET-2024-001"
}
```

**Resposta de sucesso:**

```json
HTTP 201
{
  "ok": true,
  "id": 15,
  "status": "nova"
}
```

**Erros possíveis:**

```json
HTTP 400 — campo obrigatório ausente
{ "ok": false, "erro": "Campo obrigatório: titulo." }

HTTP 400 — body inválido
{ "ok": false, "erro": "Body JSON inválido ou vazio." }
```

---

### `GET /?id=X` — Detalhe de uma solicitação

Retorna todos os dados de uma solicitação, incluindo o histórico completo de status.

**Parâmetros de URL:**

| Parâmetro | Obrigatório | Descrição |
|---|---|---|
| `id` | ✅ | ID da solicitação |

**Requisição:**

```http
GET /demandas/api/solicitacoes.php?id=15
X-Api-Token: SEU_TOKEN_AQUI
```

**Resposta de sucesso:**

```json
HTTP 200
{
  "ok": true,
  "solicitacao": {
    "id": 15,
    "tipo": "melhoria",
    "titulo": "Adicionar campo CPF no cadastro de participantes",
    "descricao": "Precisamos registrar o CPF...",
    "cidade": "Bituruna",
    "nome_solicitante": "Maria Souza",
    "email_solicitante": "maria@bituruna.pr.gov.br",
    "telefone_solicitante": "(42) 99812-3456",
    "cargo_solicitante": "Assistente Social",
    "status": "em_analise",
    "prioridade": "alta",
    "origem": "api",
    "ref_externa": "TICKET-2024-001",
    "criado_em": "2026-08-21 09:30:00",
    "atualizado_em": "2026-08-21 10:15:00",
    "ativo": "1",
    "responsavel_nome": "João Desenvolvedor",
    "historico": [
      {
        "id": 2,
        "id_solicitacao": 15,
        "status_anterior": "nova",
        "status_novo": "em_analise",
        "observacao": "Iniciando análise de viabilidade",
        "nome_usuario": "João Desenvolvedor",
        "criado_em": "2026-08-21 10:15:00"
      },
      {
        "id": 1,
        "id_solicitacao": 15,
        "status_anterior": null,
        "status_novo": "nova",
        "observacao": "Criada via API",
        "nome_usuario": "Sistema Externo",
        "criado_em": "2026-08-21 09:30:00"
      }
    ]
  }
}
```

**Erro:**

```json
HTTP 404
{ "ok": false, "erro": "Solicitação não encontrada." }
```

---

### `GET /` — Listar solicitações

Retorna uma lista de solicitações com filtros opcionais.

**Parâmetros de URL (todos opcionais):**

| Parâmetro | Tipo | Descrição |
|---|---|---|
| `cidade` | string | Filtra por cidade (busca parcial) |
| `status` | string | Filtra por status exato |
| `tipo` | string | `novo_item` ou `melhoria` |
| `limit` | inteiro | Quantidade máxima de resultados *(padrão: 100, máx: 500)* |

**Requisição:**

```http
GET /demandas/api/solicitacoes.php?cidade=Bituruna&status=nova&limit=50
X-Api-Token: SEU_TOKEN_AQUI
```

**Resposta de sucesso:**

```json
HTTP 200
{
  "ok": true,
  "total": 2,
  "solicitacoes": [
    {
      "id": 15,
      "tipo": "melhoria",
      "titulo": "Adicionar campo CPF no cadastro de participantes",
      "cidade": "Bituruna",
      "nome_solicitante": "Maria Souza",
      "status": "nova",
      "prioridade": "alta",
      "origem": "api",
      "criado_em": "2026-08-21 09:30:00"
    },
    {
      "id": 14,
      "tipo": "novo_item",
      "titulo": "Relatório mensal de atendimentos",
      "cidade": "Bituruna",
      "nome_solicitante": "Carlos Lima",
      "status": "nova",
      "prioridade": "media",
      "origem": "api",
      "criado_em": "2026-08-20 14:22:00"
    }
  ]
}
```

---

### `PATCH /?id=X` — Atualizar status

Altera o status de uma solicitação e registra a mudança no histórico.

**Parâmetros de URL:**

| Parâmetro | Obrigatório | Descrição |
|---|---|---|
| `id` | ✅ | ID da solicitação |

**Campos do body:**

| Campo | Tipo | Obrigatório | Descrição |
|---|---|---|---|
| `status` | string | ✅ | Novo status (ver tabela de valores) |
| `observacao` | string | — | Comentário sobre a mudança |

**Requisição:**

```http
PATCH /demandas/api/solicitacoes.php?id=15
X-Api-Token: SEU_TOKEN_AQUI
Content-Type: application/json

{
  "status": "em_analise",
  "observacao": "Iniciando análise de viabilidade técnica."
}
```

**Resposta de sucesso:**

```json
HTTP 200
{
  "ok": true,
  "id": 15,
  "status_anterior": "nova",
  "status_novo": "em_analise"
}
```

**Erros possíveis:**

```json
HTTP 400 — status inválido
{
  "ok": false,
  "erro": "Status inválido. Valores aceitos: nova, em_analise, aprovada, em_desenvolvimento, aguardando_homologacao, implantada, rejeitada, cancelada"
}

HTTP 404 — não encontrada
{ "ok": false, "erro": "Solicitação não encontrada." }

HTTP 400 — id ausente
{ "ok": false, "erro": "Informe ?id= na URL." }
```

---

## Fluxo sugerido de integração

```
1. Sistema externo recebe solicitação do usuário
         ↓
2. POST /api/solicitacoes.php  →  obtém { id: 15, status: "nova" }
         ↓
3. Salvar o id retornado no sistema externo (para consultas futuras)
         ↓
4. (Opcional) GET /api/solicitacoes.php?id=15  →  verificar status atual
         ↓
5. (Opcional) PATCH /api/solicitacoes.php?id=15  →  atualizar status de outro sistema
```

---

## Exemplos com cURL

**Criar solicitação:**
```bash
curl -X POST "https://seudominio.com.br/demandas/api/solicitacoes.php" \
  -H "X-Api-Token: SEU_TOKEN_AQUI" \
  -H "Content-Type: application/json" \
  -d '{
    "tipo": "melhoria",
    "titulo": "Novo relatório de atendimentos",
    "cidade": "Bituruna",
    "nome_solicitante": "Maria Souza",
    "email_solicitante": "maria@municipio.gov.br",
    "prioridade": "alta"
  }'
```

**Consultar status:**
```bash
curl -X GET "https://seudominio.com.br/demandas/api/solicitacoes.php?id=15" \
  -H "X-Api-Token: SEU_TOKEN_AQUI"
```

**Atualizar status:**
```bash
curl -X PATCH "https://seudominio.com.br/demandas/api/solicitacoes.php?id=15" \
  -H "X-Api-Token: SEU_TOKEN_AQUI" \
  -H "Content-Type: application/json" \
  -d '{"status": "aprovada", "observacao": "Aprovado em reunião de equipe."}'
```

**Listar por cidade:**
```bash
curl -X GET "https://seudominio.com.br/demandas/api/solicitacoes.php?cidade=Bituruna&status=nova" \
  -H "X-Api-Token: SEU_TOKEN_AQUI"
```

---

## Códigos de resposta HTTP

| Código | Significado |
|---|---|
| `200` | Sucesso |
| `201` | Criado com sucesso |
| `204` | Sem conteúdo (resposta a OPTIONS) |
| `400` | Requisição inválida (campo ausente, valor inválido) |
| `401` | Token ausente ou inválido |
| `404` | Recurso não encontrado |
| `405` | Método HTTP não suportado |

---

## Gerenciamento de tokens

Os tokens são gerenciados diretamente no banco de dados na tabela `api_tokens`.

**Criar novo token:**
```sql
INSERT INTO api_tokens (nome, token)
VALUES ('Nome do Sistema', SHA2(CONCAT('api_', UUID()), 256));
```

**Listar tokens ativos:**
```sql
SELECT id, nome, LEFT(token, 16) AS token_inicio, criado_em
FROM api_tokens
WHERE ativo = 1;
```

**Desativar um token:**
```sql
UPDATE api_tokens SET ativo = 0 WHERE id = 1;
```

---

*Versão 1.0 — Agosto 2026*
