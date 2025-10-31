<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Procurement;

class ProcurementBusinessRules
{
    /**
     * Check if a Purchase Request can be created for the procurement.
     * Returns true if no PR exists, false if PR already exists.
     */
    public function canCreatePR(Procurement $procurement): bool
    {
        return ! $procurement->purchaseRequest()->exists();
    }

    /**
     * Check if a Purchase Order can be created for the procurement.
     * Returns true only if PR exists AND no PO exists yet.
     */
    public function canCreatePO(Procurement $procurement): bool
    {
        return $procurement->purchaseRequest()->exists()
            && ! $procurement->purchaseOrder()->exists();
    }

    /**
     * Check if a Voucher can be created for the procurement.
     * Returns true only if PO exists AND no VCH exists yet.
     */
    public function canCreateVCH(Procurement $procurement): bool
    {
        return $procurement->purchaseOrder()->exists()
            && ! $procurement->voucher()->exists();
    }

    /**
     * Check if a Purchase Request can be deleted.
     * Returns true only if no PO exists (prevents orphaned dependencies).
     */
    public function canDeletePR(Procurement $procurement): bool
    {
        return ! $procurement->purchaseOrder()->exists();
    }

    /**
     * Check if a Purchase Order can be deleted.
     * Returns true only if no VCH exists (prevents orphaned dependencies).
     */
    public function canDeletePO(Procurement $procurement): bool
    {
        return ! $procurement->voucher()->exists();
    }
}
