# ADR-0047: Importação de transferências — duplicidade com NF e quantidade

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Importação de transferências por planilha (ADR-0040)

## Contexto

O preview marcava como duplicadas linhas com a mesma origem, destino e fruta, mesmo quando a NF ou a quantidade diferiam. A operação envia várias linhas da mesma fruta no mesmo par origem/destino em NFs distintas.

## Decisão

Duplicidade na planilha só quando coincidem **todos** os campos:

- CNPJ origem
- CNPJ destino
- id CIGAM fruta
- quantidade (UM), normalizada
- número da NF (maiúsculas, trim)

Mesma origem/destino/fruta com NF ou quantidade diferentes são linhas distintas válidas.

## Alternativas consideradas

- Manter chave só origem/destino/fruta — rejeitado: bloqueia NFs diferentes na mesma remessa.
- Ignorar duplicidade — rejeitado: linhas idênticas gerariam transferências repetidas na confirmação.

## Consequências

- Mensagem de erro cita os cinco critérios e a linha da primeira ocorrência.
- NF normalizada em maiúsculas no preview para consistência com o cadastro manual.
