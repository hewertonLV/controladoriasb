# PLAN-0025: ICMS CE (compra R$/kg) e PE (venda % dentro/fora)

**ADR:** [ADR-0025](../decisions/ADR-0025-icms-ce-compra-fixa-pe-venda-percentual.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Alinhar cadastro, importação e cálculo de ICMS ao desenho CE (entrada absoluta) e PE (saída percentual dentro/fora), sem mudar a chave da tabela `fruta_icms`.

## Pré-requisitos

- ADR-0025 aceito.
- Estados CE e PE cadastrados (`EstadoSeeder`).

## Passos

1. **Enum** — adicionar `PCT` em `FrutaUmIcms`; validar: `ENTRADA` só `KG`/`UM`; `SAIDA` em PE aceita `PCT`.
2. **Estado (opcional)** — coluna `icms_momento` em `estados` ou helper em `Estado` derivado da descrição para UI condicional.
3. **Labels** — atualizar `_icms_estados.blade.php`, CRUD ICMS e template de importação: “Venda dentro do estado” / “Venda fora do estado”.
4. **Importação** — `FrutaIcmsPlanilhaNormalizer`: se estado = PE e colunas G–J preenchidas, forçar UM `PCT`; documentar na tela de importação.
5. **Cálculo venda** — `FrutaIcmsCalculoService::calcularSaidaSobreValorVenda(fruta, idEstadoDestinoVenda, idEstadoCliente, dataReferencia)` usando linha `SAIDA` e histórico vigente.
6. **Movimentações** — integrar cálculo PE em fluxo de venda quando `unidade` estiver em PE.
7. **Testes** — CE: 0,26/kg entrada; PE: 20,5% interno, 12% externo; vigência por data (ADR-0016).

## Critério de conclusão

- Cadastro e importação aceitam exemplos do usuário (CE R$/kg, PE %).
- Venda em unidade PE aplica percentual correto (dentro vs fora).
- Testes automatizados cobrem os dois estados e histórico na data da movimentação.

## Riscos

- Confusão com nomes legados `icms_venda_importada` — mitigar com labels e ADR.
- Abacaxi CE em UM (ADR-0006) — manter conversão na entrada, não forçar só KG.
