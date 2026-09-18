#!/usr/bin/env bash
set -euo pipefail

TARGET="${1:-.}"
PATCH_DIR="$(cd "$(dirname "$0")" && pwd)/repo"

if [[ ! -d "$TARGET/.git" ]]; then
  echo "Target is not a git repository: $TARGET" >&2
  exit 1
fi

cd "$TARGET"
CURRENT="$(git branch --show-current)"
if [[ "$CURRENT" != "feat/openceo-foundation" ]]; then
  if git show-ref --verify --quiet refs/heads/feat/openceo-foundation; then
    git switch feat/openceo-foundation
  else
    git switch develop
    git switch -c feat/openceo-foundation
  fi
fi

cp -R "$PATCH_DIR"/. "$TARGET"/

echo "OpenCEO foundation files copied."
echo "Review: git status"
echo "Then run: git add . && git commit -m 'feat: add OpenCEO foundation' && git push -u origin feat/openceo-foundation"
