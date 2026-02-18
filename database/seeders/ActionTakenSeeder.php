<?php

namespace Database\Seeders;

use App\Models\ActionTaken;
use Illuminate\Database\Seeder;

class ActionTakenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $actions = [
            'Creation of Purchase Request',
            'Creation of Purchase Order',
            'Creation of Voucher',
            'For Obligation Request',
            'For Approval',
            'For BAC Meeting (Mode of Procurement)',
            'For Canvassing',
            'For Award',
            "For Mayor's Approval (NOA of Summary of Price Quotation)",
            'To Complete P.R',
            "For Mayor's Signature",
            'For Supplier Signature',
            'Waiting For Delivery',
            'For Attachment of Supporting Documents',
            'To Complete P.O',
            'For Journal Entry Voucher',
            'For Preparation of Check with Signature',
            "Check for Mayor's Signature",
            'For Disbursement and Completion of Voucher',
        ];

        foreach ($actions as $action) {
            ActionTaken::firstOrCreate(['description' => $action]);
        }
    }
}
