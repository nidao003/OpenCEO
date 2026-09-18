from __future__ import annotations

import shutil
import tempfile
from pathlib import Path

from fastapi import FastAPI, File, Form, UploadFile
from pydantic import BaseModel

from .parser import parse_report
from .workflows import generate_output, ingest_report

app = FastAPI(title="OpenCEO Agent", version="0.1.0")


class OutputRequest(BaseModel):
    output_type: str
    period_key: str = ""


@app.get("/health")
def health() -> dict:
    return {"status": "ok"}


@app.post("/parse")
async def parse_word(file: UploadFile = File(...), report_type: str = Form("weekly")) -> dict:
    suffix = Path(file.filename or "report.docx").suffix or ".docx"
    with tempfile.TemporaryDirectory(prefix="openceo-upload-") as tmp:
        path = Path(tmp) / ("report" + suffix.lower())
        with path.open("wb") as out:
            shutil.copyfileobj(file.file, out)
        return parse_report(path, report_type, source_filename=file.filename).model_dump(mode="json")


@app.post("/reports/upload")
async def upload_report(
    file: UploadFile = File(...),
    report_type: str = Form("weekly"),
    provider: str = Form(""),
    external_user_id: str = Form(""),
    external_display_name: str = Form(""),
) -> dict:
    suffix = Path(file.filename or "report.docx").suffix or ".docx"
    with tempfile.TemporaryDirectory(prefix="openceo-upload-") as tmp:
        path = Path(tmp) / ("report" + suffix.lower())
        with path.open("wb") as out:
            shutil.copyfileobj(file.file, out)
        return await ingest_report(
            path,
            report_type=report_type,
            source_filename=file.filename,
            external_provider=provider,
            external_user_id=external_user_id,
            external_display_name=external_display_name,
        )


@app.post("/outputs/generate")
async def generate(req: OutputRequest) -> dict:
    return await generate_output(req.output_type, req.period_key)
