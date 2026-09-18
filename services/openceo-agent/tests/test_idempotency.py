import asyncio
from datetime import date
from pathlib import Path

from app.models import ParsedItem, ParsedReport
from app import workflows


def test_completed_duplicate_skips_derived_pipeline(monkeypatch, tmp_path: Path):
    report = ParsedReport(
        report_type="weekly",
        source_filename="weekly.docx",
        source_hash="abc123",
        raw_text="demo",
        submitter_name="张三",
        period_start=date(2026, 9, 7),
        period_end=date(2026, 9, 13),
        items=[
            ParsedItem(
                item_type="work",
                title="测试",
                content="完成测试",
                raw_project_name="OpenCEO",
            )
        ],
    )

    monkeypatch.setattr(workflows, "parse_report", lambda *args, **kwargs: report)

    calls = []

    class FakeRpc:
        async def call(self, service, method, params=None):
            calls.append((service, method, params or {}))
            if service == "Reports" and method == "findProcessedDuplicate":
                return 42
            raise AssertionError(
                f"duplicate ingest should stop before {service}.{method}"
            )

    monkeypatch.setattr(workflows, "OpenCEORpc", FakeRpc)

    path = tmp_path / "weekly.docx"
    path.write_bytes(b"placeholder")

    result = asyncio.run(
        workflows.ingest_report(
            path,
            report_type="weekly",
            source_filename="weekly.docx",
        )
    )

    assert result["report_id"] == 42
    assert result["duplicate"] is True
    assert len(calls) == 1
    assert calls[0][0:2] == ("Reports", "findProcessedDuplicate")
