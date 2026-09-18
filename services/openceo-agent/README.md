# OpenCEO Agent Sidecar

Python sidecar for Word ingestion, deterministic fact extraction, project resolution, optional LLM analysis, management-output generation, and WeCom transport.

## Configuration

Copy `.env.example` to `.env`. At minimum configure the Leantime/OpenCEO URL and an API token. LLM configuration is optional; the service still parses and stores deterministic facts without a model.

## Run

```bash
uvicorn app.main:app --host 0.0.0.0 --port 8091
```

Legacy `.doc` support requires LibreOffice in the sidecar image/host.

## WeCom bot

Set `WECHAT_BOT_ID` and `WECHAT_BOT_SECRET`, then run:

```bash
python -m app.wecom_bot
```

The bot accepts `.doc`/`.docx` files. Users may send `提交周报` or `提交月报` immediately before uploading to explicitly select the report type.
