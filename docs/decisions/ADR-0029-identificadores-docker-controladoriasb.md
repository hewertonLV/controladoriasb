# ADR-0029: Identificadores Docker e infra como controladoriasb

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Deploy em produção e alinhamento com repositório `controladoriasb`

## Contexto

Após ADR-0007, o nome exibido do sistema passou a ser **SB - CONTROLADORIA**, mas containers Docker, volume MySQL, rede, paths de deploy (`/var/www/facigam`), `DB_DATABASE` e variáveis CSS (`--facigam-primary`) mantinham o legado `facigam`.

## Decisão

Renomear **todos** os identificadores técnicos `facigam` → `controladoriasb`:

- Serviço Docker principal: `controladoriasb` (comando `docker compose exec controladoriasb …`)
- Imagens/containers: `controladoriasb-app`, `controladoriasb-mysql`, workers, scheduler, phpmyadmin
- Rede e volume: `controladoriasb-network`, `controladoriasb_mysql_data`
- `.env.example`: `DB_DATABASE=controladoriasb`
- Deploy Supervisor: `/var/www/controladoriasb`
- Tema CSS: `--controladoriasb-primary` e derivados

ADR-0007 permanece como registro histórico da fase em que nomes técnicos Docker não foram alterados.

## Alternativas consideradas

- Manter `facigam` só no Docker — rejeitado: confunde operação e documentação no deploy
- Prefixo `sb-` — rejeitado: usuário pediu `controladoriasb` explícito

## Consequências

- Ambientes existentes precisam recriar containers/volume ou migrar dados manualmente (volume `facigam_mysql_data` não é renomeado automaticamente)
- Atualizar `.env` local com `DB_DATABASE=controladoriasb` (ou manter DB antigo até migração)
- Cache Laravel pode usar prefixo antigo até `php artisan optimize:clear`
