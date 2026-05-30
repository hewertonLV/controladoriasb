# ADR-0174: PDF do romaneio por rota concluída na matriz

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Matriz de captação — abas por rota vinculada (ADR-0173)

## Contexto

Após concluir uma rota na matriz, o operador precisa baixar o romaneio de viagem em PDF, no mesmo layout do modelo operacional (`pdf/Romaneio Rota.pdf`), sem depender da tela de show do lote.

## Decisão

- Exibir botão de download **somente** quando a rota estiver **concluída** no lote e o status do lote permitir edição de vínculo de rota (`permiteEdicaoVinculoRota`).
- Endpoint GET `admin/captacao/lotes/{lote}/rotas/{rota}/romaneio.pdf`, protegido por `captacao.pedido.editar` e acesso à unidade galpão do lote.
- Retornar **404** se a rota não estiver concluída, não pertencer à carteira do lote ou não houver lojas com quantidade na rota.
- Nome do arquivo: `{nome da rota} - {nome do motorista}.pdf`; se motorista vazio, usar `Sem motorista`.
- Conteúdo gerado a partir de `RomaneioCarregamentoService::previewPorRotas()`, com coluna **Cxs** preenchida apenas quando a UM do item for CX/CXS/CAIXA/CAIXAS (mesmo valor de Qtd nesses casos).

## Alternativas consideradas

- Permitir download antes da conclusão — rejeitado: romaneio é documento de viagem fechada.
- Reutilizar view de impressão do show do lote — rejeitado: layout e agrupamento por rota diferem do modelo PDF.

## Consequências

- DomPDF gera A4 retrato; hora de saída permanece em branco até existir campo operacional.
- Botão some se o lote avançar para status que bloqueia vínculo de rota, mesmo com rota concluída.
