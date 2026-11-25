#!/usr/bin/env bash
# Builds dist/trusty-ofisina-hostinger.zip: everything to upload into public_html (or a subdomain folder).
set -euo pipefail
cd "$(dirname "$0")"
OUT=dist/trusty-ofisina
rm -rf dist && mkdir -p "$OUT"
cp -R hostinger/. "$OUT"/
rm -f "$OUT/config.php"
cp index.html "$OUT/app.html"
mkdir -p "$OUT/assets" && cp assets/*.png "$OUT/assets/"
mkdir -p "$OUT/data/uploads"
cp HOSTINGER.md "$OUT/README-INSTALL.md"
cp INSTAGRAM.md "$OUT/README-INSTAGRAM.md"
(cd dist/trusty-ofisina && zip -qr ../trusty-ofisina-hostinger.zip . -x '*.DS_Store')
echo "Built dist/trusty-ofisina-hostinger.zip"
