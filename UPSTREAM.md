# OpenCEO upstream policy

OpenCEO is developed on top of Leantime.

- Upstream repository: https://github.com/Leantime/leantime
- OpenCEO base release: Leantime v3.9.8
- License: AGPL-3.0-only for the Leantime-derived application code

## Branch policy

- `leantime-upstream`: pure mirror of Leantime upstream. No OpenCEO changes.
- `develop`: OpenCEO integration branch.
- `main`: OpenCEO stable releases.
- `feat/*`: OpenCEO feature work.
- `chore/merge-leantime-*`: temporary branches for validating upstream merges.

## Upstream updates

Do not merge `leantime-upstream` directly into `develop`. Create a dedicated
`chore/merge-leantime-vX.Y.Z` branch from `develop`, merge the selected upstream
tag or commit there, resolve conflicts, run tests, then merge by pull request.

## Modification strategy

OpenCEO minimizes changes to Leantime core. OpenCEO-specific product logic lives
under `app/Domain/OpenCEO` and the Python sidecar under `services/openceo-agent`.
Leantime remains authoritative for users, strategy, projects, tasks, goals and
other project-management entities. OpenCEO stores AI observations, report facts,
company memory and derived state in `openceo_*` tables.
