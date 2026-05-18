<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Mail;

try {
    Mail::raw('Test email from Komibook', function($msg) {
        $msg->to('theanht057@gmail.com')->subject('Test SMTP Komibook');
    });
    echo "Mail sent successfully\n";
} catch (\Exception $e) {
    echo "Mail failed: " . $e->getMessage() . "\n";
}
