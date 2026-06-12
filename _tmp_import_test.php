<?php
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

// Fake admin session so assertAdmin() passes
session(['user' => ['role' => ['id' => 1], 'eci' => 'TESTER', 'name' => 'Tester']]);

$path = base_path('_tmp_import_test.csv');
$file = new UploadedFile($path, 'import.csv', 'text/csv', null, true); // test mode = true

$request = Request::create('/api/admin/import/customers', 'POST');
$request->files->set('file', $file);

$controller = app(\App\Http\Controllers\AdminBackupController::class);
$response = $controller->importCustomers($request);

fwrite(STDOUT, '--- RESPONSE ---' . PHP_EOL);
fwrite(STDOUT, $response->getContent() . PHP_EOL);

// Inspect resulting rows
$cust = DB::table('customer')->where('customer_code', 'ZZTEST')->first();
if ($cust) {
    fwrite(STDOUT, '--- CUSTOMER ---' . PHP_EOL);
    fwrite(STDOUT, json_encode($cust) . PHP_EOL);
    $addr = DB::table('customer_address')->where('customer_id', $cust->customer_id)->first();
    fwrite(STDOUT, '--- ADDRESS ---' . PHP_EOL);
    fwrite(STDOUT, json_encode($addr) . PHP_EOL);

    // Cleanup test rows
    DB::table('customer_address')->where('customer_id', $cust->customer_id)->delete();
    DB::table('customer_basic_data')->where('customer_id', $cust->customer_id)->delete();
    DB::table('customer')->where('customer_id', $cust->customer_id)->delete();
    fwrite(STDOUT, '--- CLEANED UP ---' . PHP_EOL);
} else {
    fwrite(STDOUT, 'NO CUSTOMER CREATED' . PHP_EOL);
}
