# Roadmap

Source of requirements: `../SYSTEM_BRAIN` (processes BP-xx, rules BR-xx, defects D-/C-xx).
Each phase runs in parallel with kintone until reconciled.

## Phase 0: Foundation ✅ (done)
- Modular monolith, module system, generators, ERP panel shell, roles/panel permissions
- Core kernel: `RoundingMode`/`Yen` (BR-TX-01/02), `TaxRate` (BR-TX-03..07), `ClosingDay`/`BillingCycle` (BR-AR-01/02, BR-SA-10), `YearMonth`, `DocumentNumberGenerator` (BR-OR-01, BR-PU-01, BR-SA-14)
- Docker dev environment, CI (Pint, PHPStan L6, Pest on SQLite + PostgreSQL)

## Before Phase 1: decisions & inputs (from AUDIT_REPORT §8/§10)
- Export permissions, plugin settings, PrintCreator layouts, シライシ apps, fresh templates
- Decide every rule marked **F** in `12_BUSINESS_RULES` (profit definition, 8% purchase tax, legacy daily-report handling, …)

## Phase 1: Masters & money path (highest risk reduction)
| Module (planned slug) | Scope | Brain refs |
|---|---|---|
| `master-data` | Partners (roles, terms, routing), employees, machines, consumables | 01, 02, 03, 19 · BR-MD-* |
| `orders` | Jobs/注番, estimate calculation, work-instruction PDF | 04 · BP-03 · BR-OR-* |
| `sales` | Sales lines, validations, delivery notes, invoices (+ 随時) | 06, 10, 11 · BP-06 · BR-SA-* |
| `receivables` | Receipts, AR by calendar month and by billing cycle, month opening | 21, 23, 23-2 · BP-07/08 · BR-AR-* |

## Phase 2: Purchasing & payables
`purchasing` (05 incl. CSV import, 15, 20 with approval), `payables` (07, 23買掛) · BP-05 · BR-PU-*

## Phase 3: Production
`production` (12 schedule, 08N daily reports, 14 incidents, stock movements) · BP-04 · BR-PR-*

## Phase 4: HR, analytics, extensions
`hr` (16, 17, 18) · `analytics` (22, 25, dashboards) · extension products (Sns, SeoAnalyzer, …)

## Phase 5: Migration & cut-over
kintone data import (keep legacy record numbers), sequence continuation (`ensureNextAtLeast`), reconciliation reports (stored kintone totals vs derived totals), parallel run, cut-over.
