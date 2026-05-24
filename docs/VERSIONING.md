# Versionamento do projeto SB Controladoria

## Linhas de versão

| Versão | Tag / branch | Descrição |
|--------|----------------|-----------|
| **1.x** | `v1.0.0`, branch `version/1.x` | Movimentações, importações, galpões (0060–0065). Origem Cigan/planilhas. |
| **2.x** | `v2.0.0-planning`, branch `version/2.x`, `main` | Captação de pedidos, romaneios, pipeline Lucas/Jefferson ([PACOTE-0066](pacotes/PACOTE-0066-captacao-pedidos-romaneios-pipeline.md)). |

O arquivo [`VERSION`](../VERSION) na raiz indica a versão **atual** do checkout (ex.: `2.0.0-dev` durante o desenvolvimento da v2).

## Voltar para a versão 1

```bash
# Código exatamente como na release v1 (somente leitura recomendada)
git checkout v1.0.0

# Branch de manutenção da v1 (hotfixes pontuais, se necessário)
git checkout version/1.x
```

Para rodar em produção/homolog na v1, use o commit da tag `v1.0.0` ou o tip da branch `version/1.x`.

## Trabalhar na versão 2

```bash
git checkout main
# ou
git checkout version/2.x
```

Spec e plano mestre:

- [docs/superpowers/specs/2026-05-23-captacao-pedidos-romaneios-pipeline-design.md](superpowers/specs/2026-05-23-captacao-pedidos-romaneios-pipeline-design.md)
- [docs/superpowers/plans/2026-05-23-captacao-pedidos-romaneios-pipeline.md](superpowers/plans/2026-05-23-captacao-pedidos-romaneios-pipeline.md)

## Tags

| Tag | Momento |
|-----|---------|
| `v1.0.0` | Último estado estável **antes** da implantação do PACOTE-0066 (movimentações + import legado). |
| `v2.0.0-planning` | Documentação ADR/PLAN/spec/plano mestre da v2; início da linha de implementação. |

Próximas tags sugeridas na v2: `v2.0.0-alpha.1` (fase 1 captação), `v2.0.0-beta.1`, `v2.0.0` (go-live).

## Política

- **v1** congelada em funcionalidade; apenas correções críticas em `version/1.x`.
- **v2** evolui em `main` / `version/2.x`; não remover código de import de movimentação até pedido explícito ([ADR-0079](decisions/ADR-0079-importacao-apenas-cadastro-sem-movimentacoes.md)).
