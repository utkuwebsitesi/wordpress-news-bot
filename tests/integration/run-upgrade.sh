#!/usr/bin/env bash
set -euo pipefail
export MSYS_NO_PATHCONV=1
export MSYS2_ARG_CONV_EXCL='*'
baseline="${1:?baseline ZIP filename required}"
compose=(docker compose -f tests/infra/compose.yml -p "${WPNB_PROJECT:-wpnb-upgrade}")
cleanup(){ "${compose[@]}" down -v --remove-orphans >/dev/null 2>&1 || true; }
trap cleanup EXIT
"${compose[@]}" up -d --build
ready=0
for _ in {1..60}; do
  if "${compose[@]}" exec -T wordpress wp core install --url="http://localhost:${WPNB_HTTP_PORT:-8080}" --title=WPNB --admin_user=admin --admin_password=admin-password --admin_email=admin@example.invalid --skip-email --allow-root >/dev/null 2>&1; then ready=1; break; fi
  sleep 2
done
if (( ready == 0 )); then "${compose[@]}" logs wordpress; exit 1; fi
"${compose[@]}" exec -T wordpress sh -c 'mkdir -p wp-content/mu-plugins && cp /integration/wpnb-test-mu.php wp-content/mu-plugins/wpnb-test-mu.php'
"${compose[@]}" exec -T wordpress wp plugin install "/artifacts/$baseline" --activate --allow-root >/dev/null
"${compose[@]}" exec -T wordpress wp eval-file /integration/seed-upgrade.php --allow-root
"${compose[@]}" exec -T wordpress wp plugin install /artifacts/wordpress-news-bot-0.4.0-rc.3.zip --force --activate --allow-root >/dev/null
"${compose[@]}" exec -T wordpress wp eval 'global $wpdb; $p=(new WordPressNewsBot\DatabaseEngineRepair($wpdb))->preview(); if($p["code"]==="engine_conversion_required"){(new WordPressNewsBot\DatabaseRepair($wpdb))->run(false,true);} elseif($p["code"]!=="engine_conversion_verified"){throw new RuntimeException($p["code"]);}' --allow-root
"${compose[@]}" exec -T wordpress wp eval-file /integration/verify-upgrade.php --allow-root
if "${compose[@]}" exec -T wordpress sh -c "test -f wp-content/debug.log && grep -Eqi 'PHP (Warning|Notice|Deprecated|Fatal|Parse)' wp-content/debug.log"; then "${compose[@]}" exec -T wordpress tail -n 200 wp-content/debug.log; exit 1; fi
