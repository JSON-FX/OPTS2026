# Epic 3: Workflow Engine & Endorsement System

**Epic Goal:**

Implement the workflow management engine enabling administrators to define category-specific transaction workflows as ordered sequences of office steps with expected completion days per step. Build the complete endorsement system supporting three core actions: Endorse (move transaction to next office), Receive (accept transaction at current office), and Complete (mark transaction finished at final step). Implement transaction state machine with automatic status transitions, current office/user tracking, out-of-workflow detection for misrouted endorsements with notifications to administrators and expected recipients, ETA and delay calculations based on workflow SLAs, and visual timeline representations showing completed steps, current location, and upcoming steps with ETAs. This epic delivers the core "delivery tracking" experience where users see exactly where transactions are, when they're expected to arrive, and receive alerts when routing deviates from approved workflows. By epic completion, the system provides full workflow-driven transaction routing with SLA monitoring and proactive misroute prevention.

[Stories 3.1 through 3.11 continue as drafted previously...]

---
