# PLAN-0172: Seed captação Barbalha a partir de planilha exemplo

**ADR:** [ADR-0172](../decisions/ADR-0172-seed-captacao-barbalha-planilha-exemplo.md)
**Data:** 2026-05-29
**Status:** Concluído

## Objetivo

Popular a captação do dia da carteira Barbalha com pedidos da planilha `captação exemplo.xlsx`.

## Pré-requisitos

- Unidades **CD BARBALHA** (faturamento NF + galpão operacional) cadastradas.
- Clientes e frutas com `id_cigam` compatível com a planilha.

## Passos

1. **Reader + preço** — `CaptacaoExemploPlanilhaReader`, `CaptacaoExemploPlanilhaPreco`.
2. **Serviço seed** — carteira, lote, vínculos, pedidos/itens.
3. **Seeder** — `CaptacaoBarbalhaExemploSeeder`.
4. **Testes unitários** — parser de preço e leitura da planilha.
5. **Gitignore** — exceção para planilha modelo.

## Critério de conclusão

- `php artisan db:seed --class=CaptacaoBarbalhaExemploSeeder` cria/atualiza lote do dia com pedidos.
- Reexecução no mesmo dia atualiza quantidades/preços no lote **em andamento** sem duplicar itens.
- Se o lote do dia existir em status diferente de **Captação em andamento**, o seed cria outro lote e alimenta o novo.
- Testes unitários passando.

## Riscos

- Códigos CIGAM ausentes no banco — mitigação: avisos por linha ignorada.
