# SB - CONTROLADORIA — Sistema Web

Sistema interno (Laravel 13 + MySQL + Highdmin/Bootstrap) integrado ao ERP CIGAM.

**Versão atual:** ver arquivo [`VERSION`](VERSION). Linha **2.x** em desenvolvimento (captação de pedidos). Para voltar à linha **1.x** (movimentações + importações), consulte [`docs/VERSIONING.md`](docs/VERSIONING.md).

## Pilha técnica

- PHP 8.3+
- Laravel 13
- MySQL 8
- Laravel Breeze (autenticação)
- Spatie Laravel Permission (roles e permissões)
- Maatwebsite Excel 3.1 (importação de planilhas)
- barryvdh/laravel-dompdf 3.1 (exportação de PDF)
- Tema Highdmin (Bootstrap 5.3) — assets pré-compilados em `public/assets`
- Docker Compose para desenvolvimento

## Portas

| Serviço | Porta no host |
|---|---|
| Aplicação web | `44432` |
| MySQL | `44433` |
| phpMyAdmin | `44434` |

### URL da aplicação (`APP_URL`)

Deixe `APP_URL` **vazio** no `.env`. A aplicação usa automaticamente o endereço que você
digitar no navegador (IP ou hostname + porta). Não é necessário alterar código ao mudar o IP
do servidor. Só preencha `APP_URL` se precisar forçar um domínio fixo (ex.: HTTPS público).

## Ambiente de desenvolvimento

```bash
# A partir da raiz do repositório (pasta acima de backend/)
docker compose up -d

# Dentro do container "controladoriasb"
docker compose exec controladoriasb composer install
docker compose exec controladoriasb php artisan key:generate
docker compose exec controladoriasb php artisan migrate --seed
docker compose exec controladoriasb php artisan optimize:clear

# Agendador (backup MySQL diário às 01:00 — America/Sao_Paulo)
docker compose up -d scheduler
```

Acesse http://localhost:44432 e entre com `hewerton@sitiobarreiras.com.br` / `casa1234`.

### Backup automático do banco

Todo dia à **1h da manhã** (horário de Brasília), o comando `db:backup` gera um dump completo
em `storage/app/backups/database/` (arquivo `.sql.gz`). Configuração no `.env`:

| Variável | Padrão | Descrição |
|----------|--------|-----------|
| `BACKUP_ENABLED` | `true` | Liga/desliga o agendamento |
| `BACKUP_DAILY_AT` | `01:00` | Horário do backup |
| `BACKUP_RETENTION_DAYS` | `14` | Dias para manter arquivos antigos |
| `BACKUP_PATH` | `backups/database` | Pasta dentro de `storage/app/` |

```bash
# Backup manual imediato (dentro do container)
docker compose exec controladoriasb php artisan db:backup

# Alternativa no host (sem mysqldump local — usa o container mysql)
./scripts/backup/mysql-dump.sh

# Ver agendamento
docker compose exec controladoriasb php artisan schedule:list

# Logs do job agendado
tail -f storage/logs/backup.log
```

O serviço `scheduler` no `docker-compose.yml` executa `php artisan schedule:work` e dispara o
backup no horário configurado. Após alterar o `Dockerfile` (cliente MySQL), reconstrua a imagem:

`docker compose up -d --build controladoriasb scheduler`

## Testes automatizados

O projeto usa PHPUnit via `php artisan test`. O `phpunit.xml` já aponta para
SQLite em memória (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`), cache em
array, sessão em array, e fila `sync`, para que os testes não encostem no banco
real nem dependam de serviços externos.

Existe um modelo de ambiente em `.env.testing.example`. Ele não precisa ser
copiado para rodar a suíte padrão, mas serve como referência caso seja necessário
executar testes com variáveis explícitas em CI.

Comandos úteis:

```bash
# Todos os testes
php artisan test

# Apenas testes do módulo Empresas
php artisan test --filter=Empresa

# Um arquivo específico
php artisan test tests/Feature/Admin/Empresas/EmpresaCadastroTest.php

# Dentro do Docker
docker compose exec controladoriasb php artisan test
docker compose exec controladoriasb php artisan test --filter=Empresa
```

Os testes do módulo Empresas cobrem cadastro, edição, listagem, pesquisa,
paginação, ordenação, inativação/reativação, histórico/auditoria, permissões,
importação Excel em background e exportação PDF. Eles usam factories e dados
fictícios; não usam planilhas reais, banco real, e-mail real ou serviços
externos. Arquivos gerados nos testes usam `Storage::fake()`.

Observação sobre SQLite: a ordenação numérica de `id_cigam` usa `CAST(... AS
INTEGER)` no ambiente de testes e `CAST(... AS UNSIGNED)` no MySQL. A regra de
negócio permanece a mesma; muda apenas a expressão SQL compatível com o driver.

## Estrutura de módulos já implementados

- Autenticação (Breeze adaptado ao Highdmin)
- Troca obrigatória de senha no 1º login (`/alterar-senha-obrigatoria`)
- Bloqueio de usuário desativado em login e em sessão ativa
- Administração de Usuários (`/admin/usuarios`)
- Administração de Grupos de Permissões (`/admin/grupos-permissoes`)
- Cadastro de Empresas (`/admin/empresas`)
- Inativar/Reativar empresas
- Importação de empresas via Excel **em background** com fila/Job + polling de progresso (`/admin/empresas/importar`)
- Auditoria de empresas (`/admin/empresas/{empresa}/historico`)
- Pesquisa, paginação e exportação PDF assíncrona de empresas (`/admin/empresas/exportacoes/pdf/iniciar`)
- Tabelas administrativas padronizadas via componente reutilizável `<x-admin.data-table>` (Empresas, Usuários, Grupos de Permissões)

> O usuário **Programador** (Hewerton) recebe acesso total via `Gate::before`. Outros usuários precisam ter as permissões granulares vinculadas aos seus grupos.

## Tabelas administrativas (componente reutilizável)

As três telas administrativas com listagem usam o mesmo componente Blade:

- `resources/views/components/admin/data-table.blade.php`

Cada tela define apenas:

- a partial da tabela: `resources/views/admin/<modulo>/_table.blade.php`
- a `index.blade.php` chamando `<x-admin.data-table>` com slots `actions` e (opcional) `filters`

Comportamento padrão do componente:

- Campo de pesquisa com **debounce de 300 ms**, sem botão "Pesquisar" e sem refresh.
- Seletor "Por página" (10, 20, 50, 100, Todos) que aciona fetch automaticamente.
- Links de paginação interceptados via delegate listener: navegam por `fetch` e atualizam só a área da tabela.
- URL sincronizada via `history.replaceState`, preservando `search` e `per_page`.
- Botões de ações podem iniciar fluxos assíncronos (ex.: `Gerar PDF`) preservando os filtros atuais da tabela.
- Indicador de loading enquanto a requisição está em andamento.
- O controller decide a resposta: se a requisição for AJAX (`$request->ajax()`), retorna **somente a partial `_table`**; caso contrário, devolve a view completa.

Para criar uma nova listagem administrativa:

1. Crie `_table.blade.php` com tabela + footer (contador + paginação).
2. No `index.blade.php`, use `<x-admin.data-table ... :endpoint="route('...')">` e inclua a partial dentro do slot padrão.
3. No controller, aplique filtros, gere o paginator e devolva a partial quando a requisição for AJAX:

```php
if ($request->ajax()) {
    return view('admin.<modulo>._table', $payload);
}

return view('admin.<modulo>.index', $payload);
```

## Importação de Empresas em background

A importação de Empresas via Excel deixou de ser processada inline na requisição HTTP e passou a usar **fila + job + polling**:

1. **Upload** — `POST /admin/empresas/importar/iniciar`
   - Salva o arquivo em `storage/app/private/empresas/importacoes/` (disco `local`).
   - Cria um registro em `empresa_importacoes` com `status=AGUARDANDO`.
   - Despacha `App\Jobs\Empresas\ProcessarPreviewImportacaoEmpresasJob` para a fila `empresas-importacao`.
   - Retorna `202 Accepted` com `{ uuid, urls }`.
2. **Polling** — `GET /admin/empresas/importar/{uuid}/status` (a cada ~1,5 s)
   - Devolve `status`, `total_linhas`, `linhas_processadas`, `percentual` e contadores parciais (`novas_count`, `atualizacoes_count`, `sem_alteracoes_count`, `erros_count`).
3. **Resultado** — `GET /admin/empresas/importar/{uuid}/resultado` (somente quando `status=CONCLUIDO`)
   - Devolve os arrays completos: `novas`, `atualizacoes`, `sem_alteracoes`, `erros`.
4. **Confirmação** — `POST /admin/empresas/importar/{uuid}/confirmar`
   - Recebe apenas `row_ids_novas` e `row_ids_atualizacoes`. O backend lê o resultado salvo no banco (não confia em dados do front) e persiste em `DB::transaction()`, gerando auditoria em `empresa_historicos`.
   - Apaga o arquivo temporário ao final.

Detalhes técnicos:

- A leitura é feita com **PhpSpreadsheet puro** (sem `maatwebsite/excel` para esta importação) usando `setReadDataOnly(true)` + `IReadFilter` (colunas A:G, até 5000 linhas).
- O progresso é gravado no banco a cada **25 linhas físicas** ou a cada chunk de **100 linhas úteis** comparadas com o banco.
- Apenas o **dono da importação** ou usuários com role `Programador` podem consultar/confirmar uma importação (validado pelo UUID na URL).

## Importação de Unidades de Negócio em background

O fluxo é o **mesmo padrão** da importação de Empresas (upload → job em fila dedicada `unidades-negocio-importacao` → polling de `status` → `resultado` → confirmação seletiva com `row_ids_*`). Planilha com colunas **A = ID_cigam**, **B = nome** (layout fixo; linha 1 cabeçalho livre). `id_cigam` novo cria registro **ativo**; existente com nome diferente entra em **atualizações** (só o `nome` é gravado na confirmação; **`status` da unidade existente não muda** — unidade inativa permanece inativa). O cadastro na listagem usa **Inativar** / **Ativar** (`unidades-negocio.inativar` / `unidades-negocio.ativar`); **não há exclusão física** de Unidade de Negócio. Detalhes em `docs/SISTEMA_CONTEXTO.md` §21.

## Exportação PDF de Empresas em background

A exportação PDF da listagem de Empresas também roda fora da requisição web:

1. **Solicitação** — `POST /admin/empresas/exportacoes/pdf/iniciar`
   - Recebe os filtros atuais da tela (`search`, `status`, `per_page`) e os normaliza em `App\Queries\EmpresaQuery`.
   - Cria um registro em `empresa_exportacoes` com `tipo=PDF` e `status=AGUARDANDO`.
   - Despacha `App\Jobs\Empresas\GerarPdfEmpresasJob` para a fila `empresas-exportacao`.
   - Retorna `202 Accepted` com `uuid`, `status`, `mensagem`, `created_at` e URLs de status/download.
2. **Polling** — `GET /admin/empresas/exportacoes/{uuid}/status`
   - Devolve `AGUARDANDO`, `PROCESSANDO`, `CONCLUIDO` ou `FALHOU`, mensagem amigável, timestamps, total de registros e URL de download quando pronto.
3. **Download** — `GET /admin/empresas/exportacoes/{uuid}/download`
   - Entrega o arquivo salvo em `storage/app/private/empresas/exportacoes/`, sem expor o caminho real do storage.

Desempenho e limite:

- A exportação PDF é intencionalmente simples: HTML puro, CSS mínimo, sem layout principal, sem Highdmin/Bootstrap, sem JavaScript, sem ícones, sem imagens e sem assets externos.
- O job seleciona apenas as colunas necessárias para o relatório: `id_cigam`, `nome`, `fantasia`, `cpf_cnpj`, `unidade_negocio`, `status` e `tipo_pessoa`.
- O limite inicial é de **1000 registros por PDF**. Acima disso, a exportação é marcada como `FALHOU` com a mensagem: "A exportação PDF suporta até 1000 registros por vez. Utilize filtros para reduzir o resultado."
- PDFs não são ideais para datasets gigantes. Para volumes maiores, a evolução recomendada é oferecer exportação XLSX/CSV em fila, mantendo PDF para relatórios visuais menores.

Segurança:

- Só o usuário que solicitou a exportação ou um usuário com role `Programador` pode consultar/baixar o PDF.
- As rotas usam `uuid`, não `id` incremental.
- A rota antiga `GET /admin/empresas/exportar-pdf` permanece apenas como fallback técnico para relatórios pequenos; ela não é exibida na interface principal.
- Não há alteração em Empresas, importação, cadastro ou auditoria; o PDF é somente leitura.

### Configuração mínima de fila

`QUEUE_CONNECTION` deve ser **diferente de `sync`** para que o processamento ocorra fora da requisição HTTP:

```bash
# .env (dev e produção)
QUEUE_CONNECTION=database
```

A tabela `jobs` já existe na migration padrão do Laravel.

Em desenvolvimento, o worker pode rodar em foreground enquanto você testa:

```bash
# diretamente
php artisan queue:work --queue=empresas-importacao,unidades-negocio-importacao,empresas-exportacao,default --sleep=1 --tries=1 --timeout=900

# dentro do Docker
docker compose exec controladoriasb php artisan queue:work --queue=empresas-importacao,unidades-negocio-importacao,empresas-exportacao,default --sleep=1 --tries=1 --timeout=900
```

> Para produção, **use Supervisor com workers separados** (Linux bare-metal/VM) ou os serviços `worker-importacao` e `worker-exportacao` do `docker-compose.yml` (Docker em produção).

## Queue Workers em produção

A importação de Empresas e qualquer outro job ficam parados em `AGUARDANDO` se não houver worker rodando. Em produção, os workers devem:

- iniciar **automaticamente** com o servidor (boot);
- ser **reiniciados** automaticamente se morrerem (OOM, exception, deploy);
- ser **reiniciados** após cada deploy para recarregar o código novo;
- rodar em processo **separado** do PHP-FPM/Apache (não compartilhar lifecycle do servidor web).

### Opção A — Linux bare-metal / VM com Supervisor

Há configs prontas em [`docs/deploy/supervisor/`](docs/deploy/supervisor/) e guia rápido em [`docs/deploy/README.md`](docs/deploy/README.md):

- `laravel-worker-importacao.conf` — consome `empresas-importacao` e `unidades-negocio-importacao`.
- `laravel-worker-exportacao.conf` — consome apenas `empresas-exportacao`.

Separar os workers evita que uma importação grande bloqueie PDFs e que um PDF grande bloqueie importações.

```bash
# 1. Instalar Supervisor
sudo apt update
sudo apt install supervisor -y

# 2. Copiar configs e ajustar caminhos absolutos (directory / command)
sudo cp docs/deploy/supervisor/laravel-worker-*.conf /etc/supervisor/conf.d/
sudoedit /etc/supervisor/conf.d/laravel-worker-importacao.conf
sudoedit /etc/supervisor/conf.d/laravel-worker-exportacao.conf

# 3. Aplicar
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker-importacao:*
sudo supervisorctl start laravel-worker-exportacao:*

# 4. Validar
sudo supervisorctl status laravel-worker-*:*
```

Resumo das configs:

- `command`: `php artisan queue:work ... --sleep=1 --tries=1 --timeout=900` (no Docker, preferir lista YAML no `docker-compose.yml` para `php` ser PID 1).
- `--tries=1`: jobs longos não devem ser reprocessados automaticamente sem análise.
- `--timeout=900`: bate com o timeout dos jobs pesados.
- `--max-time=3600`: recicla o processo a cada 1h para liberar memória.
- `autostart=true` e `autorestart=true`: inicia no boot e reinicia se cair.
- `stopwaitsecs=910`: maior que `--timeout`, evita matar o worker no meio de um job.
- logs separados em `/var/log/supervisor/laravel-worker-*.{out,err}.log`.

**Após cada deploy** (Composer install, migrations, etc.):

```bash
sudo supervisorctl restart laravel-worker-importacao:*
sudo supervisorctl restart laravel-worker-exportacao:*
```

Sem este `restart`, os workers continuam servindo o **código antigo** (eles fazem boot do framework apenas uma vez e processam vários jobs).

Outros comandos úteis:

```bash
sudo supervisorctl status laravel-worker-*:*
sudo supervisorctl tail -f laravel-worker-importacao:laravel-worker-importacao_00 stdout
sudo supervisorctl tail -f laravel-worker-exportacao:laravel-worker-exportacao_00 stdout
sudo supervisorctl stop laravel-worker-*:*
sudo supervisorctl start laravel-worker-*:*
```

### Opção B — Docker em produção (workers separados)

O `docker-compose.yml` inclui dois serviços dedicados que reusam a mesma imagem da aplicação, mas executam `php artisan queue:work`:

```yaml
worker-importacao:
  build: .
  image: controladoriasb-app:latest
  restart: unless-stopped
  container_name: controladoriasb-worker-importacao
  command:
    - php
    - artisan
    - queue:work
    - --queue=empresas-importacao,unidades-negocio-importacao
    - --sleep=1
    - --tries=1
    - --timeout=900
    - --max-time=3600
  stop_signal: SIGTERM
  stop_grace_period: 930s

worker-exportacao:
  image: controladoriasb-app:latest
  restart: unless-stopped
  container_name: controladoriasb-worker-exportacao
  command:
    - php
    - artisan
    - queue:work
    - --queue=empresas-exportacao
    - --sleep=1
    - --tries=1
    - --timeout=900
    - --max-time=3600
  stop_signal: SIGTERM
  stop_grace_period: 930s
```

Comandos:

```bash
docker compose up -d worker-importacao worker-exportacao
docker compose logs -f worker-importacao
docker compose logs -f worker-exportacao
docker compose restart worker-importacao worker-exportacao
docker compose ps worker-importacao worker-exportacao
```

`restart: unless-stopped` garante que o container do worker volta sozinho após reboot do host Docker. Para **forçar** restart depois de um deploy:

```bash
docker compose pull   # se a imagem mudou
docker compose up -d --build worker-importacao worker-exportacao
docker compose restart worker-importacao worker-exportacao
```

### Health check de filas

```bash
php artisan queue:failed                                                   # listar falhos
php artisan queue:retry all                                                # retentar todos
php artisan queue:retry <uuid>                                             # retentar um
php artisan queue:flush                                                    # apagar TODOS os falhos (cuidado)
php artisan queue:monitor empresas-importacao,unidades-negocio-importacao,empresas-exportacao,default --max=100
```

`queue:monitor` retorna exit code != 0 se o backlog exceder o limite — útil para health checks externos (Nagios, Datadog, healthchecks.io).

### Não fazer em produção

- ❌ `php artisan queue:listen` — recarrega o framework a cada job, lento e instável. **Use `queue:work`**.
- ❌ `QUEUE_CONNECTION=sync` — processa dentro da requisição HTTP, sem progresso, e estoura `max_execution_time`.
- ❌ Worker no mesmo processo do Apache/PHP-FPM — derruba a aplicação web quando o job falha.
- ❌ `--timeout` < tempo real do job mais longo (resultado: jobs matados pela metade).
- ❌ `--tries > 1` para as filas `empresas-importacao`, `unidades-negocio-importacao` e `empresas-exportacao` sem revisar idempotência.

### Limpeza futura

Arquivos temporários de importações e PDFs gerados ficam em `storage/app/private/empresas/`. Hoje o arquivo de importação é removido após a confirmação, mas PDFs concluídos permanecem disponíveis para download. Futuramente, criar um comando agendado para limpar exportações antigas (por exemplo, com mais de 7 ou 30 dias), removendo o arquivo e atualizando o registro em `empresa_exportacoes`.

## Checklist de produção / deploy

> Execute **na ordem**. Nunca use `migrate:fresh`, `truncate` ou `delete()` em produção.

```bash
# 1. Dependências (sem dev)
composer install --no-dev --optimize-autoloader

# 2. Configurar .env de produção (APP_ENV=production, APP_DEBUG=false, DB_*, MAIL_*, etc.)
cp -n .env.example .env   # apenas se estiver criando do zero

# 3. App key — somente em instalação nova
php artisan key:generate

# 4. Migrations (segura e idempotente)
php artisan migrate --force

# 5. Seeders idempotentes (rodam sem truncate; criam o que faltar e preservam o que existe)
php artisan db:seed --class=Database\\Seeders\\PermissionSeeder --force
php artisan db:seed --class=Database\\Seeders\\RoleSeeder --force
php artisan db:seed --class=Database\\Seeders\\UserSeeder --force   # apenas se ainda não houver o usuário base

# 6. Limpar caches e recompor
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Reiniciar workers (CRÍTICO — sem isso eles continuam servindo o código antigo)
sudo supervisorctl restart laravel-worker-importacao:* laravel-worker-exportacao:*   # bare-metal / VM
# OU
docker compose restart worker-importacao worker-exportacao                           # Docker
```

### Deploy contínuo (releases subsequentes)

A partir da segunda release em diante, o roteiro típico é:

```bash
cd /var/www/controladoriasb

# 1. Atualizar código
git pull --rebase

# 2. Dependências
composer install --no-dev --optimize-autoloader

# 3. Assets (se houver alteração em resources/js ou resources/css)
npm ci
npm run build

# 4. Migrations, seeders e caches
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\PermissionSeeder --force
php artisan db:seed --class=Database\\Seeders\\RoleSeeder --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Reiniciar workers (OBRIGATÓRIO)
sudo supervisorctl restart laravel-worker-importacao:* laravel-worker-exportacao:*
```

Em ambiente Docker, troque o passo 5 por `docker compose restart worker-importacao worker-exportacao` e considere `docker compose up -d --build worker-importacao worker-exportacao` se a imagem mudou.

### Permissões de filesystem

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage -type d -exec chmod 775 {} \;
sudo find storage -type f -exec chmod 664 {} \;
sudo find bootstrap/cache -type d -exec chmod 775 {} \;
sudo find bootstrap/cache -type f -exec chmod 664 {} \;
```

### Extensões PHP obrigatórias

- `mbstring`, `pdo`, `pdo_mysql`, `tokenizer`, `xml`, `ctype`, `json`, `openssl`, `bcmath`
- **`zip`** — exigida pelo Maatwebsite Excel
- **`gd`** — exigida por dependências do Maatwebsite Excel e por `barryvdh/laravel-dompdf` (renderização de imagens no PDF)
- `dom`, `xmlwriter`, `xmlreader` — exigidas pelo DomPDF
- `fileinfo`, `intl`

Verifique com:

```bash
php -m | grep -Ei '^(zip|gd|mbstring|pdo_mysql|intl|fileinfo)$'
```

### `php.ini` para importação de planilhas

| Diretiva | Valor recomendado |
|---|---|
| `upload_max_filesize` | `50M` |
| `post_max_size` | `50M` |
| `memory_limit` | `512M` recomendado para importações maiores (`256M` atende planilhas pequenas/limpas) |
| `max_execution_time` | `300` |
| `max_input_time` | `300` |

Em ambiente Docker do projeto, já está definido em `docker/php/conf.d/99-uploads.ini`.

> O endpoint de importação também chama `set_time_limit(300)` e tenta elevar o `memory_limit` para `512M` somente durante o processamento daquela requisição, sem afetar outras requisições.

### Boas práticas para planilhas Excel

- A importação de empresas lê somente as colunas **A até G** e processa em chunks para reduzir uso de memória.
- Evite planilhas com milhares de linhas vazias formatadas, fórmulas arrastadas ou estilos aplicados até o fim da aba.
- Se a importação retornar erro de memória ou formatação excessiva, selecione apenas o intervalo com dados, copie para uma planilha nova e salve novamente como `.xlsx` limpo.
- O sistema limita o upload de importação a **5 MB** e processa até **1000 linhas úteis** por arquivo.

### Banco de dados

- MySQL 8 com charset `utf8mb4` / collation `utf8mb4_unicode_ci`.
- Backup diário obrigatório.
- **Nunca** rodar `migrate:fresh`, `migrate:reset`, `truncate` ou `delete()` em produção.
- Seeders são **idempotentes**: rodá-los novamente em produção não duplica nem apaga registros.

### Após o deploy

- Acesse **Administração → Grupos de Permissões** e revise quais permissões cada grupo deve ter (especialmente as novas permissões adicionadas em cada release).
- Acesse **Administração → Usuários** e confirme se a coluna **Status** dos usuários relevantes está como esperado.

## Histórico de mudanças técnicas necessárias em produção

A cada release, ao subir em produção, é necessário executar (no mínimo):

1. `composer install --no-dev --optimize-autoloader` — garante que `maatwebsite/excel`, `barryvdh/laravel-dompdf` e quaisquer novas dependências estejam instaladas.
2. `php artisan migrate --force` — executa todas as migrations pendentes:
   - Permissions/Roles tables (Spatie)
   - `add_login_to_users_table`
   - `add_must_change_password_to_users_table`
   - `add_ativo_to_users_table`
   - `create_empresas_table`
   - `add_created_by_updated_by_to_empresas_table`
   - `create_empresa_historicos_table`
   - `create_empresa_exportacoes_table` (controle da exportação PDF assíncrona)
   - `create_empresa_importacoes_table` (novo — controle de importações em background)
   - `create_jobs_table` (já existe; necessária para `QUEUE_CONNECTION=database`)
3. `php artisan db:seed --class=Database\\Seeders\\PermissionSeeder --force` — cria idempotentemente todas as permissões do `App\Enums\Permissions` (incluindo as novas: `empresas.exportar-pdf`, `empresas.historico`, `empresas.importar`, `empresas.importar-confirmar`, `empresas.inativar`, `empresas.reativar`, `usuarios.desativar`, `usuarios.reativar`, `usuarios.resetar-senha`).
4. `php artisan optimize:clear` (e depois `config:cache`, `route:cache`, `view:cache`) — garante que o Laravel pegue os novos middlewares, rotas e views. Isso é **especialmente importante** para a view `admin.empresas.pdf` ser compilada com a versão atual.
5. Verificar **extensões PHP** `zip`, `gd`, `dom`, `xmlwriter`, `xmlreader` na instância de produção.
6. Validar `upload_max_filesize`/`post_max_size` ≥ 50 MB no PHP, embora o endpoint de importação aceite arquivos até 5 MB.
7. Validar `memory_limit` (recomendado 512 MB para importações maiores; 256 MB pode funcionar em planilhas pequenas e limpas).
8. Para PDFs grandes: garantir `memory_limit` ≥ 256 MB.
9. **Workers de fila** ativos via Supervisor/systemd (`empresas-importacao` e `empresas-exportacao`). Sem eles, importações/exportações ficam em `AGUARDANDO`.
10. Garantir que `QUEUE_CONNECTION` no `.env` de produção esteja como `database` (ou outro driver real — **nunca `sync`**, que processaria dentro da requisição HTTP).
11. Garantir permissão de escrita em `storage/app/private/empresas/importacoes/` (já coberto pelas permissões padrão de `storage/`).
12. Em **Administração → Grupos de Permissões**, marcar as novas permissões nos grupos que devem ter acesso (em especial `empresas.exportar-pdf`).

### Checklist de smoke test pós-deploy

- [ ] Login funciona com usuário Programador.
- [ ] `/admin/empresas` lista as empresas existentes.
- [ ] Criar uma empresa manualmente → conferir histórico em `/admin/empresas/{id}/historico`.
- [ ] Inativar e reativar uma empresa → conferir histórico.
- [ ] Importar planilha Excel → upload retorna 202 imediato, barra de progresso avança ("Processados X de Y"), cards aparecem ao terminar, confirmação grava em transação.
- [ ] Workers `empresas-importacao` e `empresas-exportacao` estão rodando (sem worker, importação/PDF ficam em AGUARDANDO indefinidamente).
- [ ] **Pós-reboot**: após reiniciar o servidor, `sudo supervisorctl status laravel-worker-*:*` mostra `RUNNING` sem intervenção manual (ou `docker compose ps worker-importacao worker-exportacao` mostra `Up` se for Docker).
- [ ] **Pós-deploy**: `sudo supervisorctl restart laravel-worker-importacao:* laravel-worker-exportacao:*` (ou `docker compose restart worker-importacao worker-exportacao`) foi executado para os workers carregarem o código novo.
- [ ] `php artisan queue:failed` está vazio (ou os jobs falhos foram analisados/retentados).
- [ ] Histórico mostra usuário, origem (MANUAL/IMPORTACAO_EXCEL) e campos alterados.
- [ ] Usuário desativado é bloqueado no login.
- [ ] Pesquisa em `/admin/empresas` filtra por nome/CNPJ/ID CIGAM; paginação e "Todos" funcionam.
- [ ] Exportar PDF respeita os filtros aplicados e abre em nova aba.
- [ ] Pesquisa, troca de "por página" e clique em paginação em `/admin/empresas`, `/admin/usuarios` e `/admin/grupos-permissoes` atualizam a tabela **sem refresh** (componente `<x-admin.data-table>`).

## Convenções

- **Sem exclusão**: nenhuma entidade do sistema possui rota `DELETE` ativa. Use inativação/desativação.
- **Permissões**: `<modulo>.<acao>` (ex.: `empresas.editar`). Centralizadas em `app/Enums/Permissions.php` e criadas pelo `PermissionSeeder` de forma idempotente.
- **Auditoria**: registrada em `empresa_historicos` para o módulo Empresas; campos `created_by` e `updated_by` são preenchidos automaticamente pelos controllers.
- **Senhas**: hash via `Hash::make()`; padrão para reset administrativo = `sitiosbs` com `must_change_password=true`.

## Segurança

- CSRF habilitado em todas as rotas POST/PUT/DELETE.
- Rate limit no login (Breeze).
- `Gate::before` libera o Programador apenas se a role estiver vinculada via Spatie.
- Bloqueio de usuário inativo em duas camadas (login + middleware `user.active`).
- Senhas em hash, nunca em texto puro.
- Importação revalida tudo no servidor antes de gravar; preview não persiste nada.

## Preparação do repositório Git

Antes de subir o projeto para qualquer remoto (GitHub, GitLab, Bitbucket etc.) ele
precisa estar limpo: somente o **código-fonte e os arquivos necessários para recriar
o sistema em outro ambiente**. Nada de dependências, caches, logs, dumps,
planilhas, PDFs gerados ou segredos.

### O que deve subir

- `app/`, `bootstrap/`, `config/`, `routes/`, `database/migrations/`,
  `database/seeders/`, `database/factories/`.
- `resources/views/`, `resources/css/`, `resources/js/`.
- `public/index.php`, `public/.htaccess`, `public/favicon.ico` e o tema
  embarcado em `public/assets/` (Highdmin). Esse tema **precisa** ser versionado,
  pois o sistema em produção depende diretamente dele e não há build externo.
- `composer.json`, `composer.lock`, `package.json`, `package-lock.json`,
  `vite.config.js`, `postcss.config.js`, `tailwind.config.js`.
- `Dockerfile`, `docker-compose.yml` e configs em `docker/php/` (sem dados sensíveis).
- `docs/`, `README.md`, `phpunit.xml`, `artisan`, `.gitattributes`,
  `.editorconfig`, `.dockerignore`, `.gitignore`, `.env.example`.

### O que NUNCA deve subir

- `.env`, `.env.local`, `.env.production`, `.env.backup` — qualquer arquivo
  com segredos reais (senhas de banco, API keys, AWS, mail, etc.).
- `vendor/`, `node_modules/` — dependências instaladas; recriadas com
  `composer install` / `npm install`.
- `storage/app/private/*` — uploads reais (planilhas de importação) e PDFs
  gerados pela exportação em background.
- `storage/app/public/*` (uploads expostos), `storage/framework/{cache,sessions,views}/*`,
  `storage/logs/*`, `bootstrap/cache/*` — runtime do Laravel.
- `public/build`, `public/hot`, `public/storage` (symlink), `public/fonts-manifest.dev.json`.
- `database/database.sqlite*` e qualquer `*.sql`, `*.dump`, `*.tar.gz`,
  `*.zip` (dumps / backups).
- `*.xlsx`, `*.xls`, `*.csv`, `*.ods`, `*.pdf` — planilhas reais usadas em
  importação e PDFs gerados.
- `*.log`, `*.tmp`, `*.bak`, `yarn-error.log`, `.phpunit.result.cache`,
  `_ide_helper.php`.
- Diretórios de IDE: `.idea/`, `.vscode/`, `.cursor/`, `.zed/`, `.nova/`.
- `docker/mysql` (volume local do MySQL em desenvolvimento).
- Chaves privadas, certificados (`*.key`, `*.pem`, `*.crt`), `auth.json`.

> Importante: o `.gitignore` do projeto **já mantém** os `.gitignore` internos
> do Laravel em `storage/...` e `bootstrap/cache/`. Esses arquivos preservam a
> estrutura mínima de pastas que o framework precisa em runtime — não os apague.

### `.env.example`

O arquivo `.env.example` versionado é o **modelo** para que outra pessoa monte
o ambiente. Ele:

- **Não** contém senhas reais nem `APP_KEY`. Use placeholders em branco.
- Mantém `DB_HOST=mysql` (nome do serviço Docker) e a porta padrão.
- Indica `QUEUE_CONNECTION=database`, `CACHE_STORE=database`,
  `SESSION_DRIVER=database` — os mesmos que o projeto usa.

Antes de subir o repositório, abra o `.env.example` e confirme que **não há
senha real, token, API key ou chave de criptografia** colada por engano.

> Observação sobre constantes do código:
> O limite de linhas da importação de Empresas (`5000`) está fixo no service
> `EmpresaImportacaoProcessor` e não vem do `.env`. Se precisar mudar, ajuste
> direto no código e registre na seção de checklist de deploy.

### Primeiro commit (fluxo recomendado)

```bash
cd backend

git status                  # confira que .env, vendor/ e node_modules/ não aparecem

git add .
git status                  # revise tudo que será adicionado
git commit -m "chore: preparar repositorio para versionamento"

# adicionar o remoto e enviar
git remote add origin <URL_DO_REPOSITORIO>
git branch -M main
git push -u origin main
```

### Checklist de verificação antes do `git commit` / `git push`

Rode `git status` e confirme que **nenhum** dos itens abaixo aparece na lista:

- [ ] `.env`, `.env.local`, `.env.production`, `.env.backup`.
- [ ] `vendor/`, `node_modules/`.
- [ ] `storage/app/private/empresas/importacoes/<arquivo>.xlsx`.
- [ ] `storage/app/private/empresas/exportacoes/<arquivo>.pdf`.
- [ ] `storage/framework/cache/...`, `storage/framework/sessions/...`,
      `storage/framework/views/...`, `storage/logs/laravel.log`.
- [ ] `database/database.sqlite`.
- [ ] Qualquer `*.sql`, `*.zip`, `*.tar.gz`, `*.bak`, `*.xlsx`, `*.csv`, `*.pdf` na raiz.
- [ ] Diretórios de IDE: `.idea/`, `.vscode/`, `.cursor/`.

E confirme que **estes itens estão presentes** no `git status` / `git ls-files`:

- [ ] `composer.json` e `composer.lock`.
- [ ] `package.json` e `package-lock.json`.
- [ ] `.env.example` (sem senhas reais).
- [ ] `Dockerfile`, `docker-compose.yml` e configs em `docker/`.
- [ ] `README.md` e tudo em `docs/`.
- [ ] `public/assets/` (tema Highdmin) — confira que existem subpastas
      `css/`, `js/`, `images/`, `vendor/`.

Comandos auxiliares de auditoria:

```bash
# Quantos arquivos vão ser versionados ao todo?
git ls-files --others --exclude-standard | wc -l

# Garantir que NENHUM arquivo sensível está prestes a entrar:
git ls-files --others --exclude-standard \
  | grep -E '(^\.env$|\.sqlite|\.log$|/vendor/|/node_modules/|storage/app/private/.+/)' \
  && echo "ATENÇÃO: arquivos sensíveis seriam versionados" \
  || echo "ok: nenhum arquivo sensível na lista de untracked"

# Conferir uma regra específica do .gitignore (com a regra exata que ignorou):
git check-ignore -v storage/app/private/empresas/importacoes/qualquer.xlsx
```

### Se algum arquivo proibido já foi rastreado por engano

Use `git rm --cached` para tirar do índice **sem apagar do disco**:

```bash
# arquivo único
git rm --cached .env

# diretório inteiro
git rm -r --cached vendor
git rm -r --cached node_modules
git rm -r --cached storage/app/private
git rm -r --cached bootstrap/cache

git commit -m "chore: remover arquivos sensiveis do controle de versao"
```

> Se um segredo (senha, token, chave) chegou a ser commitado **e enviado para o
> remoto**, considere o segredo comprometido: rotacione a credencial e, se
> possível, reescreva o histórico com `git filter-repo` (ou clone limpo).

## Licença

Uso interno. Direitos reservados — Sítio Barreiras.
