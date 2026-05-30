# ADR-0175: Branding do PDF romaneio de rota

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Romaneio PDF por rota concluída (ADR-0174)

## Contexto

O romaneio de viagem deve identificar visualmente a marca Sítio Barreiras, alinhado ao logo institucional usado no sistema.

## Decisão

- Incluir `public/assets/images/logo cor.png` no cabeçalho do PDF, embutido em base64 (compatível com DomPDF).
- Paleta derivada do logo:
  - Azul `#1A5FB4` — título principal, bordas e cabeçalho da tabela
  - Verde `#2E7D32` — nome da rota, rótulos dos metadados e totais por loja
  - Amarelo `#FBC02D` — faixa superior e destaque do total geral
- Constantes centralizadas em `RomaneioRotaPdfBranding`.

## Alternativas consideradas

- Usar `logo.png` sem fundo — rejeitado: usuário solicitou explicitamente a versão colorida da marca.
- Referenciar URL/asset no HTML — rejeitado: DomPDF exige caminho absoluto ou data URI.

## Consequências

- Se o arquivo de logo for removido, o PDF é gerado sem imagem (fallback silencioso).
- Cores fixas no código; alteração futura exige atualizar a classe de branding.
