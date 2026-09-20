<?php

namespace App\Modules\Ledger\Services;

/**
 * Time budget for BYOK statement import.
 *
 * HTTP must finish (or fail) before the job timeout. The queue worker
 * `--timeout` must exceed the job timeout, and Redis `retry_after` must
 * exceed the worker timeout, or a slow provider call kills PID 1 and the
 * import loops in "processing" with no error.
 */
final class StatementImportLimits
{
    public const HTTP_TIMEOUT_SECONDS = 150;

    public const HTTP_CONNECT_TIMEOUT_SECONDS = 10;

    public const JOB_TIMEOUT_SECONDS = 180;

    public const WORKER_TIMEOUT_SECONDS = 210;

    public const QUEUE_RETRY_AFTER_SECONDS = 240;

    public const UNIQUE_LOCK_SECONDS = 240;
}
