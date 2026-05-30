# ADR-0172: Seed captação Barbalha a partir de planilha exemplo

**Data:** 2026-05-29
**Status:** Aceito
**Contexto:** Ambiente de demonstração / carga inicial captação por loja

## Contexto

A operação possui planilha real de captação (`captação exemplo.xlsx`) com lojas, frutas, quantidades, preços e número do pedido. Era necessário um seed idempotente que monte a captação do dia corrente na carteira Barbalha (CD BARBALHA).

## Decisão

- Seeder dedicado: `CaptacaoBarbalhaExemploSeeder` (`php artisan db:seed --class=CaptacaoBarbalhaExemploSeeder`).
- Fonte: `planilhas/captação exemplo.xlsx` (colunas Cod. Cliente, Cod. Produto, Nº Pedido, Quantidade, Preço Tabela, Preço Promoção).
- **Preço efetivo:** coluna promoção; se vazia ou zero, usa preço tabela.
- Carteira **Barbalha** com faturamento e galpão **CD BARBALHA** (cria carteira se não existir; atualiza UN se já existir).
- Lote do **dia atual** (tipo captação pedidos): reutiliza **somente** lote existente da carteira na data com status **Captação em andamento**; se existir lote no dia em outro status, cria **novo** lote complementar via `CaptacaoLoteService` e alimenta esse; se não existir lote, cria o primeiro.
- Lojas da planilha são **vinculadas** à carteira (força `id_captacao_carteira` e alinha faturamento).
- Pedidos/itens: `updateOrCreate` por lote × cliente × fruta; reabre pedido concluído; grava `numero_pedido`.
- Resolução de códigos CIGAM com chaves normalizadas (pad 6 dígitos e sem zeros à esquerda).
- Cria vínculo loja×fruta ausente antes de gravar item.
- Clientes/frutas não encontrados: aviso no console, linha ignorada (não aborta o seed).

## Alternativas consideradas

- **Usar PedidoService** — rejeitada para seed; bloqueia quando lote não está em captação ou pedido concluído; upsert direto é mais previsível para carga demo.
- **Carteira inferida só por UN** — rejeitada; operação pediu nome fixo Barbalha.

## Consequências

- [PLAN-0172](../plans/PLAN-0172-seed-captacao-barbalha-planilha-exemplo.md).
- Planilha versionada no repositório (exceção no `.gitignore`).
