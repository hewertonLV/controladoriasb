# PLAN-0001: Mapeamento de código legado "8" para id_cigam "000400" na importação de veículos

**ADR:** [ADR-0001](../decisions/ADR-0001-mapeamento-unidade-negocio-veiculos.md)
**Data:** 2026-05-17
**Status:** Concluído

## Objetivo

Preparar a planilha de veículos para importação substituindo o código legado de unidade pelo id_cigam correto.

## Pré-requisitos

- Confirmar que a unidade com `id_cigam = 000400` existe cadastrada em `unidades_negocio`
- Ter acesso à planilha `planilhas/veiculos.xlsx`

## Passos

1. **Identificar a coluna de unidade** — localizar a coluna que contém o valor `8` na planilha
2. **Substituir os valores** — trocar todos os `8` por `000400` na coluna de unidade de negócio
3. **Reposicionar as colunas** — garantir que o layout final segue: A=id_sbs, B=nome, C=tipo, D=id_cigam_unidade, E=status
4. **Validar** — abrir a planilha e confirmar que todos os registros têm `000400` na coluna D

## Critério de conclusão

Planilha salva com coluna D preenchida com `000400` em todas as linhas de dados, sem nenhuma ocorrência do valor `8`.

## Riscos

- `id_cigam 000400` não cadastrado no banco — importação falha com erro de unidade não encontrada; verificar antes de importar
