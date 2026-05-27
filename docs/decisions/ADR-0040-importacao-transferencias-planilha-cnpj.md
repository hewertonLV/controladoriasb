# ADR-0040: Importação de transferências por planilha (CNPJ, NF, id CIGAM)

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Transferências manuais exigem preencher origem, destino, fruta e NF no formulário; operação em lote precisa de planilha.

## Contexto

Usuários precisam registrar várias transferências de uma vez. Origem e destino são unidades de negócio identificáveis pelo CNPJ cadastrado. Cada linha representa uma fruta (id CIGAM), quantidade na unidade de medição (UM) e o número da NF de origem.

## Decisão

Oferecer importação assíncrona (preview + confirmação), espelhando o fluxo de importação de estoques:

- Layout fixo na aba ativa, linha 1 = cabeçalho: **A** CNPJ origem · **B** CNPJ destino · **C** id CIGAM fruta · **D** quantidade (UM) · **E** número da NF.
- Resolver origem/destino pelo `cpf_cnpj` da unidade de negócio (`possui_estoque = true`) → `empresas.id`.
- Uma fruta por linha; `id_cigam` normalizado até 6 dígitos.
- Quantidade sempre em UM; kg derivado no domínio ao criar a transferência.
- NF obrigatória por linha (não vazia).
- Preview classifica linhas em **prontas** (validação OK) ou **erros**; confirmação cria transferências via `TransferenciaMovimentacaoService::criarTransferencia`.
- Regras de ADR-0002 mantidas: origem sem registro em `estoques` para a fruta → erro na linha.

Permissões: `movimentacoes.transferencias.importar` (enviar/analisar) e `movimentacoes.transferencias.importar-confirmar` (gravar).

## Alternativas consideradas

- **id_cigam da unidade em vez de CNPJ** — rejeitado: o pedido explicitou CNPJ para origem/destino.
- **Várias frutas por linha** — rejeitado: 1 fruta por linha simplifica preview e confirmação.
- **Importação síncrona sem fila** — rejeitado: planilhas grandes bloqueiam HTTP; padrão do projeto é fila dedicada.

## Consequências

- Modelo `transferencia_importacoes`, fila `transferencias-importacao` e planilha modelo em `planilhas/transferencias_importacao.xlsx`.
- Usuários sem permissão de importar não veem o botão na listagem de transferências.
- Linhas com CNPJ inexistente, mesma origem/destino, fruta inválida ou origem sem estoque aparecem só em erros no preview.
