# ADR-0009: Grupos de contrato com descontos mensais

**Data:** 2026-05-18
**Status:** Aceito
**Contexto:** Remoção de `clientes.desconto_contrato` e criação de histórico mensal por grupo

## Contexto

O desconto de contrato estava gravado diretamente em `clientes`, sem representar vigência mensal nem histórico de participantes.
O novo fluxo precisa registrar grupos contratuais, lançamentos mensais e quem fazia parte do grupo em determinada competência.

## Decisão

Criar uma estrutura nova de Grupo de Contrato, separada de `grupos`, com participação mensal de clientes e lançamentos mensais de desconto em R$.
Remover `desconto_contrato` de `clientes` e tratar meses sem lançamento como ausência válida de desconto.

## Alternativas consideradas

- Reaproveitar `grupos`: rejeitado por misturar cadastro genérico com regra contratual específica.
- Manter apenas grupo atual no cliente e auditar mudanças: rejeitado porque não responde diretamente quem pertencia ao grupo em uma competência.

## Consequências

Consultas históricas passam a ser baseadas em vigência por competência mensal.
Importação, validação, exportação e auditoria de clientes precisam deixar de depender de `desconto_contrato`.
