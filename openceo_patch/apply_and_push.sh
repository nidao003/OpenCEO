#!/usr/bin/env bash
set -euo pipefail

TARGET="${1:-.}"
PATCH_DIR="$(cd "$(dirname "$0")" && pwd)/repo"
BRANCH="feat/openceo-foundation"

if [[ ! -d "$TARGET/.git" ]]; then
  echo "Target is not a git repository: $TARGET" >&2
  exit 1
fi

cd "$TARGET"
git fetch origin develop --quiet || true

if git show-ref --verify --quiet "refs/heads/$BRANCH"; then
  git switch "$BRANCH"
else
  git switch develop
  git pull --ff-only origin develop || true
  git switch -c "$BRANCH"
fi

cp -R "$PATCH_DIR"/. "$TARGET"/

# Lightweight checks that do not require the full container stack.
find app/Domain/OpenCEO -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
if command -v python >/dev/null 2>&1; then
  python -m compileall -q services/openceo-agent/app || true
fi

git add \
  app/Domain/OpenCEO \
  services/openceo-agent \
  docs/openceo \
  .docker/docker-compose.openceo.yml \
  OPENCEO.md UPSTREAM.md THIRD_PARTY.md

if git diff --cached --quiet; then
  echo "No OpenCEO foundation changes to commit."
else
  git commit -m "feat: add OpenCEO foundation"
fi

git push -u origin "$BRANCH"

echo
echo "OpenCEO foundation pushed to origin/$BRANCH"
echo "Next: open a PR from $BRANCH to develop."
