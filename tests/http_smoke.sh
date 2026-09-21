#!/usr/bin/env bash

set -euo pipefail

project_root="$(cd "$(dirname "$0")/.." && pwd)"
port="${HALSTEAD_TEST_PORT:-18080}"
server_log="${TMPDIR:-/tmp}/halstead-http-test.log"

php -S "127.0.0.1:${port}" -t "$project_root/public" >"$server_log" 2>&1 &
server_pid=$!
trap 'kill "$server_pid" 2>/dev/null || true' EXIT

for _ in {1..20}; do
    if curl -fsS "http://127.0.0.1:${port}/" > /tmp/halstead-get.html 2>/dev/null; then
        break
    fi
    sleep 0.1
done

grep -F 'Метрики Холстеда' /tmp/halstead-get.html >/dev/null
curl -fsS -X POST -d 'action=load_sample' "http://127.0.0.1:${port}/" > /tmp/halstead-post.html
grep -F 'Производные метрики' /tmp/halstead-post.html >/dev/null
grep -F 'η₁' /tmp/halstead-post.html >/dev/null
grep -F 'f1j' /tmp/halstead-post.html >/dev/null
curl -fsS "http://127.0.0.1:${port}/?sample=1" > /tmp/halstead-sample-get.html
grep -F 'Производные метрики' /tmp/halstead-sample-get.html >/dev/null
grep -F 'V = ' /tmp/halstead-sample-get.html >/dev/null
