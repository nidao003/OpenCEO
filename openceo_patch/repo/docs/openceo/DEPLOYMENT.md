# OpenCEO V0.1 deployment

## 1. Apply the foundation files

Use the provided patch bundle on top of the `feat/openceo-foundation` branch. The OpenCEO code is additive: `app/Domain/OpenCEO`, `services/openceo-agent`, documentation and a Docker Compose overlay.

## 2. Build the OpenCEO Leantime image + sidecar

From repository root:

```bash
docker compose \
  -f .docker/docker-compose.yml \
  -f .docker/docker-compose.openceo.yml \
  up -d --build
```

The overlay is important: it replaces the stock Leantime image with an image built from the OpenCEO working tree, while adding the Python sidecar on the same `leantime-net` network.

## 3. Install OpenCEO tables

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

All OpenCEO tables use the `openceo_*` prefix. No Leantime project-table schema change is required.

## 4. Create an API credential for the sidecar

Leantime supports bearer credentials on `/api/jsonrpc`. For example:

```bash
php bin/leantime auth:create-bearer-token --email=owner@example.com --quiet-output
```

Put the token in `services/openceo-agent/.env` as `OPENCEO_API_TOKEN`. Alternatively use a Leantime API key through `OPENCEO_API_KEY`. Use a manager/admin/owner credential because the sidecar needs to create project candidates and, after human confirmation, formal projects.

## 5. Agent configuration

```bash
cp services/openceo-agent/.env.example services/openceo-agent/.env
```

LLM is optional. For an OpenAI-compatible provider configure:

```text
OPENCEO_LLM_BASE_URL=https://provider.example/v1
OPENCEO_LLM_API_KEY=...
OPENCEO_LLM_MODEL=...
```

Without an LLM, deterministic Word extraction, project matching, basic observation and Company State still work.

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

For local development you can also run `python -m app.wecom_bot` inside `services/openceo-agent`.

The bot uses the official WebSocket SDK, downloads/decrypts `.doc`/`.docx` files, maps the external WeCom identity and submits the report into OpenCEO. Unknown people and unknown projects remain pending until a human confirms them.

## 7. CEO Desk

After login as manager/admin/owner, open:

```text
/openceo/desk
```

The first screen provides Company Profile/Memory, current company state and pending project-candidate review.
