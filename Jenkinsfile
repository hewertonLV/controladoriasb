pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        buildDiscarder(logRotator(numToKeepStr: '5', daysToKeepStr: '5'))
        timestamps()
        // Checkout manual após limpar permissões deixadas pelo Docker (root/www-data).
        skipDefaultCheckout(true)
    }

    environment {
        COMPOSE_PROJECT_NAME = 'controladoria_sb_jenkins'
        APP_SERVICE = 'controladoriasb'
        DB_SERVICE = 'mysql'
    }

    stages {
        stage('Limpar permissões do workspace') {
            steps {
                sh '''
                    set +e
                    unset COMPOSE_PROJECT_NAME || true
                    export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-controladoria_sb_jenkins}"

                    # Build anterior pode ter deixado storage/ e bootstrap/cache como root (volume Docker).
                    docker compose down --remove-orphans 2>/dev/null || true

                    JENKINS_UID=$(id -u)
                    JENKINS_GID=$(id -g)

                    if command -v docker >/dev/null 2>&1; then
                        docker run --rm -v "${WORKSPACE}:/workspace" alpine:3.20 \
                            chown -R "${JENKINS_UID}:${JENKINS_GID}" \
                            /workspace/storage /workspace/bootstrap/cache 2>/dev/null || true

                        docker run --rm -v "${WORKSPACE}:/workspace" alpine:3.20 \
                            sh -c 'rm -rf /workspace/storage /workspace/bootstrap/cache' 2>/dev/null || true
                    fi

                    sudo chown -R "${JENKINS_UID}:${JENKINS_GID}" storage bootstrap/cache 2>/dev/null || true
                    sudo rm -rf storage bootstrap/cache 2>/dev/null || true

                    set -e
                '''
            }
        }

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

        // MySQL sobe via depends_on/healthcheck do compose (app aguarda healthy).
        // Testes PHPUnit usam SQLite em memória (phpunit.xml) — sem migrate:fresh no CI.

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
            script {
                try {
                    sh '''
                        set +e
                        unset COMPOSE_PROJECT_NAME || true
                        export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-controladoria_sb_jenkins}"

                        if [ -f docker-compose.yml ]; then
                            docker compose logs --tail=150 || true
                            docker compose down --remove-orphans || true
                        fi

                        JENKINS_UID=$(id -u)
                        JENKINS_GID=$(id -g)

                        if command -v docker >/dev/null 2>&1; then
                            docker run --rm -v "${WORKSPACE}:/workspace" alpine:3.20 \
                                chown -R "${JENKINS_UID}:${JENKINS_GID}" \
                                /workspace/storage /workspace/bootstrap/cache 2>/dev/null || true
                        fi

                        sudo chown -R "${JENKINS_UID}:${JENKINS_GID}" storage bootstrap/cache 2>/dev/null || true
                        set -e
                    '''
                } catch (Exception ignored) {
                    echo 'Pós-build: limpeza Docker/permissões ignorada (workspace pode estar indisponível).'
                }
            }
        }

        success {
            echo 'Pipeline finalizado com sucesso.'
        }

        failure {
            echo 'Pipeline falhou. Verifique o Console Output.'
        }
    }
}
