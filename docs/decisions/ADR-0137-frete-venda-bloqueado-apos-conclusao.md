# ADR-0137: Frete de vendas bloqueado após «Concluir frete venda»

**Data:** 2026-05-27
**Status:** Aceito
**Contexto:** [ADR-0136](ADR-0136-status-vincular-frete-venda.md), [ADR-0125](ADR-0125-frete-vendas-por-loja-pos-finalizacao.md)

## Contexto

Na etapa `VINCULAR_FRETE_VENDA` o operador vincula frete opcional por loja. Após **Concluir frete venda** (`VENDAS_FINALIZADAS`), alterações acidentais não devem ser permitidas ao perfil operacional.

## Decisão

- Em `VINCULAR_FRETE_VENDA`: vínculo, alteração e remoção de frete permitidos (quem tem `captacao.lote.frete.vincular`).
- Em `VENDAS_FINALIZADAS`: **bloqueio** de POST `fretes/venda-loja` para demais perfis; **somente** usuário com role **Administrador** pode alterar/remover.
- UI: sem select/remover; exibe nome do frete vinculado ou «Sem frete».

## Alternativas consideradas

- Bloquear também na visualização da aba — rejeitado; consulta permanece em `VENDAS_FINALIZADAS`.
- Nova permissão granulada — rejeitado no MVP; role Administrador alinhada a cancelamentos admin de movimentação.

## Consequências

- Validação em `CaptacaoLoteFreteService::assertPodeAlterarFreteVenda`.
- Testes de bloqueio pós-finalização e exceção para administrador.
