# PLAN-0062: CNPJ/CPF de cliente não é único

**ADR:** [ADR-0062](../decisions/ADR-0062-cliente-cnpj-cpf-nao-unico.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Permitir vários cadastros de cliente com o mesmo CPF/CNPJ, removendo bloqueios na importação e documentando impacto na importação de vendas.

## Pré-requisitos

- ADR-0062 aceita
- Schema `clientes` sem índice unique em `cnpj_cpf` (migration remove se existir em produção)

## Passos

1. **Migration** — remover índice unique em `clientes.cnpj_cpf` se existir (ambientes legados).
2. **Importação preview** — remover checagem de CPF/CNPJ duplicado na planilha e colisão com banco em `ClienteImportacaoProcessor`.
3. **Importação confirmar** — remover `exists()` por `cnpj_cpf` em `ClienteImportacaoController`.
4. **Vendas import** — mensagem clara quando documento ambíguo em `VendaImportacaoProcessor`.
5. **Testes** — cadastro manual com mesmo CNPJ; importação com CNPJ repetido entre linhas novas (preview + confirmar).
6. **Verificação** — rodar testes de clientes e importação.

## Critério de conclusão

- Dois clientes com `id_cigam` distintos e mesmo `cnpj_cpf` passam no cadastro manual e na importação.
- `id_cigam` duplicado continua bloqueado.
- Testes relacionados passam.

## Riscos

- Importação de vendas com CNPJ ambíguo — mitigação: mensagem de erro orientando cadastro manual.
