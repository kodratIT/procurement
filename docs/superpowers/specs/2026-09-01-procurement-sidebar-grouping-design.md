# Procurement Sidebar Grouping Design

## Problem

The Filament Procurement panel currently exposes many resources in one ungrouped sidebar list. Resource names also mix Indonesian and English and use inconsistent singular/plural forms, which makes the available features difficult to scan.

## Decision

Use the selected **Option A: process-oriented navigation** with consistent English labels. Keep Dashboard outside the groups, then order groups according to the Procurement workflow:

1. Procurement
2. Approvals
3. Master Data
4. Umrah Operations
5. Organization & Finance
6. Settings

Use concise, descriptive English labels. Use plural nouns for list resources where that improves consistency. Preserve existing URLs, permissions, models, routes, and business behavior.

## Navigation Contract

### Dashboard

- Dashboard

### Procurement

- Requests (existing Purchase Request resource)
- Quotes (existing Quotation resource)
- Purchase Orders
- Invoices
- Distributions

### Approvals

- Approval Inbox
- Procurement Reviews

### Master Data

- Items
- Categories
- Units
- Variants
- Custom Fields
- Vendors
- Workflows
- Workflow Stages
- Workflow Versions

### Umrah Operations

- Pilgrims
- Umrah Batches
- Departure Batches
- Sample Shipments
- Assignments

### Organization & Finance

- Branches
- Offices
- Departments
- Cost Centers
- Budgets

### Settings

- Approver Mappings
- Approver Delegations
- Roles

## Architecture

- Configure the panel's `navigationGroups()` list in `AdminPanelProvider` to establish the six group order and labels.
- Set each application resource's `navigationGroup`, `navigationLabel`, and `navigationSort` properties to place it in the contract above.
- Configure the Filament Shield plugin's navigation group, label, and sort through its fluent plugin API so its built-in Role resource appears under Settings without modifying vendor code.
- Keep the existing automatic resource/page discovery and authorization checks.

## Behavior

- Filament continues to hide resources when the current user cannot view them.
- A resource's active state, URL, route name, CRUD pages, and permission checks remain unchanged.
- Sidebar ordering is deterministic within each group through explicit navigation sort values.
- Labels apply to sidebar navigation only; model labels used by forms, tables, and notifications are not changed unless required by the existing navigation API.

## Non-goals

- No new resources, pages, permissions, roles, or database changes.
- No URL or route renaming.
- No redesign of the dashboard content or sidebar styling.
- No translation system or locale switch.

## Verification

- Run a focused application check that boots the Filament panel and confirms the configured navigation groups and labels are registered.
- Run the affected PHPUnit tests if existing navigation coverage is available; otherwise add only a small test for the observable navigation contract.
- Use the local browser to confirm the sidebar shows the six groups in order, English labels, and expected links for an authenticated user with access.
