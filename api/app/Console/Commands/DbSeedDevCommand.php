<?php

namespace App\Console\Commands;

use Database\Seeders\DevSeeder;
use Illuminate\Console\Command;

class DbSeedDevCommand extends Command
{
    protected $signature = 'db:seed:dev
        {--pending : Leave the settlement pending for manual confirmation in the UI}';

    protected $description = 'Seed dev data (users, ledger, accounts, transactions). No-op in testing.';

    public function handle(): int
    {
        if ($this->option('pending')) {
            config(['database.seeders.dev_leave_settlement_pending' => true]);
        }

        $this->call(DevSeeder::class);

        return self::SUCCESS;
    }
}
