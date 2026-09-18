# PR #1 review fixes

This patch addresses the first integration review findings before merging into `develop`:

- move OpenCEO CLI commands to `app/Command/` so Leantime discovers them reliably;
- build the PHP image from official `leantime/leantime:3.9.8` and copy OpenCEO code into it;
- make active-user lookup portable with `LOWER(status) = 'a'`;
- make report ingest idempotent for repeated delivery of the same source hash;
- avoid returning internal exception text to WeCom users;
- remove the unused LangGraph dependency from V0.1;
- add a minimal PR CI workflow for PHP lint and Python tests.
