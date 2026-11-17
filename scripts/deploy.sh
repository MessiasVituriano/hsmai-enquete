#!/usr/bin/env bash
#
# Deploy em produção: atualiza o código, reconstrói as imagens e troca os
# containers. Pensado para ser rodado no servidor, de dentro do repositório.
#
#   ./scripts/deploy.sh              # deploy completo
#   ./scripts/deploy.sh --no-pull    # usa o código que já está no disco
#   ./scripts/deploy.sh --force      # ignora a trava de evento ao vivo
#
# O que ele deliberadamente NÃO faz:
#
#   down -v    apagaria o volume do Postgres — e com ele todas as respostas.
#   migrate    o entrypoint do container já roda `migrate --force` e o seed
#   db:seed    (idempotente) a cada boot (docker/php/entrypoint.sh).
#
# Antes de trocar os containers ele faz um dump do banco em $BACKUP_DIR
# (padrão ~/enquete-backups). Mantenha esse diretório FORA do repositório: dentro,
# os dumps sujam o working tree e o próprio deploy passa a se recusar a rodar.
set -euo pipefail

cd "$(dirname "$0")/.."

PULL=yes
FORCE=no
for arg in "$@"; do
    case "$arg" in
        --no-pull) PULL=no ;;
        --force)   FORCE=yes ;;
        -h|--help) awk 'NR>1 && /^#/ {sub(/^# ?/, ""); print; next} NR>1 {exit}' "$0"; exit 0 ;;
        *) echo "opção desconhecida: $arg" >&2; exit 2 ;;
    esac
done

BACKUP_DIR="${BACKUP_DIR:-$HOME/enquete-backups}"

step()  { printf '  %-12s %s\n' "$1" "$2"; }
abort() { printf '\n  ✘ %s\n\n' "$1" >&2; exit 1; }
env_get() { grep -E "^$1=" .env 2>/dev/null | head -1 | cut -d= -f2- | tr -d '"'; }

# --- pré-condições -------------------------------------------------------
# Tudo que pode dar errado é checado ANTES de mexer nos containers: um deploy
# que falha no meio é pior que um deploy que nem começou.

command -v docker >/dev/null || abort "docker não encontrado"
docker compose version >/dev/null 2>&1 \
    || abort "plugin do docker compose ausente — instale docker-compose-plugin"

[ -f .env ] || abort ".env não existe (copie de .env.example e preencha)"

[ -n "$(env_get APP_KEY)" ] || abort "APP_KEY vazio no .env — o app responderia 500"
[ "$(env_get APP_ENV)" = production ] || abort "APP_ENV não é production no .env"
[ "$(env_get APP_DEBUG)" = false ] \
    || abort "APP_DEBUG não é false — a página de erro vazaria senha e master token"

DOMAIN="$(env_get APP_DOMAIN)"
[ -n "$DOMAIN" ] || abort "APP_DOMAIN vazio no .env"
if [ "$DOMAIN" = ":80" ]; then
    step aviso "APP_DOMAIN=:80 — subindo SEM HTTPS"
fi

if [ "$PULL" = yes ] && [ -n "$(git status --porcelain)" ]; then
    abort "working tree com alterações não commitadas — resolva ou use --no-pull"
fi

# --- trava de enquete ao vivo --------------------------------------------
# Trocar os containers reinicia o php-fpm: os votos em trânsito caem com a sala
# olhando para o telão. Enquete recebendo respostas E com respostas no banco é
# sinal de que o painel está acontecendo. A consulta vai direto no banco para
# funcionar mesmo com o app fora do ar.

poll_status() {
    docker compose exec -T pgsql psql -U "$(env_get DB_USERNAME)" \
        -d "$(env_get DB_DATABASE)" -tAc \
        "SELECT CASE WHEN s.accepting THEN 'aberta' ELSE 'encerrada' END || ':' || (SELECT count(*) FROM answers)
           FROM poll_states s ORDER BY s.id LIMIT 1" 2>/dev/null | tr -d '[:space:]'
}

STATUS="$(poll_status || true)"
case "$STATUS" in
    aberta:0) step enquete "aberta, sem respostas" ;;
    aberta:*)
        [ "$FORCE" = yes ] \
            || abort "enquete recebendo respostas (${STATUS#aberta:} no banco). Use --force se tem certeza."
        step aviso "enquete aberta — prosseguindo por --force"
        ;;
    '') step banco "fora do ar ou vazio — nada a preservar" ;;
    *)  step enquete "$STATUS" ;;
esac

# --- backup --------------------------------------------------------------
# Barato (o banco de um evento cabe em alguns MB) e a única rede de segurança
# que existe: `git revert` traz o código de volta, não as respostas.

if [ -n "$STATUS" ]; then
    mkdir -p "$BACKUP_DIR"
    DUMP="$BACKUP_DIR/pre-deploy-$(date +%Y%m%d-%H%M%S).sql.gz"
    docker compose exec -T pgsql pg_dump -U "$(env_get DB_USERNAME)" \
        -d "$(env_get DB_DATABASE)" | gzip > "$DUMP"
    step backup "$DUMP ($(du -h "$DUMP" | cut -f1))"
fi

# --- código --------------------------------------------------------------

if [ "$PULL" = yes ]; then
    git pull --ff-only
    step commit "$(git log --oneline -1)"
else
    step commit "--no-pull: $(git log --oneline -1)"
fi

# --- build e troca -------------------------------------------------------
# Build ANTES do `up`: a build leva minutos e os containers antigos seguem
# atendendo o tempo todo. O `up` só troca no fim, e aí a indisponibilidade é
# de segundos em vez de minutos.

step build "compilando assets e imagem php (pode demorar)"
docker compose build

step subindo "trocando os containers"
docker compose up -d --remove-orphans

# --- verificação ---------------------------------------------------------
# Um deploy que devolve o prompt sem checar nada é um deploy que você descobre
# que falhou quando a sala já está cheia.

BASE="http://localhost"
if [ "$DOMAIN" != ":80" ]; then
    BASE="https://$DOMAIN"
fi

step checando "aguardando $BASE/up"
for i in $(seq 1 45); do
    if curl -fsS -m 5 -o /dev/null "$BASE/up" 2>/dev/null; then
        step ok "aplicação respondendo em ${i}s"
        docker compose ps --format '  {{.Service}}\t{{.Status}}'
        printf '\n  ✔ deploy concluído\n\n'
        exit 0
    fi
    sleep 1
done

docker compose logs --tail=30 app
abort "aplicação não respondeu em 45s — log do app acima"
