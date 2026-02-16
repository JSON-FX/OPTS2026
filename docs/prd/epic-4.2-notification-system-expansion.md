# Epic 4.2: Notification System Expansion — Brownfield Enhancement

**Epic Goal:**

Expand the existing notification infrastructure (built in Story 3.8 for out-of-workflow alerts) to cover all notification types defined in FR31: received items awaiting action, overdue transaction alerts, transaction completion notices, and administrator announcements. Deliver a complete notification experience where users are proactively alerted about items requiring their attention.

---

## Existing System Context

- **Current relevant functionality**: Story 3.8 built the notification foundation — `notifications` table (Laravel notifications), `NotificationController` with mark-as-read/delete/bulk actions, `NotificationBell.tsx` with unread count badge and popover, `Notifications/Index.tsx` full-page listing with filters/pagination. Currently only supports `out_of_workflow` notification type.
- **Technology stack**: Laravel 12.x + React 18 + TypeScript + Inertia.js + shadcn/ui + Tailwind CSS, Laravel Notifications system
- **Integration points**: Existing `HandleInertiaRequests` middleware shares notification data, `NotificationBell` component in AuthenticatedLayout, Laravel event/notification system, `EtaCalculationService` for overdue detection

## Enhancement Details

- **What's being added**: Three new notification types (received, overdue, completion), scheduled overdue check command, admin announcement system with banners, optional notification preferences
- **How it integrates**: Extends existing Laravel Notification classes and NotificationController. New notification types follow the same pattern as `OutOfWorkflowNotification`. Announcements add a new model/controller/page. All notification types render through the existing bell + notification page infrastructure.
- **Success criteria**: Users receive timely notifications for all workflow events, administrators can broadcast announcements, and the notification bell accurately reflects all pending actionable items

---

## Stories

### Story 4.2.1: Additional Notification Types (Received, Overdue, Completion)

**As a** user,
**I want** to receive notifications when transactions are received at my office, when my transactions become overdue, and when transactions I'm tracking are completed,
**so that** I can take timely action on items requiring my attention.

**Key deliverables:**
- `TransactionReceivedNotification` — sent to the receiving office's users when a transaction is received at their office
- `TransactionOverdueNotification` — sent to current holder and administrators when a transaction exceeds its current step ETA
- `TransactionCompletedNotification` — sent to the transaction creator and relevant stakeholders when a transaction completes
- Laravel Artisan scheduled command: `opts:check-overdue` — runs daily, finds newly overdue transactions, sends notifications (idempotent — won't re-notify)
- Update `NotificationBell.tsx` and `Notifications/Index.tsx` to render new notification types with appropriate icons and labels
- Update type filter dropdown on notifications page with new types
- Unit tests for each notification class, feature test for scheduled command

**Dependencies:** Story 3.8 (notification infrastructure), Story 3.5 (receive action), Story 3.9 (ETA/overdue detection), Story 3.6 (complete action)

---

### Story 4.2.2: Admin Announcement System (FR30)

**As an** Administrator,
**I want** to create announcements with severity levels that display as banners and notifications,
**so that** I can communicate important information to all system users.

**Key deliverables:**
- `announcements` table migration: title, body, severity (Normal/Advisory/Emergency), is_active, starts_at, ends_at, created_by_user_id
- `Announcement` model with scopes (active, current)
- `AnnouncementController` — CRUD for Administrators
- Admin Announcements management page (list, create, edit, delete)
- Banner display component in AuthenticatedLayout — shows active announcements:
  - Normal: blue info banner
  - Advisory: yellow warning banner
  - Emergency: red alert banner, sticky
- `AnnouncementNotification` — sent to all users when a new announcement is published
- Dismissible banners (per-user, per-announcement, stored in localStorage or DB)
- Feature tests for CRUD and banner display

**Dependencies:** Story 3.8 (notification infrastructure), RBAC (Administrator role)

---

### Story 4.2.3: Notification Preferences

**As a** user,
**I want** to configure which notification types I receive,
**so that** I only get alerts relevant to my workflow responsibilities.

**Key deliverables:**
- `notification_preferences` table or JSON column on users table: per-type enable/disable flags
- Default preferences: all notifications enabled
- User settings page/section for notification preferences
- Notification send logic checks user preferences before dispatching
- Preference types: out_of_workflow, received, overdue, completed, announcements
- Announcements with Emergency severity bypass preferences (always delivered)
- Feature tests for preference-based notification filtering

**Dependencies:** Story 4.2.1 (all notification types exist), Story 4.2.2 (announcement type)

---

## Compatibility Requirements

- [x] Existing APIs remain unchanged — extends existing notification routes, doesn't modify them
- [x] Database schema changes are backward compatible — new `announcements` table, possible `notification_preferences` addition; no changes to existing tables
- [x] UI changes follow existing patterns — announcements page follows Admin CRUD pattern, banners use shadcn/ui Alert component
- [x] Performance impact is minimal — scheduled command runs once daily, notifications use Laravel's queue system

## Risk Mitigation

- **Primary Risk:** Overdue check command could generate excessive notifications if many transactions are overdue
- **Mitigation:** Idempotent notification logic — track last notification date per transaction to prevent duplicate alerts. Configurable notification frequency (daily max).
- **Rollback Plan:** Each notification type is independent. Remove notification class and its dispatch points. Announcements are a self-contained feature with its own migration — can be rolled back independently.

## Definition of Done

- [ ] All 3 stories completed with acceptance criteria met
- [ ] Existing out-of-workflow notifications still work correctly
- [ ] NotificationBell shows accurate counts for all notification types
- [ ] Scheduled overdue check runs correctly and is idempotent
- [ ] Announcement banners display with correct severity styling
- [ ] Notification preferences respected in delivery
- [ ] No regression in existing features
- [ ] Documentation updated
