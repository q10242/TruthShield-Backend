#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-${1:-http://127.0.0.1:18080}}"
NEWS_URL="${NEWS_URL:-https://www.cna.com.tw/news/aipl/202605060001.aspx}"

request() {
  local path="$1"
  local expected="${2:-200}"
  local url="${BASE_URL}${path}"
  local status
  status="$(curl -sS -o /tmp/truthshield-smoke.json -w '%{http_code}' "${url}")"

  if [[ "${status}" != "${expected}" ]]; then
    echo "Smoke test failed: ${url} returned ${status}, expected ${expected}" >&2
    cat /tmp/truthshield-smoke.json >&2 || true
    exit 1
  fi

  echo "OK ${status} ${path}"
}

request '/api/system/health'
request '/api/tags?locale=zh-TW'
request '/api/tags?locale=en'
request "/api/news/status?url=$(php -r 'echo rawurlencode(getenv("NEWS_URL") ?: $argv[1]);' "${NEWS_URL}")"
request '/api/news-domains'
request '/api/donations/config'
request '/api/vision-readiness'

echo "TruthShield smoke test passed for ${BASE_URL}"
