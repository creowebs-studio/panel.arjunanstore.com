#!/usr/bin/env bash
# Baseline: halaman ringan (tabel kecil) vs halaman berat.
set -uo pipefail
BASE="${BASE:-http://localhost:8000}"
JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT

TOKEN="$(curl -s -c "$JAR" "$BASE/login" | sed -n 's/.*name="csrf-token" content="\([^"]*\)".*/\1/p' | head -1)"
curl -s -b "$JAR" -c "$JAR" -X POST "$BASE/login" \
  --data-urlencode "_token=$TOKEN" \
  --data-urlencode "email=superadmin@arj.test" \
  --data-urlencode "password=change-me-please" -o /dev/null

for p in login orders resi komisi aturan-komisi; do
  printf '%-16s ' "/$p"
  curl -s -b "$JAR" -o /dev/null -w '%{time_total}s\n' "$BASE/$p"
done
