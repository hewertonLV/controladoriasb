# ADR 0002: Transferências com saldo negativo e origem sem histórico

Status: Proposta

Data: 2026-05-17

## Contexto

Movimentações do tipo transferência representam uma saída de estoque na unidade de origem e uma entrada pendente na unidade de destino. Esse fluxo precisa preservar rastreabilidade operacional, custo econômico da fruta transferida e conciliação posterior do recebimento.

Na prática, a unidade de origem pode ter divergências entre saldo físico e saldo registrado no sistema. Por isso, bloquear toda transferência com saldo insuficiente impede correções operacionais e pode travar fluxos em que o estoque existe fisicamente, mas ainda não foi reconciliado no sistema.

Ao mesmo tempo, existe uma diferença importante entre "saldo insuficiente" e "produto nunca existiu naquela origem". Se a origem nunca recebeu determinada fruta, não há histórico mínimo para calcular custo, preço médio ou justificar uma saída operacional daquele produto.

## Decisão

Permitir transferências que deixem saldo negativo na unidade de origem quando já existir registro de estoque para a combinação unidade/fruta.

Bloquear a transferência quando a unidade de origem nunca tiver recebido aquele produto, ou seja, quando não existir registro em `estoques` para a combinação `id_unidade_negocio` e `id_fruta`.

Nessa situação, o sistema deve retornar erro tratado de validação, não exceção não capturada. Em telas HTML, o usuário deve retornar ao formulário com a mensagem no campo de fruta. Em chamadas JSON, a resposta deve ser `422 Unprocessable Entity`.

## Regras Operacionais

- A existência de `estoques` para origem/fruta indica que o produto já fez parte da operação daquela unidade.
- Saldo insuficiente em uma origem com histórico não bloqueia a transferência.
- O saldo da origem pode ficar negativo após a saída.
- A ausência de registro em `estoques` bloqueia a transferência.
- O sistema não deve criar automaticamente um estoque zerado na origem apenas para permitir a saída.
- O destino continua podendo ter estoque criado automaticamente, pois a transferência representa uma entrada pendente a receber.

## Feedback Ao Usuário

Quando a origem nunca recebeu o produto, a mensagem deve explicar a causa operacional:

> A unidade de origem nunca recebeu este produto; por isso não é possível executar esta transferência.

Na interface de criação de transferência, essa mensagem deve ser exibida como erro de validação no campo `Fruta`. Um tooltip deve reforçar que a origem ainda não possui registro em estoque para aquela fruta e que é necessário registrar uma entrada/compra antes de executar a transferência.

## Consequências

Saldos negativos passam a ser estados válidos e auditáveis para transferências, desde que exista histórico de estoque na origem.

O bloqueio deixa de depender do saldo atual e passa a depender da existência histórica mínima da origem/fruta. Isso reduz erros 500, melhora a experiência do usuário e evita criação artificial de estoque sem evento operacional real.

Relatórios e telas de estoque devem estar preparados para exibir saldos negativos quando a operação permitir. Rotinas de reconciliação e auditoria devem tratar saldo negativo como uma divergência operacional possível, não como inconsistência técnica automática.

## Alternativas Consideradas

### Bloquear qualquer transferência com saldo insuficiente

Essa era a regra anterior. Ela evita saldo negativo, mas gera bloqueios operacionais e levou a exceções não tratadas quando o usuário tentava transferir quantidade maior que o saldo registrado.

### Criar estoque automaticamente na origem

Essa alternativa foi rejeitada porque mascararia a ausência de histórico real. Um estoque zerado criado no momento da saída não prova que a origem já recebeu aquele produto.

### Permitir transferência mesmo sem histórico na origem

Essa alternativa foi rejeitada porque não haveria base confiável para custo, preço médio e justificativa operacional da saída. Também dificultaria auditoria posterior.

## Critérios De Aceite

- Transferência com origem/fruta existente em `estoques` pode deixar saldo negativo.
- Transferência com origem/fruta inexistente em `estoques` retorna erro de validação tratado.
- O erro não deve abrir tela de exceção 500.
- A tela de criação deve exibir mensagem amigável e tooltip explicativo.
- O endpoint JSON deve retornar `422` com erro no campo `id_fruta`.
