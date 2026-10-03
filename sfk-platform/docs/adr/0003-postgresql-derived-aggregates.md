# ADR 0003: PostgreSQL, integer yen, derived aggregates

- Status: accepted · Date: 2026-10-03

## Context
The kintone audit (SYSTEM_BRAIN 17) found that most financial defects come from stored totals updated by scripts (job costs, AR/AP balances, stock), from string join keys, and from `max+1` numbering.

## Decision
- PostgreSQL 17 with real foreign keys and unique constraints.
- Money stored as integer yen; decimal maths via bcmath (`Core\Domain\Money\Yen`); rounding per partner rule.
- Aggregates computed from source transactions (queries/views); persisted only as snapshots of closed periods, always re-computable.
- Document numbers from a row-locked sequence table (`Core` `DocumentNumberGenerator`).
- Tests run on SQLite locally and on PostgreSQL in CI.

## Consequences
- + Totals cannot drift; corrections are just edits to source rows
- + Concurrency-safe numbering and period records
- − Reporting queries need care (indexes, possibly materialized views) as data grows
