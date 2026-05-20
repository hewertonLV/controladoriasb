# ADR-0014: Importação de ICMS separada e quatro tipos com UM própria

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** ICMS saiu de `frutas`, mas a importação de frutas ainda trazia colunas de imposto; o cadastro usava uma única UM para entrada e um único campo de venda.

## Contexto

A planilha de frutas deve conter apenas dados mestres (CIGAM, nome, unidade, kg). ICMS varia por estado e por tipo (compra nacional/externa, venda importada/nacional), cada um com unidade de medida (KG ou UM) própria.

## Decisão

1. **Importação de frutas:** colunas A–D apenas (sem ICMS).
2. **Importação de ICMS:** planilha própria com colunas A–J:
   - A: referência da fruta (ID CIGAM ou nome)
   - B: estado (ID, sigla ou nome)
   - C/D: ICMS compra nacional + UM
   - E/F: ICMS compra exterior + UM
   - G/H: ICMS venda fruta importada + UM
   - I/J: ICMS venda fruta nacional + UM
3. **Persistência:** expandir `fruta_icms` com `um_icms_nacional`, `um_icms_externo`, `icms_venda_importada`, `um_icms_venda_importada`, `icms_venda_nacional`, `um_icms_venda_nacional` (linhas ENTRADA/SAIDA mantidas).
4. **Cadastro:** grid por estado no formulário da fruta com os quatro pares valor+UM; tela dedicada de importação em `/admin/frutas/icms/importar`.
5. **Permissões:** `frutas.icms.importar` e `frutas.icms.importar-confirmar` (reutilizam perfis que já importam frutas).

## Alternativas consideradas

- Manter ICMS na planilha de frutas — rejeitado pelo usuário.
- Uma linha por estado sem operação ENTRADA/SAIDA — rejeitado; mantém ADR-0013 e separa cálculo de entrada/saída.

## Consequências

- Cálculo de entrada soma nacional e exterior convertidos para kg com UMs distintas.
- Venda passa a ter dois percentuais/valores (importada e nacional) para uso futuro.
- Planilhas antigas de frutas com colunas E–H deixam de ser aceitas na importação de frutas.
