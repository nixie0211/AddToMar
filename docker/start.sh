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

php -r '
$keys = ["DB_HOST","DB_PORT","DB_NAME","DB_USER","DB_PASS","DB_SSL","DB_SSL_CA","APP_URL","PUBLIC_APP_URL"];
$out = [];
foreach ($keys as $key) {
    $value = getenv($key);
    if ($value !== false) {
        $out[$key] = $value;
    }
}
file_put_contents("/tmp/addtomar-runtime-env.php", "<?php\nreturn " . var_export($out, true) . ";\n");
'
chmod 640 /tmp/addtomar-runtime-env.php || true
chown www-data:www-data /tmp/addtomar-runtime-env.php || true

exec apache2-foreground
