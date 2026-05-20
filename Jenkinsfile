pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        buildDiscarder(logRotator(numToKeepStr: '5', daysToKeepStr: '5'))
        timestamps()
    }

    environment {
        COMPOSE_PROJECT_NAME = 'controladoria_sb_jenkins'
        APP_SERVICE = 'controladoriasb'
        DB_SERVICE = 'mysql'
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Preparar .env') {
            steps {
                sh '''
                    if [ ! -f .env.testing ]; then
                        echo "ERRO: .env.testing não encontrado."
                        exit 1
                    fi
                    cp .env.testing .env
                '''
            }
        }

        stage('Subir containers') {
            steps {
                sh '''
                    unset COMPOSE_PROJECT_NAME || true
                    export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-controladoria_sb_jenkins}"

                    docker compose down --remove-orphans || true
                    docker compose up -d --build
                    docker compose ps
                '''
            }
        }

        stage('Aguardar MySQL') {
            steps {
                sh '''
                    unset COMPOSE_PROJECT_NAME || true
                    export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-controladoria_sb_jenkins}"

                    DB_PASSWORD=$(grep -E '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d '"' | tr -d "'")
                    DB_PASSWORD=${DB_PASSWORD:-root}

                    echo "Aguardando MySQL iniciar..."

                    for i in $(seq 1 60); do
                        if docker compose exec -T $DB_SERVICE mysqladmin ping -h"127.0.0.1" -uroot -p"${DB_PASSWORD}" --silent; then
                            echo "MySQL está pronto."
                            exit 0
                        fi

                        echo "MySQL ainda não está pronto... tentativa $i"
                        sleep 2
                    done

                    echo "MySQL não iniciou a tempo."
                    docker compose logs $DB_SERVICE
                    exit 1
                '''
            }
        }

        stage('Instalar dependências PHP') {
            steps {
                sh '''
                    unset COMPOSE_PROJECT_NAME || true
                    export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-controladoria_sb_jenkins}"

                    docker compose exec -T $APP_SERVICE composer install --no-interaction --prefer-dist --optimize-autoloader
                '''
            }
        }

        stage('Preparar Laravel') {
            steps {
                sh '''
                    unset COMPOSE_PROJECT_NAME || true
                    export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-controladoria_sb_jenkins}"

                    docker compose exec -T $APP_SERVICE php artisan key:generate --force
                    docker compose exec -T $APP_SERVICE php artisan config:clear
                    docker compose exec -T $APP_SERVICE php artisan cache:clear
                    docker compose exec -T $APP_SERVICE php artisan route:clear
                    docker compose exec -T $APP_SERVICE php artisan view:clear
                '''
            }
        }

        stage('Rodar migrations') {
            steps {
                sh '''
                    unset COMPOSE_PROJECT_NAME || true
                    export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-controladoria_sb_jenkins}"

                    docker compose exec -T $APP_SERVICE php artisan migrate:fresh --force
                '''
            }
        }

        stage('Rodar testes') {
            steps {
                sh '''
                    unset COMPOSE_PROJECT_NAME || true
                    export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-controladoria_sb_jenkins}"

                    docker compose exec -T $APP_SERVICE php artisan test
                '''
            }
        }
    }

    post {
        always {
            sh '''
                unset COMPOSE_PROJECT_NAME || true
                export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-controladoria_sb_jenkins}"

                docker compose logs --tail=150 || true
                docker compose down --remove-orphans || true
            '''
        }

        success {
            echo 'Pipeline finalizado com sucesso.'
        }

        failure {
            echo 'Pipeline falhou. Verifique o Console Output.'
        }
    }
}
