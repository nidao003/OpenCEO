from __future__ import annotations

"""WeCom transport adapter.

The actual SDK connection bootstrap is intentionally isolated here so OpenCEO's
report workflow does not depend on WeCom. The official WeCom AI Bot Python SDK
can call `handle_downloaded_file` after it downloads/decrypts an incoming file.
"""

from pathlib import Path
from typing import Any

from .workflows import ingest_report


def infer_report_type(filename: str) -> str:
    lowered = filename.lower()
    return "monthly" if "月报" in filename or "monthly" in lowered else "weekly"


async def handle_downloaded_file(
    path: Path,
    *,
    filename: str,
    external_user_id: str,
    external_display_name: str = "",
    report_type: str | None = None,
) -> dict[str, Any]:
    resolved_report_type = report_type or infer_report_type(filename)
    if resolved_report_type not in {"weekly", "monthly"}:
        raise ValueError(f"Unsupported report type: {resolved_report_type}")

    return await ingest_report(
        path,
        report_type=resolved_report_type,
        source_filename=filename,
        external_provider="wecom",
        external_user_id=external_user_id,
        external_display_name=external_display_name,
    )
