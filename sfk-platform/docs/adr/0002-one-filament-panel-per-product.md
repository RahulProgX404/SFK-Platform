# ADR 0002: One Filament panel per product; business modules as panel plugins

- Status: accepted · Date: 2026-10-03

## Context
About 80% of the ERP is forms, lists, filters and bulk actions, close to how users work in kintone. Extension products need their own UI, URL/domain and access control, while sharing user accounts.

## Decision
- Filament 5 for all back-office UI.
- The ERP is one panel (`erp`), owned by the kernel module **Erp**. Business modules contribute a `ModulePlugin` that auto-discovers their Resources/Pages/Widgets.
- Each extension module owns a separate panel whose id is its slug (`/<slug>` or its own domain).
- Shared `BasePanelProvider` (middleware, login, branding); access through `panel.<id>.access` permissions or super-admin.

## Consequences
- + Consistent UX; very fast CRUD development; per-product access control without separate user stores
- + Public/customer-facing features can still use plain Laravel routes/controllers or a separate frontend calling the module API
- − Highly custom screens (e.g. a Gantt schedule) need custom Livewire components inside Filament pages
