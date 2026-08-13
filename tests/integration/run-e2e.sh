#!/usr/bin/env bash
set -euo pipefail
compose=(docker compose -f tests/infra/compose.yml -p "${WPNB_PROJECT:-wpnb-e2e}")
cleanup(){ "${compose[@]}" down -v --remove-orphans >/dev/null 2>&1 || true; }
trap cleanup EXIT
"${compose[@]}" up -d --build
ready=0
for _ in {1..60}; do
  if "${compose[@]}" exec -T wordpress wp core install --url="http://127.0.0.1:${WPNB_HTTP_PORT:-8080}" --title=WPNB --admin_user=admin --admin_password=admin-password --admin_email=admin@example.invalid --skip-email --allow-root >/dev/null 2>&1; then ready=1; break; fi
  sleep 2
done
if (( ready == 0 )); then "${compose[@]}" logs wordpress; exit 1; fi
"${compose[@]}" exec -T wordpress sh -c 'mkdir -p wp-content/mu-plugins && cp /integration/wpnb-test-mu.php wp-content/mu-plugins/wpnb-test-mu.php && chown -R www-data:www-data wp-content'
"${compose[@]}" exec -T wordpress curl --fail --silent --show-error --cacert /certs/ca.crt -H 'Authorization: Bearer fixture-secret-key' -H 'Content-Type: application/json' --data '{}' https://openai.test/v1/responses >/dev/null
"${compose[@]}" exec -T wordpress wp eval 'if(!function_exists("sodium_crypto_secretbox")&&!function_exists("openssl_encrypt"))throw new RuntimeException("OpenAI secret encryption is unavailable.");' --allow-root
export WPNB_BASE_URL="http://127.0.0.1:${WPNB_HTTP_PORT:-8080}"
export WPNB_ZIP_PATH="${WPNB_ARTIFACTS_DIR}/wordpress-news-bot-0.4.0-rc.1.zip"
npm ci
npx playwright install --with-deps chromium
npx playwright test
if "${compose[@]}" exec -T wordpress sh -c "test -f wp-content/debug.log && grep -Eqi 'PHP (Warning|Notice|Deprecated|Fatal|Parse)' wp-content/debug.log"; then "${compose[@]}" exec -T wordpress tail -n 200 wp-content/debug.log; exit 1; fi
