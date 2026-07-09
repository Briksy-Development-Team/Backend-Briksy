<?php

namespace Tests\Feature;

use App\Models\DynamicIdSetting;
use App\Services\DynamicIdGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DynamicIdConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\DynamicIdSettingSeeder::class,
        ]);
    }

    public function test_concurrent_processes_generate_unique_sequential_ids(): void
    {
        $databasePath = storage_path('framework/testing/' . uniqid('dynamic-id-', true) . '.sqlite');
        $barrierPath = storage_path('framework/testing/' . uniqid('dynamic-id-barrier-', true) . '.lock');
        $workerPath = storage_path('framework/testing/' . uniqid('dynamic-id-worker-', true) . '.php');

        $this->ensureParentDirectory($databasePath);
        touch($databasePath);

        try {
            $this->runProcess([
                PHP_BINARY,
                'artisan',
                'migrate:fresh',
                '--force',
            ], $databasePath);

            $this->runProcess([
                PHP_BINARY,
                'artisan',
                'db:seed',
                '--class=Database\\Seeders\\DynamicIdSettingSeeder',
                '--force',
            ], $databasePath);

            file_put_contents($workerPath, $this->workerScript());
            @unlink($barrierPath);

            $outputs = [];
            $processes = [];

            for ($index = 0; $index < 2; $index++) {
                $process = new Process(
                    [PHP_BINARY, $workerPath, $barrierPath],
                    base_path(),
                    array_replace($_ENV, $_SERVER, $this->workerEnvironment($databasePath))
                );
                $process->setTimeout(30);
                $process->start();

                $processes[] = $process;
            }

            usleep(250000);
            touch($barrierPath);

            foreach ($processes as $process) {
                $process->wait();

                $this->assertTrue(
                    $process->isSuccessful(),
                    trim($process->getErrorOutput() . $process->getOutput())
                );

                $outputs[] = trim($process->getOutput());
            }

            sort($outputs);

            $this->assertSame([
                'COM-000001',
                'COM-000002',
            ], $outputs);
        } finally {
            @unlink($workerPath);
            @unlink($barrierPath);
            @unlink($databasePath);
        }
    }

    public function test_generation_rolls_back_when_the_counter_update_fails(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS abort_dynamic_id_update');
        DB::statement(<<<'SQL'
CREATE TRIGGER abort_dynamic_id_update
BEFORE UPDATE ON dynamic_id_settings
WHEN NEW.current_number = 1
BEGIN
    SELECT RAISE(ABORT, 'forced failure');
END;
SQL);

        $generator = app(DynamicIdGeneratorService::class);

        try {
            $generator->generate('organizations');
            $this->fail('Expected the dynamic ID generation to fail.');
        } catch (\Throwable $throwable) {
            $this->assertStringContainsString('forced failure', $throwable->getMessage());
        } finally {
            DB::statement('DROP TRIGGER IF EXISTS abort_dynamic_id_update');
        }

        $setting = DynamicIdSetting::query()
            ->where('entity_type', 'organizations')
            ->firstOrFail();

        $this->assertSame(0, (int) $setting->current_number);
    }

    private function runProcess(array $command, string $databasePath): void
    {
        $process = new Process(
            $command,
            base_path(),
            array_replace($_ENV, $_SERVER, $this->workerEnvironment($databasePath))
        );
        $process->setTimeout(120);
        $process->mustRun();
    }

    private function workerEnvironment(string $databasePath): array
    {
        return [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'true',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $databasePath,
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'BROADCAST_CONNECTION' => 'null',
            'PULSE_ENABLED' => 'false',
            'TELESCOPE_ENABLED' => 'false',
            'NIGHTWATCH_ENABLED' => 'false',
        ];
    }

    private function workerScript(): string
    {
        $autoload = var_export(base_path('vendor/autoload.php'), true);
        $bootstrap = var_export(base_path('bootstrap/app.php'), true);

        return sprintf(
            <<<'PHP'
<?php

require %s;

$app = require %s;
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$barrierPath = $argv[1];

while (!file_exists($barrierPath)) {
    usleep(10000);
    clearstatcache(false, $barrierPath);
}

$result = $app->make(App\Services\DynamicIdGeneratorService::class)->generate('organizations');

echo $result;
PHP,
            $autoload,
            $bootstrap
        );
    }

    private function ensureParentDirectory(string $path): void
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }
}
