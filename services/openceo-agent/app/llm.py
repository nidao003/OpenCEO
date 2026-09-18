from __future__ import annotations

import json
from typing import Any
import httpx

from .config import get_settings


class LLMClient:
    def __init__(self) -> None:
        self.settings = get_settings()

    @property
    def enabled(self) -> bool:
        return bool(self.settings.openceo_llm_base_url and self.settings.openceo_llm_model)

    async def complete_json(self, system: str, user: str) -> dict[str, Any]:
        if not self.enabled:
            return {}
        url = self.settings.openceo_llm_base_url.rstrip("/") + "/chat/completions"
        headers = {"Content-Type": "application/json"}
        if self.settings.openceo_llm_api_key:
            headers["Authorization"] = f"Bearer {self.settings.openceo_llm_api_key}"
        payload = {
            "model": self.settings.openceo_llm_model,
            "messages": [
                {"role": "system", "content": system},
                {"role": "user", "content": user},
            ],
            "temperature": 0.1,
            "response_format": {"type": "json_object"},
        }
        async with httpx.AsyncClient(timeout=120) as client:
            res = await client.post(url, headers=headers, json=payload)
        res.raise_for_status()
        content = res.json()["choices"][0]["message"]["content"]
        return json.loads(content)

    async def complete_text(self, system: str, user: str) -> str:
        if not self.enabled:
            return ""
        url = self.settings.openceo_llm_base_url.rstrip("/") + "/chat/completions"
        headers = {"Content-Type": "application/json"}
        if self.settings.openceo_llm_api_key:
            headers["Authorization"] = f"Bearer {self.settings.openceo_llm_api_key}"
        payload = {
            "model": self.settings.openceo_llm_model,
            "messages": [{"role": "system", "content": system}, {"role": "user", "content": user}],
            "temperature": 0.2,
        }
        async with httpx.AsyncClient(timeout=120) as client:
            res = await client.post(url, headers=headers, json=payload)
        res.raise_for_status()
        return res.json()["choices"][0]["message"]["content"].strip()
