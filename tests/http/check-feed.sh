#!/usr/bin/env bash
set -Eeuo pipefail

output="${RUNNER_TEMP:-/tmp}/article-geolocation-smoke"
php -S 127.0.0.1:8765 -t . > "$output-server.log" 2>&1 &
pid=$!
trap 'kill "$pid" 2>/dev/null || true' EXIT

for attempt in $(seq 1 20); do
    if curl -fsS -D "$output-headers" -o "$output-body" \
        'http://127.0.0.1:8765/index.php?title=Especial:ArticleGeolocationFeed'; then
        break
    fi
    sleep 1
done
grep -qi '^Content-Type: application/geo+json' "$output-headers"
grep -qi '^Cache-Control: no-store' "$output-headers"
php -r '$data=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);if($data["type"]!=="FeatureCollection" || $data["features"]!==[]) exit(1);' "$output-body"
! grep -qi '<html' "$output-body"
