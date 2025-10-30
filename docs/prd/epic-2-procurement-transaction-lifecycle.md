# Epic 2: Procurement & Transaction Lifecycle

**Epic Goal:**

Build the core domain models and user interfaces for creating and managing Procurements and their three dependent transactions (Purchase Request, Purchase Order, Voucher). Implement the reference number generation service with database-level uniqueness constraints and atomic sequence management, enforce business rule dependencies (PO requires existing PR, VCH requires existing PO), and provide CRUD operations with role-based access control. This epic delivers the complete procurement lifecycle tracking capability allowing Endorsers and Administrators to create procurements, add dependent transactions sequentially, view procurement and transaction details with progressive disclosure of linked data, and track basic status transitions (Created → In Progress → Completed). By epic completion, users can manage the full procurement record structure without workflow routing (workflow engine delivered in Epic 3).

[Stories 2.1 through 2.10 continue as drafted previously...]

---
