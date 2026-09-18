from __future__ import annotations

from datetime import date
from typing import Any, Literal
from pydantic import BaseModel, Field


class ValidationIssue(BaseModel):
    code: str
    level: Literal["info", "warning", "error"] = "warning"
    message: str


class ParsedItem(BaseModel):
    item_type: str
    title: str = ""
    content: str = ""
    raw_project_name: str = ""
    progress: float | None = None
    status: str = ""
    metadata: dict[str, Any] = Field(default_factory=dict)


class ParsedReport(BaseModel):
    report_type: Literal["weekly", "monthly"]
    source_filename: str
    source_hash: str
    raw_text: str
    submitter_name: str = ""
    period_start: date | None = None
    period_end: date | None = None
    items: list[ParsedItem] = Field(default_factory=list)
    validation: list[ValidationIssue] = Field(default_factory=list)
    metadata: dict[str, Any] = Field(default_factory=dict)


class ProjectMatch(BaseModel):
    status: Literal["matched", "candidate", "unmatched"]
    project_id: int | None = None
    candidate_id: int | None = None
    match_type: str = ""
