# ADR 0004: Business rules live in a tested shared kernel, traced to SYSTEM_BRAIN

- Status: accepted · Date: 2026-10-03

## Context
Rules such as 端数区分 rounding, tax per rate, closing days (締日), billing-cycle month (月度) and invoice dates are used by several modules (orders, sales, receivables, payables). In kintone they were copy-pasted into many formulas and drifted.

## Decision
Cross-module business rules are implemented once in `modules/Core/Domain`, as framework-free classes with unit tests that use the examples and rule IDs from `SYSTEM_BRAIN/12_BUSINESS_RULES` (e.g. BR-AR-01, BR-SA-10). Module-specific rules stay in that module's `Domain/`.

## Consequences
- + One source of truth per rule; easy to verify with the business (tests read like the rulebook)
- + Rules marked "F" (fix) in SYSTEM_BRAIN are decided explicitly before implementation
- − Core must stay small and stable; only genuinely shared rules belong there
