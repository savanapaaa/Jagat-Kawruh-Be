#!/bin/bash

# Get siswa token
SISWA_TOKEN=$(php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$siswa = \App\Models\User::where('role', 'siswa')->first();
echo \$siswa->createToken('test')->plainTextToken;
")

echo "Testing /api/pbl/{pblId}/kelompok/{kelompokId}/nilai-individu with siswa token"
echo "Token: $SISWA_TOKEN"
echo ""

# Test: Kelompok dengan submission nilai
# Dari database, cari submission dengan nilai
curl -s -X GET \
  "http://localhost:5173/api/pbl/pbl-8/kelompok/kelompok-10/nilai-individu" \
  -H "Authorization: Bearer $SISWA_TOKEN" \
  -H "Accept: application/json" | jq '.'

echo ""
echo "---"
echo ""

# Test: Kelompok lain
curl -s -X GET \
  "http://localhost:5173/api/pbl/pbl-9/kelompok/kelompok-11/nilai-individu" \
  -H "Authorization: Bearer $SISWA_TOKEN" \
  -H "Accept: application/json" | jq '.'
