# ADR 0005: Permissões por unidade de negócio

Status: Proposta

Data: 2026-05-18

## Contexto

Movimentações de estoque impactam saldos, custos médios, resultados econômicos e trilhas de auditoria por unidade de negócio. Antes desta decisão, um usuário com permissão funcional para uma categoria de movimentação podia operar qualquer unidade disponível no formulário ou enviada manualmente na requisição.

Esse comportamento não separa a permissão funcional, como criar compra ou transferência, da autorização territorial por unidade de negócio. Usuários operacionais precisam atuar apenas nos polos vinculados a eles, enquanto perfis administrativos devem manter visão e operação global.

## Decisão

Criar vínculo direto entre `users` e `unidades_negocio` por meio da tabela `unidade_negocio_user`.

Usuários comuns só podem movimentar unidades vinculadas. Usuários com role `Programador` ou `Administrador` têm acesso a todas as unidades, independentemente de vínculo explícito.

A autorização por unidade deve ser centralizada em `UnidadeNegocioAccessService`, com métodos específicos por operação:

- compra valida unidade de destino;
- transferência valida unidade de origem;
- doação valida unidade de origem;
- descarte valida unidade de origem;
- venda valida unidade de origem;
- conversão valida unidade de origem;
- devolução valida unidade de retorno quando houver retorno de estoque;
- ajuste manual de estoque valida a unidade ajustada.

## Aplicação

A regra deve existir em duas camadas:

- Interface: selects e cartões de unidade exibem apenas unidades permitidas para o usuário logado, exceto administradores globais.
- Backend: Form Requests validam obrigatoriamente a unidade recebida, retornando erro amigável quando o usuário tenta operar unidade não vinculada.

Mensagem padrão:

> Você não possui permissão para movimentar esta Unidade de Negócio.

## Consequências

A permissão funcional continua controlando o que o usuário pode fazer. O vínculo por unidade passa a controlar onde ele pode fazer.

Testes de fluxos operacionais devem vincular explicitamente unidades aos usuários quando necessário. Testes de bloqueio devem garantir que usuários sem vínculo não consigam manipular a requisição manualmente.
