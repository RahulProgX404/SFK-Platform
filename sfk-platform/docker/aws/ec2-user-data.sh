#!/bin/bash
# EC2 "User data" for the SFK server (docs/deploy-aws.md, step 5): paste this into Advanced details → User data when
# launching an Amazon Linux 2023 instance. It runs once, as root, on the first boot. Amazon Linux 2023 already has the
# AWS CLI and the SSM agent (Session Manager, Run Command).
set -euxo pipefail

dnf update -y
dnf install -y docker
systemctl enable --now docker

# Docker Compose v2 (a CLI plugin; not in the Amazon Linux repositories)
COMPOSE_VERSION=v2.29.7
mkdir -p /usr/local/lib/docker/cli-plugins
curl -fsSL "https://github.com/docker/compose/releases/download/${COMPOSE_VERSION}/docker-compose-linux-$(uname -m)" \
    -o /usr/local/lib/docker/cli-plugins/docker-compose
chmod +x /usr/local/lib/docker/cli-plugins/docker-compose

# 2 GB of swap: a safety net for a 2 GB instance (image pulls, a busy moment), not working memory
if [ ! -f /swapfile ]; then
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

# the app's home; the first deploy fills it (docs/deploy-aws.md, step 7)
mkdir -p /opt/sfk
chmod 750 /opt/sfk
timedatectl set-timezone Asia/Tokyo
