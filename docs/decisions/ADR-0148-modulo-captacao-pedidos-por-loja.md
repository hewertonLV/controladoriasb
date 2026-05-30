# ADR-0148: Entrada do módulo Captação em Captação por loja

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Hub de módulos (ADR-0146) e perfil Vendedor (ADR-0147).

## Contexto

O módulo Captação no hub apontava para a listagem de lotes (`/admin/captacao/lotes`). Vendedores operam pela tela **Captação por loja**, não pelo pipeline administrativo de lotes.

## Decisão

- Entrada do módulo Captação no hub: **`admin.captacao.pedidos-por-loja.carteiras`**.
- A tela de carteiras inclui o formulário **Abrir captação do dia** (data + carteira), reutilizando o POST existente `admin.captacao.lotes.store`.
- Após criar captação com `app_modulo = captacao` na sessão, redirecionar para **lojas da carteira** (`pedidos-por-loja.lojas`), não para o detalhe do lote.
- Fora do contexto do módulo Captação (admin via sidebar), o redirect pós-criação permanece em `lotes.show`.

## Alternativas consideradas

- Tela dedicada só para vendedor — rejeitado: duplicaria formulário e regras já existentes.
- Manter lotes.index como entrada — rejeitado: expõe fluxo administrativo inadequado ao vendedor.

## Consequências

- Vendedor abre e captura sem passar pela listagem de lotes.
- Formulário de abertura compartilhado via partial `_abrir-captacao-form.blade.php`.
