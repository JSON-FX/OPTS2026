# User Interface Design Goals

## Overall UX Vision

OPTS should deliver a delivery-tracking-inspired experience where users can instantly see "where things are" and "when they'll arrive." The interface prioritizes **status visibility** and **timeline clarity** over data entry complexity. Users should feel confident about transaction location, progress, and next steps without hunting through menus or reports. The system should feel like tracking a package: simple status cards, clear progression indicators, and proactive alerts when things go off-track.

The interface adopts a **progressive disclosure** approach—showing high-level summaries by default (Dashboard cards, list views) with drill-down detail views revealing timelines, audit trails, and action buttons contextually based on user role and transaction state.

## Key Interaction Paradigms

- **Dashboard-First Navigation:** Users land on a comprehensive dashboard showing at-a-glance metrics, office workload, recent activity, and stagnant items. The dashboard serves as mission control for the entire system.
- **Timeline-Centric Transaction Views:** Transaction detail pages feature prominent horizontal or vertical timeline visualizations showing completed steps (with timestamps/actors), current location (highlighted), and upcoming steps (with ETAs).
- **Contextual Actions:** Action buttons (Endorse, Receive, Complete) appear conditionally based on user role, current transaction state, and workflow position—reducing clutter and preventing invalid operations.
- **Smart Defaults with Override:** Endorsement forms default to the expected next office per workflow, with clear visual indicators when selecting an out-of-workflow target.
- **Repository-Driven Selects:** Forms use dropdowns/autocomplete populated from centralized repositories (Offices, Suppliers, Particulars, Fund Types, Action Taken) ensuring data consistency.
- **Notification-Driven Attention:** Bell icon with badge count for actionable items (received transactions, overdue items, out-of-workflow alerts) minimizing need to check lists proactively.
- **Announcement Banners:** Severity-based banners (Normal/Advisory/Emergency) for system-wide communications with persistent visibility until dismissed.

## Core Screens and Views

1. **Login Screen** — Authentication with email/password
2. **Dashboard** — Summary cards, office workload table, activity feed, stagnant transactions panel
3. **Procurement List** — Searchable/filterable list with status indicators
4. **Procurement Detail** — High-level procurement info with progressive disclosure of linked PR/PO/VCH
5. **New Procurement Form** — Create procurement with End User, Particular, Purpose, ABC, Date
6. **Transaction List** — Unified or category-filtered (PR/PO/VCH) list with search/filter
7. **Transaction Detail** — Timeline, current holder, endorsement history, audit log, action buttons
8. **New Transaction Form (PR)** — Fund Type selection, workflow assignment
9. **New Transaction Form (PO)** — Supplier selection with auto-populated address, contract price, workflow
10. **New Transaction Form (VCH)** — Payee selection, reference number entry, workflow
11. **Endorse Transaction Modal/Page** — Select Action Taken, target office (defaulted to next in workflow), notes
12. **Receive Transaction Modal/Page** — Confirm receipt with current Action Taken context
13. **Endorsement Lists** — View pending, received, and completed endorsements
14. **Admin: User Management** — CRUD users, assign office, assign role
15. **Admin: Repository Management** — CRUD for all repository entities (Offices, Suppliers, etc.)
16. **Admin: Workflow Management** — Define workflows with ordered office steps and expected_days
17. **Admin: Bypass Endorsement** — Correct misrouted transactions with reason
18. **Admin: Page Access Management** — Role-to-page permission matrix
19. **Admin: Announcement Management** — Create/edit/deactivate announcements with severity levels
20. **Notifications Panel/Page** — Bell menu dropdown or dedicated page for notification history
21. **Export Interface** — Filter selection and format choice (CSV/JSON) for data export
22. **User Profile/Settings** — Basic profile info and preferences (assumed, not in Brief)

## Accessibility: WCAG 2.1 AA

All interfaces must meet WCAG 2.1 AA standards including keyboard navigation, screen reader compatibility, sufficient color contrast, focus indicators, and semantic HTML structure.

## Branding

**ASSUMPTION:** No specific branding guidelines provided in Brief. Recommend clean, professional government/enterprise aesthetic with:
- Neutral color palette (blues/grays for primary, yellows/reds for alerts)
- Clear typography optimized for data-dense tables and forms
- Status color coding: Green (Completed), Blue (In Progress), Yellow (On Hold), Red (Cancelled/Overdue)

**Question for stakeholder:** Are there existing LGU branding guidelines, color schemes, or logo assets to incorporate?

## Target Device and Platforms: Web Responsive

Responsive web application supporting modern browsers (Chrome, Firefox, Safari, Edge) on desktop, tablet, and mobile devices. No native mobile apps in MVP scope—mobile users access via responsive web interface.

---
