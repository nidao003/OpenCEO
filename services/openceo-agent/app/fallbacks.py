from __future__ import annotations

from typing import Any


def observation_from_items(
    project_name: str, items: list[dict[str, Any]]
) -> dict[str, Any]:
    progress_values = [
        i.get("progress")
        for i in items
        if isinstance(i.get("progress"), (int, float))
    ]
    risks = [
        i.get("content", "")
        for i in items
        if i.get("item_type") == "risk" and i.get("content")
    ]
    blockers = [
        i.get("content", "")
        for i in items
        if any(
            k in (i.get("status") or "")
            for k in ("延迟", "阻塞", "暂停", "不一致")
        )
    ]
    progress = max(progress_values) if progress_values else None
    health = "yellow" if risks or blockers else "green"
    schedule = "at_risk" if blockers else "on_track"
    summary_parts = [
        i.get("content") or i.get("title")
        for i in items
        if (i.get("content") or i.get("title"))
    ]

    return {
        "progressEstimate": progress,
        "health": health,
        "scheduleState": schedule,
        "trend": "stable",
        "blockers": blockers[:10],
        "risks": risks[:10],
        "summary": "；".join(summary_parts[:6]),
        "confidence": 0.45,
    }


def deterministic_summary(state: dict[str, Any], title: str) -> str:
    lines = [f"# {title}", "", f"生成时间：{state.get('generated_at', '')}", ""]
    projects = state.get("projects") or []

    lines.append("## 项目状态")
    for item in projects:
        project = item.get("project") or {}
        obs = (
            item.get("effective_observation")
            or item.get("ai_observation")
            or {}
        )

        if not project.get("name"):
            continue

        health = obs.get("health") or "unknown"
        progress = obs.get("progress_estimate")
        suffix = (
            f"，AI观察进度 {progress}%"
            if progress not in (None, "")
            else ""
        )
        lines.append(f"- {project['name']}：{health}{suffix}")

        if obs.get("summary"):
            lines.append(f"  - {obs['summary']}")

    lines += ["", "## 待决策事项"]
    for decision in state.get("pending_decisions") or []:
        lines.append(f"- {decision.get('title', '')}")

    lines += ["", "## 风险"]
    for risk in state.get("open_risks") or []:
        lines.append(
            f"- [{risk.get('severity', 'medium')}] {risk.get('title', '')}"
        )

    lines += ["", "## 行动项"]
    for action in state.get("open_actions") or []:
        due = (
            f"（截止 {action.get('due_date')}）"
            if action.get("due_date")
            else ""
        )
        lines.append(f"- {action.get('title', '')}{due}")

    return "\n".join(lines).strip() + "\n"
