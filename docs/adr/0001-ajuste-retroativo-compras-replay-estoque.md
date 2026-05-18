# ADR 0001: Ajuste retroativo de compras com replay de estoque

Status: Proposta

Data: 2026-05-17

## Contexto

Movimentações do tipo compra alimentam o custo de entrada da fruta, o saldo de estoque e o preço médio da unidade/fruta. Hoje o sistema já grava snapshots financeiros na própria `movimentacoes`, incluindo NF, frete, custo operacional, ICMS convertido, preço médio do lote e vínculos para `movimentacao_estoques`.

O ajuste de compra existente é restrito: ele só permite alterar a compra que gerou a posição atual do estoque. Essa regra evita inconsistência imediata, mas impede correções de lançamentos antigos. Na operação real, notas antigas podem precisar de correção depois de outras entradas, transferências, vendas, devoluções, doações ou descartes já terem acontecido.

Também existe uma exigência contábil importante: quando uma compra antiga é ajustada, os lançamentos futuros devem ser recalculados usando os dados que eram vigentes na data de cada movimentação, não os valores atuais dos cadastros. Por exemplo, o ICMS da fruta ou o custo operacional da unidade podem ter mudado depois da compra original. O replay deve respeitar o valor histórico aplicável na data de criação de cada movimentação.

## Decisão

Permitir ajuste retroativo de compras por meio de versionamento imutável da movimentação ajustada e replay determinístico da linha do tempo de estoque da unidade/fruta afetada.

A movimentação original não será editada em linha. Ela será marcada como substituída, e uma nova versão ativa será criada com os dados corrigidos. Depois disso, o sistema reprocessará, em ordem cronológica, todos os eventos vigentes futuros da mesma unidade de negócio e fruta para reconstruir:

- os snapshots em `movimentacao_estoques`;
- os saldos finais em `estoques`;
- os campos derivados em `movimentacoes`, como saldo após evento, preço médio, frete rateado e valores econômicos dependentes do estoque.

Durante o replay, cada evento deve considerar os valores históricos vigentes na data da própria movimentação. Quando a movimentação já possuir snapshot do dado histórico, esse snapshot tem prioridade.

## Identidade E Versionamento

O ajuste de um lançamento não deve sobrescrever o registro antigo. A compra original continua existindo como o registro que representa aquele ponto da linha do tempo operacional, com sua `data_movimentacao`, `versao`, snapshots e auditoria preservados.

Compras possuem dois identificadores:

- `numero_compra`: identificador operacional exibido ao usuário, no formato `Compra #<numero_compra>`;
- `id`: identificador técnico interno da linha em `movimentacoes`.

O `numero_compra` deve ser gerado na criação da compra e preservado em todas as versões da mesma cadeia. O nome da movimentação de compra deve usar `numero_compra`, não o `id` técnico.

Quando uma compra for ajustada:

- o registro antigo recebe `status_registro = SUBSTITUIDO`;
- o registro antigo aponta para o novo registro por `substituida_por_id`;
- o novo registro recebe `status_registro = ATIVO`;
- o novo registro aponta para a compra raiz por `movimentacao_origem_id`;
- o novo registro mantém o mesmo `numero_compra` da compra original;
- o novo registro mantém a `data_movimentacao` da compra original, pois a correção pertence ao mesmo momento da linha do tempo;
- o novo registro usa `created_at` como data em que a atualização/correção foi registrada;
- os dados corrigidos ficam somente no novo registro, nunca sobrescritos no registro antigo.

Consultas operacionais devem resolver a cadeia de versionamento e retornar a versão ativa mais recente. Assim, quando uma tela, API ou rotina solicitar a compra vigente, ela deve receber o novo registro. Quando a consulta for histórica/auditoria/linha do tempo completa, ela deve conseguir mostrar que aquele lançamento nasceu no registro antigo, foi substituído, e passou a ter uma nova versão ativa.

A linha do tempo de estoque deve ordenar pela data operacional da compra original e pela cadeia de versionamento, não apenas pelo `id` novo. Isso garante que uma correção feita hoje em uma compra de meses atrás continue sendo aplicada no ponto correto do passado, afetando os lançamentos posteriores sem deslocar o evento para o presente.

## Regras De Vigência

Para compras existentes, a regra principal é preservar os snapshots já gravados em `movimentacoes`:

- `valor_custo_operacional` e `id_custo_operacional` representam o custo operacional aplicado à compra.
- `icms_convertido_kg`, `valor_icms_total`, `valor_icms_kg` e `valor_icms_um` representam o ICMS aplicado à compra.
- `valor_frete_kg`, `valor_frete_rateio` e `valor_frete_um` representam o rateio do frete aplicado ao lote.

Para movimentações criadas antes de algum snapshot existir ou em rotinas de backfill, o sistema deve buscar o histórico vigente pela data de criação da movimentação:

- Custo operacional: buscar em `historico_c_o_un_ng` pelo `id_unidade_negocio` e maior `created_at <= movimentacao.data_movimentacao`.
- ICMS da fruta: criar uma fonte histórica consultável para os campos fiscais da fruta, pois `fruta_historicos` é uma auditoria JSON e não deve ser usado como tabela operacional de cálculo. A implementação recomendada é uma tabela específica de snapshots fiscais da fruta, por exemplo `historico_icms_frutas`, com `id_fruta`, `icms_ex_compra`, `icms_na_compra`, `um_icms`, `icms_venda`, `status_position` e `created_at`.

Alterações posteriores no cadastro atual da fruta ou no custo operacional atual da unidade não podem alterar o custo histórico de uma movimentação antiga, exceto quando a própria movimentação estiver sendo recalculada por falta de snapshot e houver um registro histórico vigente naquela data.

## Desenho Proposto

Criar um serviço de aplicação para ajuste retroativo de compra, por exemplo `AjustarCompraRetroativaService`, com responsabilidade de orquestrar a transação:

1. Validar que a movimentação é uma compra ativa e vigente para cálculo.
2. Validar que a alteração é permitida pelo versionamento.
3. Bloquear, com `lockForUpdate`, a unidade/fruta afetada, o estoque correspondente e as movimentações vigentes da linha do tempo a partir da compra ajustada.
4. Criar nova versão da compra com os dados corrigidos, preservando `data_movimentacao` original e apontando `movimentacao_origem_id` para a compra raiz.
5. Marcar a versão anterior como substituída e preencher `substituida_por_id` com o novo registro ativo.
6. Reexecutar a linha do tempo da unidade/fruta desde a posição base anterior à compra ajustada.
7. Atualizar `movimentacao_estoques` e `estoques` de forma consistente.
8. Recalcular rateios de frete impactados.

O replay deve ser idempotente: executar o mesmo replay duas vezes sobre a mesma linha do tempo vigente deve produzir os mesmos saldos, preços médios e snapshots.

Embora esta ADR tenha nascido do ajuste retroativo de compras, a regra de replay da unidade/fruta é uma política geral de estoque. Ela deve ser seguida por qualquer tipo de movimentação que altere saldo, custo médio ou snapshots posteriores, incluindo compras, vendas, transferências, devoluções, doações, descartes, substituições de versão e cancelamentos administrativos.

## Impacto No Estoque

`movimentacao_estoques` deve continuar sendo a trilha de snapshots de estoque após cada evento. Para uma compra antiga ajustada, os snapshots posteriores da mesma unidade/fruta deixam de ser confiáveis até o replay terminar.

O replay deve:

- desmarcar posições antigas como `status_ultima_posicao = false`;
- recriar ou atualizar a posição vinculada a cada movimentação vigente, ou seja: para cada evento recalculado, deve existir exatamente uma linha em `movimentacao_estoques` com `movimentacao_id` apontando para aquela movimentação ativa. Se essa linha já existir para a versão vigente da movimentação, ela pode ser atualizada com os novos saldos, preços médios e valor total acumulado calculados pelo replay. Se a movimentação ganhou uma nova versão, ou se a posição anterior estava vinculada à versão substituída, uma nova posição deve ser criada para a nova versão ativa. Posições antigas de versões substituídas devem permanecer como histórico, mas não podem ficar marcadas como última posição nem ser usadas como base dos próximos cálculos;
- manter e respeitar uma posição inicial sem `movimentacao_id` quando ela representar saldo inicial/importado; se não existir baseline, criar uma posição inicial zerada;
- usar como baseline a posição imediatamente anterior ao primeiro evento vigente recalculado. Se essa posição pertencer a uma movimentação cancelada ou substituída, o replay deve caminhar pela cadeia de `id_movimentacao_estoque_old` até encontrar uma posição base válida;
- nunca usar uma posição criada por estorno/cancelamento como base indevida para eventos futuros quando ela apenas representa um estado intermediário da transação de cancelamento;
- reprocessar saídas vigentes mesmo quando não houver entrada vigente no recorte recalculado, pois o estoque pode ter vindo de saldo inicial/importado e ainda assim descartes, doações, vendas ou transferências posteriores precisam permanecer refletidos no saldo final;
- marcar somente a última posição recalculada como `status_ultima_posicao = true`;
- atualizar a tabela `estoques` com o saldo e preço médio finais.

Saídas futuras devem ser consideradas na linha do tempo quando impactarem saldo ou custo médio. Se alguma saída futura ficar inválida depois do ajuste, por exemplo estoque insuficiente, o replay deve falhar a transação inteira e retornar erro claro ao usuário, sem gravar estado parcial.

Cancelamentos administrativos devem ser tratados como uma mudança de vigência na linha do tempo, não como um ajuste isolado de saldo. Ao cancelar uma movimentação, o registro cancelado deixa de participar dos cálculos vigentes, e todos os eventos ativos posteriores da mesma unidade/fruta devem ser recalculados a partir do baseline correto. Isso evita tanto saldo preso, quando uma saída cancelada não retorna ao estoque, quanto saldo duplicado, quando uma posição transitória de estorno é usada como base de replay.

## Rateio De Frete

Quando a compra ajustada estiver vinculada a um frete, o rateio desse frete pode impactar outras compras associadas ao mesmo frete. A rotina de replay deve recalcular o rateio das compras vigentes do frete antes de recalcular o preço médio da linha do tempo.

O valor total do frete permanece o valor cadastrado no frete. O `valor_fruta_kg` do frete deve refletir o valor total dividido pelo total de KG vigente das compras vinculadas.

## Consequências

Benefícios:

- Permite corrigir compras antigas sem edição destrutiva.
- Mantém rastreabilidade por versionamento.
- Preserva valores históricos de ICMS e custo operacional.
- Reconstrói estoque e preço médio de forma consistente.

Custos:

- A implementação exige replay transacional mais amplo e mais testes de linha do tempo.
- Será necessário formalizar histórico operacional de ICMS da fruta em tabela própria, ou garantir backfill confiável para snapshots já existentes.
- Ajustes retroativos podem falhar quando lançamentos futuros dependem de saldo que deixará de existir após a correção.

## Alternativas Consideradas

Editar a movimentação antiga em linha foi descartado porque destruiria a trilha de auditoria e dificultaria explicar saldos históricos.

Permitir ajuste apenas da última compra foi mantido como comportamento seguro atual, mas não atende à necessidade operacional de correção de notas antigas.

Recalcular tudo usando cadastros atuais foi descartado porque muda o passado quando ICMS ou custo operacional foram alterados depois da data da movimentação.

Usar `fruta_historicos` como fonte operacional de ICMS foi descartado porque a tabela guarda diffs/auditoria em JSON e não oferece uma semântica simples de vigência para cálculo.

## Plano De Implementação

1. Criar tabela de histórico fiscal da fruta, se ainda não existir, e alimentar snapshots nas criações/alterações/importações de fruta.
2. Criar serviço resolvedor de valores vigentes por data, por exemplo `ValoresHistoricosMovimentacaoResolver`.
3. Alterar o ajuste de compra para aceitar compra antiga e criar nova versão mantendo a data original e o `numero_compra`.
4. Evoluir `ReplayEstoqueCompraService` ou criar replay unificado de linha do tempo para processar entradas e saídas da unidade/fruta em ordem cronológica.
5. Recalcular rateios de frete antes de consolidar preços médios impactados.
6. Garantir que falhas de estoque futuro invalidem a transação inteira.
7. Cobrir com testes de feature e integração.

## Critérios De Aceite

- Uma compra antiga pode ter `valor_nf_total` corrigido mesmo quando existirem movimentações posteriores da mesma unidade/fruta.
- A versão anterior da compra fica substituída e a nova versão fica ativa.
- O registro antigo continua existindo, referenciando o novo registro por `substituida_por_id`.
- Consultas operacionais retornam a versão ativa da cadeia, enquanto consultas históricas conseguem exibir todas as versões.
- A compra é exibida como `Compra #<numero_compra>`, preservando esse número em todas as versões.
- A nova versão mantém a data operacional original para ocupar o mesmo ponto da linha do tempo.
- A nova versão registra a data da correção em `created_at`.
- Os lançamentos futuros da unidade/fruta são recalculados em ordem cronológica.
- Cancelamentos administrativos de qualquer tipo de movimentação recalculam a linha do tempo da unidade/fruta afetada a partir do baseline correto.
- Saldos iniciais/importados em posições sem `movimentacao_id` são preservados como base válida de replay.
- `estoques` reflete o saldo e preço médio finais após o replay.
- `movimentacao_estoques` tem somente uma última posição por unidade/fruta.
- Custo operacional usado no replay corresponde ao snapshot da movimentação ou ao histórico vigente na data da movimentação.
- ICMS usado no replay corresponde ao snapshot da movimentação ou ao histórico fiscal da fruta vigente na data da movimentação.
- Alterações atuais de ICMS/custo operacional não mudam movimentações antigas já snapshotadas.
- O replay falha sem gravar alterações parciais se uma saída futura ficar com estoque insuficiente.

## Testes Recomendados

- Ajustar compra antiga com compras futuras e validar recomposição do preço médio.
- Ajustar compra antiga com venda futura e validar custo de saída recalculado.
- Ajustar compra antiga com doação/descarte futuro e validar baixa de estoque.
- Ajustar compra antiga com transferência futura e validar impacto na saída e na entrada pareada.
- Alterar custo operacional depois da compra e provar que replay usa o custo da data original.
- Alterar ICMS da fruta depois da compra e provar que replay usa o ICMS da data original.
- Ajustar compra vinculada a frete compartilhado e validar rateio em todas as compras do frete.
- Tentar ajuste que torna uma saída futura impossível e validar rollback total.
