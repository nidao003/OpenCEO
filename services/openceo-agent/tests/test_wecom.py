import asyncio
from pathlib import Path

from app import wecom
from app.wecom import infer_report_type


def test_infer_monthly():
    assert infer_report_type("张三9月月报.docx") == "monthly"
    assert infer_report_type("weekly-report.docx") == "weekly"


def test_explicit_report_type_wins(monkeypatch, tmp_path: Path):
    captured = {}

    async def fake_ingest_report(path, **kwargs):
        captured.update(kwargs)
        return {"report_id": 1}

    monkeypatch.setattr(wecom, "ingest_report", fake_ingest_report)
    path = tmp_path / "looks-like-weekly.docx"
    path.write_bytes(b"dummy")

    asyncio.run(wecom.handle_downloaded_file(
        path,
        filename="looks-like-weekly.docx",
        external_user_id="u1",
        report_type="monthly",
    ))

    assert captured["report_type"] == "monthly"
