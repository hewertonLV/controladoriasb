# ADR-0052: Importação de transferências — alterar destino na prévia

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Importação de transferências (ADR-0040)

## Contexto

Após a análise da planilha, o destino vem do CNPJ da coluna B. Na operação, o destino real às vezes difere do informado na planilha; o usuário precisa ajustar antes de confirmar, sem reenviar o arquivo.

## Decisão

Na tela de preview (linhas prontas), exibir um **select** de unidades de negócio com estoque (mesmo conjunto do formulário manual de transferência), pré-selecionado com o destino resolvido da planilha.

O select é **agrupado por origem + número da NF**: uma alteração de destino aplica a todas as linhas prontas da mesma origem com o mesmo NF.

Na confirmação, aceitar mapa opcional `id_empresa_destino_por_row` (`row_id` → `empresas.id`). Valores omitidos mantêm o destino do preview. O serviço de transferência valida origem ≠ destino e demais regras ao gravar.

A planilha e o preview inicial continuam resolvendo destino por CNPJ; o override só vale na confirmação.

## Alternativas consideradas

- **Reimportar planilha corrigida** — rejeitado: retrabalho e fila desnecessária.
- **Editar CNPJ na tabela** — rejeitado: risco de digitação; select alinha ao cadastro de UN.
- **Persistir override no banco antes de confirmar** — rejeitado: estado efêmero no cliente até confirmar é suficiente.

## Consequências

- Endpoint `resultado` inclui lista `empresas_destino` para popular os selects.
- Confirmação pode gravar destino diferente do CNPJ da coluna B.
- Duplicidade na planilha (ADR-0047) continua baseada no CNPJ da planilha, não no destino escolhido na UI.
