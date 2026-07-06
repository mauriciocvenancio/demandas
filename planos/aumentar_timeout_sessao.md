# Aumentar Timeout de Sessão para 1 Hora

**Data:** 23/04/2026  
**Status:** Concluído

## Problema

O sistema retornava à tela de login após ~24 minutos de inatividade, pois o `session_start()` usava o padrão do XAMPP (`session.gc_maxlifetime = 1440` segundos).

## Causa raiz

O arquivo `includes/auth.php` chamava `session_start()` sem configurar duração da sessão:

```php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

## Solução aplicada

Adicionadas duas diretivas via `ini_set` antes do `session_start()` em `includes/auth.php`:

```php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 3600);
    ini_set('session.cookie_lifetime', 3600);
    session_start();
}
```

- `session.gc_maxlifetime = 3600` — servidor mantém os dados da sessão por até 1 hora de inatividade
- `session.cookie_lifetime = 3600` — cookie de sessão no navegador também expira em 1 hora

## Arquivo modificado

- `includes/auth.php` — ponto central de autenticação, incluído por todas as páginas via `require_once`

## Observações

- Não foi necessário alterar o `php.ini` do XAMPP (evita impacto em outros projetos)
- Não foi necessário criar `.htaccess`
- Não havia lógica de inatividade no frontend para ajustar
