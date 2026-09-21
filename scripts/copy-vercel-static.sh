#!/bin/sh
set -e
OUT="${1:-vercel-gateway}"
mkdir -p "$OUT/css" "$OUT/js" "$OUT/residence/css" "$OUT/pharmacy/css" "$OUT/admin/css"
cp -R css/. "$OUT/css/" 2>/dev/null || true
cp -R js/. "$OUT/js/" 2>/dev/null || true
cp -R residence/css/. "$OUT/residence/css/" 2>/dev/null || true
cp -R pharmacy/css/. "$OUT/pharmacy/css/" 2>/dev/null || true
cp -R admin/css/. "$OUT/admin/css/" 2>/dev/null || true
cp -f 2.png gcash.png "$OUT/" 2>/dev/null || true
printf 'AddToMar static assets for Vercel. PHP stays on Render.\n' > "$OUT/README.txt"
