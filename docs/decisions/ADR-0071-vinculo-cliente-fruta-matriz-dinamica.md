# ADR-0071: Vínculo cliente×fruta e colunas dinâmicas na matriz

**Data:** 2026-05-23
**Status:** Aceito
**Contexto:** Matriz de captação ([ADR-0068](ADR-0068-api-pedidos-painel-matriz-tempo-real.md)); catálogo com 500+ materiais/frutas

## Contexto

O cadastro de frutas/materiais tem **mais de 500 itens**. Exibir todas as colunas na matriz loja×fruta tornaria a planilha inviável. Cada **loja (cliente)** compra apenas um subconjunto de frutas. É necessário cadastrar esse vínculo e fazer a matriz exibir **somente as colunas relevantes**, expandindo dinamicamente conforme lojas entram na captação do dia.

## Decisão

### Cadastro de vínculo (pré-captação)

- Entidade **`cliente_fruta_vinculos`** (ou equivalente): `id_cliente` + `id_fruta`, único por par; vínculo **persistente** (não só do dia).
- Tela admin **“Vincular frutas ao cliente”**: busca cliente, lista frutas com checkbox/busca; salvar conjunto de frutas que a loja compra.
- Escopo: cliente já cadastrado com unidade/praça do faturamento ([ADR-0058](ADR-0058-cliente-praca-filtrada-por-unidade.md)); permissão por faturamento/galpão quando aplicável.
- Histórico de alteração do vínculo recomendado (padrão `cliente_historicos` ou tabela dedicada leve).

### Regra das colunas na matriz

- **Não** carregar as 500+ frutas como colunas fixas.
- Colunas do dia = **união** das frutas vinculadas a **todas as lojas presentes** na captação ativa do lote/galpão:
  - `colunas = ⋃ frutas_vinculadas(cliente)` para cada `cliente` com linha na matriz (ou com pedido no lote).
- Ao **incluir uma nova loja** na captação (nova linha ou primeiro pedido do app): recalcular colunas; **inserir novas colunas** à direita para frutas que ainda não existiam na união (sem remover colunas já usadas por outras lojas no mesmo dia).
- Loja sem vínculo cadastrado: **bloquear** inclusão na matriz ou exibir fluxo obrigatório “configure frutas deste cliente” (preferência: bloquear com link para tela de vínculo).

### Linhas na matriz

- Eixo vertical: lojas **adicionadas à captação** do lote (não todos os clientes do banco).
- Inclusão de loja: ação explícita na web ou primeiro pedido via app para aquele cliente no lote.

### App móvel

- Ao selecionar cliente, listar **apenas frutas vinculadas** a ele (mesma tabela de vínculo).
- Permitir cadastrar pedido só para frutas vinculadas; API valida `id_fruta` ∈ vínculos do cliente.

### API matriz

- `GET .../captacao/{lote}/matriz` retorna `lojas[]`, `frutas[]` (união dinâmica), `celulas{}`.
- Evento broadcast `MatrizColunasAtualizadas` quando nova loja altera a união de colunas.

## Alternativas consideradas

- **Todas as 500 colunas com scroll horizontal** — rejeitado; UX e performance inadequados.
- **Colunas fixas por galpão** — rejeitado; não reflete mix por loja.
- **Vínculo só no dia (por lote)** — rejeitado; operação quer cadastro estável reutilizável.
- **Inferir vínculo só pelo histórico de vendas** — rejeitado no MVP; pode ser sugestão futura na tela de vínculo.

## Consequências

- [ADR-0068](ADR-0068-api-pedidos-painel-matriz-tempo-real.md) atualizada: eixo horizontal = união dinâmica, não catálogo completo.
- [PLAN-0071](../plans/PLAN-0071-vinculo-cliente-fruta-matriz-dinamica.md); executar **antes ou junto** ao passo matriz do PLAN-0068.
- Índice em `cliente_fruta_vinculos (id_cliente, id_fruta)`.
