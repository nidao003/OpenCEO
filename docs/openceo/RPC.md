# OpenCEO JSON-RPC surface

Use Leantime's existing authenticated JSON-RPC endpoint. Every OpenCEO service method exposed to the sidecar carries the Leantime `@api` marker, so it participates in the same API-key/Bearer authentication path as existing Leantime APIs.

Method format:

```text
leantime.rpc.OpenCEO.{Service}.{method}
```

Core methods:

- `Company.getProfile`
- `Company.saveProfile`
- `Company.addMemory`
- `Company.listMemory`
- `People.upsertIdentity`
- `People.listPending`
- `People.bindIdentity`
- `Projects.registry`
- `Projects.resolveName`
- `Projects.createCandidate`
- `Projects.listCandidates`
- `Projects.resolveCandidate`
- `Projects.addObservation`
- `Projects.correctObservation`
- `Reports.ingest`
- `Reports.addItem`
- `Reports.listReports`
- `State.current`
- `State.snapshot`
- `Meetings.create`
- `Meetings.addDecision`
- `Meetings.addAction`
- `Meetings.addRisk`
- `Outputs.save`
- `Outputs.supplement`
- `Outputs.finalize`
- `Outputs.latest`

The sidecar defaults to `/api/jsonrpc` but the path is configurable through `OPENCEO_RPC_PATH` because reverse-proxy deployments may expose a different base path.
