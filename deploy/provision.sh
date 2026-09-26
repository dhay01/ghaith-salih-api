#!/usr/bin/env bash
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive

# --- swap ---
if ! swapon --show | grep -q .; then
  fallocate -l 2G /swapfile
  chmod 600 /swapfile
  mkswap /swapfile
  swapon /swapfile
  grep -q '/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

# --- users ---
id deploy >/dev/null 2>&1 || useradd --create-home --shell /bin/bash deploy
usermod -aG www-data deploy
install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
touch /home/deploy/.ssh/authorized_keys
chmod 600 /home/deploy/.ssh/authorized_keys
chown deploy:deploy /home/deploy/.ssh/authorized_keys

# --- composer ---
if [ ! -x /usr/local/bin/composer ]; then
  curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# --- ssh ---
cat >/etc/ssh/sshd_config.d/ghaith.conf <<'EOF'
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin prohibit-password
X11Forwarding no
AllowTcpForwarding no
ClientAliveInterval 30
ClientAliveCountMax 6
EOF
systemctl reload ssh

# --- fail2ban ---
systemctl enable --now fail2ban

# --- unattended ---
dpkg-reconfigure -f noninteractive unattended-upgrades >/dev/null 2>&1 || true

# --- php ---
cat >/etc/php/8.5/mods-available/ghaith.ini <<'EOF'
expose_php=Off
memory_limit=256M
upload_max_filesize=32M
post_max_size=32M
max_execution_time=120
max_input_time=120
opcache.enable=1
opcache.memory_consumption=128
opcache.validate_timestamps=0
opcache.max_accelerated_files=20000
EOF
phpenmod ghaith

# CLI opcache timestamps stay off in prod; artisan still works.
sed -i 's/^pm = .*/pm = ondemand/' /etc/php/8.5/fpm/pool.d/www.conf
sed -i 's/^pm.max_children = .*/pm.max_children = 5/' /etc/php/8.5/fpm/pool.d/www.conf
grep -q '^pm.process_idle_timeout' /etc/php/8.5/fpm/pool.d/www.conf \
  || echo 'pm.process_idle_timeout = 10s' >> /etc/php/8.5/fpm/pool.d/www.conf
sed -i 's/^;\?pm.max_requests = .*/pm.max_requests = 200/' /etc/php/8.5/fpm/pool.d/www.conf

systemctl restart php8.5-fpm

# --- cloudflare real IP ---
{
  echo '# generated from cloudflare.com/ips'
  curl -fsSL https://www.cloudflare.com/ips-v4 | while read -r cidr; do
    echo "set_real_ip_from $cidr;"
  done
  curl -fsSL https://www.cloudflare.com/ips-v6 | while read -r cidr; do
    echo "set_real_ip_from $cidr;"
  done
  echo 'real_ip_header CF-Connecting-IP;'
} >/etc/nginx/conf.d/cloudflare-realip.conf

# --- nginx extra ---
cat >/etc/nginx/conf.d/ghaith-tuning.conf <<'EOF'
server_tokens off;
ssl_session_cache shared:SSL:10m;
ssl_session_timeout 1d;
EOF

# --- firewall ---
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
while read -r cidr; do
  [ -n "$cidr" ] || continue
  ufw allow proto tcp from "$cidr" to any port 80,443 comment 'Cloudflare'
done < <(curl -fsSL https://www.cloudflare.com/ips-v4; curl -fsSL https://www.cloudflare.com/ips-v6)
ufw --force enable

# --- dirs ---
install -d -m 750 /etc/ssl/ghaith
install -d -m 755 /var/www
echo provision-ok
