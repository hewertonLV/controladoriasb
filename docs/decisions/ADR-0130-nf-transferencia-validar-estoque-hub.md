# ADR-0130: Validar estoque HUB antes do upload da NF de transferência

**Data:** 2026-05-26
**Status:** Aceito
**Contexto:** Captação — upload da NF em `TRANSFERENCIA_CIGAN_INICIADA`

## Contexto

Após iniciar a transferência Cigan, o operador envia a NF antes da saída física de estoque. As transferências gerenciais (HUB → galpão) dependem do estoque real na unidade HUB selecionada (`id_unidade_negocio_hub_origem`). Permitir o upload sem saldo suficiente adianta o fluxo e só falha na conclusão da saída física.

## Decisão

Bloquear o upload da NF (e revalidar ao concluir saída estoque físico) quando o estoque ativo no HUB for menor que a **necessidade total no HUB** por fruta: `a receber` (transferência ao galpão) + demanda das lojas com saída física no HUB ([ADR-0131](ADR-0131-abastecimento-exclui-venda-direta-hub.md)). O retorno à matriz abre **modal** com tabela: fruta, estoque no HUB e quantidade em falta (UM e kg).

## Alternativas consideradas

- Validar só na conclusão da saída físico — rejeitada: o operador descobre tarde que faltou estoque no HUB.
- Aviso sem bloqueio — rejeitada: o requisito é impedir o upload.

## Consequências

- `EnviarNfTransferenciaCiganLoteAction` e o `FormRequest` do upload chamam o validador antes de gravar o arquivo.
- Lotes sem demanda a receber (ou com galpão já abastecido) seguem permitidos.
- Testes de integração cobrem bloqueio e sucesso com estoque suficiente.
