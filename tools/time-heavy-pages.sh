#!/usr/bin/env bash
# Ukur waktu server-side halaman berat (rekap-adv & dashboard) berulang kali.
set -uo pipefail
BASE="${BASE:-http://localhost:8000}"
JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT

TOKEN="$(curl -s -c "$JAR" "$BASE/login" | sed -n 's/.*name="csrf-token" content="\([^"]*\)".*/\1/p' | head -1)"
curl -s -b "$JAR" -c "$JAR" -X POST "$BASE/login" \
  --data-urlencode "_token=$TOKEN" \
  --data-urlencode "email=superadmin@arj.test" \
  --data-urlencode "password=change-me-please" -o /dev/null

for i in 1 2 3; do
  printf 'rekap-adv: '
  curl -s -b "$JAR" -o /dev/null -w '%{time_total}s %{size_download}B\n' "$BASE/rekap-adv"
done
for i in 1 2 3; do
  printf 'dashboard #%s: ' "$i"
  curl -s -b "$JAR" -o /dev/null -w '%{time_total}s\n' "$BASE/dashboard"
done
