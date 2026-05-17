# ADR 0003: Rateio de frete compartilhado entre movimentações

Status: Proposta

Data: 2026-05-17

## Contexto

Fretes podem estar vinculados a movimentações de naturezas diferentes, como compras, transferências e vendas. Antes, cada fluxo recalculava o frete de forma isolada por categoria: compras olhavam somente compras, transferências olhavam somente saídas de transferência e vendas olhavam somente vendas.

Essa regra cria distorção quando o mesmo frete é usado por mais de uma categoria. O valor por KG deixa de representar o custo real do frete inteiro, pois cada fluxo divide o valor total somente pelo seu próprio subconjunto de movimentações.

Além disso, frete pode afetar campos derivados posteriores. Em compras e entradas de transferência, ele compõe custo de entrada, preço médio da fruta e snapshots de estoque. Em vendas, ele afeta o resultado da movimentação, mas não altera o saldo ou preço médio do estoque de origem.

## Decisão

Centralizar o cálculo de frete por `id_frete`, considerando todas as movimentações vigentes e elegíveis vinculadas ao mesmo frete, independentemente da categoria.

O valor do frete por KG deve ser calculado como:

```text
valor_frete_kg = frete.valor / soma(qtd_fruta_kg das movimentações elegíveis do frete)
```

Depois disso, cada movimentação recebe:

- `valor_frete_kg`: valor unitário do frete por KG.
- `valor_frete_rateio`: `valor_frete_kg * qtd_fruta_kg` da movimentação.
- `valor_frete_um`: rateio dividido pela quantidade em unidade de medida da movimentação.

## Movimentações Elegíveis

Entram na base do rateio as movimentações ativas vinculadas ao frete com quantidade em KG positiva.

Para transferências, somente a perna de saída entra na base do KG. A perna de entrada pareada recebe os mesmos valores de frete da saída, mas não entra novamente na soma, para evitar duplicidade de peso.

Movimentações substituídas ou canceladas não participam do cálculo.

## Propagação Dos Efeitos

O recálculo do frete deve ajustar todas as movimentações afetadas pelo mesmo `id_frete`.

Alterações manuais no cadastro do frete também participam dessa regra. Quando `fretes.valor` for alterado, o sistema deve chamar o serviço central de rateio para recalcular automaticamente todas as movimentações vigentes vinculadas ao frete.

Quando o frete altera custo de entrada, o sistema deve reprocessar a linha do tempo posterior de estoque da unidade/fruta afetada:

- em compras, reprocessar a unidade de destino da compra;
- em transferências recebidas conforme, atualizar a entrada pareada e reprocessar a unidade de destino da transferência.

Quando o frete está em venda, o sistema deve atualizar `resultado_movimentacao` considerando o novo rateio, mas não precisa reprocessar estoque apenas por alteração do frete, porque o frete da venda não compõe o custo médio do estoque de origem.

## Ordem De Reprocessamento

Quando o mesmo frete afeta uma compra e uma transferência posterior, a ordem importa. A compra deve ser recalculada e o estoque da origem deve ser reprocessado antes de recalcular o custo da entrada da transferência, pois a saída da transferência depende do preço médio vigente na origem.

A ordem adotada é:

1. Recalcular compras e atualizar seus custos de entrada.
2. Reprocessar os estoques afetados por compras.
3. Recalcular entradas pareadas de transferências com base no preço da saída já atualizado.
4. Reprocessar os estoques de destino das transferências recebidas conforme.
5. Recalcular resultados de vendas.

## Consequências

O campo `fretes.valor_fruta_kg` passa a representar o valor por KG do frete inteiro, não de uma categoria específica.

Fluxos antigos que chamavam métodos específicos de compra, venda ou transferência devem delegar para o serviço central de rateio, preservando compatibilidade de chamadas existentes sem manter regras divergentes.

O replay integrado precisa tolerar saldos negativos quando a linha do tempo já contém operações válidas nessa condição. O replay não deve abortar apenas porque uma saída posterior deixa saldo negativo; ele deve reconstruir os snapshots e saldos de forma determinística.

## Alternativas Consideradas

### Manter rateio independente por categoria

Essa alternativa foi rejeitada porque distorce o custo quando um mesmo frete está associado a movimentações de categorias diferentes.

### Duplicar o KG da transferência usando saída e entrada

Essa alternativa foi rejeitada porque a transferência possui duas pernas para representar o mesmo produto em trânsito. Somar saída e entrada duplicaria o peso real transportado.

### Atualizar somente a movimentação recém-criada

Essa alternativa foi rejeitada porque qualquer alteração no total de KG vinculado ao frete altera o valor por KG de todas as movimentações participantes. Manter apenas a nova linha recalculada deixaria as demais inconsistentes.

## Critérios De Aceite

- O rateio de um frete deve considerar todas as movimentações vigentes elegíveis vinculadas ao mesmo `id_frete`.
- Movimentações de categorias diferentes devem receber o mesmo `valor_frete_kg` quando compartilham o mesmo frete.
- Transferências devem usar apenas a saída na base do KG e copiar o rateio para a entrada pareada.
- Recalcular frete deve atualizar todas as movimentações afetadas.
- Alterar `fretes.valor` pelo cadastro deve disparar o recálculo automático dos rateios e campos derivados das movimentações vinculadas.
- Recalcular frete deve reprocessar estoques posteriores quando o frete altera custo de entrada.
- Vendas devem ter `resultado_movimentacao` atualizado pelo novo rateio.
- Movimentações canceladas ou substituídas não devem participar da soma de KG.
