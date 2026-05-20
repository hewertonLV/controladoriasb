# ADR-0023: Fuso horário de Brasília na aplicação

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Horários gravados e exibidos com 3 horas a mais que o horário local do usuário no Brasil.

## Contexto

O `.env.example` já definia `APP_TIMEZONE`, mas `config/app.php` ignorava a variável e usava `UTC` fixo. O MySQL em Docker rodava em UTC, ampliando a diferença em colunas `timestamp`.

## Decisão

- Aplicação Laravel: `APP_TIMEZONE=America/Sao_Paulo` (horário de Brasília, UTC−3, sem horário de verão).
- Conexão MySQL: `DB_TIMEZONE=-03:00` na sessão PDO.
- Containers PHP e MySQL no Docker: `TZ=America/Sao_Paulo` e `--default-time-zone=-03:00`.

## Alternativas consideradas

- Manter `UTC` no app e converter só na view — rejeitado: exige conversão em todo lugar e já causava erro visível.
- `America/Fortaleza` — mesmo offset atual; rejeitado em favor de `America/Sao_Paulo`, identificador usual para Brasília.

## Consequências

- Novos registros passam a usar horário de Brasília.
- Registros antigos gravados em UTC continuarão com valor “adiantado” até correção manual ou migração pontual, se necessário.
