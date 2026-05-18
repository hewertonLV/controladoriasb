# ADR-0002: Status padrão "ATIVO" para todos os veículos na importação inicial

**Data:** 2026-05-17  
**Status:** Aceito  
**Contexto:** Planilha `planilhas/veiculos.xlsx` — importação de veículos

## Contexto

A planilha de veículos não possuía coluna de status. O importador (`VeiculoPlanilhaNormalizer`) aceita `ATIVO` ou `INATIVO`, assumindo `ATIVO` quando a coluna estiver vazia.

## Decisão

Todos os veículos da carga inicial serão importados com status `ATIVO`, sem distinção. Nenhum veículo da planilha foi marcado como inativo.

## Alternativas consideradas

- Adicionar coluna de status por veículo com valores individuais: rejeitado — não havia informação disponível sobre veículos inativos no momento da carga.
- Importar sem status e definir depois: rejeitado — o campo é obrigatório no modelo.

## Consequências

- Veículos eventualmente inativos precisarão ser desativados manualmente após a importação.
- A planilha (`planilhas/veiculos.xlsx`) foi atualizada para incluir coluna `status = ATIVO` explicitamente.
