<?php

namespace OiLab\OiLaravelInsee\Support;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use OiLab\OiLaravelInsee\Exceptions\InseeQuotaExceededException;

/**
 * Two-window limiter (per minute and per hour) shared by every call of the
 * package, and by every process using the same cache store (Redis in
 * production): all of its state lives in the cache, never in the instance.
 *
 * The quota headers of the API (`x-quota-*` for the hour, `x-rate-limit-*` for
 * the minute) are the source of truth once a response has been seen; the local
 * counters only cover the calls made before that.
 */
class InseeRateLimiter
{
    private const MINUTE_KEY = 'insee:rate-limit:minute';

    private const HOUR_KEY = 'insee:rate-limit:hour';

    private const QUOTA_STATE = 'insee:rate-limit:quota';

    private const RATE_STATE = 'insee:rate-limit:rate';

    /**
     * @param  array{per_minute: int, per_hour: int, background_ceiling: int, max_wait_seconds: int, legacy_max_wait_seconds: int}  $config
     */
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly Repository $cache,
        private readonly array $config,
    ) {}

    public static function fromConfig(): self
    {
        $config = config('oi-laravel-insee.rate_limits', []);

        return new self(
            app(RateLimiter::class),
            Cache::store(config('cache.limiter')),
            [
                'per_minute' => (int) ($config['per_minute'] ?? 30),
                'per_hour' => (int) ($config['per_hour'] ?? 2000),
                'background_ceiling' => (int) ($config['background_ceiling'] ?? 1600),
                'max_wait_seconds' => (int) ($config['max_wait_seconds'] ?? 65),
                'legacy_max_wait_seconds' => (int) ($config['legacy_max_wait_seconds'] ?? 3),
            ],
        );
    }

    /**
     * Reserve one call. Waits when the minute window is full (a few seconds for
     * legacy calls, which then go through anyway) and throws when the hourly
     * budget of the priority is exhausted.
     *
     * @throws InseeQuotaExceededException
     */
    public function acquire(CallPriority $priority): void
    {
        $this->assertHourlyBudget($priority);
        $this->waitForMinuteWindow($priority);

        $this->limiter->hit(self::HOUR_KEY, 3600);
        $this->limiter->hit(self::MINUTE_KEY, 60);
    }

    /**
     * Keep the quota headers of a response: they tell the real remainder of
     * the hour and of the minute, and when each resets.
     */
    public function recordResponse(Response $response): void
    {
        $this->remember(self::QUOTA_STATE, $response, 'x-quota-remaining', 'x-quota-reset', 3600);
        $this->remember(self::RATE_STATE, $response, 'x-rate-limit-remaining', 'x-rate-limit-reset', 60);
    }

    /**
     * A 429 was received: pause every process and say when to come back, the
     * reset of the hour if it is exhausted, else the one of the minute.
     */
    public function markRejected(Response $response): CarbonImmutable
    {
        $quotaRemaining = $this->headerInt($response, 'x-quota-remaining');
        $quotaReset = $this->resetAt($response, 'x-quota-reset');
        $rateReset = $this->resetAt($response, 'x-rate-limit-reset');

        if ($quotaRemaining !== null && $quotaRemaining <= 0 && $quotaReset !== null) {
            $this->store(self::QUOTA_STATE, 0, $quotaReset);

            return $quotaReset;
        }

        $retryAt = $rateReset ?? CarbonImmutable::now()->addMinute();
        $this->store(self::RATE_STATE, 0, $retryAt);

        return $retryAt;
    }

    private function assertHourlyBudget(CallPriority $priority): void
    {
        $perHour = $this->config['per_hour'];
        $ceiling = min($this->config['background_ceiling'], $perHour);
        $quota = $this->state(self::QUOTA_STATE);

        if ($quota !== null) {
            if ($quota['remaining'] <= 0 || ($priority === CallPriority::Background && $quota['remaining'] <= $perHour - $ceiling)) {
                throw $this->exhausted($priority, $quota['reset_at']);
            }

            return;
        }

        $retryIn = fn (): CarbonImmutable => CarbonImmutable::now()->addSeconds(max(1, $this->limiter->availableIn(self::HOUR_KEY)));

        if ($this->limiter->tooManyAttempts(self::HOUR_KEY, $perHour)
            || ($priority === CallPriority::Background && $this->limiter->tooManyAttempts(self::HOUR_KEY, $ceiling))) {
            throw $this->exhausted($priority, $retryIn()->getTimestamp());
        }
    }

    private function waitForMinuteWindow(CallPriority $priority): void
    {
        for ($i = 0; $i < 3; $i++) {
            $wait = $this->minuteWait();

            if ($wait <= 0) {
                return;
            }

            if ($priority === CallPriority::Legacy) {
                Sleep::for(min($wait, $this->config['legacy_max_wait_seconds']))->seconds();

                return;
            }

            if ($wait > $this->config['max_wait_seconds']) {
                throw $this->exhausted($priority, CarbonImmutable::now()->addSeconds($wait)->getTimestamp());
            }

            Sleep::for($wait)->seconds();
        }
    }

    /**
     * Seconds to wait before the minute window accepts a call (0 when open).
     */
    private function minuteWait(): int
    {
        $rate = $this->state(self::RATE_STATE);

        if ($rate !== null && $rate['remaining'] <= 0) {
            return max(1, $rate['reset_at'] - CarbonImmutable::now()->getTimestamp());
        }

        if ($rate === null && $this->limiter->tooManyAttempts(self::MINUTE_KEY, $this->config['per_minute'])) {
            return max(1, $this->limiter->availableIn(self::MINUTE_KEY));
        }

        return 0;
    }

    private function exhausted(CallPriority $priority, int $resetAt): InseeQuotaExceededException
    {
        $retryAt = CarbonImmutable::createFromTimestamp($resetAt);
        $scope = $priority === CallPriority::Background ? 'Le plafond des recherches de fond' : 'Le quota';

        return new InseeQuotaExceededException(
            "{$scope} de l'API Sirene est atteint, reprise possible à {$retryAt->toIso8601String()}.",
            $retryAt,
        );
    }

    private function remember(string $key, Response $response, string $remainingHeader, string $resetHeader, int $fallbackTtl): void
    {
        $remaining = $this->headerInt($response, $remainingHeader);

        if ($remaining === null) {
            return;
        }

        $this->store($key, $remaining, $this->resetAt($response, $resetHeader) ?? CarbonImmutable::now()->addSeconds($fallbackTtl));
    }

    private function store(string $key, int $remaining, CarbonImmutable $resetAt): void
    {
        $ttl = $resetAt->getTimestamp() - CarbonImmutable::now()->getTimestamp();

        if ($ttl <= 0) {
            return;
        }

        $this->cache->put($key, ['remaining' => $remaining, 'reset_at' => $resetAt->getTimestamp()], $ttl);
    }

    /**
     * @return array{remaining: int, reset_at: int}|null
     */
    private function state(string $key): ?array
    {
        $state = $this->cache->get($key);

        if (! is_array($state) || $state['reset_at'] <= CarbonImmutable::now()->getTimestamp()) {
            return null;
        }

        return $state;
    }

    private function headerInt(Response $response, string $name): ?int
    {
        $value = $response->header($name);

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * The reset headers are epoch timestamps in milliseconds.
     */
    private function resetAt(Response $response, string $name): ?CarbonImmutable
    {
        $value = $this->headerInt($response, $name);

        return $value === null ? null : CarbonImmutable::createFromTimestampMs($value);
    }
}
