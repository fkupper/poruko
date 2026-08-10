<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ledger = \App\Models\Ledger::first();
echo $ledger->users()->toSql();
echo "\n";
