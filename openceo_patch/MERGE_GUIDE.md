# OpenCEO Foundation v0.1 — local merge guide

This bundle is an **overlay** for `nidao003/OpenCEO` based on the current `develop` line (Leantime v3.9.8 baseline). It does not include or replace the Leantime upstream source tree.

## Recommended path

From the directory containing this bundle and your local `OpenCEO` repository:

```bash
cd /path/to/OpenCEO
git switch develop
git pull --ff-only origin develop
git switch -c feat/openceo-foundation

/path/to/OpenCEO-foundation-v0.1-final/apply_to_repo.sh .

git status
git diff --stat
```

Then run the lightweight checks:

```bash
find app/Domain/OpenCEO -name '*.php' -print0 | xargs -0 -n1 php -l
cd services/openceo-agent
python -m pip install -e '.[test]'
python -m pytest -q
cd ../..
```

If everything is clean:

```bash
git add app/Domain/OpenCEO services/openceo-agent docs/openceo \
  .docker/docker-compose.openceo.yml OPENCEO.md UPSTREAM.md THIRD_PARTY.md

git commit -m "feat: add OpenCEO foundation"
git push -u origin feat/openceo-foundation
```

Or use the included one-shot helper:

```bash
/path/to/OpenCEO-foundation-v0.1-final/apply_and_push.sh /path/to/OpenCEO
```

## What is included

- `app/Domain/OpenCEO/`: Leantime-native OpenCEO domain
- `services/openceo-agent/`: FastAPI/LangGraph/Word/WeCom sidecar
- `.docker/docker-compose.openceo.yml`: OpenCEO Compose overlay
- `docs/openceo/`: architecture, deployment, RPC, legacy workflow mapping and real-data regression notes
- `OPENCEO.md`, `UPSTREAM.md`, `THIRD_PARTY.md`

## Important merge policy

- Do not commit OpenCEO changes to `leantime-upstream`.
- Keep `main` for stable OpenCEO releases.
- Merge this feature branch into `develop` through review after local deployment verification.
- Future Leantime releases should first be integrated in a dedicated `chore/merge-leantime-*` branch.

## Validation already completed on this bundle

- PHP syntax: all OpenCEO PHP files pass `php -l`
- Python: module compilation passes
- Python tests: 4 passed
- Real report regression: 16/16 Word reports parsed, including one legacy `.doc`
- Submitter recognition: 16/16
- Deterministic extracted items: 195
- Distinct normalized table-header layouts: 18
- Remaining report-quality warning: 1 incomplete source period (`2026- 至 2026-09-`), intentionally not guessed
