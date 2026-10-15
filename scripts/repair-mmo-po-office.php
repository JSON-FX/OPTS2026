<?php

/** One-time local repair. Dry run by default; back up the database before --apply. */
declare(strict_types=1);

use App\Services\SSOService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$root = is_file(dirname(__DIR__).'/vendor/autoload.php') ? dirname(__DIR__) : getcwd();
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$options = getopt('', ['apply', 'backup:']);
$apply = array_key_exists('apply', $options);
if (! app()->environment('local') || DB::getDriverName() !== 'mysql') {
    throw new RuntimeException('This repair is restricted to the local MySQL OPTS environment.');
}
$expected = [
    'procurements.end_user_id', 'transaction_actions.from_office_id', 'transaction_actions.to_office_id',
    'transactions.current_office_id', 'users.office_id', 'workflow_steps.office_id',
];
$schema = DB::select("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='offices' ORDER BY TABLE_NAME,COLUMN_NAME");
$actual = array_map(fn ($ref) => $ref->TABLE_NAME.'.'.$ref->COLUMN_NAME, $schema);
if ($actual !== $expected) {
    throw new RuntimeException('Office foreign keys changed; investigate before applying this repair.');
}
$record = app(SSOService::class)->office(54);
if ($record === null || $record['id'] !== 54 || $record['abbreviation'] !== 'MMO-PO'
    || $record['name'] !== "Municipal Mayor's Office - Purchasing Section" || ! $record['is_active']) {
    throw new RuntimeException('SSO office 54 no longer matches the reviewed identity.');
}
$report = DB::transaction(function () use ($apply, $options, $schema, $record) {
    $offices = DB::table('offices')->whereIn('id', [6, 80])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    $legacy = $offices->get(6);
    $canonical = $offices->get(80);
    if (! $legacy || ! $canonical || $legacy->sso_id !== null || (int) $canonical->sso_id !== 54) {
        throw new RuntimeException('Local office identities changed; investigate before proceeding.');
    }
    $archived = $legacy->abbreviation === 'ARCHIVED-LOCAL-6' && $legacy->deleted_at !== null && ! $legacy->is_active;
    if (! $archived && ($legacy->abbreviation !== 'MMO-PO' || $legacy->name !== 'MMO - Procurement Office')) {
        throw new RuntimeException('Legacy office no longer matches the reviewed identity.');
    }
    if ($canonical->name !== $record['name'] || ! in_array($canonical->abbreviation, ['MMO-PS-PURCH', 'MMO-PO'], true)) {
        throw new RuntimeException('Canonical local office no longer matches the reviewed identity.');
    }
    $references = [];
    foreach ($schema as $ref) {
        $references[$ref->TABLE_NAME][$ref->COLUMN_NAME] = DB::table($ref->TABLE_NAME)
            ->where($ref->COLUMN_NAME, 6)->orderBy('id')->lockForUpdate()->pluck('id')->all();
    }
    $counts = [];
    foreach ($references as $table => $columns) {
        foreach ($columns as $column => $ids) {
            $counts[$table.'.'.$column] = count($ids);
        }
    }
    if ($archived && array_sum($counts) === 0 && $canonical->abbreviation === $record['abbreviation']
        && $canonical->name === $record['name'] && $canonical->is_active && $canonical->deleted_at === null) {
        return ['status' => 'already_applied', 'reference_counts' => $counts];
    }
    if ($counts !== [
        'procurements.end_user_id' => 1, 'transaction_actions.from_office_id' => 4,
        'transaction_actions.to_office_id' => 4, 'transactions.current_office_id' => 0,
        'users.office_id' => 2, 'workflow_steps.office_id' => 2,
    ]) {
        throw new RuntimeException('Impacted reference counts changed; review a new snapshot before applying.');
    }
    if (DB::table('offices')->whereNotIn('id', [6, 80])->where(fn ($query) => $query
        ->where('abbreviation', $record['abbreviation'])->orWhere('name', $record['name'])
        ->orWhere('abbreviation', 'ARCHIVED-LOCAL-6')->orWhere('name', 'Archived local office #6 (replaced by SSO #54)'))->exists()) {
        throw new RuntimeException('Another office conflicts with the reviewed repair labels.');
    }
    if (! $apply) {
        return ['status' => 'dry_run', 'legacy_id' => 6, 'canonical_id' => 80, 'sso_id' => 54, 'reference_counts' => $counts];
    }
    $backupPath = $options['backup'] ?? '';
    if (! is_string($backupPath) || ! str_starts_with($backupPath, '/') || ! is_dir(dirname($backupPath))) {
        throw new RuntimeException('Provide --backup=/absolute/private/path.json in an existing private directory.');
    }
    $handle = fopen($backupPath, 'x');
    if ($handle === false || ! chmod($backupPath, 0600)) {
        throw new RuntimeException('Cannot create a new private repair backup.');
    }
    $backup = json_encode([
        'operation' => 'mmo-po-local-6-to-80', 'created_at' => now()->toISOString(),
        'offices' => $offices->values()->all(), 'references' => $references,
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    if (fwrite($handle, $backup) !== strlen($backup) || ! fflush($handle) || (function_exists('fsync') && ! fsync($handle))) {
        fclose($handle);
        throw new RuntimeException('Repair backup could not be persisted; no database updates applied.');
    }
    fclose($handle);
    foreach ($references as $table => $columns) {
        foreach ($columns as $column => $ids) {
            $changed = DB::table($table)->whereIn('id', $ids)->where($column, 6)->update([$column => 80]);
            if ($changed !== count($ids)) {
                throw new RuntimeException('Reference counts changed; transaction rolled back.');
            }
        }
    }
    // Free the historical abbreviation even for SSO's withTrashed conflict scan.
    DB::table('offices')->where('id', 6)->update([
        'name' => 'Archived local office #6 (replaced by SSO #54)', 'abbreviation' => 'ARCHIVED-LOCAL-6',
        'is_active' => false, 'deleted_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('offices')->where('id', 80)->update([
        'name' => $record['name'], 'abbreviation' => $record['abbreviation'],
        'is_active' => true, 'deleted_at' => null, 'sso_synced_at' => now(), 'updated_at' => now(),
    ]);
    foreach ($schema as $ref) {
        if (DB::table($ref->TABLE_NAME)->where($ref->COLUMN_NAME, 6)->exists()) {
            throw new RuntimeException('Legacy references remain; transaction rolled back.');
        }
    }

    return ['status' => 'applied', 'reference_counts' => $counts, 'backup' => $backupPath];
});
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
