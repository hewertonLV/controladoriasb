# ADR-0041: Vincular frete em transferência pendente ou recebida conforme

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Transferências recebidas conforme não permitiam alterar o frete; o frete só podia ser informado na criação ou no reenvio após divergência.

## Contexto

Operação precisa corrigir ou incluir frete depois do recebimento conforme. O custo de entrada no destino inclui rateio de frete (ADR-0003); alterar o vínculo exige recalcular o frete compartilhado e reprocessar a linha do tempo de estoque no destino.

## Decisão

Permitir edição **somente do frete** (`id_frete` ou remoção) nas transferências com status:

- `PENDENTE_RECEBIMENTO`
- `RECEBIDA_CONFORME`

Fluxo:

1. Atualizar `id_frete` na saída e na entrada pareada (versão ativa).
2. Recalcular rateio via `FreteRateioMovimentacaoService` para frete(s) antigo e novo (ADR-0003).
3. Se frete removido, zerar campos de frete e recalcular preço de entrada da perna de destino sem frete.
4. Se `RECEBIDA_CONFORME` e frete removido, reprocessar estoque da unidade/fruta no destino (`ReplayLinhaTempoEstoqueService`).
5. Se frete vinculado/alterado, o recálculo central já atualiza entrada e dispara replay quando conforme.

Permissão: `movimentacoes.transferencias.editar`. Rota: `POST .../vincular-frete`.

Não permitir neste fluxo: alterar quantidade, origem, destino ou fruta (continua via reenvio após divergência).

## Alternativas consideradas

- **Nova versão da transferência (substituição)** — rejeitado: frete compartilhado já tem serviço de recálculo; substituir par inteiro seria pesado demais só para frete.
- **Permitir em recebida divergente** — rejeitado: divergente já usa reenvio com frete no formulário.

## Consequências

- Tela de detalhe da transferência exibe formulário de frete para pendente e conforme.
- Movimentações posteriores no destino podem ter custo médio alterado após replay.
- Fretes devem estar ABERTOS para vinculação.
