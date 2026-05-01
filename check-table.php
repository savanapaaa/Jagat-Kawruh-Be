<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$cols = Illuminate\Support\Facades\Schema::getColumnListing('kuis_attempts');
echo "Columns in kuis_attempts:\n";
print_r($cols);
