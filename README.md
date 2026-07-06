# Sistema de Demandas

Sistema web para gerenciamento de demandas de desenvolvimento e atendimentos de suporte, voltado para equipes internas que atendem múltiplos clientes.

---

## Funcionalidades

| Módulo | Descrição |
|--------|-----------|
| **Demandas** | Cadastro, edição e acompanhamento de demandas com status, criticidade, prazo, tipo, solicitante e anexos |
| **Fila de Demandas** | Fila pessoal do usuário logado com cards de contagem por tipo e filtro interativo |
| **Suportes** | Registro de atendimentos com tipo de contato, duração, resolução e criação automática de demanda vinculada |
| **Clientes** | CRUD de clientes com busca por nome, empresa, e-mail e telefone |
| **Dashboard** | Cards de KPIs, demandas pendentes e atendimentos recentes |
| **Relatórios** | Relatório de demandas e de suportes com filtros; exportação CSV para suportes |
| **Gráficos** | 5 gráficos Chart.js: demandas por semana, por cliente, por status; suportes por tipo e por cliente |

---

## Tecnologias

- **Backend:** PHP (PDO, sessões, `password_hash`)
- **Banco de dados:** MySQL
- **Frontend:** HTML, CSS e JavaScript vanilla
- **Gráficos:** [Chart.js 4](https://www.chartjs.org/) (via CDN, apenas em `graficos.php`)
- **Servidor local:** XAMPP

---

## Requisitos

- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Servidor web (Apache via XAMPP recomendado)
- Extensão PDO habilitada

---

## Instalação

### 1. Clonar o repositório

```bash
git clone <url-do-repositorio> C:\xampp\htdocs\demandas
```

### 2. Criar o banco de dados

Crie um banco chamado `pitfallcom_demanda` no MySQL e execute o SQL abaixo para criar as tabelas principais:

```sql
CREATE DATABASE IF NOT EXISTS pitfallcom_demanda CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pitfallcom_demanda;

CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    empresa VARCHAR(200) NULL,
    email VARCHAR(200) NULL,
    telefone VARCHAR(30) NULL,
    observacoes TEXT NULL,
    ativo TINYINT NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NULL
);

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    email VARCHAR(200) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    tipo ENUM('analista','desenvolvedor','suporte') NOT NULL DEFAULT 'suporte',
    ativo TINYINT NOT NULL DEFAULT 1,
    id_cliente INT NULL,
    criado_em DATETIME NOT NULL,
    ultimo_login DATETIME NULL
);

CREATE TABLE demandas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(300) NOT NULL,
    descricao TEXT NULL,
    id_cliente INT NOT NULL,
    id_responsavel INT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'nao_iniciado',
    criticidade VARCHAR(20) NOT NULL DEFAULT 'media',
    prazo DATE NULL,
    tipo_demanda VARCHAR(50) NULL DEFAULT NULL,
    nome_solicitante VARCHAR(200) NULL DEFAULT NULL,
    ativo TINYINT NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NULL
);

CREATE TABLE demandas_arquivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_demanda INT NOT NULL,
    nome_original VARCHAR(300) NOT NULL,
    arquivo VARCHAR(300) NOT NULL,
    mime VARCHAR(100) NULL,
    tamanho INT NULL,
    criado_em DATETIME NOT NULL
);

CREATE TABLE suportes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assunto VARCHAR(300) NOT NULL,
    descricao TEXT NULL,
    resolucao TEXT NULL,
    id_cliente INT NOT NULL,
    id_usuario_registro INT NOT NULL,
    id_usuario_responsavel INT NULL,
    tipo_contato VARCHAR(50) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'em_andamento',
    criticidade VARCHAR(20) NOT NULL DEFAULT 'media',
    duracao_min INT NULL,
    id_demanda INT NULL,
    ativo TINYINT NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL
);
```

### 3. Configurar a aplicação

Edite o arquivo `includes/config.php` com as suas credenciais:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'pitfallcom_demanda');
define('DB_USER', 'root');
define('DB_PASS', '');

define('BASE_URL', '/demandas');
```

### 4. Criar a pasta de uploads

```bash
mkdir C:\xampp\htdocs\demandas\uploads\demandas
```

A pasta é criada automaticamente na primeira vez que um anexo é enviado, mas criar manualmente garante as permissões corretas.

### 5. Criar o primeiro usuário

Acesse `http://localhost/demandas/cadastrar.php` e registre o primeiro usuário.

---

## Estrutura de pastas

```
demandas/
├── includes/
│   ├── config.php        # Configurações de banco e URL base
│   ├── db.php            # Conexão PDO singleton
│   ├── auth.php          # Sessão, login, logout (timeout: 1h)
│   ├── helpers.php       # h() escape, redirect()
│   ├── layout_top.php    # Cabeçalho e sidebar
│   └── layout_bottom.php # Fechamento do HTML
├── uploads/
│   └── demandas/         # Anexos organizados por ID de demanda
├── planos/               # Documentação de alterações aplicadas
├── livro/                # Submódulo independente (caixa e alunos)
├── login.php
├── dashboard.php
├── demandas.php
├── demanda_edit.php
├── demanda_view.php
├── demandas_process.php
├── fila_demandas.php
├── clientes.php
├── suportes.php
├── relatorios.php
├── graficos.php
└── ...
```

---

## Tipos de usuário

| Tipo | Acesso |
|------|--------|
| `analista` | Acesso completo ao sistema interno |
| `desenvolvedor` | Acesso completo; é o tipo atribuído automaticamente a demandas criadas via suporte |
| `suporte` | Acesso completo ao sistema interno |
| **Usuário cliente** | Acesso restrito apenas ao módulo Demandas, scoped ao seu cliente |

Usuários com `id_cliente` preenchido são tratados como **usuários clientes**: veem apenas suas próprias demandas e são redirecionados diretamente para `demandas.php` após o login.

---

## Tipos de demanda

| Valor | Rótulo |
|-------|--------|
| `bug` | Bug |
| `solicitacao_novo_item` | Solicitação novo item ou melhoria |
| `uso_incorreto` | Uso incorreto do Cliente |
| `orientacoes_duvidas` | Orientações e Dúvidas |

Quando o tipo é **Solicitação novo item ou melhoria**, um campo adicional `nome_solicitante` é exibido nos formulários de criação e edição.

---

## Status das demandas

| Valor | Descrição |
|-------|-----------|
| `nao_iniciado` | Aguardando início |
| `em_andamento` | Em execução |
| `aguardando_cliente` | Aguardando retorno do cliente |
| `finalizado` | Concluída |
| `publicado` | Entregue/publicada |

---

## Sessão

O tempo de inatividade da sessão é de **1 hora** (3600 segundos), configurado em `includes/auth.php`.

---

## Observações

- Exclusões são **soft delete** — registros recebem `ativo = 0` e não são apagados fisicamente
- CSRF tokens protegem todos os formulários de criação e edição
- A pasta `livro/` contém um submódulo independente (livro caixa e gestão de alunos) que compartilha o mesmo banco de dados mas tem sua própria autenticação
