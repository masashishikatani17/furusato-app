#!/usr/bin/env bash
set -euo pipefail

# 手元検証用スクリプト（任意）。CI と同じ apply をローカルでも再現。
# 使い方: .github/scripts/apply-unified-diff.sh path/to/patch.diff

PATCH_FILE="${1:-}"
if [[ -z "${PATCH_FILE}" || ! -f "${PATCH_FILE}" ]]; then
  echo "Usage: $0 path/to/patch.diff" >&2
  exit 1
fi

git switch sub 2>/dev/null || git switch -c sub
git fetch origin main
git reset --hard origin/main

git apply --whitespace=fix --index "${PATCH_FILE}"
git status
echo
echo "✅ Staged. You can now commit:"
echo "   git commit -m 'sub: apply local patch' && git push -u origin sub"
