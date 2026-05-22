# ADR-0054: APP_URL dinâmica sem IP fixo no código

**Data:** 2026-05-20
**Status:** Aceito
**Contexto:** Servidor de produção muda de IP na rede local; `APP_URL` fixo quebra links, avatares e rotas absolutas.

## Contexto

A aplicação roda em Docker com porta publicada (`APP_PORT`). O IP da máquina pode mudar. Manter `APP_URL=http://192.168.x.x:44432` no `.env` ou no código exige manutenção manual a cada mudança de rede.

## Decisão

- Manter `APP_URL` **vazio** no `.env` de produção.
- Centralizar a resolução em `App\Support\DynamicAppUrl`:
  - **Requisições HTTP:** usar o host/porta do navegador (`Host` da requisição).
  - **CLI/fila (fallback):** detectar IPv4 da máquina + `APP_PORT`.
- Aplicar a URL resolvida em `app.url`, `filesystems.disks.public.url` e `URL::forceRootUrl()`.

## Alternativas consideradas

- IP fixo no `.env` — rejeitado; quebra quando a rede muda.
- Apenas middleware — insuficiente; `Storage::url()` e jobs usam config carregada no boot.
- Variável `APP_HOST` obrigatória — rejeitado; ainda seria configuração manual.

## Consequências

- Links e avatares seguem o endereço que o usuário digitou no navegador.
- Não é necessário alterar código ao mudar o IP do servidor.
- Em e-mails/notificações geradas fora de requisição HTTP, a URL usa o IP detectado da máquina (pode diferir do hostname usado no browser, se houver).
