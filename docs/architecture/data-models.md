# Data Models

## Core Domain Entities

This section defines the primary domain entities with TypeScript interfaces representing the data structures used throughout the application. These interfaces align with Eloquent models and Inertia.js page props.

### Procurement

```typescript
interface Procurement {
  id: number;
  end_user_id: number; // FK to offices table
  particular_id: number; // FK to particulars table
  purpose: string;
  abc_amount: number; // Approved Budget for Contract
  date_of_entry: string; // YYYY-MM-DD
  status: ProcurementStatus;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;

  // Relationships
  end_user?: Office;
  particular?: Particular;
  purchase_request?: PurchaseRequest;
  purchase_order?: PurchaseOrder;
  voucher?: Voucher;
  status_history?: ProcurementStatusHistory[];
}

enum ProcurementStatus {
  Created = 'Created',
  InProgress = 'In Progress',
  Completed = 'Completed',
  OnHold = 'On Hold',
  Cancelled = 'Cancelled'
}

interface ProcurementStatusHistory {
  id: number;
  procurement_id: number;
  old_status: ProcurementStatus;
  new_status: ProcurementStatus;
  reason: string | null;
  changed_by_user_id: number;
  changed_at: string;

  // Relationships
  changed_by?: User;
}
```

### Transaction (Base Interface)

Transactions follow a polymorphic pattern with PR, PO, and VCH as specific implementations.

```typescript
interface Transaction {
  id: number;
  procurement_id: number;
  category: TransactionCategory;
  reference_number: string;
  status: TransactionStatus;
  workflow_id: number;
  current_office_id: number | null;
  current_user_id: number | null;
  created_by_user_id: number;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;

  // Relationships
  procurement?: Procurement;
  workflow?: Workflow;
  current_office?: Office;
  current_user?: User;
  created_by?: User;
  actions?: TransactionAction[];
}

enum TransactionCategory {
  PR = 'PR',
  PO = 'PO',
  VCH = 'VCH'
}

enum TransactionStatus {
  Created = 'Created',
  InProgress = 'In Progress',
  Completed = 'Completed',
  OnHold = 'On Hold',
  Cancelled = 'Cancelled'
}
```

### Purchase Request (PR)

```typescript
interface PurchaseRequest extends Transaction {
  category: TransactionCategory.PR;
  fund_type_id: number;

  // Relationships
  fund_type?: FundType;
}
```

### Purchase Order (PO)

```typescript
interface PurchaseOrder extends Transaction {
  category: TransactionCategory.PO;
  supplier_id: number;
  supplier_address: string; // Auto-populated from supplier, read-only
  contract_price: number;

  // Relationships
  supplier?: Supplier;
}
```

### Voucher (VCH)

```typescript
interface Voucher extends Transaction {
  category: TransactionCategory.VCH;
  payee: string; // Free-text field
}
```

[Continue with remaining data models as per the draft...]

---

[Document continues with all remaining sections...]
