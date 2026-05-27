# PLAN-0029: Identificadores Docker e infra como controladoriasb

**ADR:** [ADR-0029](../decisions/ADR-0029-identificadores-docker-controladoriasb.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Substituir `facigam` por `controladoriasb` em Docker, env de exemplo, deploy, CSS e documentação.

## Pré-requisitos

- Nenhum

## Passos

1. **docker-compose.yml** — serviço, imagens, containers, rede, volume
2. **.env.example** e scripts (`script.sh`, `mysql-dump.sh`)
3. **docs/deploy** — supervisor e README
4. **README.md** e **docs/SISTEMA_CONTEXTO.md** — comandos `docker compose exec`
5. **theme-dynamic.css** — variáveis `--controladoriasb-*`

## Critério de conclusão

- `grep -r facigam` no repositório (exceto ADR-0007 histórico e logs) retorna vazio
- Documentação usa `docker compose exec controladoriasb`

## Riscos

- Volume MySQL antigo — recriar stack ou `docker volume` + restore backup
- `.env` local não versionado — operador deve alinhar `DB_DATABASE` manualmente
