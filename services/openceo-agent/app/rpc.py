from __future__ import annotations

import itertools
from typing import Any
import httpx

from .config import get_settings


class RpcError(RuntimeError):
    pass


class OpenCEORpc:
    def __init__(self) -> None:
        self.settings = get_settings()
        self._ids = itertools.count(1)

    @property
    def url(self) -> str:
        return self.settings.openceo_base_url.rstrip("/") + "/" + self.settings.openceo_rpc_path.lstrip("/")

    async def call(self, service: str, method: str, params: dict[str, Any] | None = None) -> Any:
        payload = {
            "jsonrpc": "2.0",
            "id": next(self._ids),
            "method": f"leantime.rpc.OpenCEO.{service}.{method}",
            "params": params or {},
        }
        headers = {"Content-Type": "application/json"}
        if self.settings.openceo_api_key:
            headers["x-api-key"] = self.settings.openceo_api_key
        elif self.settings.openceo_api_token:
            headers["Authorization"] = f"Bearer {self.settings.openceo_api_token}"
        async with httpx.AsyncClient(timeout=60) as client:
            response = await client.post(self.url, json=payload, headers=headers)
        response.raise_for_status()
        data = response.json()
        if "error" in data:
            raise RpcError(str(data["error"]))
        return data.get("result")
