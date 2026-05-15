# Arquivos de deploy — facigam

Este diretório contém artefatos prontos para uso em servidores Linux de produção.

## Estrutura

```
docs/deploy/
├── README.md                       (este arquivo)
└── supervisor/
    ├── laravel-worker-importacao.conf  Worker da fila empresas-importacao
    └── laravel-worker-exportacao.conf  Worker da fila empresas-exportacao
```

## Supervisor — Laravel Queue Workers

Mantém os workers do Laravel rodando 24/7, com restart automático após reboot
do servidor e após cada deploy.

### Instalação rápida

```bash
sudo apt update
sudo apt install supervisor -y

sudo cp docs/deploy/supervisor/laravel-worker-*.conf /etc/supervisor/conf.d/

# Ajuste os caminhos absolutos no arquivo (directory / command) antes do passo
# abaixo. Por padrão estão em /var/www/facigam.
sudoedit /etc/supervisor/conf.d/laravel-worker-importacao.conf
sudoedit /etc/supervisor/conf.d/laravel-worker-exportacao.conf

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker-importacao:*
sudo supervisorctl start laravel-worker-exportacao:*
```

### Após cada deploy

```bash
sudo supervisorctl restart laravel-worker-importacao:*
sudo supervisorctl restart laravel-worker-exportacao:*
```

Sem este `restart`, os workers em execução continuam servindo o **código
antigo** (eles bootam o framework uma única vez e processam vários jobs).

### Comandos úteis

```bash
sudo supervisorctl status laravel-worker-*:*       # ver se estão rodando
sudo supervisorctl tail -f laravel-worker-importacao:laravel-worker-importacao_00 stdout
sudo supervisorctl tail -f laravel-worker-exportacao:laravel-worker-exportacao_00 stdout
sudo supervisorctl restart laravel-worker-*:*      # reiniciar (deploy)
sudo supervisorctl stop laravel-worker-*:*         # parar (manutenção)
sudo supervisorctl start laravel-worker-*:*        # iniciar de novo
```

### Logs

```
/var/log/supervisor/laravel-worker-importacao.out.log
/var/log/supervisor/laravel-worker-importacao.err.log
/var/log/supervisor/laravel-worker-exportacao.out.log
/var/log/supervisor/laravel-worker-exportacao.err.log
```

## Health check / filas

```bash
php artisan queue:failed       # listar jobs que falharam
php artisan queue:retry all    # retentar todos os jobs falhos
php artisan queue:flush        # apagar TODOS os jobs falhos (cuidado)
php artisan queue:monitor empresas-importacao,empresas-exportacao,default --max=100
```
