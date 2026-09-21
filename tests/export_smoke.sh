#!/usr/bin/env bash

set -euo pipefail

project_root="$(cd "$(dirname "$0")/.." && pwd)"
output_file="${TMPDIR:-/tmp}/halstead-result.json"

php "$project_root/tools/export_result.php" > "$output_file"

python3 - "$output_file" <<'PY'
import json
import math
import sys

with open(sys.argv[1], encoding="utf-8") as source:
    result = json.load(source)

required = {"operators", "operands", "eta1", "eta2", "N1", "N2", "eta", "N", "V"}
assert required.issubset(result), sorted(required - set(result))
assert result["eta"] == result["eta1"] + result["eta2"]
assert result["N"] == result["N1"] + result["N2"]
assert math.isclose(result["V"], result["N"] * math.log2(result["eta"]), rel_tol=1e-12)
assert result["eta1"] > 0 and result["eta2"] > 0
PY
