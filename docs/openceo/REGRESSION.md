# Report-parser regression

Regression source: the 16 real employee weekly-report files supplied with the original OpenCEO discovery material.

## Result

- Word files: **16**
- Successfully parsed: **16 / 16**
- Legacy `.doc`: **1 / 1**, converted with LibreOffice headless
- Distinct normalized table-header layouts: **18**
- Deterministic extracted items: **195**
- Submitter names identified: **16 / 16**
- Files with zero deterministic facts: **0**
- Remaining validation warnings: **1**

The one warning is expected source-data quality: one employee report contains an incomplete period value (`2026- 至 2026-09-`), so OpenCEO correctly reports `missing_period` rather than inventing dates.

## Covered input variation

Examples observed in the real sample include:

- `所属项目 / 事项名称 / 本周产出结果 / 当前进度 / 与计划是否一致 / 协作人员`
- `项目 / 工作事项 / 本周产出结果 / 当前进度 / 协作人员`
- `所属项目 / 事项名称 / 本周产出结果 / 进度 / 一致 / 协作 / 状态`
- issue/risk tables (`问题描述 / 疑难描述 / 建议`)
- next-week planning tables with `预估时长` or `计划完成时间`
- narrative operating reports containing execution amount, contract amount, new locations/products and similar business facts

The parser therefore does not depend on one rigid Word template.
