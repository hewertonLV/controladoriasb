# ADR-0105: Arquivo Cigan EDI NF — transferência com HUB de origem

**Data:** 2026-06-04
**Status:** Aceito
**Contexto:** Download TXT na fase `TRANSFERENCIA_CIGAN_INICIADA` ([ADR-0103](ADR-0103-matriz-aba-arquivo-cigan-txt.md), layout `EDI_NF_CIGAM.pdf`)

## Contexto

O Cigan importa NF via TXT de largura fixa (registros `N` nota + `I` itens). A transferência fiscal HUB → galpão usa as quantidades **a receber** do Romaneio 2. A origem física (HUB) varia por operação e deve ser escolhida antes do arquivo.

## Decisão

- Persistir `captacao_lotes.id_unidade_negocio_hub_origem` (UN com `is_hub = true`).
- Na aba **Arquivo Cigan**, obrigar **salvar o HUB** antes de habilitar o download.
- Gerar TXT com `CiganEdiNfTransferenciaGerador`:
  - **Registro N:** **cliente** (pos. 52–57) e **cobrança** (pos. 59–64) = `id_cigam` do **cliente principal** da unidade de faturamento ([ADR-0106](ADR-0106-unidade-negocio-codigo-cliente.md)); nome/CNPJ seguem esse cliente; **data emissão** (26–33) e **data entrada** (35–42) = dia do download (`DDMMAAAA`). **Série** (3–7) + **Número NF** (9–15): código único **`NF` + id_cigam** do HUB sem zeros à esquerda (ex. UN `000120` → série `NF120`, número em branco) — [ADR-0115](ADR-0115-cigan-edi-numero-nf-unidade-negocio.md); não usa série config `001`. **Tipo de operação** (20–24 e 372–376 no I): **`5152A`** (transferência) — [ADR-0117](ADR-0117-cigan-edi-tipo-operacao-5152a.md). **Transportadora** (132–137): fixo **`000488`** (`captacao_cigan_edi.transportadora`). **Entrada/Saída** (283): sempre **`S`**. **Condição pagamento** (316–318): **3 espaços**. HUB de origem continua obrigatório no lote (controle operacional), mas **não** entra no código da transportadora. Romaneio = galpão do lote.
- **Registro I:** um por fruta com `a_receber_um > 0`; **código material** (3–22, `id_cigam` dos pedidos do lote) — [ADR-0111](ADR-0111-cigan-edi-codigo-material-unidade-negocio.md), [ADR-0114](ADR-0114-cigan-edi-material-fruta-do-lote.md); **unidade negócio** (656–658, HUB origem); **quantidade** (pos. 24–38, máscara N8.6) = `a_receber_um` × 1.000.000 — [ADR-0113](ADR-0113-cigan-edi-quantidade-sem-n86.md); **peças** (pos. 40–53) em branco; **preço unitário** (pos. 56–70) em branco — [ADR-0110](ADR-0110-cigan-edi-preco-unitario-em-branco.md); **espécie estoque** (679, com separador 659–678) = `S` — [ADR-0112](ADR-0112-cigan-edi-especie-estoque-saida.md); **sequência item** (681–685) em branco; tipo de operação em branco. **Unidade negócio** no `N` (602–604): mesmo HUB de origem; **centro armazenagem** (605–607) do HUB — [ADR-0116](ADR-0116-cigan-edi-centro-armazenagem-hub.md); **espécie estoque** (608) = `S`.
- Somente campos **obrigatórios** do manual; opcionais em branco. Registros `O`, `L`, `G` etc. **não** são emitidos.
- Charset do arquivo: **ISO-8859-1**; comprimento linha N = 688, I = 719 (exemplo oficial).
- Defaults em `config/captacao_cigan_edi.php` (série 1, transportadora 000488, entrada/saída S, divisão 10).

## Alternativas consideradas

- Um HUB fixo no sistema — rejeitado; operação escolhe a unidade de saída.
- CSV legado do «Iniciar transferência» — mantido separado; TXT segue layout Cigam.
- Preencher todos os campos do PDF — rejeitado; escopo só obrigatórios + dados do cadastro SB.

## Consequências

- Cadastro: unidade de faturamento com **cliente principal** (`id_cliente`) e `id_cigam` do cliente; HUB e frutas com `id_cigam`; cliente com CNPJ; unidade do cliente com **UF** (estado).
- Contato, fone, endereço, bairro, cidade, CEP e inscrição estadual no TXT ficam **em branco** (espaços); **nome** (322–381) e **UF** (549–550) vêm do cliente; CNPJ do cliente.
- Tipo de operação e materiais devem existir no Cigan; validação fiscal ocorre na importação.
- [PLAN-0105](../plans/PLAN-0105-arquivo-cigan-edi-transferencia-hub.md).
