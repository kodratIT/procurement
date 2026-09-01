# Procurement Feature Modules Design

## Problem

The Filament admin sidebar currently exposes six navigation sections and 30 grouped feature entries, plus Dashboard outside the groups. The application already hides resources when a user's role fails the resource policy, but it has no administrator-controlled global switch for making an individual feature or an entire section unavailable.

Current sidebar inventory:

- Procurement: Requests, Quotes, Purchase Orders, Invoices, Distributions (5)
- Approvals: Approval Inbox, Procurement Reviews (2)
- Master Data: Items, Categories, Units, Variants, Custom Fields, Vendors, Workflows, Workflow Stages, Workflow Versions (9)
- Umrah Operations: Pilgrims, Umrah Batches, Departure Batches, Sample Shipments, Assignments (5)
- Organization & Finance: Branches, Offices, Departments, Cost Centers, Budgets (5)
- Settings: Approver Mappings, Approver Delegations, Roles, Activity Log (4)
- Dashboard: outside the six groups

## Decision

Add an internal **Feature Modules** manager with plugin-like enable/disable behavior. This is not dynamic PHP plugin installation: all feature code remains deployed, while the administrator controls whether a module is available at runtime.

Use a database-backed feature-state registry:

- six section flags;
- 30 feature flags;
- explicit stable keys and resource/model mappings in application code;
- persisted `enabled` state in the database;
- all existing flags initially enabled to preserve current behavior.

The manager supports both section-level and feature-level switches. A feature is effectively available only when both its own flag and its parent section flag are enabled:

```text
feature.effective = section.enabled && feature.enabled
```

A section toggle does not overwrite child feature states. Re-enabling a section restores each child's previous state.

## Navigation Contract

Add the core page **Settings → Feature Modules**. Dashboard and Feature Modules are always available. If the Settings section is disabled, its ordinary children may disappear, but the Feature Modules page remains visible so an authorized administrator can recover the configuration.

The six existing navigation groups and their current labels, URLs, sorts, resources, and permissions remain unchanged. The new manager is an additional administrative page and is not itself controlled by the feature flags.

## Data Model

Create one `feature_flags` table for state, rather than storing labels or resource metadata in mutable admin data:

- `key` — unique stable string, such as `section.master-data` or `master-data.vendors`;
- `enabled` — boolean, default `true`;
- `updated_by` — nullable user foreign key for audit attribution;
- timestamps.

The Feature Registry owns metadata:

- key;
- section key;
- navigation/resource label;
- navigation/resource class and model target;
- sort order;
- core flag.

The registry must explicitly cover every current sidebar resource, including the Filament Shield Roles resource and Activity Log resource. A focused test must fail if a managed sidebar entry has no registry mapping.

## Admin UI

Implement a Filament page under the Settings navigation group, protected by `procurement.manage-features`:

- one block per navigation section;
- section toggle in the block header;
- feature toggles nested beneath the section;
- `Aktif`, `Nonaktif`, and `Nonaktif karena section` status indicators;
- child toggles remain visible but are locked while their parent section is disabled;
- section disable confirmation includes the number of affected features;
- feature disable confirmation is required;
- each change saves immediately, invalidates feature-state cache, and records an activity entry.

Only the Admin role receives the new permission through the application seeder. The existing super-admin behavior remains available for recovery.

## Runtime Enforcement

Use one shared feature-availability service and one explicit registry instead of adding ad hoc checks to every page.

The global authorization hook resolves a model/resource target to its feature key and denies authorization when the effective feature state is disabled. This covers the current Filament resource flow:

1. Filament checks `Resource::canAccess()` while building navigation;
2. the resource delegates to its `viewAny` policy;
3. the global feature check denies disabled modules before ordinary role/scope authorization;
4. direct resource routes perform the same access check and return HTTP 403;
5. when enabled, the existing role permissions, office context, and record policies continue unchanged.

Filament omits navigation groups with no visible items, so a section with no available child resources is not rendered. Dashboard and Feature Modules are excluded from the global disable map. Super-admin access remains a recovery path.

The user-visible denial message is: `Fitur sedang dinonaktifkan administrator.`

## State and Error Handling

- Missing state rows for registered keys default to enabled and are created by the initial migration/seed path, preserving backward compatibility.
- Unknown feature keys are not exposed by the manager and cannot be toggled through the UI.
- Registry metadata is code-owned; administrators cannot change technical keys, routes, or resource classes.
- State writes use a transaction, validate the key, and invalidate the shared cache after commit.
- Toggling a section never deletes business data, permissions, records, or feature configuration.
- The manager remains accessible even when ordinary Settings children are disabled.

## Verification

Add focused tests for the observable contract:

- migration/seed defaults all six sections and 30 features to enabled;
- section-off disables every child effectively;
- child-off does not change sibling or parent state;
- section re-enable restores stored child states;
- disabled resource navigation is absent;
- disabled resource direct access returns 403;
- enabled resources still require their existing role and office permissions;
- Settings can retain Feature Modules while ordinary Settings children are disabled;
- unauthorized users cannot open Feature Modules;
- super-admin recovery remains possible;
- an empty feature section is omitted from navigation;
- activity logging records actor, target, old state, and new state.

Run the focused PHPUnit tests and the existing sidebar navigation test. Verify the actual authenticated Filament panel after implementation.

## Non-goals

- No dynamic plugin package installation, upload, or code loading.
- No changes to existing feature URLs, models, policies, roles, or business workflows beyond the global availability gate and the new feature-management permission.
- No deletion or archival of business records when a module is disabled.
- No redesign of the existing sidebar styling.
- No modification of unrelated working-tree changes.
