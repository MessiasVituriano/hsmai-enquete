#!/bin/sh
set -e

# O compose já espera o Postgres ficar "healthy" (depends_on), então aqui só
# garantimos o schema e os caches antes de entregar requests.
php artisan migrate --force

# O seeder é idempotente: sincroniza as perguntas de config/enquete.php com o
# banco pela posição, sem duplicar e sem apagar respostas. Rodar a cada boot
# faz um deploy com texto revisado chegar ao telão sem passo manual.
php artisan db:seed --force

# Em local (APP_DEBUG=true) manter os caches desligados evita ter de limpar a
# cada troca de config; em prod cacheamos tudo para o opcache trabalhar quente.
if [ "${APP_ENV}" != "local" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
else
    php artisan config:clear
fi

exec "$@"
