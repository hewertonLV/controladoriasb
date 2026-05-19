# ADR-0021: Recorte e compressão da foto de perfil no navegador

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Upload de fotos grandes falhava; usuário quer recortar e reduzir tamanho antes de enviar.

## Decisão

- Biblioteca **Cropper.js** (CDN) na página Meu Perfil.
- Após escolher arquivo, modal com recorte **1:1** (quadrado).
- Saída redimensionada para **512×512 px** em JPEG.
- Compressão progressiva no cliente (qualidade 0,92 → 0,5) até ficar **≤ 2 MB** (`PROFILE_AVATAR_MAX_KB`).
- Arquivo final substitui o `input[type=file]` antes do submit do formulário.

## Alternativas consideradas

- **Processar só no servidor (Intervention Image)** — rejeitado: upload pesado antes do corte; pior UX.
- **Sem recorte, só compressão** — rejeitado: não atende enquadramento desejado.

## Consequências

- Dependência de CDN para Cropper.js na tela de perfil.
- Fotos enviadas ao servidor já otimizadas (tipicamente &lt; 200 KB).
