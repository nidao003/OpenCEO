# OpenCEO V0.1 deployment

## 1. Apply the foundation files

OpenCEO keeps product-specific PHP code under `app/Domain/OpenCEO`, the Python sidecar under `services/openceo-agent`, and language compatibility overlays under `custom/Language`.

## 2. Prepare OpenCEO agent configuration

Create the sidecar environment file **before** running Docker Compose, because the Compose overlay references it:

```bash
cp services/openceo-agent/.env.example services/openceo-agent/.env
```

OpenCEO defaults to Simplified Chinese in the Docker overlay:

```text
LEAN_LANGUAGE=zh-CN
```

You can also place `LEAN_LANGUAGE=zh-CN` in `.docker/.env`. Existing Leantime installations that already store another company language should switch the company language to **中文（简体）** in Company Settings.

LLM is optional. For an OpenAI-compatible provider configure:

```text
OPENCEO_LLM_BASE_URL=https://provider.example/v1
OPENCEO_LLM_API_KEY=...
OPENCEO_LLM_MODEL=...
```

Without an LLM, deterministic Word extraction, project matching, basic observations and Company State still work.

## 3. Build the OpenCEO Leantime image + sidecar

From repository root:

```bash
docker compose \
  -f .docker/docker-compose.yml \
  -f .docker/docker-compose.openceo.yml \
  up -d --build
```

The overlay builds an OpenCEO image on top of `leantime/leantime:3.9.8`, copies in the OpenCEO PHP domain/commands and custom language overlays, and adds the Python sidecar on the same `leantime-net` network.

## 4. Install or upgrade OpenCEO tables

```bash
docker compose \
  -f .docker/docker-compose.yml \
  -f .docker/docker-compose.openceo.yml \
  exec leantime php bin/leantime openceo:install
```

Check:

```bash
docker compose \
  -f .docker/docker-compose.yml \
  -f .docker/docker-compose.openceo.yml \
  exec leantime php bin/leantime openceo:status
```

`openceo:install` is idempotent and also applies small OpenCEO-owned schema upgrades, such as the project-level human correction overlay column.

All OpenCEO tables use the `openceo_*` prefix. No Leantime project-table schema change is required.

## 5. Create an API credential for the sidecar

Leantime supports bearer credentials on `/api/jsonrpc`. For example:

```bash
php bin/leantime auth:create-bearer-token --email=owner@example.com --quiet-output
```

Put the token in `services/openceo-agent/.env` as `OPENCEO_API_TOKEN`. Alternatively use a Leantime API key through `OPENCEO_API_KEY`.

Use a manager/admin/owner credential. OpenCEO RPC methods also enforce manager-level access internally.

## 6. WeCom

Configure the official WeCom AI Bot credentials:

```text
WECHAT_BOT_ID=...
WECHAT_BOT_SECRET=...
```

Start the optional WeCom bot service:

```bash
docker compose \
  -f .docker/docker-compose.yml \
  -f .docker/docker-compose.openceo.yml \
  --profile wecom up -d openceo-wecom
```

For local development you can also run:

```bash
cd services/openceo-agent
python -m app.wecom_bot
```

The bot uses Simplified Chinese responses, downloads/decrypts `.doc`/`.docx` files, maps the external WeCom identity and submits the report into OpenCEO. Unknown people and unknown projects remain pending until a human confirms them.

Completed duplicate reports are detected by source hash + submitter/report type before LLM analysis, so a WeCom retry does not duplicate project items/events/observations or consume a second LLM analysis.

## 7. CEO Desk

After login as manager/admin/owner, open:

```text
/openceo/desk
```

The CEO Desk uses Leantime's native i18n layer. `zh-CN` is the primary supported OpenCEO V0.1 language, with an English fallback pack for future switching.

## 8. Simplified Chinese compatibility

Leantime 3.9.8 already ships `app/Language/zh-CN.ini`, but the upstream file currently misses a set of newer English keys. OpenCEO fills those gaps in:

```text
custom/Language/zh-CN.ini
```

Leantime automatically loads `custom/Language/<locale>.ini` after the upstream language file, so this avoids modifying the upstream language source and reduces future merge conflicts.
