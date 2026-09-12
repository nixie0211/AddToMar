#!/bin/bash
set -euo pipefail

PORT="${PORT:-10000}"

cat >/etc/apache2/ports.conf <<EOF
Listen ${PORT}
EOF

sed -i "s/\${PORT}/${PORT}/g" /etc/apache2/sites-available/000-default.conf

mkdir -p /var/www/html/data/uploads/receipts \
    /var/www/html/data/uploads/orders \
    /var/www/html/data/uploads/medicines \
    /var/www/html/data/uploads/pharmacies

chown -R www-data:www-data /var/www/html/data || true

exec apache2-foreground
