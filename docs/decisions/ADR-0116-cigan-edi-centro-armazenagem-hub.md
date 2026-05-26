# ADR-0116: Centro de armazenagem no EDI Cigan (HUB)

**Data:** 2026-05-26
**Status:** Aceito
**Contexto:** Importação Cigan retorna «sem estoque suficiente» com estoque existente ([ADR-0109](ADR-0109-unidade-negocio-centro-armazenagem.md), [ADR-0112](ADR-0112-cigan-edi-especie-estoque-saida.md))

## Contexto

O Cigan valida saldo por **unidade de negócio** (602–604) e **centro de armazenagem** (605–607 no registro `N`), códigos distintos no cadastro (`unidades_negocio.centro_armazenagem`). O gerador deixava 605–607 em branco; o ERP não encontrava o saldo na combinação correta.

## Decisão

- **Campo 052 — Centro armazenagem** (pos. **605–607**, **somente** registro `N`): `centro_armazenagem` do **HUB de origem**, exatamente 3 caracteres com `colocarExato` (ex.: `001`). O Cigan herda do cabeçalho nos itens.
- **Registro `I`**, pos. **659–678** em branco; pos. **679** = espécie `S`. **Não** repetir centro em 659–661 — o ERP interpretava mal (ex.: «Centro Armazen. 50» ao gravar `050` ou desalinhamento).
- Cadastro HUB deve usar o **mesmo** código existente no Cigan (tipicamente `001`). Valor `050` no SB vira «50» na validação do ERP (pos. 606–607).
- Obrigatório cadastrar `centro_armazenagem` na UN HUB antes do download.

## Alternativas consideradas

- Deixar 605–607 em branco — rejeitado: Cigan reporta falta de estoque mesmo com saldo na UN.
- Reutilizar últimos 3 dígitos do `id_cigam` — rejeitado em [ADR-0109](ADR-0109-unidade-negocio-centro-armazenagem.md).

## Consequências

- [PLAN-0116](../plans/PLAN-0116-cigan-edi-centro-armazenagem-hub.md).
- Atualizar [ADR-0112](ADR-0112-cigan-edi-especie-estoque-saida.md) e [ADR-0105](ADR-0105-arquivo-cigan-edi-transferencia-hub.md).
