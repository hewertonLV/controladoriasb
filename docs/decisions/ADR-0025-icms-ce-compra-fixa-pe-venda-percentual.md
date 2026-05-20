# ADR-0025: ICMS CE (compra R$/kg) e PE (venda % dentro/fora)

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Regras de negócio de ICMS por estado (Ceará na entrada, Pernambuco/Petrolina na venda) precisam de modelo de dados e rótulos alinhados ao cálculo.

## Contexto

- **Ceará (CE):** ICMS na **compra/entrada** de fruta que entra no estado; valor **absoluto** por kg ou por unidade (ex.: R$ 0,26/kg; abacaxi por UN — ADR-0006).
- **Pernambuco (PE), operação Petrolina:** ICMS na **venda**; alíquota **percentual** sobre o valor vendido, distinta para venda **dentro** do estado (ex.: 20,5%) e **fora** do estado (ex.: 12%).
- **Petrolina** é cidade/unidade de negócio, não UF; o cadastro de imposto vincula-se ao estado **PERNAMBUCO** (`estados.id = 2`).
- A tabela `fruta_icms` já existe (ADR-0013/0014) com duas linhas por par fruta+estado: `ENTRADA` e `SAIDA`.

## Decisão

1. **Manter** `fruta_icms` e `fruta_icms_historicos` com chave `(fruta_id, id_estado, operacao)` — sem nova tabela paralela.
2. **Ceará:** usar linha `operacao = ENTRADA`. Preencher `icms_externo` + `um_icms_externo` para compra de fora do CE (caso principal); `icms_nacional` + `um_icms_nacional` quando a origem for nacional dentro da regra fiscal. Valores são **R$ por KG ou R$ por UM**, nunca percentual.
3. **Pernambuco:** usar linha `operacao = SAIDA`. Mapear:
   - `icms_venda_nacional` → percentual venda **dentro do estado**;
   - `icms_venda_importada` → percentual venda **fora do estado** (interestadual; não significa “fruta importada”).
   - `um_icms_venda_nacional` e `um_icms_venda_importada` = **`PCT`** (novo valor no enum `FrutaUmIcms`).
4. **Estados sem cobrança** (ex.: Alagoas): zeros nas duas linhas ou ausência de registro; cálculo retorna 0.
5. **UI e importação:** rótulos “Compra nacional/exterior” e “Venda dentro/fora do estado”; ocultar colunas irrelevantes conforme `estados.descricao` / metadado futuro `icms_momento` (`ENTRADA` | `SAIDA` | `NENHUM`).
6. **Cálculo:** entrada CE continua `FrutaIcmsCalculoService::calcularEntradaPorKg` (soma nacional+externo convertidos a R$/kg). Saída PE: novo método `calcularSaidaPercentual` = `valor_venda * (aliquota/100)`, escolhendo coluna nacional ou “fora” conforme UF do destino da venda.

## Alternativas consideradas

- **Uma linha por (fruta, estado)** com colunas entrada e saída — mais simples na tela, mas quebra ADR-0013 e exige migração ampla.
- **Tabela `estado_icms_regras` separada** — rejeitado; duplicaria vínculo fruta×estado já modelado.
- **Renomear colunas no banco** (`venda_dentro_estado`) — adiado; semântica documentada; rename opcional em migração posterior.

## Consequências

- Enum `FrutaUmIcms` ganha `PCT`; validação rejeita PCT em linhas `ENTRADA`.
- Planilha de importação mantém colunas C–J; para PE, UM deve ser `PCT` (ou sistema força PCT quando estado = PE e colunas G–J).
- Histórico operacional (ADR-0016) replica os mesmos campos no snapshot.
- Implementação do cálculo de venda PE fica no PLAN-0025.
