# Existing production workflow → OpenCEO migration

The four supplied packages contain one real weekly-report dataset (16 employee Word files) and three production AI workflows:

1. **Weekly/monthly management summary** — currently fetches raw employee reports by `reportId` and uses a large prompt containing organization/project rules.
2. **PPT outline generation** — consumes the generated management summary and produces a Markdown outline for an external PPT product.
3. **Voice/video narration generation** — consumes the generated management summary and produces narration for an external video product.

## What OpenCEO keeps

- Project attribution must follow the report's original project field.
- Preserve factual numbers, amounts, progress, owners and risk evidence.
- Weekly output focuses on key change, risk, next actions and management attention rather than a raw diary.
- Monthly output performs deduplication, trend/plateau detection, milestone/customer/financial review.
- PPT and narration remain downstream content transforms in Phase 1.

## What OpenCEO removes from prompts

The old prompts contain project names, owners, organization membership and special mappings such as multi-entity projects. Those become data:

- canonical project → Leantime project
- alternative names → `openceo_project_aliases`
- unknown names → `openceo_project_candidates`
- owner/member → Leantime user/project membership
- parent/child project → Leantime project hierarchy
- business rules/background → Company Memory

This eliminates weekly/monthly prompt drift and gives every workflow one shared source of truth.
