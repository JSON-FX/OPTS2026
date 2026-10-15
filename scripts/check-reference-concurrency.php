<?php

declare(strict_types=1);

// Run inside the local OPTS image with this repository mounted read-only at /work.
// Only two randomly prefixed probe tables are created; application tables are untouched.
require '/var/www/html/vendor/autoload.php';
require '/work/app/Services/ReferenceNumberService.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ReferenceNumberService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

if (! function_exists('pcntl_fork') || config('database.default') !== 'mysql') {
    throw new RuntimeException('Probe requires pcntl and the local MySQL connection.');
}
$prefix = 'opts_ref_probe_'.bin2hex(random_bytes(5)).'_';
$config = config('database.connections.mysql');
$config['prefix'] = $prefix;
config(['database.connections.reference_probe' => $config]);
$connection = DB::connection('reference_probe');
$schema = $connection->getSchemaBuilder();
$directory = sys_get_temp_dir().'/'.$prefix;
mkdir($directory, 0700);
$children = [];
$failure = null;
$check = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

try {
    $schema->create('reference_sequences', static function (Blueprint $table): void {
        $table->id();
        $table->string('category', 3);
        $table->unsignedSmallInteger('year');
        $table->unsignedInteger('last_sequence')->default(0);
        $table->timestamps();
        $table->unique(['category', 'year'], 'category_year_unique');
    });
    $schema->create('transactions', static function (Blueprint $table): void {
        $table->id();
        $table->string('category', 3);
        $table->string('reference_number', 50);
        $table->unique('reference_number', 'reference_unique');
        $table->softDeletes();
    });
    DB::purge('reference_probe');
    for ($worker = 0; $worker < 8; $worker++) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Could not start probe worker.');
        }
        if ($pid === 0) {
            try {
                while (! file_exists($directory.'/start')) {
                    usleep(1000);
                }
                DB::purge('reference_probe');
                $db = DB::connection('reference_probe');
                $service = new ReferenceNumberService($db);
                $refs = [];
                for ($index = 0; $index < 25; $index++) {
                    $refs[] = $db->transaction(static function () use ($db, $service): string {
                        $ref = $service->allocateDocumentReference('PO', '2098', '10');
                        $db->table('transactions')->insert(['category' => 'PO', 'reference_number' => $ref]);

                        return $ref;
                    }, 5);
                }
                file_put_contents($directory.'/'.$worker.'.json', json_encode($refs, JSON_THROW_ON_ERROR));
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($directory.'/'.$worker.'.error', $exception::class.': '.$exception->getMessage());
                exit(1);
            }
        }
        $children[] = $pid;
    }
    touch($directory.'/start');
    $workersOk = true;
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
        $workersOk = $workersOk && pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0;
    }
    $children = [];
    $check($workersOk, 'At least one concurrency worker failed: '.implode(' | ', array_map('file_get_contents', glob($directory.'/*.error') ?: [])));
    $refs = [];
    foreach (glob($directory.'/*.json') as $file) {
        $refs = array_merge($refs, json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR));
    }
    $check(count($refs) === 200 && count(array_unique($refs)) === 200, 'Parallel references must be unique.');
    sort($refs);
    $check($refs[0] === 'PO-2098-10-001' && end($refs) === 'PO-2098-10-200', 'First-use sequence must cover 001 through 200.');
    DB::purge('reference_probe');
    $connection = DB::connection('reference_probe');
    $service = new ReferenceNumberService($connection);
    $check($connection->table('transactions')->count() === 200, 'Every allocated record must persist.');
    $check($service->allocateDocumentReference('PO', '2098', '11') === 'PO-2098-11-201', 'Changing month must not reset the yearly sequence.');
    $check($service->allocateDocumentReference('PO', '2099', '01') === 'PO-2099-01-001', 'New year must reset.');
    $check($service->allocateDocumentReference('PR', '2098', '10', 'GF') === 'PR-GF-2098-10-001', 'Categories must be independent.');
    $service->trackManualReference('PO', 'po-2098-10-500');
    $check($service->previewSequence('PO', 2098) === '501', 'Recognized manual references must raise the counter.');
    try {
        $connection->transaction(static function () use ($service): void {
            $service->allocateDocumentReference('PO', '2098', '11');
            throw new RuntimeException('Intentional rollback');
        });
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() !== 'Intentional rollback') {
            throw $exception;
        }
    }
    $check($service->previewSequence('PO', 2098) === '501', 'A failed save must roll back the increment.');
    $service->trackManualReference('PO', 'pr-GF-2098-10-600');
    $check($service->previewSequence('PR', 2098) === '601', 'Manual cross-prefix references must track the actual prefix.');
    $connection->table('transactions')->insert(['category' => 'VCH', 'reference_number' => 'pr-GF-2098-10-650', 'deleted_at' => now()]);
    $check($service->previewSequence('PR', 2098) === '651', 'Deleted references owned by another category must remain reserved.');

    DB::purge('reference_probe');
    for ($worker = 0; $worker < 2; $worker++) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Could not start cross-prefix worker.');
        }
        if ($pid === 0) {
            try {
                while (! file_exists($directory.'/start-cross')) {
                    usleep(1000);
                }
                DB::purge('reference_probe');
                $db = DB::connection('reference_probe');
                $service = new ReferenceNumberService($db);
                for ($index = 0; $index < ($worker === 0 ? 1 : 50); $index++) {
                    $db->transaction(static function () use ($db, $service, $worker): void {
                        $ref = $worker === 0 ? 'pr-GF-2097-10-5000' : $service->allocateDocumentReference('PR', '2097', '10', 'GF');
                        if ($worker === 0) {
                            $service->trackManualReference('PO', $ref);
                        }
                        $db->table('transactions')->insert(['category' => $worker === 0 ? 'PO' : 'PR', 'reference_number' => $ref]);
                    }, 5);
                }
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents($directory.'/cross-'.$worker.'.error', $exception::class.': '.$exception->getMessage());
                exit(1);
            }
        }
        $children[] = $pid;
    }
    touch($directory.'/start-cross');
    $workersOk = true;
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
        $workersOk = $workersOk && pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0;
    }
    $children = [];
    $check($workersOk, 'Cross-prefix concurrent save failed: '.implode(' | ', array_map('file_get_contents', glob($directory.'/cross-*.error') ?: [])));
    DB::purge('reference_probe');
    $connection = DB::connection('reference_probe');
    $service = new ReferenceNumberService($connection);
    $check($connection->table('transactions')->where('reference_number', 'like', '%-2097-%')->count() === 51, 'Cross-prefix workers must save all 51 records.');
    $check((int) $service->previewSequence('PR', 2097) > 5000, 'Cross-prefix manual and automatic saves must share the same counter.');
    echo "MySQL probe passed: 8 simultaneous workers, 200 unique saved references; yearly reset, month continuity, category isolation, manual high-water mark, rollback, historical/deleted cross-prefix refs and 51 concurrent cross-prefix saves verified.\n";
} catch (Throwable $exception) {
    $failure = $exception;
} finally {
    touch($directory.'/start');
    touch($directory.'/start-cross');
    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
    }
    DB::purge('reference_probe');
    $schema = DB::connection('reference_probe')->getSchemaBuilder();
    $schema->dropIfExists('transactions');
    $schema->dropIfExists('reference_sequences');
    foreach (glob($directory.'/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($directory);
}

if ($failure !== null) {
    fwrite(STDERR, 'MySQL probe failed: '.$failure->getMessage().PHP_EOL);
    exit(1);
}
