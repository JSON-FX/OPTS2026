<?php

declare(strict_types=1);

namespace App\Services\Migration;

use Illuminate\Support\Collection;

class ReferenceChainResolver
{
    /**
     * Resolve ETTS transactions into procurement groups.
     *
     * In ETTS, both POs and VCHs link to the PR via sub_reference_id.
     * In OPTS2026, the chain is PR → PO → VCH under a single procurement.
     *
     * All reference_id and sub_reference_id values are normalized
     * (whitespace stripped) before matching, as ETTS data has inconsistencies
     * like "GF - 809" vs "GF-809".
     *
     * @param Collection $ettsTransactions All transactions from ETTS temp DB
     * @return array Array of groups: [['pr' => $pr|null, 'pos' => [...], 'vchs' => [...]], ...]
     */
    public function resolve(Collection $ettsTransactions): array
    {
        $typeMapping = config('etts_migration.process_type_mapping', []);

        // Tag each transaction with its category and normalize references
        $ettsTransactions = $ettsTransactions->map(function ($txn) use ($typeMapping) {
            $txn->category = $typeMapping[$txn->process_types_id] ?? null;
            $txn->normalized_ref = $this->normalize($txn->reference_id ?? '');
            $txn->normalized_sub_ref = $this->normalize($txn->sub_reference_id ?? '');
            return $txn;
        });

        // Separate by category
        $prs = $ettsTransactions->where('category', 'PR');
        $pos = $ettsTransactions->where('category', 'PO');
        $vchs = $ettsTransactions->where('category', 'VCH');

        // Build PR groups indexed by normalized reference_id
        $prGroups = [];
        foreach ($prs as $pr) {
            $prGroups[$pr->normalized_ref] = [
                'pr' => $pr,
                'pos' => [],
                'vchs' => [],
            ];
        }

        $linkedPoIds = [];
        $linkedVchIds = [];

        // Link POs to PRs: PO.sub_reference_id → PR.reference_id
        foreach ($pos as $po) {
            $subRef = $po->normalized_sub_ref;
            if ($subRef !== '' && isset($prGroups[$subRef])) {
                $prGroups[$subRef]['pos'][] = $po;
                $linkedPoIds[] = $po->id;
            }
        }

        // Link VCHs to PRs: VCH.sub_reference_id → PR.reference_id
        // (ETTS links VCHs directly to PRs, not to POs)
        foreach ($vchs as $vch) {
            $subRef = $vch->normalized_sub_ref;
            if ($subRef !== '' && isset($prGroups[$subRef])) {
                $prGroups[$subRef]['vchs'][] = $vch;
                $linkedVchIds[] = $vch->id;
            }
        }

        $groups = array_values($prGroups);

        // Add orphaned POs (POs whose sub_ref doesn't match any PR)
        foreach ($pos as $po) {
            if (!in_array($po->id, $linkedPoIds)) {
                $groups[] = [
                    'pr' => null,
                    'pos' => [$po],
                    'vchs' => [],
                ];
            }
        }

        // Add orphaned VCHs (VCHs whose sub_ref doesn't match any PR)
        foreach ($vchs as $vch) {
            if (!in_array($vch->id, $linkedVchIds)) {
                $groups[] = [
                    'pr' => null,
                    'pos' => [],
                    'vchs' => [$vch],
                ];
            }
        }

        return $groups;
    }

    /**
     * Normalize a reference string by stripping all whitespace.
     */
    private function normalize(string $value): string
    {
        return preg_replace('/\s+/', '', $value);
    }
}
