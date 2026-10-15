<?php

declare(strict_types=1);

// Run in the local image with the repository mounted read-only at /work.
// CREATE TABLE LIKE copies structures only. No application rows are copied or modified.
require '/var/www/html/vendor/autoload.php';
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $path = '/work/app/'.str_replace('\\', '/', substr($class, 4)).'.php';
        if (is_file($path)) {
            require $path;
        }
    }
}, true, true);
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Transaction;
use App\Models\TransactionAction;
use App\Models\User;
use App\Services\EndorsementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

if (! function_exists('pcntl_fork') || config('database.default') !== 'mysql' || ! app()->environment('testing')) {
    throw new RuntimeException('Probe requires testing environment, pcntl and the local MySQL connection.');
}

$prefix = 'opts_qr_probe_'.bin2hex(random_bytes(5)).'_';
$mysql = config('database.connections.mysql');
$mysql['prefix'] = $prefix;
config([
    'database.connections.handoff_probe' => $mysql,
    'cache.default' => 'array',
    'permission.cache.store' => 'array',
    'logging.default' => 'stderr',
]);
DB::setDefaultConnection('handoff_probe');
Event::fake();
app(PermissionRegistrar::class)->forgetCachedPermissions();
// Fail closed if later service changes try to address an unprefixed table.
$connectProbe = static function () use ($prefix): \Illuminate\Database\Connection {
    DB::purge('handoff_probe');
    $connection = DB::connection('handoff_probe');
    $connection->beforeExecuting(static function (string $query) use ($prefix): void {
        preg_match_all('/\b(?:from|join|into|update|table)\s+`?([a-zA-Z0-9_]+)`?/i', $query, $matches);
        foreach ($matches[1] as $table) {
            if (! str_starts_with($table, $prefix)) {
                throw new RuntimeException('Probe refused a query outside its isolated tables.');
            }
        }
    });

    return $connection;
};
$connectProbe();

$tables = [
    'offices', 'users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions',
    'role_has_permissions', 'particulars', 'fund_types', 'action_taken', 'procurements',
    'workflows', 'workflow_steps', 'transactions', 'transaction_actions',
    'transaction_status_history', 'procurement_status_history',
];
$created = [];
$children = [];
$directory = sys_get_temp_dir().'/'.$prefix;
mkdir($directory, 0700);
$failure = null;
$check = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$expectRejected = static function (Closure $action, string $exceptionClass) use ($check): void {
    try {
        $action();
    } catch (Throwable $exception) {
        $check($exception instanceof $exceptionClass, 'Expected '.$exceptionClass.', got '.$exception::class);

        return;
    }
    throw new RuntimeException('An invalid handoff was accepted.');
};

try {
    $sourcePrefix = config('database.connections.mysql.prefix', '');
    foreach ($tables as $table) {
        $physical = $prefix.$table;
        $source = $sourcePrefix.$table;
        $check((bool) preg_match('/^[a-zA-Z0-9_]+$/', $physical.$source), 'Unsafe table identifier.');
        DB::connection('mysql')->statement('CREATE TABLE `'.$physical.'` LIKE `'.$source.'`');
        $created[] = $physical;
    }
    // The explicit list is also useful if the process is forcibly killed before finally runs.
    echo 'Created isolated structures: '.implode(', ', $created).PHP_EOL;
    $db = DB::connection();
    $timestamp = now()->toDateTimeString();
    foreach ([1 => 'Synthetic sending office', 2 => 'Synthetic receiving office', 3 => 'Synthetic final office'] as $id => $name) {
        $db->table('offices')->insert([
            'id' => $id, 'name' => $name, 'type' => 'Administrative',
            'abbreviation' => 'PROBE'.$id, 'is_active' => true, 'sso_id' => 900000 + $id,
            'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
        $db->table('users')->insert([
            'id' => $id, 'name' => 'Synthetic probe user '.$id,
            'email' => 'probe'.$id.'@example.invalid', 'password' => password_hash(bin2hex(random_bytes(20)), PASSWORD_BCRYPT),
            'office_id' => $id, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
    }
    $db->table('roles')->insert(['id' => 1, 'name' => 'Endorser', 'guard_name' => 'web']);
    foreach ([1, 2, 3] as $id) {
        $db->table('model_has_roles')->insert(['role_id' => 1, 'model_type' => User::class, 'model_id' => $id]);
    }
    $db->table('particulars')->insert(['id' => 1, 'description' => 'Synthetic probe only', 'is_active' => true]);
    $db->table('action_taken')->insert(['id' => 1, 'description' => 'Synthetic handoff', 'is_active' => true]);
    $db->table('procurements')->insert([
        'id' => 1, 'end_user_id' => 1, 'particular_id' => 1, 'purpose' => 'Isolated handoff concurrency probe',
        'abc_amount' => 1, 'date_of_entry' => now()->toDateString(), 'status' => 'In Progress',
        'created_by_user_id' => 1, 'created_at' => $timestamp, 'updated_at' => $timestamp,
    ]);

    $service = app(EndorsementService::class);
    foreach (['PR', 'PO', 'VCH'] as $category) {
        $workflowId = $db->table('workflows')->insertGetId([
            'category' => $category, 'name' => 'Synthetic '.$category.' route', 'is_active' => true,
        ]);
        $steps = [];
        foreach ([1, 2, 3] as $officeId) {
            $steps[] = $db->table('workflow_steps')->insertGetId([
                'workflow_id' => $workflowId, 'office_id' => $officeId, 'step_order' => $officeId,
                'expected_days' => 1, 'is_final_step' => $officeId === 3,
            ]);
        }
        $reference = $category.'-QR-PROBE-001';
        $transactionId = $db->table('transactions')->insertGetId([
            'procurement_id' => 1, 'category' => $category, 'reference_number' => $reference,
            'status' => 'Created', 'workflow_id' => $workflowId, 'current_office_id' => 1,
            'current_step_id' => $steps[0], 'created_by_user_id' => 1,
            'created_at' => $timestamp, 'updated_at' => $timestamp,
        ]);
        foreach (['endorse', 'receive'] as $phase) {
            if ($phase === 'receive') {
                $before = (array) $db->table('transactions')->where('id', $transactionId)->first();
                $actionCount = TransactionAction::count();
                foreach (['', 'WRONG-REFERENCE', strtolower($reference)] as $scan) {
                    $expectRejected(static fn () => $service->receive(Transaction::findOrFail($transactionId), User::findOrFail(2), $scan), ValidationException::class);
                }
                $check($before === (array) $db->table('transactions')->where('id', $transactionId)->first(), 'Invalid receipt scans modified the transaction.');
                $check(TransactionAction::count() === $actionCount, 'Invalid receipt scans wrote audit actions.');
            }
            // Every worker loads the same pre-action state before the parent releases the barrier.
            DB::disconnect('handoff_probe');
            DB::disconnect('mysql');
            $barrier = $directory.'/'.$category.'-'.$phase;
            for ($worker = 0; $worker < 8; $worker++) {
                $pid = pcntl_fork();
                if ($pid === -1) {
                    throw new RuntimeException('Could not start a handoff worker.');
                }
                if ($pid === 0) {
                    try {
                        $connectProbe();
                        $transaction = Transaction::findOrFail($transactionId);
                        $user = User::findOrFail($phase === 'endorse' ? 1 : 2);
                        touch($barrier.'-'.$worker.'.ready');
                        $deadline = microtime(true) + 20;
                        while (! is_file($barrier.'.start')) {
                            if (microtime(true) > $deadline) {
                                throw new RuntimeException('Worker barrier timed out.');
                            }
                            usleep(1000);
                        }
                        try {
                            if ($phase === 'endorse') {
                                app(EndorsementService::class)->endorse($transaction, $user, 1, 2, $reference);
                            } else {
                                app(EndorsementService::class)->receive($transaction, $user, $reference."\r\n");
                            }
                            $result = 'accepted';
                        } catch (AuthorizationException) {
                            $result = 'rejected';
                        }
                        file_put_contents($barrier.'-'.$worker.'.json', json_encode(['result' => $result], JSON_THROW_ON_ERROR));
                        exit(0);
                    } catch (Throwable $exception) {
                        file_put_contents($barrier.'-'.$worker.'.error', $exception::class.': '.$exception->getMessage());
                        exit(1);
                    }
                }
                $children[] = $pid;
            }
            $deadline = microtime(true) + 20;
            while (count(glob($barrier.'-*.ready') ?: []) < 8) {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException('Not all handoff workers reached the barrier.');
                }
                usleep(1000);
            }
            touch($barrier.'.start');
            $workersOk = true;
            foreach ($children as $pid) {
                pcntl_waitpid($pid, $status);
                $workersOk = $workersOk && pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0;
            }
            $children = [];
            $errors = array_map('file_get_contents', glob($barrier.'-*.error') ?: []);
            $check($workersOk, 'Worker failed: '.implode(' | ', $errors));
            $results = array_map(static fn (string $file): array => json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR), glob($barrier.'-*.json') ?: []);
            $check(count($results) === 8 && count(array_filter($results, static fn (array $row): bool => $row['result'] === 'accepted')) === 1, $category.' '.$phase.' must accept exactly one of eight simultaneous calls.');
            $db = $connectProbe();
            $fresh = Transaction::findOrFail($transactionId);
            $check($fresh->actions()->where('action_type', $phase)->count() === 1, 'A duplicate '.$phase.' audit action was saved.');
            $check($fresh->current_office_id === 2, 'The destination office changed unexpectedly.');
            $check($fresh->current_step_id === ($phase === 'endorse' ? $steps[0] : $steps[1]), 'The workflow must advance exactly once and only on receipt.');
            $check(($fresh->received_at !== null) === ($phase === 'receive'), 'Unexpected receipt state.');
            echo $category.' '.$phase.': 1 accepted, 7 rejected; one audit action, correct office/step.'.PHP_EOL;
        }

        // Direct service calls must validate both the current database reference and fresh policy state.
        $user = User::findOrFail(2);
        $stale = Transaction::findOrFail($transactionId);
        $db->table('transactions')->where('id', $transactionId)->update(['reference_number' => $reference.'-REVISED']);
        $before = $db->table('transactions')->where('id', $transactionId)->first();
        $actionCount = TransactionAction::count();
        $expectRejected(static fn () => $service->endorse($stale, $user, 1, 3, $reference), ValidationException::class);
        $expectRejected(static fn () => $service->endorse($stale, $user, 1, 3, 'WRONG-REFERENCE'), ValidationException::class);
        $expectRejected(static fn () => $service->endorse($stale, $user, 1, 3, ''), ValidationException::class);
        $check((array) $before === (array) $db->table('transactions')->where('id', $transactionId)->first(), 'Invalid scans modified the transaction.');
        $check(TransactionAction::count() === $actionCount, 'Invalid scans wrote audit actions.');
        $user->load('office');
        $db->table('offices')->where('id', 2)->update(['is_active' => false]);
        $expectRejected(static fn () => $service->endorse($stale, $user, 1, 3, $reference.'-REVISED'), AuthorizationException::class);
        $db->table('offices')->where('id', 2)->update(['is_active' => true]);
        $check(TransactionAction::count() === $actionCount, 'A stale SSO office relationship allowed an action.');
    }
    echo 'MySQL handoff probe passed: 48 service calls in six races of eight simultaneous workers across PR, PO and VCH; duplicates, fresh locked policy, stale/missing/mismatched scans, scanner CR/LF and one-time workflow advancement verified.'.PHP_EOL;
} catch (Throwable $exception) {
    $failure = $exception;
} finally {
    foreach (glob($directory.'/*.ready') ?: [] as $file) {
        touch(preg_replace('/-\d+\.ready$/', '.start', $file));
    }
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
    }
    DB::purge('handoff_probe');
    DB::purge('mysql');
    foreach (array_reverse($created) as $physical) {
        DB::connection('mysql')->statement('DROP TABLE `'.$physical.'`');
    }
    foreach (glob($directory.'/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($directory);
    echo 'Removed '.count($created).' explicitly named isolated tables.'.PHP_EOL;
}

if ($failure !== null) {
    fwrite(STDERR, 'MySQL handoff probe failed: '.$failure->getMessage().PHP_EOL);
    exit(1);
}
