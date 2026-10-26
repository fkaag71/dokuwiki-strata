#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
docker run --rm --entrypoint sh \
  -v "$PWD:/var/www/html/lib/plugins.core/strata:ro" \
  dokuwiki/dokuwiki:2026-07-14c \
  -ec '/dokuwiki-storagesetup.sh >/dev/null 2>&1; cd /var/www/html/lib/plugins.core/strata; exec "$@"' sh "$@"
