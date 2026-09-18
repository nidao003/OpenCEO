from __future__ import annotations

import asyncio
import logging
import os
import tempfile
from pathlib import Path
from typing import Any

from .wecom import handle_downloaded_file, infer_report_type

# Lightweight in-process intent state for V0.1. It is deliberately replaceable by
# Valkey/Redis later without changing the report workflow.
_pending_report_type: dict[str, str] = {}
logger = logging.getLogger("openceo.wecom")


def _sender(frame: dict[str, Any]) -> tuple[str, str]:
    body = frame.get("body", {}) or {}
    sender = body.get("from", {}) or body.get("sender", {}) or {}
    user_id = (
        sender.get("userid")
        or sender.get("user_id")
        or body.get("from_userid")
        or body.get("userid")
        or body.get("user_id")
        or "unknown"
    )
    display_name = sender.get("name") or sender.get("display_name") or body.get("from_name") or ""
    return str(user_id), str(display_name)


def build_client():
    from aibot import WSClient, WSClientOptions, generate_req_id

    bot_id = os.getenv("WECHAT_BOT_ID", "")
    secret = os.getenv("WECHAT_BOT_SECRET", "")
    if not bot_id or not secret:
        raise RuntimeError("WECHAT_BOT_ID and WECHAT_BOT_SECRET are required")

    client = WSClient(WSClientOptions(bot_id=bot_id, secret=secret))

    @client.on("authenticated")
    def _authenticated():
        print("OpenCEO WeCom bot authenticated")

    @client.on("event.enter_chat")
    async def _welcome(frame):
        await client.reply_welcome(frame, {
            "msgtype": "text",
            "text": {"content": "您好，我是 OpenCEO。可直接上传 Word 周报，或先发送“提交月报”再上传月报文件。"},
        })

    @client.on("message.text")
    async def _text(frame):
        body = frame.get("body", {}) or {}
        content = (body.get("text", {}) or {}).get("content", "").strip()
        user_id, _ = _sender(frame)
        if "月报" in content:
            _pending_report_type[user_id] = "monthly"
            reply = "已切换为月报提交，请上传 .doc 或 .docx 文件。"
        elif "周报" in content:
            _pending_report_type[user_id] = "weekly"
            reply = "已切换为周报提交，请上传 .doc 或 .docx 文件。"
        else:
            reply = "OpenCEO 当前支持：上传周报/月报 Word；发送“提交周报”或“提交月报”可指定类型。"
        stream_id = generate_req_id("openceo")
        await client.reply_stream(frame, stream_id, reply, True)

    @client.on("message.file")
    async def _file(frame):
        body = frame.get("body", {}) or {}
        file_data = body.get("file", {}) or {}
        file_url = file_data.get("url")
        aes_key = file_data.get("aeskey")
        if not file_url:
            return

        user_id, display_name = _sender(frame)
        stream_id = generate_req_id("openceo-report")
        await client.reply_stream(frame, stream_id, "已收到文件，正在解析并更新公司状态…", False)

        try:
            buffer, sdk_filename = await client.download_file(file_url, aes_key)
            filename = sdk_filename or "report.docx"
            suffix = Path(filename).suffix.lower()
            if suffix not in {".doc", ".docx"}:
                await client.reply_stream(frame, stream_id, "目前仅支持 .doc / .docx 周报或月报。", True)
                return

            report_type = _pending_report_type.pop(user_id, None) or infer_report_type(filename)
            with tempfile.TemporaryDirectory(prefix="openceo-wecom-") as tmp:
                path = Path(tmp) / ("report" + suffix)
                path.write_bytes(buffer)
                result = await handle_downloaded_file(
                    path,
                    filename=filename,
                    external_user_id=user_id,
                    external_display_name=display_name,
                    report_type=report_type,
                )

            parsed = result.get("parsed", {})
            items = parsed.get("items") or []
            warnings = parsed.get("validation") or []
            warn_text = f"；{len(warnings)} 条数据质量提醒" if warnings else ""
            message = f"解析完成：报告 #{result.get('report_id')}，识别 {len(items)} 条事项{warn_text}。新项目将进入人工确认，不会自动写入正式项目。"
            await client.reply_stream(frame, stream_id, message, True)
        except Exception:
            logger.exception("WeCom report ingestion failed")
            await client.reply_stream(frame, stream_id, "解析失败，请稍后重试或联系管理员查看 OpenCEO 日志。", True)

    return client


def main() -> None:
    build_client().run()


if __name__ == "__main__":
    main()
