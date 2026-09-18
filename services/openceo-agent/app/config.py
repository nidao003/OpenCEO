from __future__ import annotations

from functools import lru_cache
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    openceo_base_url: str = "http://leantime:8080"
    openceo_rpc_path: str = "/api/jsonrpc"
    openceo_api_token: str = ""
    openceo_api_key: str = ""
    openceo_llm_base_url: str = ""
    openceo_llm_api_key: str = ""
    openceo_llm_model: str = ""
    openceo_default_timezone: str = "Asia/Shanghai"
    libreoffice_bin: str = "libreoffice"


@lru_cache
def get_settings() -> Settings:
    return Settings()
