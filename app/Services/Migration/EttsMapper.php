<?php

declare(strict_types=1);

namespace App\Services\Migration;

use App\Models\ActionTaken;
use App\Models\FundType;
use App\Models\Office;
use App\Models\Particular;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EttsMapper
{
    private array $officeCache = [];
    private array $userCache = [];
    private array $particularCache = [];
    private array $actionTakenCache = [];
    private array $fundTypeCache = [];
    private string $tempDatabase;

    public function __construct(string $tempDatabase)
    {
        $this->tempDatabase = $tempDatabase;
    }

    public function mapOffice(int $ettsOfficeId, string $sourceTable = 'endorsing_offices'): ?int
    {
        $cacheKey = "{$sourceTable}_{$ettsOfficeId}";

        if (isset($this->officeCache[$cacheKey])) {
            return $this->officeCache[$cacheKey];
        }

        $ettsOffice = DB::connection('etts_temp')
            ->table($sourceTable)
            ->where('id', $ettsOfficeId)
            ->first();

        if (!$ettsOffice) {
            $this->officeCache[$cacheKey] = null;
            return null;
        }

        $abbreviation = trim($ettsOffice->name ?? $ettsOffice->abbreviation ?? '');
        $mapping = config('etts_migration.office_mapping');
        $mappedAbbr = $mapping[$abbreviation] ?? null;

        if ($mappedAbbr) {
            $office = Office::where('abbreviation', $mappedAbbr)->first();
            $this->officeCache[$cacheKey] = $office?->id;
            return $this->officeCache[$cacheKey];
        }

        $this->officeCache[$cacheKey] = null;
        return null;
    }

    public function mapUser(int $ettsUserId): ?int
    {
        if (isset($this->userCache[$ettsUserId])) {
            return $this->userCache[$ettsUserId];
        }

        $ettsUser = DB::connection('etts_temp')
            ->table('users')
            ->where('id', $ettsUserId)
            ->first();

        if (!$ettsUser) {
            $this->userCache[$ettsUserId] = null;
            return null;
        }

        // Try matching by email first
        $optsUser = User::where('email', $ettsUser->email)->first();

        // If no match, create a legacy user so the actor is properly attributed
        if (!$optsUser && $ettsUser->email) {
            $officeId = isset($ettsUser->offices_id)
                ? $this->mapOffice((int) $ettsUser->offices_id, 'endorsing_offices')
                : null;

            $roleMapping = config('etts_migration.role_mapping', []);
            $ettsRole = DB::connection('etts_temp')
                ->table('roles')
                ->where('id', $ettsUser->roles_id ?? 0)
                ->value('name');
            $optsRoleName = $roleMapping[$ettsRole ?? ''] ?? 'Viewer';

            $optsUser = User::create([
                'name' => $ettsUser->name,
                'email' => $ettsUser->email,
                'password' => null,
                'office_id' => $officeId,
                'is_active' => false,
                'sso_position' => 'ETTS Legacy User',
            ]);

            $optsUser->assignRole($optsRoleName);

            Log::info('Created legacy user from ETTS migration', [
                'etts_user_id' => $ettsUserId,
                'opts_user_id' => $optsUser->id,
                'email' => $ettsUser->email,
            ]);
        }

        $this->userCache[$ettsUserId] = $optsUser?->id;
        return $this->userCache[$ettsUserId];
    }

    public function mapParticular(int $ettsPrDescriptionId): int
    {
        if (isset($this->particularCache[$ettsPrDescriptionId])) {
            return $this->particularCache[$ettsPrDescriptionId];
        }

        $ettsDesc = DB::connection('etts_temp')
            ->table('pr_descriptions')
            ->where('id', $ettsPrDescriptionId)
            ->first();

        $description = $ettsDesc->name ?? $ettsDesc->description ?? 'Unknown';

        $particular = Particular::firstOrCreate(
            ['description' => $description],
            ['is_active' => true]
        );

        $this->particularCache[$ettsPrDescriptionId] = $particular->id;
        return $particular->id;
    }

    public function mapStatus(int $ettsStatusId): string
    {
        $mapping = config('etts_migration.status_mapping');
        return $mapping[$ettsStatusId] ?? 'Created';
    }

    public function mapActionTaken(int $ettsActionTakenId): ?int
    {
        if (isset($this->actionTakenCache[$ettsActionTakenId])) {
            return $this->actionTakenCache[$ettsActionTakenId];
        }

        $ettsAction = DB::connection('etts_temp')
            ->table('action_takens')
            ->where('id', $ettsActionTakenId)
            ->first();

        if (!$ettsAction) {
            $this->actionTakenCache[$ettsActionTakenId] = null;
            return null;
        }

        $actionName = $ettsAction->name ?? $ettsAction->description ?? 'Unknown';
        $optsAction = ActionTaken::where('description', $actionName)->first();

        if (!$optsAction) {
            $optsAction = ActionTaken::create([
                'description' => $actionName,
                'is_active' => true,
            ]);
        }

        $this->actionTakenCache[$ettsActionTakenId] = $optsAction->id;
        return $optsAction->id;
    }

    public function mapFundType(string $referenceId): ?int
    {
        if (isset($this->fundTypeCache[$referenceId])) {
            return $this->fundTypeCache[$referenceId];
        }

        $prefixes = config('etts_migration.fund_type_prefixes', []);

        foreach ($prefixes as $prefix) {
            if (str_starts_with(strtoupper($referenceId), $prefix)) {
                $fundType = FundType::where('abbreviation', $prefix)->first();
                $this->fundTypeCache[$referenceId] = $fundType?->id;
                return $this->fundTypeCache[$referenceId];
            }
        }

        $this->fundTypeCache[$referenceId] = null;
        return null;
    }

    public function getSourceCounts(): array
    {
        return [
            'transactions' => DB::connection('etts_temp')->table('transactions')->count(),
            'endorsements' => DB::connection('etts_temp')->table('endorsements')->count(),
            'events' => DB::connection('etts_temp')->table('events')->count(),
            'users' => DB::connection('etts_temp')->table('users')->count(),
            'offices' => DB::connection('etts_temp')->table('endorsing_offices')->count()
                + DB::connection('etts_temp')->table('receiving_offices')->count(),
        ];
    }

    public function autoMapAll(): array
    {
        return [
            'offices' => $this->autoMapOffices(),
            'users' => $this->autoMapUsers(),
            'particulars' => $this->autoMapParticulars(),
            'action_taken' => $this->autoMapActionTaken(),
            'source_counts' => $this->getSourceCounts(),
        ];
    }

    private function autoMapOffices(): array
    {
        $results = [];

        $tablesToMap = ['endorsing_offices', 'receiving_offices', 'offices'];

        foreach ($tablesToMap as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }

            $offices = DB::connection('etts_temp')->table($table)->get();
            foreach ($offices as $office) {
                $targetId = $this->mapOffice($office->id, $table);
                $targetOffice = $targetId ? Office::find($targetId) : null;

                $results[] = [
                    'source_table' => $table,
                    'source_id' => $office->id,
                    'source_name' => $office->name ?? $office->abbreviation ?? "Office #{$office->id}",
                    'target_id' => $targetId,
                    'target_name' => $targetOffice?->name,
                    'status' => $targetId ? 'matched' : 'unmatched',
                ];
            }
        }

        return $results;
    }

    private function autoMapUsers(): array
    {
        $results = [];
        $ettsUsers = DB::connection('etts_temp')->table('users')->get();

        foreach ($ettsUsers as $ettsUser) {
            $targetId = $this->mapUser($ettsUser->id);
            $targetUser = $targetId ? User::find($targetId) : null;

            $results[] = [
                'source_id' => $ettsUser->id,
                'source_name' => $ettsUser->email ?? $ettsUser->name ?? "User #{$ettsUser->id}",
                'target_id' => $targetId,
                'target_name' => $targetUser?->name,
                'status' => $targetId ? 'matched' : 'new',
            ];
        }

        return $results;
    }

    private function autoMapParticulars(): array
    {
        $results = [];
        $ettsDescs = DB::connection('etts_temp')->table('pr_descriptions')->get();

        foreach ($ettsDescs as $desc) {
            $descName = $desc->name ?? $desc->description ?? 'Unknown';
            $existing = Particular::where('description', $descName)->first();

            $results[] = [
                'source_id' => $desc->id,
                'source_name' => $descName,
                'target_id' => $existing?->id,
                'target_name' => $existing?->description,
                'status' => $existing ? 'matched' : 'new',
            ];
        }

        return $results;
    }

    private function autoMapActionTaken(): array
    {
        $results = [];

        if (!$this->tableExists('action_takens')) {
            return $results;
        }

        $ettsActions = DB::connection('etts_temp')->table('action_takens')->get();

        foreach ($ettsActions as $action) {
            $actionName = $action->name ?? $action->description ?? 'Unknown';
            $existing = ActionTaken::where('description', $actionName)->first();

            $results[] = [
                'source_id' => $action->id,
                'source_name' => $actionName,
                'target_id' => $existing?->id,
                'target_name' => $existing?->description,
                'status' => $existing ? 'matched' : 'new',
            ];
        }

        return $results;
    }

    private function tableExists(string $table): bool
    {
        try {
            DB::connection('etts_temp')->table($table)->limit(1)->get();
            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
