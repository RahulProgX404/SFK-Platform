#!/bin/sh
# Deploys one image tag on the AWS EC2 server (docs/deploy-aws.md). Run as root in /opt/sfk — by GitHub Actions through
# SSM Run Command (.github/workflows/deploy-aws.yml) or by hand:  sudo /opt/sfk/deploy.sh <tag>
#
# 1. logs in to ECR with the instance role, pulls <ECR_IMAGE>:<tag>
# 2. copies compose.yml, Caddyfile, php-fpm-pool.conf and this script out of that image (the repository is the only
#    place they are edited)
# 3. records the tag in .env and restarts the stack; the app container migrates the database as it starts
#
# A failed pull or copy changes nothing; the previous tag stays in .env, so `deploy.sh <previous tag>` rolls back.
set -eu
cd /opt/sfk

TAG="${1:?usage: deploy.sh <image tag>}"
# ECR_IMAGE (registry/repository) and SFK_DOMAIN from the compose .env
ECR_IMAGE="$(sed -n 's/^ECR_IMAGE=//p' .env)"
[ -n "$ECR_IMAGE" ] || { echo "ECR_IMAGE missing in /opt/sfk/.env" >&2; exit 1; }
REGISTRY="${ECR_IMAGE%%/*}"
REGION="$(echo "$REGISTRY" | cut -d. -f4)"
IMAGE="$ECR_IMAGE:$TAG"

echo "==> pulling $IMAGE"
aws ecr get-login-password --region "$REGION" | docker login --username AWS --password-stdin "$REGISTRY" >/dev/null
docker pull --quiet "$IMAGE"

echo "==> refreshing the server files from the image"
CID="$(docker create "$IMAGE")"
trap 'docker rm -f "$CID" >/dev/null 2>&1 || true' EXIT
for FILE in compose.yml Caddyfile php-fpm-pool.conf deploy.sh; do
    docker cp "$CID:/var/www/html/docker/aws/$FILE" "$FILE.new"
    mv "$FILE.new" "$FILE"
done
chmod +x deploy.sh

echo "==> starting $TAG"
if grep -q '^APP_TAG=' .env; then
    sed -i "s/^APP_TAG=.*/APP_TAG=$TAG/" .env
else
    echo "APP_TAG=$TAG" >> .env
fi
docker compose up -d --remove-orphans

# the app is up when nginx answers Laravel's health route (migrations run first, so allow a minute)
echo "==> waiting for the app"
for _ in $(seq 1 30); do
    if docker compose exec -T app php -r 'exit(@file_get_contents("http://127.0.0.1:8080/up") === false ? 1 : 0);' 2>/dev/null; then
        echo "==> $TAG is live"
        docker image prune -af --filter "until=168h" >/dev/null
        exit 0
    fi
    sleep 3
done
echo "!! the app did not answer /up within 90 s — docker compose logs app" >&2
docker compose logs --tail 80 app >&2
exit 1
