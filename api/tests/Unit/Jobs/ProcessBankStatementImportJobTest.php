<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessBankStatementImportJob;
use App\Modules\Ledger\Services\StatementImportLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('ai-import')]
#[CoversClass(ProcessBankStatementImportJob::class)]
#[CoversClass(StatementImportLimits::class)]
class ProcessBankStatementImportJobTest extends TestCase
{
    public function testTimeoutsLeaveRoomForTheProviderCall(): void
    {
        $job = new ProcessBankStatementImportJob(1);

        $this->assertSame(StatementImportLimits::JOB_TIMEOUT_SECONDS, $job->timeout);
        $this->assertSame(StatementImportLimits::UNIQUE_LOCK_SECONDS, $job->uniqueFor);
        $this->assertGreaterThan(StatementImportLimits::HTTP_TIMEOUT_SECONDS, $job->timeout);
        $this->assertGreaterThan($job->timeout, StatementImportLimits::WORKER_TIMEOUT_SECONDS);
        $this->assertGreaterThan(
            StatementImportLimits::WORKER_TIMEOUT_SECONDS,
            StatementImportLimits::QUEUE_RETRY_AFTER_SECONDS,
        );
        $this->assertGreaterThan(
            $job->timeout,
            (int) config('queue.connections.redis.retry_after'),
        );
        $this->assertGreaterThanOrEqual(
            StatementImportLimits::QUEUE_RETRY_AFTER_SECONDS,
            $job->uniqueFor,
        );
    }
}
