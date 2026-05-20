# Importações Assíncronas

Este projeto processa importações pesadas fora da requisição HTTP. A tela apenas envia o arquivo, cria o registro de controle e consulta o status por polling.

## Configuração Obrigatória

Use fila real para importações:

```bash
QUEUE_CONNECTION=database
```

Também pode ser usado Redis, desde que exista worker consumindo a fila correta.

Não use `QUEUE_CONNECTION=sync` em produção. Com `sync`, a planilha é processada dentro da requisição HTTP, ocupando PHP-FPM/Apache/Nginx e impedindo progresso assíncrono.

As migrations padrão do projeto criam:

- `jobs`
- `failed_jobs`
- `job_batches`

## Fluxo de Fornecedores

1. `POST /admin/fornecedores/importar/iniciar` recebe e salva o arquivo em `storage/app/private/fornecedores/importacoes`.
2. O registro é criado em `fornecedor_importacoes` com status `AGUARDANDO`.
3. O job `ProcessarPreviewImportacaoFornecedoresJob` é despachado na fila `imports`.
4. O worker processa a planilha, atualiza `total_linhas`, `linhas_processadas`, `percentual`, contadores e erros.
5. A tela consulta `GET /admin/fornecedores/importar/{uuid}/status` até `CONCLUIDO` ou `FALHOU`.

## Worker Local

```bash
php artisan queue:work --queue=imports --sleep=3 --tries=3 --timeout=300
```

Para validar sem deixar o terminal preso quando não há jobs:

```bash
php artisan queue:work --queue=imports --sleep=3 --tries=3 --timeout=300 --stop-when-empty
```

Após alterar código de jobs em desenvolvimento:

```bash
php artisan queue:restart
```

## Supervisor Em Produção

Exemplo:

```ini
[program:laravel-worker-imports]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/controladoriasb/artisan queue:work --queue=imports,default --sleep=3 --tries=3 --timeout=300 --max-time=3600
directory=/var/www/controladoriasb
user=www-data
autostart=true
autorestart=true
numprocs=1
redirect_stderr=false
stdout_logfile=/var/log/supervisor/laravel-worker-imports.out.log
stderr_logfile=/var/log/supervisor/laravel-worker-imports.err.log
stopwaitsecs=330
stopsignal=TERM
killasgroup=true
stopasgroup=true
```

Comandos:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker-imports:*
sudo supervisorctl status laravel-worker-imports:*
sudo supervisorctl restart laravel-worker-imports:*
```

Após deploy:

```bash
php artisan queue:restart
sudo supervisorctl restart laravel-worker-imports:*
```

## Diagnóstico

Listar jobs falhos:

```bash
php artisan queue:failed
```

Ver jobs pendentes no banco:

```bash
php artisan tinker
DB::table('jobs')->where('queue', 'imports')->count();
```

Reprocessar jobs falhos:

```bash
php artisan queue:retry all
php artisan queue:retry <uuid>
```

Limpar jobs falhos antigos:

```bash
php artisan queue:flush
```

Limpar jobs pendentes antigos com cuidado:

```bash
php artisan queue:clear database --queue=imports
```

## Causas Comuns De "Na Fila"

- Worker não está rodando.
- Worker está consumindo outra fila.
- `QUEUE_CONNECTION=sync` em produção.
- Worker antigo não foi reiniciado após deploy.
- Job falhou e foi para `failed_jobs`.
- Falta permissão de leitura no arquivo salvo em storage.

## Status JSON

Endpoint atual:

```http
GET /admin/fornecedores/importar/{uuid}/status
```

Resposta:

```json
{
  "status": "PROCESSANDO",
  "progresso": 25,
  "linhas_processadas": 100,
  "total_linhas": 400,
  "erros": [],
  "mensagem": "Processados 100 de 400 registros."
}
```
