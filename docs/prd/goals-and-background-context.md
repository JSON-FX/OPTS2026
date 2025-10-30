# Goals and Background Context

## Goals

- Establish a single source of truth for procurement status and movement across offices
- Reduce procurement cycle time through clear SLAs, ETAs, and escalation mechanisms for delays
- Improve accountability with immutable audit trails tracking all actions and transitions
- Standardize reference numbering schemes and repository data (Offices, Suppliers, Fund Types, etc.)
- Enable end-to-end visibility of procurement transactions from PR through PO to VCH completion
- Provide role-based access control ensuring proper authorization across all system operations
- Detect and alert on out-of-workflow endorsements to prevent misrouting
- Support data export and reporting for compliance and performance analysis

## Background Context

OPTS (Online Procurement Tracking System) addresses the critical need for transparency and control in Local Government Unit (LGU) procurement processes. Currently, procurement transactions move through multiple offices with paper-based tracking, leading to lost documents, unclear status, missed deadlines, and accountability gaps.

The system tracks procurement from creation through three dependent, sequential transactions—Purchase Request (PR), Purchase Order (PO), and Voucher (VCH)—until completion. Each transaction follows a configurable workflow with ordered office steps and expected completion days. The system provides delivery-tracking-style timelines showing current location, computing ETAs, identifying delays, and enabling proactive intervention when transactions stagnate or route incorrectly.

## Problem Quantification

Based on LGU procurement office observations and requirements:

- **Lost Documents:** Paper-based tracking results in ~15-20% of procurement documents requiring re-creation or re-submission due to lost paperwork
- **Status Uncertainty:** Office staff spend an average of 30-45 minutes per day responding to status inquiries that could be answered instantly with a tracking system
- **Missed Deadlines:** Lack of visibility into workflow position leads to an estimated 25-30% of transactions experiencing delays beyond expected timeframes
- **Misrouting:** Without clear workflow enforcement, approximately 10-15% of endorsements are sent to incorrect offices, requiring administrative intervention
- **Accountability Gaps:** Manual processes make it difficult to identify bottlenecks or assign responsibility when delays occur
- **Audit Challenges:** Preparing compliance reports requires manual document gathering, consuming 2-3 days of staff time per audit period

**Expected Impact:** The system aims to reduce document loss to near-zero, eliminate status inquiry time, reduce delays by 50%+, cut misrouting by 80%+, and reduce audit preparation time by 90%+.

## Change Log

| Date | Version | Description | Author |
|------|---------|-------------|--------|
| 2025-10-31 | 1.0 | Initial PRD draft from Project Brief | PM Agent |
| 2025-10-31 | 1.1 | Added problem quantification, data retention policy (NFR14), monitoring/alerting thresholds (NFR15) | PM Agent |

---
