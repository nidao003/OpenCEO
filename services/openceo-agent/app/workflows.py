from __future__ import annotations

import calendar
import json
import re
from datetime import date
from pathlib import Path
from typing import Any

from .fallbacks import deterministic_summary, observation_from_items
from .llm import LLMClient
from .models import ParsedReport
from .parser import parse_report
from .prompts import (
    MONTHLY_SUMMARY_SYSTEM,
    PPT_OUTLINE_SYSTEM,
    REPORT_ANALYST_SYSTEM,
    VIDEO_NARRATION_SYSTEM,
    WEEKLY_SUMMARY_SYSTEM,
)
from .rpc import OpenCEORpc


async def ingest_report(
    path: Path,
    report_type: str,
    source_filename: str | None = None,
    external_provider: str = "",
    external_user_id: str = "",
    external_display_name: str = "",
) -> dict[str, Any]:
    report = parse_report(path, report_type=report_type, source_filename=source_filename)
    rpc = OpenCEORpc()

    identity_id = 0
    user_id = 0
    if external_provider and external_user_id:
        identity = await rpc.call(
            "People",
            "upsertIdentity",
            {
                "provider": external_provider,
                "externalUserId": external_user_id,
                "displayName": external_display_name or report.submitter_name,
                "metadata": {"source": "report_upload"},
            },
        )
        identity_id = int(identity.get("id") or 0)
        user_id = int(identity.get("user_id") or 0)

    # Fast-path exact transport retries before any LLM work or derived writes.
    duplicate_id = await rpc.call(
        "Reports",
        "findProcessedDuplicate",
        {
            "reportType": report.report_type,
            "sourceHash": report.source_hash,
            "userId": user_id,
            "externalIdentityId": identity_id,
        },
    )
    duplicate_id = int(duplicate_id or 0)
    if duplicate_id:
        return {
            "report_id": duplicate_id,
            "duplicate": True,
            "parsed": report.model_dump(mode="json"),
            "structured": {"deterministic_items": [i.model_dump() for i in report.items]},
        }

    registry = await rpc.call("Projects", "registry")
    profile = await rpc.call("Company", "getProfile")
    memory = await rpc.call("Company", "listMemory", {"limit": 100})
    llm = LLMClient()

    structured: dict[str, Any] = {
        "deterministic_items": [i.model_dump() for i in report.items]
    }
    if llm.enabled:
        structured["llm"] = await llm.complete_json(
            REPORT_ANALYST_SYSTEM,
            json.dumps(
                {
                    "company_profile": profile,
                    "company_memory": memory,
                    "project_registry": registry,
                    "report": report.model_dump(mode="json"),
                },
                ensure_ascii=False,
            ),
        )

    report_id = await rpc.call(
        "Reports",
        "ingest",
        {
            "reportType": report.report_type,
            "sourceFilename": report.source_filename,
            "sourceHash": report.source_hash,
            "rawText": report.raw_text,
            "structured": structured,
            "validation": [v.model_dump() for v in report.validation],
            "reportBatchKey": _period_key(report),
            "userId": user_id,
            "externalIdentityId": identity_id,
            "periodStart": report.period_start.isoformat() if report.period_start else "",
            "periodEnd": report.period_end.isoformat() if report.period_end else "",
        },
    )
    report_id = int(report_id)

    # If a previous attempt stopped mid-pipeline, rebuild its derived rows from
    # the deterministic source report rather than accumulating duplicates.
    await rpc.call("Reports", "clearDerived", {"reportId": report_id})

    groups: dict[str, list[dict[str, Any]]] = {}

    for item in report.items:
        payload = item.model_dump()
        raw_project = item.raw_project_name.strip()

        if raw_project:
            groups.setdefault(raw_project, []).append(payload)

        match = None
        if raw_project:
            match = await rpc.call("Projects", "resolveName", {"name": raw_project})

            if match.get("status") == "unmatched":
                candidate_id = await rpc.call(
                    "Projects",
                    "createCandidate",
                    {
                        "name": raw_project,
                        "confidence": 0.65,
                        "evidence": [{"report_id": report_id, "item": payload}],
                    },
                )
                match = {"status": "candidate", "candidate_id": candidate_id}

        project_id = int(match.get("project_id") or 0) if match else 0
        candidate_id = int(match.get("candidate_id") or 0) if match else 0

        await rpc.call(
            "Reports",
            "addItem",
            {
                "reportId": report_id,
                "itemType": item.item_type,
                "title": item.title,
                "content": item.content,
                "projectId": project_id,
                "candidateId": candidate_id,
                "progress": item.progress,
                "status": item.status,
                "metadata": item.metadata | {"raw_project_name": raw_project},
            },
        )

        if project_id or candidate_id:
            await rpc.call(
                "Projects",
                "addEvent",
                {
                    "eventType": item.item_type,
                    "reportId": report_id,
                    "projectId": project_id,
                    "candidateId": candidate_id,
                    "payload": payload,
                    "sourceText": item.content or item.title,
                    "confidence": 1.0,
                },
            )

    # Add one observation per matched canonical project. Unknown projects wait
    # for human candidate resolution before becoming authoritative project state.
    for raw_project, items in groups.items():
        match = await rpc.call("Projects", "resolveName", {"name": raw_project})
        if match.get("status") != "matched":
            continue

        project_id = int(match["project_id"])
        observation = observation_from_items(raw_project, items)

        if llm.enabled and structured.get("llm"):
            for candidate in structured["llm"].get("project_observations", []):
                if candidate.get("raw_project_name") == raw_project:
                    observation.update(
                        {k: v for k, v in candidate.items() if k in observation}
                    )
                    observation["confidence"] = float(
                        candidate.get("confidence") or 0.7
                    )
                    break

        await rpc.call(
            "Projects",
            "addObservation",
            {
                "projectId": project_id,
                "reportId": report_id,
                **observation,
            },
        )

    await rpc.call("Reports", "markProcessed", {"reportId": report_id})

    return {
        "report_id": report_id,
        "duplicate": False,
        "parsed": report.model_dump(mode="json"),
        "structured": structured,
    }


async def generate_output(output_type: str, period_key: str = "") -> dict[str, Any]:
    rpc = OpenCEORpc()
    llm = LLMClient()
    state = await rpc.call("State", "current")
    profile = await rpc.call("Company", "getProfile")
    report_context: dict[str, Any] = {}

    if output_type in {"weekly_summary", "meeting_agenda"}:
        weekly_reports = await rpc.call(
            "Reports",
            "listReports",
            {
                "reportType": "weekly",
                "reportBatchKey": period_key,
                "limit": 200,
            },
        )
        report_context["weekly_reports"] = _compact_reports(weekly_reports)
    elif output_type == "monthly_summary" and re.fullmatch(
        r"\d{4}-\d{2}", period_key or ""
    ):
        year, month = map(int, period_key.split("-"))
        last_day = calendar.monthrange(year, month)[1]
        start = date(year, month, 1).isoformat()
        end = date(year, month, last_day).isoformat()

        weekly_reports = await rpc.call(
            "Reports",
            "listReports",
            {
                "reportType": "weekly",
                "limit": 500,
                "periodStart": start,
                "periodEnd": end,
            },
        )
        monthly_reports = await rpc.call(
            "Reports",
            "listReports",
            {
                "reportType": "monthly",
                "limit": 200,
                "periodStart": start,
                "periodEnd": end,
            },
        )
        report_context["weekly_reports"] = _compact_reports(weekly_reports)
        report_context["monthly_source_reports"] = _compact_reports(monthly_reports)

    title_map = {
        "weekly_summary": "OpenCEO 周度经营总结",
        "monthly_summary": "OpenCEO 月度经营总结",
        "meeting_agenda": "OpenCEO 管理会议议程",
    }

    if output_type in ("weekly_summary", "monthly_summary", "meeting_agenda"):
        system = (
            WEEKLY_SUMMARY_SYSTEM
            if output_type != "monthly_summary"
            else MONTHLY_SUMMARY_SYSTEM
        )
        if llm.enabled:
            content = await llm.complete_text(
                system,
                json.dumps(
                    {
                        "company": profile,
                        "state": state,
                        "reports": report_context,
                    },
                    ensure_ascii=False,
                ),
            )
        else:
            content = deterministic_summary(state, title_map[output_type])
    elif output_type in ("ppt_outline", "video_narration"):
        source_type = (
            "monthly_summary"
            if re.fullmatch(r"\d{4}-\d{2}", period_key or "")
            else "weekly_summary"
        )
        latest = await rpc.call(
            "Outputs",
            "latest",
            {"outputType": source_type, "periodKey": period_key},
        )
        source = latest.get("content") or deterministic_summary(
            state, title_map.get(source_type, source_type)
        )
        system = (
            PPT_OUTLINE_SYSTEM
            if output_type == "ppt_outline"
            else VIDEO_NARRATION_SYSTEM
        )

        if llm.enabled:
            content = await llm.complete_text(system, source)
        elif output_type == "ppt_outline":
            content = "# PPT 大纲\n\n" + source
        else:
            content = source.replace("# ", "").replace("## ", "")
    else:
        raise ValueError(f"Unsupported output_type: {output_type}")

    saved = await rpc.call(
        "Outputs",
        "save",
        {
            "outputType": output_type,
            "content": content,
            "periodKey": period_key,
            "status": "draft",
            "metadata": {"llm_enabled": llm.enabled, "language": "zh-CN"},
        },
    )

    return {"saved": saved, "content": content}


def _compact_reports(reports: list[dict[str, Any]] | Any) -> list[dict[str, Any]]:
    if not isinstance(reports, list):
        return []

    result: list[dict[str, Any]] = []

    for report in reports:
        if not isinstance(report, dict):
            continue

        result.append(
            {
                "id": report.get("id"),
                "user_id": report.get("user_id"),
                "source_filename": report.get("source_filename"),
                "period_start": report.get("period_start"),
                "period_end": report.get("period_end"),
                "structured": report.get("structured_json") or {},
                "validation": report.get("validation_json") or [],
            }
        )

    return result


def _period_key(report: ParsedReport) -> str:
    if report.report_type == "monthly" and report.period_end:
        return report.period_end.strftime("%Y-%m")

    if report.period_end:
        iso = report.period_end.isocalendar()
        return f"{iso.year}-W{iso.week:02d}"

    return ""
