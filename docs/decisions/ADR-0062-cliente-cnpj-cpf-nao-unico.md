# ADR-0062: CNPJ/CPF de cliente não é único

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Cadastro e importação de clientes

## Contexto

O negócio pode manter vários cadastros distintos para o mesmo documento (CNPJ ou CPF), diferenciados por `id_cigam`, unidade, praça ou razão social. A importação de clientes e parte da lógica de preview rejeitavam CPF/CNPJ repetido, embora o schema e o formulário manual já não impusessem unicidade.

## Decisão

Permitir múltiplos registros em `clientes` com o mesmo `cnpj_cpf`. A chave de negócio permanece `id_cigam` (único). Remover validações de colisão por documento na importação de clientes (preview e confirmação). Manter bloqueio apenas de `id_cigam` duplicado na planilha e no banco.

Na importação de vendas, a resolução do cliente continua por CPF/CNPJ da planilha: exige **exatamente um** cadastro com aquele documento; se houver zero ou mais de um, a linha falha com mensagem explícita (cadastro manual ou ajuste de clientes).

## Alternativas consideradas

- Manter CNPJ/CPF único — rejeitada; contradiz a operação real (filiais, cadastros legados distintos).
- Resolver venda por `id_cigam` do cliente na planilha — exigiria alterar layout da planilha de vendas; fora do escopo imediato.

## Consequências

- Cadastro manual e importação de clientes aceitam documentos repetidos.
- Importação de vendas pode falhar quando o CNPJ/CPF mapeia para mais de um cliente; operador deve usar venda manual ou consolidar cadastros.
- Consultas por documento (`where cnpj_cpf = ?`) podem retornar N registros; código que assume um único resultado deve tratar ambiguidade.
