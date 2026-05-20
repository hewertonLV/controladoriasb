# SB - CONTROLADORIA — Documento Mestre de Contexto do Sistema

> Este arquivo é a memória técnica principal do projeto **SB - CONTROLADORIA**. Ele é
> obrigatório como leitura para qualquer pessoa (ou agente de IA) que vá
> continuar o desenvolvimento. Em novos chats, basta dizer:
>
> "Leia `backend/docs/SISTEMA_CONTEXTO.md` antes de continuar."
>
> Se você for um agente: leia este arquivo na íntegra antes de propor mudanças.
> Ele descreve **o que o sistema faz hoje**, **por que está assim** e
> **quais padrões devem ser preservados**. Mudanças que conflitam com este
> documento devem ser discutidas explicitamente antes de implementadas.

Última atualização do documento: 2026-05-17 (inclui atualização do nome do sistema para SB - CONTROLADORIA).

---

## Índice

1. [Visão Geral do Sistema](#1-visão-geral-do-sistema)
2. [Stack Tecnológica](#2-stack-tecnológica)
3. [Arquitetura do Projeto](#3-arquitetura-do-projeto)
4. [Convenções do Projeto](#4-convenções-do-projeto)
5. [Módulo de Usuários](#5-módulo-de-usuários)
6. [Módulo de Permissões](#6-módulo-de-permissões)
7. [Módulo de Empresas](#7-módulo-de-empresas)
8. [Histórico / Auditoria](#8-histórico--auditoria)
9. [Componente de Tabela Administrativa](#9-componente-de-tabela-administrativa)
10. [Importação de Empresas](#10-importação-de-empresas)
11. [Exportação PDF](#11-exportação-pdf)
12. [Filas e Workers](#12-filas-e-workers)
13. [Docker](#13-docker)
14. [README e Deploy](#14-readme-e-deploy)
15. [Segurança](#15-segurança)
16. [Performance](#16-performance)
17. [Testes](#17-testes)
18. [Problemas Já Encontrados e Soluções](#18-problemas-já-encontrados-e-soluções)
19. [Como Continuar o Projeto](#19-como-continuar-o-projeto)
20. [Checklist Obrigatório para Novas Features](#20-checklist-obrigatório-para-novas-features)
21. [Módulo de Unidades de Negócio](#21-módulo-de-unidades-de-negócio)

---

## 1. Visão Geral do Sistema

**SB - CONTROLADORIA** é um sistema web administrativo interno usado para gerenciar o
**cadastro mestre de Empresas** que se relacionam com o ERP **CIGAM** da
organização (Grupo Sítio Barreiras / Sítios Barreiras). O ERP CIGAM é a fonte
primária de muitos dos dados de empresas, mas o SB - CONTROLADORIA adiciona controle,
auditoria, importação em massa, relatórios e uma camada de governança que o
CIGAM puro não oferece de forma amigável.

### 1.1. Objetivo

- Centralizar o cadastro de Empresas vinculadas ao ERP CIGAM em uma interface
  web moderna e auditável.
- Permitir que diferentes áreas (Controladoria, Hub, Unidade Comercial,
  Logística, Consulta) trabalhem com a mesma base, com **permissões granulares**.
- Suportar **importação periódica em massa** de empresas via planilha Excel
  exportada do CIGAM, com pré-visualização e confirmação seletiva.
- Permitir **exportação de relatório em PDF** dos dados filtrados.
- Manter **rastreabilidade total** de quem criou, alterou, inativou ou
  reativou cada empresa, e por qual origem (manual ou importação).
- Garantir que **nenhum registro seja apagado fisicamente** — toda saída é
  modelada como inativação para preservar histórico contábil/operacional.

### 1.2. Contexto e integração com o CIGAM

O sistema **não acessa o banco do CIGAM diretamente**. Ele é alimentado por:

1. **Cadastros manuais** (CRUD em `/admin/empresas`).
2. **Importação assíncrona** de planilha Excel (.xlsx/.xls) exportada do CIGAM,
   com layout fixo de 7 colunas (A..G) descritas em §10.

A identificação principal de cada empresa no SB - CONTROLADORIA é o campo `id_cigam`,
que **deve bater com o código numérico daquela empresa dentro do CIGAM**. Esse
campo é único, obrigatório e armazenado como `varchar` (vem do ERP como
string), mas é **ordenado numericamente** na UI (veja §7 e §16).

### 1.3. Público-alvo

- **Programador**: papel técnico com acesso total. Bypass via `Gate::before`.
- **Administrador**: gestão do sistema (usuários, grupos, empresas).
- **Controladoria**, **Hub**, **Unidade Comercial**, **Logística**: usuários
  operacionais com permissões variando por módulo.
- **Consulta**: somente leitura.

### 1.4. Funcionamento geral

O sistema é uma SPA-leve (Blade + AJAX/fetch — sem framework JS pesado). Os
fluxos principais são:

| Fluxo | Tipo | Acesso |
|---|---|---|
| Login + troca obrigatória de senha | síncrono | `auth`, `user.active` |
| Dashboard | síncrono | autenticado |
| CRUD de Empresas | síncrono | `permission:empresas.*` |
| Listagem de Empresas (pesquisa, ordenação, paginação) | **AJAX** | `permission:empresas.visualizar` |
| Importação Excel de Empresas | **assíncrona (queue)** | `permission:empresas.importar` |
| Confirmação seletiva da importação | síncrono | `permission:empresas.importar-confirmar` |
| Exportação PDF | **assíncrona (queue)** | `permission:empresas.exportar-pdf` |
| CRUD de Usuários | síncrono | `permission:usuarios.*` |
| CRUD de Grupos de Permissões | síncrono | `permission:grupos-permissoes.*` |
| CRUD de Unidades de Negócio (cadastro + inativar/ativar, sem exclusão física) | síncrono | `permission:unidades-negocio.*` |
| Listagem de Unidades de Negócio (pesquisa, ordenação, paginação) | **AJAX** | `permission:unidades-negocio.visualizar` |
| Importação Excel de Unidades de Negócio | **assíncrona (queue)** | `permission:unidades-negocio.importar` |
| Confirmação seletiva da importação (UN) | síncrono | `permission:unidades-negocio.importar-confirmar` |

Tudo que é "pesado" (Excel, PDF) vai para fila. Tudo que mexe em dados de
empresas registra histórico. Todo acesso passa por permissão. Detalhes nos
módulos.

---

## 2. Stack Tecnológica

### 2.1. Versões e dependências

Backend (declaradas em `backend/composer.json`):

- **PHP** `^8.3`
- **Laravel** `^13.7` (`laravel/framework`)
- **MySQL** `8.0` em produção/dev (SQLite `:memory:` apenas em testes)
- **Spatie Laravel Permission** `^7.4` (`spatie/laravel-permission`)
- **Maatwebsite Excel** `^3.1` (`maatwebsite/excel`) — instalado, mas hoje a
  importação Excel usa **PhpSpreadsheet puro** via `EmpresaImportacaoProcessor`
  para controlar leitura/memória; o pacote permanece para manter
  compatibilidade.
- **Barryvdh DomPDF** `^3.1` (`barryvdh/laravel-dompdf`)
- **Laravel Breeze** `^2.4` (scaffolding de autenticação, dev only)
- **Laravel Pail** `^1.2`, **Pint** `^1.27`, **Tinker** `^3.0`
- **PHPUnit** `^12.5`

Frontend / template:

- **Blade** (engine padrão do Laravel).
- **Highdmin** (template Bootstrap 5.3.3 customizado, integrado em
  `resources/views/layouts/app.blade.php` e assets sob `public/assets/`).
- **Bootstrap 5** (vem do Highdmin; usamos `Paginator::useBootstrapFive()`).
- **Remixicon** (ícones `ri-*`).
- **JS vanilla** (sem framework). Alpine.js está disponível pelo Highdmin mas
  não é usado intensivamente; a maior parte da interação é `fetch` + DOM nativo.

Infraestrutura:

- **Docker** + **docker-compose** com 4 serviços: `controladoriasb` (app),
  `worker-importacao`, `worker-exportacao`, `mysql`, `phpmyadmin`.
- **Supervisor** documentado para produção bare-metal/VM
  (`laravel-worker-importacao.conf` e `laravel-worker-exportacao.conf`).

### 2.2. Por que cada tecnologia foi escolhida

- **Laravel 13**: já era o padrão da equipe; trouxe maturidade de queues,
  migrations, validação, route model binding por UUID e Spatie integrável.
- **MySQL 8** em produção: ambiente operacional já existia. SQLite só em
  testes para velocidade (a query de ordenação numérica de `id_cigam` lida
  com os dois — veja §16).
- **Blade + JS vanilla**: o front é majoritariamente CRUD administrativo;
  adicionar SPA (Vue/React) era overkill e aumentaria o atrito do time.
- **Highdmin (Bootstrap)**: tema corporativo já adotado; mantém consistência
  visual com outros sistemas internos.
- **Spatie Permission**: padrão de mercado para roles/permissions; suporta
  `Gate::before` que usamos para liberar tudo ao **Programador**.
- **PhpSpreadsheet** (em vez do `Maatwebsite\Excel` para o pipeline real):
  controle fino sobre `setReadDataOnly`, `setReadFilter` e leitura por célula
  para conter memória/tempo em planilhas grandes (veja §10/§16/§18).
- **DomPDF**: já estava no projeto; foi mantido mas o pipeline de geração
  foi simplificado e limitado a 1000 registros para evitar OOM (veja §11/§18).
- **Docker**: padronização do ambiente; portas customizadas (44432, 44433,
  44434) para não colidir com outros serviços do mesmo host de dev.

---

## 3. Arquitetura do Projeto

### 3.1. Estrutura de pastas relevantes

```
backend/
├── app/
│   ├── Enums/                          # Permissions, Roles
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/                  # CRUD admin (Empresa, Usuario, GrupoPermissao, ...)
│   │   │   └── Auth/                   # Breeze + Force password change
│   │   ├── Middleware/                 # EnsureUserIsActive, EnsurePasswordWasChanged
│   │   └── Requests/Admin/             # FormRequests por módulo
│   ├── Jobs/Empresas/                  # importação/exportação Empresas
│   ├── Jobs/UnidadesNegocio/           # importação UN (preview)
│   ├── Models/                         # User, Empresa, UnidadeNegocio, UnidadeNegocioImportacao, ...
│   ├── Providers/AppServiceProvider.php
│   ├── Queries/                        # EmpresaQuery, UnidadeNegocioQuery
│   └── Services/                       # Empresas/*, UnidadesNegocio/* (importação)
├── database/
│   ├── factories/                      # UserFactory, EmpresaFactory, UnidadeNegocioFactory
│   ├── migrations/                     # estado atual em §3.4
│   └── seeders/                        # PermissionSeeder, RoleSeeder, UserSeeder, DatabaseSeeder
├── docker-compose.yml                  # app + workers + mysql + phpmyadmin
├── docker/php/conf.d/99-uploads.ini    # ini overrides para PHP no container
├── resources/views/
│   ├── admin/
│   │   ├── empresas/                   # index, create, edit, historico, importar, _table, pdf
│   │   ├── unidades-negocio/           # index, create, edit, importar, _table, ...
│   │   ├── usuarios/                   # index, create, edit, _table
│   │   └── grupos-permissoes/          # index, create, edit, _table
│   ├── components/admin/               # data-table, sortable-th, table-pagination
│   ├── auth/                           # views do Breeze customizadas
│   └── layouts/app.blade.php           # layout Highdmin
├── routes/web.php                      # rotas admin + auth (§1.4)
├── tests/
│   ├── Feature/Admin/Empresas/         # suite do módulo Empresas
│   ├── Feature/Admin/UnidadesNegocio/  # suite do módulo Unidades de Negócio
│   ├── Feature/Auth/                   # auth padrão do Breeze
│   ├── Feature/ExampleTest.php
│   ├── Feature/ProfileTest.php
│   └── Support/CreatesUsersWithRoles.php
├── storage/app/private/                # PDFs e planilhas reais (NÃO publicar)
├── storage/app/empresas/exportacoes/   # PDFs gerados (acesso via rota segura)
├── storage/app/empresas/importacoes/   # planilhas em processamento
├── storage/app/unidades-negocio/importacoes/   # planilhas UN (via disk local)
├── docs/SISTEMA_CONTEXTO.md            # ESTE arquivo
└── README.md                           # deploy + queue + checklist operacional
```

### 3.2. Camadas e padrões

| Camada | Responsabilidade | Exemplos |
|---|---|---|
| **Controllers** (`Admin/*Controller`) | HTTP, autorização, redirecionamento, JSON | `EmpresaController`, `EmpresaImportacaoController`, `EmpresaExportacaoController`, `UnidadeNegocioController`, `UnidadeNegocioImportacaoController`, `UsuarioController`, `GrupoPermissaoController` |
| **FormRequests** (`Http/Requests/Admin/*`) | validação e mensagens | `StoreEmpresaRequest`, `UpdateEmpresaRequest`, `StoreUnidadeNegocioRequest`, `UpdateUnidadeNegocioRequest`, `StoreUsuarioRequest`, `UpdateUsuarioRequest`, `StoreGrupoPermissaoRequest`, `UpdateGrupoPermissaoRequest` |
| **Services** (`Services/Empresas/*`) | regras de negócio reutilizáveis e auditoria | `EmpresaAuditoriaService`, `EmpresaImportacaoProcessor`, `EmpresaPlanilhaNormalizer`, `ImportacaoReadFilter` |
| **Query Objects** (`Queries/*`) | montagem segura de queries com filtros/sort | `EmpresaQuery`, `UnidadeNegocioQuery` |
| **Jobs** (`Jobs/Empresas/*`) | processamento assíncrono | `ProcessarPreviewImportacaoEmpresasJob`, `GerarPdfEmpresasJob` |
| **Models** (`Models/*`) | Eloquent (estado e relações) | `User`, `Empresa`, `UnidadeNegocio`, `EmpresaHistorico`, `EmpresaImportacao`, `EmpresaExportacao` |
| **Middleware** (`Http/Middleware/*`) | gate de sessão/usuário | `EnsureUserIsActive` (`user.active`), `EnsurePasswordWasChanged` (`password.changed`) |
| **Views/Components** (`resources/views/*`) | UI Blade | `<x-admin.data-table>`, `<x-admin.sortable-th>`, `<x-admin.table-pagination>` |

Padrões essenciais:

- **Service Layer** para regras que não pertencem ao controller (auditoria,
  processamento de planilha).
- **Query Object** (`EmpresaQuery`) para encapsular filtros, ordenações
  permitidas (allow-list), normalização e paginação.
- **Componentização Blade** para tabelas administrativas: nenhuma tela
  reimplementa pesquisa, paginação e ordenação — todas usam o componente
  `<x-admin.data-table>`.
- **Processamento assíncrono** com **filas dedicadas** (uma para importação,
  uma para exportação). Importação grande **não pode** bloquear PDF.
- **Polling** em vez de WebSocket: a UI consulta um endpoint `status` a cada
  ~1.5s para mostrar progresso "honesto" (veja §11).

### 3.3. Storage

- `storage/app/private/empresas/importacoes/` — planilhas durante processamento.
  Removidas após `confirmar` no controller (`removerArquivoTemporario`).
- `storage/app/private/empresas/exportacoes/` — PDFs gerados. Permanecem
  disponíveis até serem limpos (futuro: comando agendado).
- O disk usado é o **`local`** (`storage/app/private`). **Nunca usar o disk
  `public`** para esses arquivos — eles são privados e o download passa
  obrigatoriamente pela rota autorizada.

### 3.4. Migrations (estado atual)

Ordem cronológica das migrations relevantes:

1. `0001_01_01_000000_create_users_table` — base do Breeze.
2. `0001_01_01_000001_create_cache_table`.
3. `0001_01_01_000002_create_jobs_table` — tabela de fila `database`.
4. `2026_05_11_185525_create_permission_tables` — Spatie.
5. `2026_05_11_185536_add_login_to_users_table` — campo `login` único.
6. `2026_05_11_191405_add_must_change_password_to_users_table`.
7. `2026_05_11_192813_add_ativo_to_users_table`.
8. `2026_05_11_195706_create_empresas_table`.
9. `2026_05_11_202532_add_created_by_updated_by_to_empresas_table` —
   rastreabilidade.
10. `2026_05_11_202533_create_empresa_historicos_table`.
11. `2026_05_11_212408_create_empresa_importacoes_table`.
12. `2026_05_11_215346_create_empresa_exportacoes_table`.
13. `2026_05_12_000001_create_unidades_negocio_table` — cadastro mestre de
    unidades de negócio SB - CONTROLADORIA (`id_cigam` único, `nome`, `status`).
14. `2026_05_12_100000_create_unidade_negocio_importacoes_table` — controle de
    importações Excel (preview + confirmação), espelhando `empresa_importacoes`.

---

## 4. Convenções do Projeto

### 4.1. Nomenclatura

- **Idioma**: identificadores de domínio em **português** (`empresas`,
  `historicos`, `inativar`). Identificadores técnicos do Laravel ficam em
  inglês (`User`, `Roles`, `Permissions`).
- **Rotas nomeadas**: `admin.<modulo>.<acao>` (ex.: `admin.empresas.index`,
  `admin.empresas.exportacoes.download`). Já há aliases de subgrupos com
  ponto duplo (`exportacoes.pdf.iniciar`, `importar.confirmar`).
- **Permissões**: `<modulo>.<acao>` (ex.: `empresas.exportar-pdf`,
  `usuarios.resetar-senha`). Registradas em `App\Enums\Permissions`.
- **Roles**: nomes humanos (`Programador`, `Administrador`,
  `Controladoria`, `Hub`, `Unidade Comercial`, `Logística`, `Consulta`)
  declarados em `App\Enums\Roles`.

### 4.2. Padrão de controllers

- Recebem dependências via construtor (`__construct` com `private readonly`).
- Validação fica em **FormRequest**, não no método.
- Operações que mexem em mais de uma tabela usam `DB::transaction(...)`.
- Resposta de tela: `redirect()->route('...')->with('success'|'info'|'error', '...')`.
- Resposta JSON: status HTTP correto (`200`, `202`, `409`, `422`, `403`, `404`).
  Sempre incluir `message` em respostas de erro e payload tipado em sucesso.

### 4.3. Padrão de jobs

- `ShouldQueue`, `Dispatchable`, `InteractsWithQueue`, `Queueable`,
  `SerializesModels`.
- `public int $timeout = 900;`
- `public int $tries = 1;` (importação e exportação **não** podem repetir).
- Selecionar a fila no construtor: `$this->onQueue('empresas-importacao')`
  ou `$this->onQueue('empresas-exportacao')`.
- Sempre tratar `Throwable` no `handle`, marcar o registro como `FALHOU`
  com `erro_mensagem` amigável e relançar para que o `failed()` também tenha
  rede de segurança.
- Mensagens de erro amigáveis em `mensagemAmigavel(Throwable)` (memória,
  timeout, regras de negócio).

### 4.4. Padrão de responses JSON (assíncronos)

- `iniciar` retorna `202 Accepted` com `{ uuid, status, mensagem, urls }`
  (URLs **relativas** geradas com `route(..., $model, false)` — veja §15).
- `status` retorna `200` com `{ status, mensagem, total_*, *_at, download_url? }`.
- `download` retorna o arquivo (`200`, `content-type: application/pdf`) ou
  `409`/`404` quando inadequado.
- `confirmar` retorna `200` com `resumo` (criadas/atualizadas/ignoradas/erros).

### 4.5. Padrão visual

- Listas administrativas: **sempre** com `<x-admin.data-table>`. Pesquisa em
  tempo real (debounce 300ms), ordenação dinâmica, paginação AJAX.
- Mensagens de feedback: `alert alert-success/info/error alert-dismissible`.
- Estados de loading: spinner pequeno embutido (`spinner-border-sm`) +
  `is-loading` no container; nada de "telas brancas".
- Botões: sempre `type="button"` para ações JS.

### 4.6. Regras invioláveis

- **Nunca delete físico** de empresas, usuários ou histórico. Use
  inativação/desativação.
- **Sempre registre histórico** para mutações em `empresas`. Mesmo um update
  silencioso (sem `diff`) chama o auditor — se o diff for vazio, nenhum
  registro é criado, mas isso é decisão do `EmpresaAuditoriaService`.
- **Nunca expor caminho físico** do storage. O download de PDF passa pela
  rota `admin.empresas.exportacoes.download`.
- **Tarefas pesadas vão para fila**, em fila dedicada (importação vs.
  exportação separadas).
- **Controllers magros**: validação em Form Request, regra de negócio em
  Service ou Query.
- **Permissões obrigatórias** em toda rota administrativa. Programador é
  liberado por `Gate::before` no `AppServiceProvider`.
- **Auditoria não vaza segredo**: o snapshot da empresa nunca inclui dados
  sensíveis. A entidade Empresa também não tem senha.
- **Pesquisa unitária**: a busca usa **uma única string** sem quebrar em
  palavras (`like %search%` sobre vários campos). Não fazer split por espaços.

---

## 5. Módulo de Usuários

### 5.1. Autenticação

- Pacote base: **Laravel Breeze 2.4** (Blade). As views ficam em
  `resources/views/auth/` e foram customizadas para o tema Highdmin.
- Tipo de login: por **`login`** (campo username único) ou **`email`** —
  o `LoginRequest` aceita ambos.
- Reset de senha via Breeze (e-mail) continua disponível, mas o fluxo
  operacional é o **reset administrativo** (§5.5).

### 5.2. Senha padrão e troca obrigatória

- `App\Http\Controllers\Admin\UsuarioController::DEFAULT_PASSWORD = 'sitiosbs'`.
- Toda criação e todo reset administrativo grava `must_change_password = true`.
- O middleware **`password.changed`** (`EnsurePasswordWasChanged`) intercepta
  qualquer rota autenticada exceto:
  - `GET /alterar-senha-obrigatoria` → `password.force.change`
  - `PUT /alterar-senha-obrigatoria` → `password.force.update`
  - `POST /logout`
- Se o usuário tenta acessar outra rota com `must_change_password = true`,
  ele é redirecionado para `password.force.change` com a flash
  `warning => "Você precisa trocar sua senha temporária antes de continuar."`.

### 5.3. Conta inativa

- Coluna `users.ativo` (boolean, default true).
- Middleware **`user.active`** (`EnsureUserIsActive`) verifica em cada
  request: se `ativo = false`, faz `Auth::logout()`, invalida sessão e
  redireciona para login com mensagem.
- Não existe rota de "deletar conta" — `ProfileTest` valida que a rota está
  desabilitada (responde `405`). A escolha é manter histórico.

### 5.4. Roles

Definidas em `App\Enums\Roles`:

```
Programador        — acesso total via Gate::before
Administrador      — acesso amplo, mas regido por permissões
Controladoria      — operacional
Hub                — operacional
Unidade Comercial  — operacional
Logística          — operacional
Consulta           — somente leitura
```

### 5.5. Reset administrativo

- Rota: `POST /admin/usuarios/{user}/resetar-senha`
- Permissão: `usuarios.resetar-senha`
- Comportamento:
  - Bloqueia reset do **usuário protegido** (e-mail `hewerton@sitiobarreiras.com.br`).
  - Bloqueia reset da própria conta (use o perfil para isso).
  - Define `password = Hash::make(DEFAULT_PASSWORD)` e
    `must_change_password = true`.

### 5.6. Desativar/reativar

- `POST /admin/usuarios/{user}/desativar` (permissão `usuarios.desativar`).
- `POST /admin/usuarios/{user}/reativar` (permissão `usuarios.reativar`).
- Regras:
  - Não permite desativar o **usuário protegido**.
  - Não permite desativar quem tem role `Programador`.
  - Não permite desativar a si mesmo.

### 5.7. Não existe exclusão

- Apesar de existir a permissão `usuarios.excluir` em
  `App\Enums\Permissions::USUARIOS_EXCLUIR`, **não há rota** de delete.
  Mantida na enum para futura modulação, mas a UI nunca mostra a ação.

---

## 6. Módulo de Permissões

### 6.1. Grupos (Roles) e permissões (Permissions)

- Implementação: **Spatie Permission** com guard `web`.
- Permissões são listadas em `App\Enums\Permissions` agrupadas por módulo
  via `Permissions::groups()`:
  - Usuários: `visualizar, criar, editar, excluir, resetar-senha, desativar, reativar`.
  - Grupos de Permissões: `visualizar, criar, editar`.
  - Empresas: `visualizar, criar, editar, inativar, reativar, importar,
    importar-confirmar, historico, exportar-pdf`.
  - Unidades de Negócio: `visualizar, criar, editar, excluir, reativar,
    importar, importar-confirmar`.
- Roles são listadas em `App\Enums\Roles`.

### 6.2. Telas (`/admin/grupos-permissoes`)

- Index, create, edit (sem delete).
- Permissões são apresentadas agrupadas por prefixo (humanizado) no
  componente de edição.
- A role `Programador` **não pode ser alterada** pela tela — qualquer
  tentativa de update é bloqueada com mensagem de erro.

### 6.3. Programador é especial

- `App\Providers\AppServiceProvider::boot()`:
  ```php
  Gate::before(fn (User $user) => $user->hasRole(Roles::PROGRAMADOR->value) ? true : null);
  ```
- Isso significa: Programador passa em **qualquer** `can()` ou middleware
  `permission:*`. Mas validações de **dono do recurso** (importação/exportação)
  ainda são feitas explicitamente para evitar vazamento por UUID.

### 6.4. Modelo de relação

- `User` usa `HasRoles`.
- `Role` tem `permissions` (M:N).
- A coluna `permissions_count` (eager loaded via `withCount`) é usada para
  ordenação na tela de grupos.

---

## 7. Módulo de Empresas

### 7.1. Tabela `empresas` (campos)

| Campo | Tipo | Regra |
|---|---|---|
| `id` | bigint PK | auto |
| `id_cigam` | varchar único | obrigatório, **ordenação numérica** |
| `status` | boolean default true | `true` = ativa, `false` = inativa |
| `nome` | varchar | obrigatório (razão social) |
| `fantasia` | varchar nullable | opcional |
| `cpf_cnpj` | varchar único (apenas dígitos) | obrigatório |
| `unidade_negocio` | int | obrigatório, ≥ 1 |
| `tipo_pessoa` | varchar (`FISICA`/`JURIDICA`) | obrigatório |
| `created_by`, `updated_by` | FK → `users.id` nullable | auditoria |
| `created_at`, `updated_at` | timestamps | |

Constantes do modelo:

- `Empresa::TIPO_PESSOA_FISICA = 'FISICA'`
- `Empresa::TIPO_PESSOA_JURIDICA = 'JURIDICA'`

Scopes:

- `Empresa::ativas()` — `where('status', true)`.
- `Empresa::inativas()` — `where('status', false)`.

Accessor:

- `cpf_cnpj_formatado` — formata para exibição (não altera o storage).

### 7.2. Validações

- **CPF** = 11 dígitos quando `tipo_pessoa = FISICA`.
- **CNPJ** = 14 dígitos quando `tipo_pessoa = JURIDICA`.
- `id_cigam` único.
- `cpf_cnpj` único.

Implementadas em `StoreEmpresaRequest` / `UpdateEmpresaRequest` e
revalidadas no fluxo de importação (`EmpresaImportacaoController` +
`EmpresaPlanilhaNormalizer`).

### 7.3. Rotas (resumo)

```
GET    /admin/empresas                             empresas.visualizar
GET    /admin/empresas/criar                       empresas.criar
POST   /admin/empresas                             empresas.criar
GET    /admin/empresas/{empresa}/editar            empresas.editar
PUT    /admin/empresas/{empresa}                   empresas.editar
GET    /admin/empresas/{empresa}/historico         empresas.historico
POST   /admin/empresas/{empresa}/inativar          empresas.inativar
POST   /admin/empresas/{empresa}/reativar          empresas.reativar
GET    /admin/empresas/importar                    empresas.importar
POST   /admin/empresas/importar/iniciar            empresas.importar
GET    /admin/empresas/importar/{importacao:uuid}/status      empresas.importar
GET    /admin/empresas/importar/{importacao:uuid}/resultado   empresas.importar
POST   /admin/empresas/importar/{importacao:uuid}/confirmar   empresas.importar-confirmar
POST   /admin/empresas/exportacoes/pdf/iniciar     empresas.exportar-pdf
GET    /admin/empresas/exportacoes/{exportacao:uuid}/status    empresas.exportar-pdf
GET    /admin/empresas/exportacoes/{exportacao:uuid}/download  empresas.exportar-pdf
GET    /admin/empresas/exportar-pdf                empresas.exportar-pdf   (fallback síncrono — não exposto na UI)
```

### 7.4. Inativação / reativação

- **Não há rota de delete.** Os testes garantem isso (`EmpresaStatusTest`).
- `inativar`/`reativar`:
  - Se já estava no estado-alvo, retorna `info` sem mexer no banco.
  - Caso contrário, `forceFill(['status' => ..., 'updated_by' => ...])` e
    chama `EmpresaAuditoriaService::registrarInativacao|registrarReativacao`.
  - Tudo em `DB::transaction`.

### 7.5. Auditoria automática

Toda criação e toda atualização de empresa **deve** passar pelo
`EmpresaAuditoriaService` (§8). O `EmpresaController` faz isso em
`store`/`update`, e o `EmpresaImportacaoController` faz nos itens
confirmados.

---

## 8. Histórico / Auditoria

### 8.1. Tabela `empresa_historicos`

| Campo | Tipo | Observação |
|---|---|---|
| `id` | bigint PK | |
| `empresa_id` | FK → `empresas.id` | |
| `user_id` | FK → `users.id` nullable | autor do evento (pode ser nulo se origem é sistêmica) |
| `origem` | varchar | `MANUAL` ou `IMPORTACAO_EXCEL` |
| `acao` | varchar | `CRIACAO`, `ATUALIZACAO`, `INATIVACAO`, `REATIVACAO`, `IMPORTACAO_CRIACAO`, `IMPORTACAO_ATUALIZACAO` |
| `dados_antes` | json nullable | snapshot anterior (na criação é null) |
| `dados_depois` | json nullable | snapshot novo (na inativação/reativação é null) |
| `alteracoes` | json nullable | lista `[{ campo, antes, depois }]` |
| `created_at` | timestamp | timestamps `UPDATED_AT = null` |

### 8.2. Service `EmpresaAuditoriaService`

Métodos públicos:

- `registrarCriacao(Empresa, ?User, string $origem)` —
  ação `CRIACAO` ou `IMPORTACAO_CRIACAO` conforme origem.
- `registrarAtualizacao(Empresa, array $antes, array $depois, ?User, string $origem, ?string $acao = null)` —
  se o `diff()` for vazio, **não cria** histórico. Caso contrário,
  ação `ATUALIZACAO` ou `IMPORTACAO_ATUALIZACAO`.
- `registrarInativacao(Empresa, ?User)`.
- `registrarReativacao(Empresa, ?User)`.

Helpers internos:

- `snapshot(Empresa)` — projeta `id_cigam, status, nome, fantasia, cpf_cnpj,
  unidade_negocio, tipo_pessoa`.
- `diff(array $antes, array $depois)` — compara apenas os
  `COMPARABLE_FIELDS` com casts coerentes (`status` boolean,
  `unidade_negocio` integer, demais strings).

### 8.3. Princípios

- **Nenhum dado sensível** é gravado em histórico (a entidade não tem senha).
- O histórico **nunca** é apagado.
- O service **não altera** `created_by`/`updated_by` da empresa: isso é
  responsabilidade do controller (quem fez a mutação).
- O `EmpresaHistorico` expõe `rotuloAcao()` e `rotuloOrigem()` para a UI.

### 8.4. Tela `/admin/empresas/{empresa}/historico`

- Permissão `empresas.historico`.
- Paginação fixa de 50 por página.
- Carrega `with('user')` para mostrar o autor de cada evento.
- Mostra `loadMissing('createdBy', 'updatedBy')` na empresa para o cabeçalho.

---

## 9. Componente de Tabela Administrativa

### 9.1. Componente principal

Arquivo: `resources/views/components/admin/data-table.blade.php`.

Props (todos opcionais salvo `endpoint`):

- `title`, `subtitle`, `searchPlaceholder`.
- `endpoint` — URL onde o controller renderiza o partial `_table` quando o
  request é AJAX (veja §9.4).
- `currentSearch`, `currentPerPage`, `currentSort`, `currentDirection`.
- `perPageOptions` (default `[10, 20, 50, 100]`).
- `showSearch`, `showPerPage`.
- `containerId` (default aleatório). Usado para gerar `data-container` e
  os IDs de filhos.

Slots:

- `actions` — botões à direita (ex.: "Gerar PDF", "Importar Excel", "Nova Empresa").
- `filters` — campos extras (`<select data-table-filter>` etc.).
- `slot` padrão — o partial `_table` da tela.

### 9.2. Comportamento JS embutido

- Usa **event delegation** no container, então sobrevive a recarregamentos
  AJAX do partial.
- `searchEl.addEventListener('input', debounce(reload, 300))` — busca em
  tempo real **enquanto digita**, sem botão.
- Pesquisa é uma **string única** (não há split em palavras).
- Mudança em `per_page`, `sort`, `direction` ou qualquer
  `[data-table-filter]` dispara `reload(root)`.
- Cliques em `a.page-link` (paginator) e `[data-table-link]` (links de
  ordenação) são interceptados; `href` vira a URL do reload.
- `history.replaceState` mantém a URL do navegador sincronizada com os
  filtros (sem somar entradas no histórico).
- Estados de loading:
  - `[data-table-loading]` ganha/perde `d-none`.
  - `.is-loading` no root ativa CSS de opacidade no container.
- Links de exportação com `[data-export-link]` recebem o querystring
  atualizado automaticamente (`updateExportLinks`).

### 9.3. Ordenação

- Componente `<x-admin.sortable-th>` em `resources/views/components/admin/sortable-th.blade.php`.
- A coluna armazena a direção em inputs `data-table-sort` e
  `data-table-direction`, escondidos no card-header.
- O componente respeita uma **allow-list** definida no backend
  (`EmpresaQuery::ALLOWED_SORTS`, `UnidadeNegocioQuery`,
  `GrupoPermissaoController::ALLOWED_SORTS`,
  `UsuarioController::ALLOWED_SORTS`). Qualquer valor fora cai no default.
- **`id_cigam` é ordenado numericamente**: ver §16.

### 9.4. Padrão dos controllers de listagem

```
public function index(Request $request): View
{
    $filtros = $this->extrairFiltros($request); // ou EmpresaQuery::filtrosFromRequest
    $query = $this->aplicarFiltros($baseQuery, $filtros);

    if ($filtros['per_page'] === 'all') { ... }
    else { $paginator = $query->paginate(...)->appends($filtros); }

    if ($request->ajax()) {
        return view('admin.<modulo>._table', $payload);
    }
    return view('admin.<modulo>.index', $payload);
}
```

Esse padrão se repete em `EmpresaController`, `UnidadeNegocioController`,
`UsuarioController`, `GrupoPermissaoController`.

---

## 10. Importação de Empresas

### 10.1. Visão

A importação carrega uma planilha exportada do CIGAM em formato fixo
(colunas A..G), processa em **background** com fila dedicada, gera um
**preview** (novas / atualizações / sem alterações / erros) e exige
**confirmação seletiva** antes de gravar em `empresas`.

### 10.2. Layout fixo da planilha (sem depender do cabeçalho)

| Coluna | Campo | Tipo | Regra |
|---|---|---|---|
| A | `status` (Ativo) | boolean | aceita `SIM/S/1/TRUE/V`, `NAO/N/0/FALSE/F`; default `true` |
| B | `id_cigam` (Empresa) | string | obrigatório |
| C | `nome` | string | obrigatório |
| D | `fantasia` | string nullable | opcional |
| E | `cpf_cnpj` (CNPJ/CPF) | string | obrigatório, apenas dígitos |
| F | `unidade_negocio` | int | obrigatório, ≥ 1 |
| G | `tipo_pessoa` (Pessoa) | string | obrigatório, aceita `F/PF/FISICA/PESSOA FISICA` ou `J/PJ/JURIDICA/PESSOA JURIDICA` |

A normalização é centralizada em `App\Services\Empresas\EmpresaPlanilhaNormalizer`.

### 10.3. Fluxo completo

1. **Upload** — `POST /admin/empresas/importar/iniciar`
   - Valida `file`, `mimes:xlsx,xls`, `max:5120` (5 MB).
   - Salva o arquivo em `storage/app/private/empresas/importacoes/` via
     `$file->store('empresas/importacoes', 'local')`.
   - Cria um registro `EmpresaImportacao` com `uuid`, `user_id`,
     `arquivo_original`, `arquivo_path`, `status = AGUARDANDO`.
   - Despacha `ProcessarPreviewImportacaoEmpresasJob::dispatch($importacao->id)`
     na fila `empresas-importacao`.
   - Retorna `202 Accepted` com `{ uuid, status, urls: { status, resultado, confirmar } }`.

2. **Worker** — `App\Jobs\Empresas\ProcessarPreviewImportacaoEmpresasJob`
   - `timeout=900`, `tries=1`, fila `empresas-importacao`.
   - Marca `STATUS_PROCESSANDO` e chama `EmpresaImportacaoProcessor::processar`.
   - Em qualquer `Throwable`, chama `marcarFalha` (status `FALHOU` + mensagem
     amigável) e relança para o queue worker registrar.

3. **Processor** — `EmpresaImportacaoProcessor`
   - Limita leitura a `MAX_LINHAS_ESCANEADAS = 5000` linhas e às colunas A..G
     via `ImportacaoReadFilter`.
   - Lê com `IOFactory::createReaderForFile`, `setReadDataOnly(true)`,
     `setReadEmptyCells(false)`.
   - Itera linha a linha; linhas vazias contam para o progresso.
   - Detecta **duplicatas dentro da planilha** por `id_cigam` e por `cpf_cnpj`.
   - A cada 100 linhas faz **flush de buffer**:
     - `WHERE id_cigam IN (...)` para detectar `empresa_existente`.
     - `WHERE cpf_cnpj IN (...)` para detectar **colisão por CPF/CNPJ**
       contra outras empresas.
     - Distribui cada linha em uma das 4 listas:
       `novas`, `atualizacoes` (com `campos_alterados`), `sem_alteracoes`,
       `erros`.
   - Atualiza `EmpresaImportacao` a cada 25 linhas e a cada flush com
     `linhas_processadas`, `percentual`, `novas_count`, `atualizacoes_count`,
     `sem_alteracoes_count`, `erros_count`.
   - Ao final grava `STATUS_CONCLUIDO`, `percentual = 100` e salva o
     `resultado` em JSON.

4. **Polling** — `GET /admin/empresas/importar/{uuid}/status`
   - Retorna `status`, `total_linhas`, `linhas_processadas`, `percentual`,
     contadores e timestamps.

5. **Resultado** — `GET /admin/empresas/importar/{uuid}/resultado`
   - `409` se ainda não está `CONCLUIDO`.
   - Retorna `novas`, `atualizacoes`, `sem_alteracoes`, `erros`.

6. **Confirmação seletiva** — `POST /admin/empresas/importar/{uuid}/confirmar`
   - Permissão **`empresas.importar-confirmar`**.
   - Body: `row_ids_novas[]`, `row_ids_atualizacoes[]`.
   - Lê o resultado **do banco** (não confia em payload do front).
   - Em `DB::transaction`:
     - Cria novas (revalida tamanho de documento, `id_cigam` único e
       colisão de `cpf_cnpj`).
     - Atualiza existentes (verifica `empresa_id ↔ id_cigam` para evitar
       inconsistência; revalida colisão de `cpf_cnpj`).
     - Cada criação/atualização registra histórico em
       `empresa_historicos` com origem `IMPORTACAO_EXCEL`.
   - Retorna `resumo` com `criadas`, `atualizadas`, `ignoradas`, `erros`.
   - Remove o arquivo temporário com `removerArquivoTemporario`.

### 10.4. Segurança

- Acesso a `status`, `resultado` e `confirmar` exige que o usuário seja
  o **dono** da importação (`user_id`) ou tenha role **Programador**.
- A rota usa `{importacao:uuid}` (não `id` incremental).

### 10.5. Por que tudo isso é assíncrono?

- Antes era síncrono e quebrava em planilhas de >2 mil linhas:
  - **Memória**: o PhpSpreadsheet inteiro carregado e mantido na requisição.
  - **Timeout**: requisição HTTP ultrapassava 300s do PHP/Nginx.
- A solução foi mover para fila **dedicada** (`empresas-importacao`), com
  `setReadDataOnly`, read filter, leitura célula a célula e buffer de 100
  linhas para minimizar queries.
- O worker é **obrigatório** em produção; sem ele a importação fica em
  `AGUARDANDO` para sempre. Detalhe em §12.

---

## 11. Exportação PDF

### 11.1. Visão

A exportação PDF da listagem de Empresas é **assíncrona via fila dedicada**
(`empresas-exportacao`). O usuário clica em "Gerar PDF", o backend devolve
um UUID, a UI faz polling até `CONCLUIDO` ou `FALHOU`, e então oferece um
botão "Baixar PDF".

### 11.2. Modelo `EmpresaExportacao`

- Tabela: `empresa_exportacoes`.
- Campos: `uuid`, `user_id`, `tipo` (`'PDF'`), `status`, `filtros` (json),
  `arquivo_path`, `arquivo_nome`, `total_registros`, `erro_mensagem`,
  `started_at`, `finished_at`.
- Statuses: `AGUARDANDO`, `PROCESSANDO`, `CONCLUIDO`, `FALHOU`.
- Route key: `uuid`.

### 11.3. Controller `EmpresaExportacaoController`

- `iniciar(Request)`:
  - Normaliza filtros via `EmpresaQuery::normalizarFiltros`.
  - Cria `EmpresaExportacao` com `status=AGUARDANDO`.
  - Despacha `GerarPdfEmpresasJob`.
  - Retorna `202` com:
    ```json
    {
      "uuid": "...",
      "status": "AGUARDANDO",
      "mensagem": "O PDF foi solicitado e aguarda o worker iniciar o processamento.",
      "created_at": "ISO-8601",
      "urls": {
        "status": "/admin/empresas/exportacoes/{uuid}/status",
        "download": "/admin/empresas/exportacoes/{uuid}/download"
      }
    }
    ```
  - **URLs são relativas** (`route(..., $model, false)`), evitando 404 quando
    `APP_URL` está com porta/host diferente do navegador (veja §15 e §18).
- `status(Request, EmpresaExportacao)`:
  - Autoriza dono ou Programador.
  - Retorna `status`, `mensagem`, `total_registros`, `arquivo_nome`,
    `erro_mensagem`, `created_at/started_at/finished_at` ISO-8601 e
    `download_url` (somente quando concluído).
- `download(Request, EmpresaExportacao)`:
  - Autoriza dono ou Programador.
  - `409` se ainda não está pronto.
  - `404` se `arquivo_path` é null ou o arquivo não existe no disk `local`.
  - `response()->download(Storage::disk('local')->path(...), arquivo_nome,
    ['Content-Type' => 'application/pdf'])`.

### 11.4. Job `GerarPdfEmpresasJob`

- Fila `empresas-exportacao`, `timeout=900`, `tries=1`.
- `LIMITE_REGISTROS_PDF = 1000` (constante pública).
- `ini_set('memory_limit', '512M')` + `@set_time_limit(900)`.
- Carrega só as colunas necessárias:
  `id_cigam, nome, fantasia, cpf_cnpj, unidade_negocio, status, tipo_pessoa`.
- Conta total (`(clone $query)->toBase()->count()`):
  - Se `> 1000`, persiste `total_registros = $total`, lança
    `RuntimeException` com mensagem amigável e marca `FALHOU`.
- Carrega `Pdf::loadView('admin.empresas.pdf', ...)` com `setPaper('a4', 'landscape')` e:
  ```php
  ->setOptions([
      'defaultFont' => 'Helvetica',
      'dpi' => 96,
      'isRemoteEnabled' => false,
      'isFontSubsettingEnabled' => true,
  ]);
  ```
- Salva o binário em `storage/app/private/empresas/exportacoes/empresas_<timestamp>_<uuid>.pdf`.
- Marca `CONCLUIDO` com `total_registros` final.
- Em qualquer `Throwable` (inclusive `Allowed memory size`, `Maximum execution time`,
  `RuntimeException` do limite), `marcarFalha` grava `FALHOU` + `erro_mensagem`
  amigável e relança.

### 11.5. View `admin/empresas/pdf.blade.php`

PDF **propositalmente simples** para minimizar parse no DomPDF:

- Sem layout principal, sem Highdmin, sem Bootstrap, sem Alpine, sem JS,
  sem assets externos, sem imagens, sem fontes customizadas.
- CSS mínimo, `font-family: Helvetica, Arial, sans-serif`.
- Apenas tabela `border-collapse` com bordas `#bbb`.
- Helper inline para formatar CPF/CNPJ e mapear `tipo_pessoa`/`status`
  para `Física/Jurídica` e `Ativa/Inativa`.

### 11.6. UI em `admin/empresas/index.blade.php`

- Card de status fica **acima da tabela**, logo abaixo do título.
- Estados visuais:
  - `AGUARDANDO`: título "Aguardando worker da fila...", spinner pequeno,
    contador "Aguardando há Xs", hint sobre verificar o worker
    `empresas-exportacao`. Após 15s mostra `alert-warning`. Após 60s
    mostra o comando técnico:
    ```bash
    php artisan queue:work --queue=empresas-exportacao,default --sleep=1 --tries=1 --timeout=900
    ```
  - `PROCESSANDO`: título "Gerando PDF...", barra animada indeterminada,
    contador some.
  - `CONCLUIDO`: título "PDF pronto", barra verde 100%, botões "Baixar PDF",
    "Gerar novo PDF" e "Fechar".
  - `FALHOU`: título "Falha ao gerar PDF", barra vermelha, mensagem do
    `erro_mensagem` (ou fallback amigável), botões "Tentar novamente" e
    "Fechar".
- Polling: intervalo `1500ms`, backoff em erro de rede até 5 tentativas;
  encerra em `CONCLUIDO`/`FALHOU`.
- Botões "Gerar PDF" / "Gerar novo PDF" / "Tentar novamente" reusam a função
  `iniciarExportacao()`.

### 11.7. Mensagem técnica do fallback síncrono

A rota `admin.empresas.exportar-pdf` (`EmpresaController::exportarPdf`)
**ainda existe**, mas **não é exibida na UI**. Ela é mantida só como
fallback técnico para relatórios pequenos / debug. O texto "Fallback
síncrono temporário para arquivos pequenos" foi **removido da interface**
e fica documentado apenas no código e no README.

---

## 12. Filas e Workers

### 12.1. Configuração

- `.env`: `QUEUE_CONNECTION=database` (em produção e dev). A tabela `jobs`
  existe via migration padrão do Laravel.
- A conexão **redis** em `config/queue.php` usa `'block_for' => null` (sem
  bloqueio longo em `blpop`); como o padrão do projeto é `database`, o worker
  faz polling curto na tabela — o parâmetro CLI `--sleep` controla a pausa entre
  tentativas quando a fila está vazia.
- **Nunca usar `QUEUE_CONNECTION=sync` em produção** — quebraria o polling.
- Em testes, usamos `QUEUE_CONNECTION=sync` propositalmente (PHPUnit) em
  conjunto com `Queue::fake()` quando é necessário verificar dispatch.

### 12.2. Filas

- `empresas-importacao` — usada por `ProcessarPreviewImportacaoEmpresasJob`.
- `unidades-negocio-importacao` — usada por
  `ProcessarPreviewImportacaoUnidadesNegocioJob`.
- `empresas-exportacao` — usada por `GerarPdfEmpresasJob`.
- `default` — fallback (não usada hoje pelos módulos do SB - CONTROLADORIA, mas
  workers a incluem para futuras tarefas leves).

**Por que duas filas?** Para que uma importação grande não bloqueie um PDF
(e vice-versa). Workers são **independentes**.

### 12.3. Como rodar workers

Local / dev (em foreground):

```bash
docker compose exec controladoriasb \
  php artisan queue:work \
  --queue=empresas-importacao,unidades-negocio-importacao,empresas-exportacao,default \
  --sleep=1 --tries=1 --timeout=900
```

Produção (Docker, recomendado para este projeto):

```bash
docker compose up -d worker-importacao worker-exportacao
docker compose logs -f worker-importacao
docker compose logs -f worker-exportacao
docker compose restart worker-importacao worker-exportacao
```

Produção (bare-metal/VM, Supervisor):

- `/etc/supervisor/conf.d/laravel-worker-importacao.conf` — filas
  `empresas-importacao,unidades-negocio-importacao`.
- `/etc/supervisor/conf.d/laravel-worker-exportacao.conf` — `--queue=empresas-exportacao`.
- Comandos:
  ```bash
  sudo supervisorctl reread
  sudo supervisorctl update
  sudo supervisorctl start laravel-worker-importacao:*
  sudo supervisorctl start laravel-worker-exportacao:*
  sudo supervisorctl restart laravel-worker-importacao:* laravel-worker-exportacao:*
  ```

### 12.4. Pós-deploy

```bash
php artisan queue:restart
# ou, com Supervisor:
sudo supervisorctl restart laravel-worker-importacao:* laravel-worker-exportacao:*
```

### 12.5. Troubleshooting

| Sintoma | Causa provável | Solução |
|---|---|---|
| Importação fica em `AGUARDANDO` | worker parado | iniciar `worker-importacao` ou rodar `queue:work` |
| Exportação fica em `AGUARDANDO` | worker parado | iniciar `worker-exportacao` |
| Job falha imediato com `MaxAttemptsExceededException` | `tries > 1` reprocessa erro lógico | manter `tries=1`; corrigir causa raiz |
| Job inicia mas trava | timeout do worker < `timeout` do job | igualar `--timeout=900` |
| Pós-deploy o job ainda usa código velho | worker mantém código carregado | `queue:restart` ou reiniciar container/Supervisor |
| `docker compose down` demora (um serviço “pendurado”) | **MySQL** (flush InnoDB) ou **worker** com job longo (até `--timeout`); `stop_grace_period` é só **teto**, não alonga parada à toa | Conferir `docker compose logs` qual serviço parou por último; workers: comando em **lista YAML** para `php` ser PID 1; não reduzir `--timeout` abaixo do pior caso de import/PDF |

## 13. Docker

### 13.1. Serviços (`docker-compose.yml`)

- **controladoriasb** (`controladoriasb-app`)
  - Imagem: `controladoriasb-app:latest` (build da raiz).
  - Porta exposta: `44432:80`.
  - Monta o código (`./:/var/www/html`) e `docker/php/conf.d/99-uploads.ini`.
- **worker-importacao** (`controladoriasb-worker-importacao`)
  - Mesma imagem; `queue:work` (não `queue:listen`) em **forma de lista** no YAML para
    `php` ser PID 1 e receber **SIGTERM** direto do Docker (encerramento mais previsível
    do que `command: >` com shell).
  - `--queue=empresas-importacao,unidades-negocio-importacao`, `--sleep=1`,
    `--tries=1`, `--timeout=900`, `--max-time=3600`.
  - `stop_signal: SIGTERM`, `stop_grace_period: 930s` (≥ timeout do job + margem).
- **worker-exportacao** (`controladoriasb-worker-exportacao`)
  - Igual ao anterior, fila `empresas-exportacao`; mesmos `stop_signal` / `stop_grace_period` / `--sleep=1`.
- **mysql** (`controladoriasb-mysql`)
  - `mysql:8.0`, porta `44433:3306`.
  - Variáveis: `MYSQL_ROOT_PASSWORD`, `MYSQL_DATABASE` lidas do `.env`.
  - Volume nomeado `controladoriasb_mysql_data`.
- **phpmyadmin** (`controladoriasb-phpmyadmin`)
  - Porta `44434:80`, `PMA_HOST=mysql`.

Network única: `controladoriasb-network`.

### 13.2. Comandos úteis

```bash
# subir tudo
docker compose up -d

# entrar no container do app
docker compose exec controladoriasb bash

# rodar Artisan
docker compose exec controladoriasb php artisan migrate --force
docker compose exec controladoriasb php artisan db:seed
docker compose exec controladoriasb php artisan optimize:clear
docker compose exec controladoriasb php artisan view:clear
docker compose exec controladoriasb php artisan queue:restart
docker compose exec controladoriasb php artisan test

# logs dos workers
docker compose logs -f worker-importacao
docker compose logs -f worker-exportacao

# reiniciar workers após deploy
docker compose restart worker-importacao worker-exportacao
# se a imagem mudou
docker compose up -d --build worker-importacao worker-exportacao
```

### 13.3. PHP config relevante

`docker/php/conf.d/99-uploads.ini` ajusta `upload_max_filesize` e
`post_max_size` para suportar a planilha de até 5 MB do módulo Empresas.

---

## 14. README e Deploy

O `backend/README.md` é o **manual operacional** (este documento é o de
contexto técnico). Os dois se complementam. Quando houver decisão nova de
arquitetura, **atualize ambos**.

### 14.1. Checklist de deploy

1. `git pull` na branch correta.
2. `composer install --no-dev --optimize-autoloader`.
3. `npm ci && npm run build` se houver mudança em assets.
4. `php artisan migrate --force`.
5. `php artisan optimize:clear` (ou `view:clear` + `route:clear` + `config:clear`).
6. `php artisan optimize` (se o `.env` final permitir).
7. **Reiniciar workers**:
   - Docker: `docker compose restart worker-importacao worker-exportacao` (ou `up -d --build` se a imagem mudou).
   - Bare-metal: `sudo supervisorctl restart laravel-worker-importacao:* laravel-worker-exportacao:*`.
8. Conferir `php artisan queue:monitor empresas-importacao,unidades-negocio-importacao,empresas-exportacao,default --max=100`.

### 14.2. Variáveis de ambiente importantes

- `APP_URL` — usado por `route()` com URL absoluta. Para URLs relativas de
  download, já passamos `false` ao `route()`, mas mantenha `APP_URL`
  alinhado com o domínio/porta real.
- `DB_*` — conexão MySQL.
- `QUEUE_CONNECTION=database`.
- `FILESYSTEM_DISK=local`.

---

## 15. Segurança

- **Permissões em todas as rotas administrativas** via middleware
  `permission:<modulo>.<acao>`. Programador passa por `Gate::before`.
- **Validação por FormRequest** em todas as ações que mutam dados.
- **Importações e exportações usam UUID** como route key, não `id`
  incremental. Mesmo assim o controller **verifica explicitamente** se
  o usuário é dono do recurso ou Programador — UUID adivinhado não vaza
  dados.
- **Nenhum link público** para o storage de PDFs/planilhas. Download passa
  pela rota nomeada `admin.empresas.exportacoes.download`.
- **CSRF**: toda mutação POST/PUT/DELETE/PATCH usa token (`@csrf` nos
  forms, `X-CSRF-TOKEN` no fetch dos AJAX).
- **SQL injection na ordenação**: o `EmpresaQuery` usa **allow-list**
  (`ALLOWED_SORTS`) para colunas; o `direction` é validado contra
  `['asc', 'desc']` antes de ir para `orderByRaw` (no caso do `id_cigam`
  numérico). Nenhum input do usuário entra cru em SQL.
- **Senha padrão obrigatoriamente trocada** no primeiro login.
- **Usuário inativado é deslogado** no próximo request.
- **Programador não pode ser desativado** (regras em
  `UsuarioController::desativar`).
- **Role Programador não pode ser alterada** (regras em
  `GrupoPermissaoController::update`).
- **Usuário protegido** (`hewerton@sitiobarreiras.com.br`) não pode ter
  senha resetada nem perder a role Programador via interface.

---

## 16. Performance

- **Listagem AJAX** com debounce de 300ms, sem reload de página.
- **Importação**:
  - `IOFactory::createReaderForFile` com `setReadDataOnly(true)`.
  - `ImportacaoReadFilter` limitando a 5000 linhas e colunas A..G.
  - Buffer de 100 linhas + `WHERE IN` em massa.
  - `setReadEmptyCells(false)`.
  - Progresso salvo a cada 25 linhas e a cada flush.
- **Exportação**:
  - `select()` com apenas 7 colunas.
  - Limite de 1000 registros.
  - DomPDF com `dpi=96`, `isRemoteEnabled=false`, `isFontSubsettingEnabled=true`,
    `defaultFont=Helvetica`.
  - HTML simples (sem layout pesado, sem CSS de framework).
- **Ordenação numérica de `id_cigam`** (`EmpresaQuery`):
  - MySQL: `ORDER BY CAST(id_cigam AS UNSIGNED) ASC|DESC`.
  - SQLite (testes): `ORDER BY CAST(id_cigam AS INTEGER) ASC|DESC`.
  - Detecção via `$query->getConnection()->getDriverName()`.
  - **Por que**: o `id_cigam` está em `varchar`, então `ORDER BY` lexicográfico
    produzia `1, 10, 100, 1000, 2, 20`. Numericamente: `1, 2, 10, 20, 100, 1000`.

---

## 17. Testes

### 17.1. Estrutura

- Framework: **PHPUnit ^12.5**.
- `phpunit.xml` aponta para SQLite `:memory:` e `QUEUE_CONNECTION=sync`
  em ambiente de testes.
- Trait base: `Tests\Support\CreatesUsersWithRoles` (cria usuário com
  permissões específicas, Programador, manager de Empresas, etc.).
- Base abstrata do módulo Empresas:
  `tests/Feature/Admin/Empresas/EmpresasTestCase.php`.

### 17.2. Suites principais

- `Tests\Feature\Admin\Empresas\EmpresaCadastroTest`
- `Tests\Feature\Admin\Empresas\EmpresaEdicaoTest`
- `Tests\Feature\Admin\Empresas\EmpresaListagemTest`
- `Tests\Feature\Admin\Empresas\EmpresaHistoricoTest`
- `Tests\Feature\Admin\Empresas\EmpresaImportacaoTest`
- `Tests\Feature\Admin\Empresas\EmpresaExportacaoTest`
- `Tests\Feature\Admin\Empresas\EmpresaPermissaoTest`
- `Tests\Feature\Admin\Empresas\EmpresaStatusTest`
- `Tests\Feature\Admin\UnidadesNegocio\UnidadeNegocioTest`
- `Tests\Feature\Admin\UnidadesNegocio\UnidadeNegocioImportacaoTest`
- `Tests\Feature\Auth\*` (Breeze)
- `Tests\Feature\ProfileTest`, `Tests\Feature\ExampleTest`

Cobre: validações, normalização, auditoria, ordenação numérica de
`id_cigam`, paginação, filtros, CRUD de unidades de negócio, importação assíncrona com `Queue::fake`,
exportação assíncrona com `Storage::fake('local')`, autorização
(dono/Programador/outros), 403/404/409, headers de PDF.

### 17.3. Comandos

```bash
# tudo
docker compose exec -T controladoriasb php artisan test

# uma suite
docker compose exec -T controladoriasb php artisan test --filter=EmpresaExportacaoTest

# por nome de teste
docker compose exec -T controladoriasb php artisan test --filter=test_endpoint_de_status_retorna_payload_para_ui
```

### 17.4. Convenção para novos testes

- Todo módulo novo cria sua testcase abstrata em `tests/Feature/.../<Modulo>TestCase.php`.
- Usar `RefreshDatabase` (já presente na base).
- Para autorização, usar `CreatesUsersWithRoles`.
- Para filas/storage, usar `Queue::fake()` e `Storage::fake('local')`.

---

## 18. Problemas Já Encontrados e Soluções

| # | Problema | Causa | Solução adotada |
|---|---|---|---|
| 1 | **Erro 404 no botão "Baixar PDF"** após exportação concluída | `route(...)` gerava URL absoluta com `APP_URL` diferente do host/porta atual | URLs `status`/`download` agora são geradas com `route(..., $model, false)` (relativas). UI segue para a mesma origem (§11/§15). |
| 2 | **Estouro de memória no DomPDF** durante exportação grande | View pesada (badges, CSS extenso) + relacionamentos eager loaded + dataset grande | View PDF simplificada (sem framework, CSS mínimo), `select()` só nas colunas necessárias, `memory_limit=512M` no job e **limite de 1000 registros** com falha amigável (§11.4). |
| 3 | **`Call to undefined method mensagemDoStatus()`** | Worker carregava bytecode antigo após o método ser adicionado | Manter o método sempre presente no controller (`EmpresaExportacaoController::mensagemDoStatus`), rodar `php artisan optimize:clear` e `php artisan queue:restart` após mudanças. |
| 4 | **Ordenação por `id_cigam` em ordem lexicográfica** | Coluna `varchar` ordenada como string | `EmpresaQuery::NUMERIC_SORTS = ['id_cigam' => true]` + `orderByRaw('CAST(... AS UNSIGNED/INTEGER) ...')` com allow-list de direção. |
| 5 | **Pesquisa quebrando texto em palavras** | Implementação anterior fazia split por espaço | Pesquisa unitária: `like %search%` em vários campos com uma única string. Mantemos isso como **decisão de produto**. |
| 6 | **Card de status no fim da tabela** sumia da viewport | Card era irmão da `<x-admin.data-table>` mas vinha **depois** dela | Card movido para cima da tabela, com `position: relative` natural, contador "Aguardando há Xs", alertas em 15s/60s (§11.6). |
| 7 | **Importação de planilha estourando timeout/memória** | Maatwebsite Excel em modo padrão carrega o workbook inteiro | Reescrita usando PhpSpreadsheet puro com `setReadDataOnly`, `ImportacaoReadFilter`, leitura célula a célula e buffer de 100 linhas (§10). |
| 8 | **Worker parado deixava importação em `AGUARDANDO` para sempre** | Sem worker, fila não consome | Documentado em §12; UI mostra hint a partir de 15s e comando de dev a partir de 60s. |
| 9 | **Paginação Tailwind quebrando layout Bootstrap** | Paginator default é Tailwind, com SVGs grandes | `Paginator::useBootstrapFive()` no `AppServiceProvider::boot()` + CSS defensivo no componente. |
| 10 | **Event listeners JS perdidos após AJAX** | `addEventListener` no `_table` somia ao trocar HTML | Componente `<x-admin.data-table>` usa **event delegation** no container; sobrevive a reloads. |
| 11 | **Vite manifest não encontrado** em ambiente sem build | Layout tentava carregar `@vite` mas `public/build` não existia | Vite mínimo configurado; em produção o `npm run build` é parte do deploy. |
| 12 | **Mensagem confusa "Fallback síncrono temporário para arquivos pequenos"** aparecendo na UI | Texto era exibido para o usuário final | Removido da interface. Rota `admin.empresas.exportar-pdf` continua existindo para uso técnico, documentado apenas neste arquivo e no README. |

---

## 19. Como Continuar o Projeto

Para qualquer pessoa (ou agente) que vá adicionar funcionalidade:

1. **Leia este arquivo inteiro antes.** Especialmente §4 (convenções), §8
   (auditoria), §9 (componente de tabela), §12 (filas) e §15 (segurança).
2. **Respeite o pacote existente**: não troque DomPDF, Spatie Permission,
   Highdmin, Bootstrap, Blade nem PhpSpreadsheet sem discussão.
3. **Use os padrões já existentes**:
   - Listagem nova? Use `<x-admin.data-table>`.
   - CRUD novo? Crie FormRequest + Controller magro + Service quando houver
     regra de negócio.
   - Mutação em dado importante? Crie/atualize entrada em histórico.
4. **Mantenha a UX sem refresh** para listas administrativas.
5. **Tarefas pesadas** (Excel, PDF, e-mails em lote, etc.) **vão para fila**.
   Crie uma fila dedicada se necessário (siga o padrão de `empresas-*`).
6. **Nada de delete físico** em entidades de domínio. Use inativação +
   histórico.
7. **Permissões obrigatórias** em qualquer rota nova. Adicione constantes
   em `App\Enums\Permissions` e registre no seeder.
8. **Atualize testes** sempre que adicionar ou mudar comportamento. Toda
   feature de domínio tem uma suite Feature em `tests/Feature/Admin/...`.
9. **Atualize este arquivo** (`docs/SISTEMA_CONTEXTO.md`) e o `README.md`
   quando houver decisão arquitetural nova.
10. **Não exponha caminho físico de storage.** Sempre via rota autorizada.

---

## 20. Checklist Obrigatório para Novas Features

Antes de abrir PR / dar deploy, percorra esta lista:

### 20.1. Funcional

- [ ] FormRequest com validação completa e mensagens em português.
- [ ] Controller magro (não mistura regra de negócio).
- [ ] Service criado quando há lógica reutilizável.
- [ ] Query Object usado se houver filtros/sorting/paginação não-trivial.
- [ ] Operações em mais de uma tabela usam `DB::transaction`.

### 20.2. Permissões

- [ ] Nova permissão registrada em `App\Enums\Permissions::groups()`.
- [ ] Seeder rodado / migration de permissões aplicada.
- [ ] Middleware `permission:*` aplicado na rota.
- [ ] Programador testado (deve passar via `Gate::before`).
- [ ] Autorização explícita por **dono do recurso** (quando aplicável).

### 20.3. Auditoria

- [ ] Toda mutação relevante chama o service de auditoria correspondente.
- [ ] Origem (`MANUAL`/`IMPORTACAO_EXCEL`/etc.) coerente.
- [ ] Snapshot não vaza dado sensível.

### 20.4. Performance

- [ ] Tarefa pesada vai para fila dedicada.
- [ ] `tries=1`, `timeout=900` (ou justificativa).
- [ ] `select(...)` apenas das colunas necessárias quando o dataset for grande.
- [ ] Eager loading só do que vai ser usado.

### 20.5. UX

- [ ] Listagem usa `<x-admin.data-table>` (search debounce + paginação AJAX + sortable).
- [ ] Botões de ação JS com `type="button"`.
- [ ] Estados de loading visíveis.
- [ ] Mensagens de sucesso/erro flash com cor coerente.
- [ ] Sem mensagens técnicas para o usuário (mapear em `mensagemAmigavel`).

### 20.6. AJAX/polling (quando aplicável)

- [ ] Endpoint de `status` devolve `status`, `mensagem`, timestamps e
      `download_url` se for assíncrono.
- [ ] Polling para em `CONCLUIDO`/`FALHOU`.
- [ ] Erros de rede com backoff e limite (sem polling infinito).
- [ ] URLs retornadas são relativas para evitar problemas de host/porta.

### 20.7. Filas

- [ ] Job na fila certa (`empresas-importacao`, `unidades-negocio-importacao`,
      `empresas-exportacao` ou uma fila nova bem nomeada).
- [ ] Worker correspondente está documentado em `docker-compose.yml` e/ou
      Supervisor.
- [ ] `queue:restart` listado nos passos de deploy.

### 20.8. Testes

- [ ] Feature test cobrindo o fluxo feliz.
- [ ] Feature test de autorização (403/404 quando aplicável).
- [ ] Para jobs: teste de sucesso e de falha amigável.
- [ ] `php artisan test` está verde.

### 20.9. Documentação

- [ ] `README.md` atualizado se houver passo novo de deploy/comando.
- [ ] `docs/SISTEMA_CONTEXTO.md` (este arquivo) atualizado em pelo menos
      uma das seções §3, §4, §10, §11, §12, §18 ou §20 quando for relevante.
- [ ] Comentários em PHP só onde explicam **intenção** ou **decisão**, não
      o que o código faz.

### 20.10. Deploy

- [ ] `php artisan migrate --force` foi necessário? Documentar.
  (Ex.: após migrations de unidades de negócio — tabela `unidades_negocio` e
  `unidade_negocio_importacoes` —, rodar migrate e
  `php artisan db:seed --class=PermissionSeeder` para gravar novas permissões.
  Se o enum `Permissions` trocou nomes de chaves (ex.: `unidades-negocio.excluir`
  → `unidades-negocio.inativar`), revisar grupos no admin e reatribuir as novas
  permissões onde necessário.)
- [ ] `php artisan optimize:clear` no checklist.
- [ ] `php artisan queue:restart` no checklist.
- [ ] Reiniciar workers Docker/Supervisor após pull.
- [ ] `.env.example` atualizado se foi adicionada variável nova.
- [ ] `.gitignore` revisado se foi criada nova pasta com dados sensíveis.

---

## 21. Módulo de Unidades de Negócio

Foi implementado o **cadastro mestre de Unidades de Negócio** usado pelo
SB - CONTROLADORIA: entidade simples com vínculo ao código do ERP CIGAM e nome legível.
**Não existe exclusão física** nem rota `destroy`: o registro permanece no
banco para histórico e vínculos futuros. A saída de uso é modelada apenas por
**inativação** (`status = false`); a **reativação** é explícita (`status =
true`) pelas ações **Inativar** / **Ativar** na listagem (confirmação via
`confirm()`, mesmo padrão visual de Empresas).

Não altera o campo numérico `unidade_negocio` já existente na tabela
`empresas` (continua independente deste cadastro).

### 21.1. Campos da entidade (`unidades_negocio`)

| Campo | Tipo | Regra |
|---|---|---|
| `id` | bigint PK | auto |
| `id_cigam` | varchar único | obrigatório; ordenação **numérica** na listagem (mesmo padrão de `EmpresaQuery`) |
| `nome` | varchar | obrigatório |
| `status` | boolean default true | `true` = **ativa**, `false` = **inativa**; não há delete físico da linha |
| `created_at`, `updated_at` | timestamps | |

### 21.2. Arquivos principais (cadastro + importação)

**Cadastro / listagem**

- `database/migrations/2026_05_12_000001_create_unidades_negocio_table.php`
- `app/Models/UnidadeNegocio.php`, `app/Queries/UnidadeNegocioQuery.php`
- `app/Http/Controllers/Admin/UnidadeNegocioController.php`
- `app/Http/Requests/Admin/StoreUnidadeNegocioRequest.php`,
  `UpdateUnidadeNegocioRequest.php`
- `database/factories/UnidadeNegocioFactory.php`
- `resources/views/admin/unidades-negocio/{index,create,edit,_form,_table}.blade.php`

**Importação Excel (mesmo fluxo conceitual do §10 — fila + preview + confirmar)**

- `database/migrations/2026_05_12_100000_create_unidade_negocio_importacoes_table.php`
- `app/Models/UnidadeNegocioImportacao.php`
- `app/Http/Controllers/Admin/UnidadeNegocioImportacaoController.php`
- `app/Jobs/UnidadesNegocio/ProcessarPreviewImportacaoUnidadesNegocioJob.php`
- `app/Services/UnidadesNegocio/UnidadeNegocioImportacaoProcessor.php`
- `app/Services/UnidadesNegocio/UnidadeNegocioImportacaoReadFilter.php`
- `resources/views/admin/unidades-negocio/importar.blade.php`

**Alterados com frequência**: `routes/web.php`, `app/Enums/Permissions.php`,
`docker-compose.yml` (worker de importação), `docs/deploy/supervisor/laravel-worker-importacao.conf`,
`resources/views/layouts/partials/sidebar.blade.php`,
`tests/Support/CreatesUsersWithRoles.php`, `docs/SISTEMA_CONTEXTO.md`.

Não há **Policy** dedicada: middleware `permission:*` nas rotas (Programador
via `Gate::before`). O **CRUD** de unidades é síncrono; a **importação** usa
fila dedicada `unidades-negocio-importacao` (§12).

### 21.3. Rotas e telas

Prefixo: `/admin/unidades-negocio` (nomeadas `admin.unidades-negocio.*`).

| Método | Caminho | Permissão |
|---|---|---|
| GET | `/admin/unidades-negocio` | `unidades-negocio.visualizar` |
| GET | `/admin/unidades-negocio/importar` | `unidades-negocio.importar` |
| POST | `/admin/unidades-negocio/importar/iniciar` | `unidades-negocio.importar` |
| GET | `/admin/unidades-negocio/importar/{uuid}/status` | `unidades-negocio.importar` |
| GET | `/admin/unidades-negocio/importar/{uuid}/resultado` | `unidades-negocio.importar` |
| POST | `/admin/unidades-negocio/importar/{uuid}/confirmar` | `unidades-negocio.importar-confirmar` |
| GET | `/admin/unidades-negocio/criar` | `unidades-negocio.criar` |
| POST | `/admin/unidades-negocio` | `unidades-negocio.criar` |
| GET | `/admin/unidades-negocio/{id}/editar` | `unidades-negocio.editar` |
| PUT | `/admin/unidades-negocio/{id}` | `unidades-negocio.editar` |
| POST | `/admin/unidades-negocio/{id}/inativar` | `unidades-negocio.inativar` |
| POST | `/admin/unidades-negocio/{id}/ativar` | `unidades-negocio.ativar` |

Listagem com `<x-admin.data-table>`. Botão **Importar Excel** na index (mesmo
padrão visual da listagem de Empresas). Tela `importar` com upload, card de
progresso/polling, tabelas de preview e resumo final pós-confirmação.

### 21.4. Importação Excel — formato e comportamento

**Layout fixo** (linha 1 = cabeçalho livre; dados a partir da linha 2), sem
depender do texto do cabeçalho:

| Coluna | Campo | Regra |
|---|---|---|
| A | ID_cigam | obrigatório; único na planilha; até 64 caracteres |
| B | nome | obrigatório; até 255 caracteres |

**Preview (job em background, fila `unidades-negocio-importacao`):**

- `id_cigam` **novo** no banco → lista **Novas**.
- `id_cigam` existente e `nome` igual ao cadastro → **Sem alterações**.
- `id_cigam` existente e `nome` diferente → **Alterações** (na confirmação
  grava **apenas** o novo `nome`; o campo `status` **não** é alterado — inclusive
  se a unidade estiver **inativa**, ela permanece inativa após a importação).
- Erros de validação ou `id_cigam` duplicado na planilha → **Erros** (com
  mensagens por linha).

**Confirmação seletiva** (como Empresas): o front envia `row_ids_novas` e
`row_ids_atualizacoes`; o controller relê o JSON salvo em
`unidade_negocio_importacoes.resultado`, aplica em `DB::transaction` e devolve
`resumo` com `criadas`, `atualizadas`, `ignoradas` e `erros` (detalhe por
linha quando houver falha na gravação). Arquivo temporário removido após
confirmar (disco `local`, pasta `unidades-negocio/importacoes/`).

**Segurança:** `status` / `resultado` / `confirmar` só para o **dono** da
importação (`user_id`) ou role **Programador** (mesma regra explícita de
Empresas).

### 21.5. Permissões (Spatie)

- `unidades-negocio.visualizar`
- `unidades-negocio.criar`
- `unidades-negocio.editar`
- `unidades-negocio.inativar`
- `unidades-negocio.ativar`
- `unidades-negocio.importar`
- `unidades-negocio.importar-confirmar`

### 21.6. Validação manual sugerida (cadastro + importação)

1. `php artisan migrate` e `php artisan db:seed --class=PermissionSeeder` (novas
   permissões, inclusive `inativar`/`ativar`; revisar grupos que ainda referenciem
   chaves antigas `unidades-negocio.excluir` / `reativar`).
2. Worker de importação consumindo **ambas** as filas (Docker/Supervisor já
   documentados em §12): `empresas-importacao,unidades-negocio-importacao`.
3. **Cadastro manual**: criar, editar, listar; **Inativar**/**Ativar** com
   confirmação (sem botão Excluir; sem delete físico).
4. **Importar**: enviar planilha `.xlsx` com colunas A/B; aguardar preview;
   marcar/desmarcar linhas; confirmar; conferir resumo (criadas/atualizadas/
   ignoradas/erros) e listagem AJAX atualizada ao voltar para a index. Com uma
   unidade **inativa** no banco e nome diferente na planilha: após confirmar,
   o **nome** muda e o **`status` permanece inativo**.
5. Caso `AGUARDANDO` prolongado: conferir worker (fila `unidades-negocio-importacao`).
6. Usuário sem `importar` não acessa a tela; sem `importar-confirmar` vê o
   preview mas não o botão de confirmar.
7. Usuário sem `inativar`/`ativar` não vê os botões correspondentes; com
   permissão, validar mensagens de sucesso e fluxo idempotente (já ativa/inativa).

---

> Fim do documento mestre.
>
> Manutenção: ao adicionar uma feature significativa, **edite este arquivo
> primeiro** (ou no mesmo PR) — esse é o contrato entre o passado, o presente
> e os próximos chats/devs do projeto.
