from __future__ import annotations

import re
from datetime import date, datetime
from pathlib import Path

from .documents import ensure_docx, flatten_text, iter_docx_content, sha256_file
from .models import ParsedItem, ParsedReport, ValidationIssue

PROJECT_HEADERS = {"所属项目", "项目", "项目名称", "归属项目"}
TITLE_HEADERS = {"事项名称", "工作事项", "事项", "工作内容", "本周重点工作"}
RESULT_HEADERS = {"本周产出结果", "产出结果", "结果", "进展", "完成情况", "本月重点进展"}
PROGRESS_HEADERS = {"当前进度", "进度", "完成进度"}
STATUS_HEADERS = {"状态", "与计划是否一致", "一致", "计划一致性"}
NEXT_HEADERS = {"下周工作计划", "下周计划", "下月计划"}
ISSUE_HEADERS = {"问题描述", "疑难描述", "建议", "问题、疑难和建议", "风险", "卡点"}


def _norm_header(value: str) -> str:
    return re.sub(r"[\s:：()（）\[\]【】]+", "", value or "").strip()


def _find_col(headers: list[str], candidates: set[str]) -> int | None:
    normalized = [_norm_header(h) for h in headers]
    for idx, header in enumerate(normalized):
        if header in {_norm_header(c) for c in candidates}:
            return idx
    for idx, header in enumerate(normalized):
        if any(_norm_header(c) in header for c in candidates):
            return idx
    return None


def _parse_progress(text: str) -> float | None:
    if not text:
        return None
    normalized = text.strip()
    if normalized in {"已完成", "完成", "已交付", "已上线"}:
        return 100.0
    m = re.search(r"(?<!\d)(\d{1,3}(?:\.\d+)?)\s*%", text)
    if not m:
        return None
    value = float(m.group(1))
    return max(0.0, min(100.0, value))


def _parse_date(value: str) -> date | None:
    value = value.strip().replace("年", "-").replace("月", "-").replace("日", "")
    value = re.sub(r"[./]", "-", value)
    for fmt in ("%Y-%m-%d", "%Y-%m-%d "):
        try:
            return datetime.strptime(value, fmt).date()
        except ValueError:
            pass
    return None


def _extract_period(text: str) -> tuple[date | None, date | None]:
    patterns = [
        r"(?:汇报周期|周期)\s*(?:[|：:]\s*)?(20\d{2}[-/.年]\d{1,2}[-/.月]\d{1,2}日?)\s*(?:至|到|[-~—])\s*(20\d{2}[-/.年]\d{1,2}[-/.月]\d{1,2}日?)",
        r"(20\d{2}-\d{1,2}-\d{1,2})\s*(?:至|到|~|—)\s*(20\d{2}-\d{1,2}-\d{1,2})",
    ]
    for pattern in patterns:
        m = re.search(pattern, text)
        if m:
            return _parse_date(m.group(1)), _parse_date(m.group(2))
    return None, None


def _extract_submitter(text: str) -> str:
    for pattern in (
        r"填报人\s*(?:[：:]|\|)\s*([^\n|]{1,40})",
        r"汇报人\s*(?:[：:]|\|)\s*([^\n|]{1,40})",
    ):
        m = re.search(pattern, text)
        if m:
            return m.group(1).strip()
    return ""


def _table_items(table: list[list[str]]) -> list[ParsedItem]:
    if len(table) < 2:
        return []
    headers = table[0]
    pcol = _find_col(headers, PROJECT_HEADERS)
    tcol = _find_col(headers, TITLE_HEADERS)
    rcol = _find_col(headers, RESULT_HEADERS)
    prcol = _find_col(headers, PROGRESS_HEADERS)
    scol = _find_col(headers, STATUS_HEADERS)
    ncol = _find_col(headers, NEXT_HEADERS)
    issue_cols = [idx for idx, h in enumerate(headers) if _norm_header(h) in {_norm_header(c) for c in ISSUE_HEADERS}]

    if all(v is None for v in (pcol, tcol, rcol, ncol)) and not issue_cols:
        return []

    header_text = " ".join(headers)
    is_plan_table = any(k in header_text for k in ("预估时长", "计划完成时间", "下周", "下月")) and rcol is None

    result: list[ParsedItem] = []
    for row in table[1:]:
        def value(idx: int | None) -> str:
            return row[idx].strip() if idx is not None and idx < len(row) else ""

        project = value(pcol)
        title = value(tcol)
        content = value(rcol)
        status = value(scol)
        progress = _parse_progress(value(prcol) or content)
        next_plan = value(ncol)

        if is_plan_table and (project or title or content):
            result.append(ParsedItem(
                item_type="next_plan",
                title=title,
                content=content or title,
                raw_project_name=project,
                progress=progress,
                status=status,
                metadata={"headers": headers},
            ))
        elif any([title, content, progress is not None, status]):
            result.append(ParsedItem(
                item_type="work",
                title=title,
                content=content,
                raw_project_name=project,
                progress=progress,
                status=status,
                metadata={"headers": headers},
            ))
        if next_plan:
            result.append(ParsedItem(
                item_type="next_plan",
                title=title,
                content=next_plan,
                raw_project_name=project,
                metadata={"headers": headers},
            ))
        for idx in issue_cols:
            issue = value(idx)
            if issue:
                result.append(ParsedItem(
                    item_type="risk",
                    title=headers[idx] if idx < len(headers) else "风险/问题",
                    content=issue,
                    raw_project_name=project,
                    metadata={"headers": headers},
                ))
    return result


def _narrative_items(raw_text: str) -> list[ParsedItem]:
    items: list[ParsedItem] = []
    money_keywords = ("执行额", "签约额", "回款", "经营情况", "合同金额", "应收")
    state_keywords = ("上线", "关停", "入账", "交付", "新增", "开通")
    for line in [ln.strip() for ln in raw_text.splitlines() if ln.strip()]:
        if any(k in line for k in money_keywords):
            items.append(ParsedItem(item_type="metric", content=line))
        elif any(k in line for k in state_keywords) and len(line) <= 240:
            items.append(ParsedItem(item_type="update", content=line))
    return items


def parse_report(path: Path, report_type: str = "weekly", source_filename: str | None = None) -> ParsedReport:
    source_filename = source_filename or path.name
    source_hash = sha256_file(path)
    docx_path, tempdir = ensure_docx(path)
    try:
        paragraphs, tables = iter_docx_content(docx_path)
    finally:
        if tempdir is not None:
            tempdir.cleanup()

    raw_text = flatten_text(paragraphs, tables)
    period_start, period_end = _extract_period(raw_text)
    submitter = _extract_submitter(raw_text)
    items: list[ParsedItem] = []
    for table in tables:
        items.extend(_table_items(table))
    items.extend(_narrative_items(raw_text))

    # de-duplicate deterministic facts without merging semantically distinct work items
    unique: dict[tuple, ParsedItem] = {}
    for item in items:
        key = (item.item_type, item.raw_project_name, item.title, item.content, item.progress, item.status)
        unique[key] = item
    items = list(unique.values())

    validation: list[ValidationIssue] = []
    if not submitter:
        validation.append(ValidationIssue(code="missing_submitter", message="未识别到填报人/汇报人。"))
    if not period_start or not period_end:
        validation.append(ValidationIssue(code="missing_period", message="未完整识别汇报周期。"))
    elif period_start > period_end:
        validation.append(ValidationIssue(code="invalid_period", level="error", message="汇报开始日期晚于结束日期。"))
    if not items:
        validation.append(ValidationIssue(code="no_deterministic_items", level="info", message="未从表格/叙述中提取确定性事项，将依赖全文语义分析。"))

    return ParsedReport(
        report_type="monthly" if report_type == "monthly" else "weekly",
        source_filename=source_filename,
        source_hash=source_hash,
        raw_text=raw_text,
        submitter_name=submitter,
        period_start=period_start,
        period_end=period_end,
        items=items,
        validation=validation,
        metadata={"paragraph_count": len(paragraphs), "table_count": len(tables)},
    )
