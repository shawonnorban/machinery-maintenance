<?php

declare(strict_types=1);

namespace App\Modules\Settings\Services;

use App\Modules\Identity\Models\MailService;
use App\Modules\Settings\Models\CompanyBackup;
use App\Modules\Settings\Models\ScheduledJobRun;
use App\Modules\Settings\Models\SmsGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Settings → System Health Monitor (AnnotechERP 03/04): one card per moving
 * part, each OK, WARNING or DOWN with a short reading.
 *
 * Signed-in and permission-checked, unlike /health — and still silent about
 * hosts, credentials and anything in another company's jobs: queue failures
 * are a count, never a payload.
 */
class SystemHealth
{
    /** A scheduler that ticks every minute and has not been heard from in this long has stopped. */
    private const SCHEDULER_SILENT_MINUTES = 10;

    private const BACKUP_STALE_DAYS = 7;

    private const STORAGE_LOW_PERCENT = 10;

    /**
     * @return list<array{key: string, status: string, value: string, detail: string|null}>
     */
    public function cards(): array
    {
        return [
            $this->database(),
            $this->cache(),
            $this->queue(),
            $this->storage(),
            $this->scheduler(),
            $this->mail(),
            $this->sms(),
            $this->backup(),
            $this->app(),
        ];
    }

    private function database(): array
    {
        return $this->probe('database', function (): array {
            $start = microtime(true);
            DB::select('select 1');
            $ms = (int) round((microtime(true) - $start) * 1000);

            return [$ms > 500 ? 'WARNING' : 'OK', "{$ms} ms", DB::connection()->getDriverName()];
        });
    }

    private function cache(): array
    {
        return $this->probe('cache', function (): array {
            $key = 'health:settings:'.bin2hex(random_bytes(4));
            Cache::put($key, '1', 10);
            $ok = Cache::get($key) === '1';
            Cache::forget($key);

            return [$ok ? 'OK' : 'DOWN', $ok ? __('settings.system.health.working') : __('settings.system.health.not_working'), (string) config('cache.default')];
        });
    }

    private function queue(): array
    {
        return $this->probe('queue', function (): array {
            $pending = Queue::size();
            $failed = DB::table('failed_jobs')->where('failed_at', '>=', CarbonImmutable::now()->subDay())->count();
            $status = $failed > 0 ? 'WARNING' : 'OK';

            return [$status, __('settings.system.health.queue_value', ['pending' => $pending, 'failed' => $failed]), (string) config('queue.default')];
        });
    }

    private function storage(): array
    {
        return $this->probe('storage', function (): array {
            $path = storage_path('app');
            $free = @disk_free_space($path);
            $total = @disk_total_space($path);
            if (! $free || ! $total) {
                return ['WARNING', '—', null];
            }
            $percent = (int) floor($free / $total * 100);

            return [$percent < self::STORAGE_LOW_PERCENT ? 'WARNING' : 'OK', __('settings.system.health.free', ['percent' => $percent]), $this->bytes((int) $free).' / '.$this->bytes((int) $total)];
        });
    }

    private function scheduler(): array
    {
        return $this->probe('scheduler', function (): array {
            $last = ScheduledJobRun::query()->max('started_at');
            if ($last === null) {
                return ['DOWN', __('settings.system.health.never'), null];
            }
            $at = CarbonImmutable::parse($last);
            $silent = $at->lt(CarbonImmutable::now()->subMinutes(self::SCHEDULER_SILENT_MINUTES));

            return [$silent ? 'DOWN' : 'OK', $at->toIso8601String(), null];
        });
    }

    private function mail(): array
    {
        return $this->probe('mail', function (): array {
            $default = MailService::query()->where('active', true)->orderByDesc('is_default')->first();

            return $default === null
                ? ['WARNING', __('settings.system.health.not_configured'), null]
                : ['OK', $default->service_name, $default->is_default ? __('settings.system.health.default') : null];
        });
    }

    private function sms(): array
    {
        return $this->probe('sms', function (): array {
            $gateway = SmsGateway::query()->where('active', true)->where('is_default', true)->first();
            if ($gateway === null) {
                return ['WARNING', __('settings.system.health.not_configured'), null];
            }
            // Whether the variable is set, never what it holds.
            $set = getenv($gateway->api_key_env) !== false || isset($_ENV[$gateway->api_key_env]) || isset($_SERVER[$gateway->api_key_env]);

            return [$set ? 'OK' : 'WARNING', $gateway->name, $set ? null : __('settings.hub.sms.env_missing', ['name' => $gateway->api_key_env])];
        });
    }

    private function backup(): array
    {
        return $this->probe('backup', function (): array {
            $last = CompanyBackup::query()->where('status', 'READY')->max('finished_at');
            if ($last === null) {
                return ['WARNING', __('settings.system.health.never'), null];
            }
            $at = CarbonImmutable::parse($last);

            return [$at->lt(CarbonImmutable::now()->subDays(self::BACKUP_STALE_DAYS)) ? 'WARNING' : 'OK', $at->toIso8601String(), null];
        });
    }

    private function app(): array
    {
        return [
            'key' => 'app',
            'status' => 'OK',
            'value' => (string) (config('app.version') ?: '—'),
            'detail' => 'PHP '.PHP_VERSION.' · Laravel '.app()->version().' · '.app()->environment(),
        ];
    }

    /**
     * @param  callable(): array{0: string, 1: string, 2: string|null}  $check
     * @return array{key: string, status: string, value: string, detail: string|null}
     */
    private function probe(string $key, callable $check): array
    {
        try {
            [$status, $value, $detail] = $check();
        } catch (Throwable $e) {
            // What failed belongs in the log, not on the screen.
            report($e);
            [$status, $value, $detail] = ['DOWN', __('settings.system.health.not_working'), null];
        }

        return ['key' => $key, 'status' => $status, 'value' => $value, 'detail' => $detail];
    }

    private function bytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, 1).' '.$units[$i];
    }
}
