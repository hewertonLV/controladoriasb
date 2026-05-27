# PLAN-0126: Arquivo Cigan EDI NF — vendas (faturamento → loja)

**ADR:** [ADR-0126](../decisions/ADR-0126-arquivo-cigan-edi-vendas-faturamento.md)
**Data:** 2026-05-26
**Status:** Concluído

## Objetivo

TXT de vendas para Cigam disponível ao iniciar o faturamento, com gerador e config separados da transferência.

## Passos

1. `config/captacao_cigan_edi_vendas.php`
2. `CiganEdiNfVendaGerador` + `GerarArquivoCiganService::conteudoTxtVendas`
3. Status `exibeAbaArquivoCiganVendas`, rota download, partial na matriz
4. Testes unitários e feature do download

## Critério de conclusão

Download gera N+I por loja com origem faturamento e destino loja; testes verdes.

## Riscos

- Tipo de operação Cigam para venda — ajustar só em `captacao_cigan_edi_vendas` após validação fiscal.
