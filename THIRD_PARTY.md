# Third-party foundations

OpenCEO is a derivative application built on Leantime. Keep all upstream copyright and license notices.

Primary reused components in Phase 1:

| Component | Role | Upstream |
|---|---|---|
| Leantime | Strategy/project/task/user backbone | `Leantime/leantime` |
| LangGraph | Agent workflow orchestration | `langchain-ai/langgraph` |
| python-docx | DOCX parsing | `python-openxml/python-docx` |
| WeCom AI Bot Python SDK | Enterprise WeChat bot transport | `WecomTeam/wecom-aibot-python-sdk` |
| LibreOffice | Legacy DOC → DOCX conversion | The Document Foundation |

Optional integrations/design references are documented separately and are not vendored into the OpenCEO source package.

Before publishing a release, run a dependency/license audit against the exact resolved PHP, Python, container and frontend dependency set. Leantime-derived application code remains subject to Leantime's AGPL-3.0-only license terms.
