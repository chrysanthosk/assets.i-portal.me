#!/usr/bin/env bash
# Run all end-to-end smoke flows in order against the running Docker stack.
set -uo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

rc=0
for flow in money_flow documents_flow ops_flow; do
  echo "############################################"
  echo "# ${flow}"
  echo "############################################"
  bash "${DIR}/${flow}.sh" || rc=1
  echo
done

exit "$rc"
