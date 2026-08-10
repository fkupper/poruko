<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$ledger = \App\Models\Ledger::first();
echo $ledger->users()->toSql()."\n";
echo $ledger->allUsers()->toSql()."\n";
