# OpenCEO V0.1 architecture

OpenCEO is a management-intelligence layer built on Leantime v3.9.8.

## Core boundary

Leantime remains authoritative for users, projects, tasks, goals and project-management state. OpenCEO stores company memory, imported employee reports, project aliases/candidates, project events, AI observations, human corrections, company snapshots, meeting decisions/actions/risks and generated management outputs in independent `openceo_*` tables.

This keeps upstream merges tractable and enforces an explicit distinction:

- **Manual truth**: Leantime project/user/business entities and human-confirmed OpenCEO data.
- **AI observed state**: derived observations with confidence and evidence.

AI observations never silently overwrite authoritative project fields.

## Main flow

```text
Company profile + memory
        ↓
WeCom / manual Word upload
        ↓
Document parse + validation
        ↓
Structured report facts
        ↓
Project alias resolver
   ┌────┴────┐
 matched   unknown
   │          ↓
   │      candidate
   │          ↓
   │    human create/link/ignore
   │          │
   └──────────┘
        ↓
Project events + observations
        ↓
Company State
        ↓
Weekly/monthly management output
        ↓
Meeting → Decision / Action / Risk
        ↓
Next-period follow-up
```

## Phase 1 output boundary

OpenCEO generates content, not rendered media:

- weekly management summary
- monthly management summary
- meeting agenda/material
- PPT outline prompt
- video narration script

PPTX/MP4 rendering is Phase 2 and remains a provider integration.

## AI sidecar

`services/openceo-agent` is a Python service because the desired AI ecosystem (LangGraph, python-docx, WeCom bot SDK) is Python-first. It talks to Leantime/OpenCEO over authenticated JSON-RPC and can be replaced independently from the PHP application.
