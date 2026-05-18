# ADR 0004: Conversão de embalagens com perda operacional

Status: Proposta

Data: 2026-05-18

## Contexto

Algumas frutas podem existir em estoque em apresentações diferentes, como `laranja pacote` e `laranja granel`. A operação de abrir uma embalagem não é uma compra, venda, transferência, doação ou descarte simples: ela baixa uma fruta de origem, gera entrada de outra fruta resultante e pode gerar perda por diferença de peso.

O usuário informa a quantidade em unidade de medida da fruta original e a quantidade em unidade de medida da fruta resultante. O sistema calcula os pesos usando `kg_por_unidade_medicao` de cada fruta.

## Decisão

Criar a categoria `CONVERSAO EMBALAGEM`.

Cada conversão gera duas movimentações ativas e pareadas:

- uma `SAIDA` da fruta original na unidade de origem;
- uma `ENTRADA` da fruta resultante na mesma unidade.

A quantidade original em KG é calculada como:

```text
qtd_original_kg = qtd_original_um * fruta_origem.kg_por_unidade_medicao
```

A quantidade resultante em KG é calculada como:

```text
qtd_resultante_kg = qtd_resultante_um * fruta_destino.kg_por_unidade_medicao
```

A perda da conversão é registrada em unidade de medida da fruta original e em KG:

```text
qtd_perda_conversao_um = max(qtd_original_um - qtd_resultante_um, 0)
qtd_perda_conversao_kg = qtd_original_kg - qtd_resultante_kg
valor_perda_conversao = qtd_perda_conversao_kg * preco_medio_kg_da_fruta_origem
```

Se a quantidade resultante pesar mais que a original, a operação deve ser rejeitada.

## Valorização Do Estoque

A saída da fruta original preserva o preço médio vigente da origem e reduz o valor acumulado pelo custo econômico da quantidade original.

A entrada da fruta resultante usa o mesmo custo médio por KG da fruta original, limitado ao peso efetivamente resultante. Se a fruta destino já possuir saldo, seu preço médio é recalculado pela entrada da conversão contra o saldo anterior.

A perda não entra no estoque destino; ela fica registrada como perda econômica da conversão. Como a conversão ocorre dentro da mesma unidade de negócio, não há aplicação de custo operacional nessa movimentação. O custo operacional já foi incorporado quando a fruta original entrou no estoque.

## Replay

O replay da linha do tempo deve tratar:

- a perna `SAIDA` da conversão como saída comum que preserva preço médio;
- a perna `ENTRADA` da conversão como entrada que usa `valor_total_movimentacao` histórico da própria conversão.

Movimentações canceladas ou substituídas não entram no cálculo.

## Consequências

A conversão passa a ser rastreável e auditável como uma movimentação própria, sem distorcer relatórios de compra, venda, doação ou descarte.

Relatórios de perdas podem usar `qtd_perda_conversao_kg` e `valor_perda_conversao` para demonstrar perda operacional por abertura/conversão de embalagem.
