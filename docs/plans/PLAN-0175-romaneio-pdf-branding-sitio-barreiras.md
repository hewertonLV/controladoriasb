# PLAN-0175: Branding do PDF romaneio de rota

**ADR:** [ADR-0175](../decisions/ADR-0175-romaneio-pdf-branding-sitio-barreiras.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Aplicar logo e cores da marca Sítio Barreiras no PDF do romaneio de rota.

## Pré-requisitos

- ADR-0174 (PDF romaneio por rota)
- Arquivo `public/assets/images/logo cor.png`

## Passos

1. **Branding helper** — `RomaneioRotaPdfBranding` com cores e data URI da logo
2. **Serviço** — passar logo e cores para a view PDF
3. **View** — cabeçalho com logo, faixa amarela, tabela com cabeçalho azul e totais coloridos
4. **Testes** — garantir que o PDF continua sendo gerado

## Critério de conclusão

- PDF exibe logo Sítio Barreiras no cabeçalho
- Cores azul, verde e amarelo aplicadas conforme ADR
- Testes de romaneio passam

## Riscos

- Logo com fundo escuro no PNG — mitigar com tamanho controlado no cabeçalho
