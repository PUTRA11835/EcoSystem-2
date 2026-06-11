<?php
$cust = DB::table('customer')->count();
$withAddr = DB::table('customer')
    ->whereIn('customer_id', DB::table('customer_address')->select('customer_id'))
    ->count();
$addrTotal = DB::table('customer_address')->count();
$addrMulti = DB::table('customer_address')
    ->select('customer_id', DB::raw('COUNT(*) as c'))
    ->groupBy('customer_id')->having('c', '>', 1)->get()->count();
fwrite(STDOUT, "Total customer        : $cust" . PHP_EOL);
fwrite(STDOUT, "Punya >=1 alamat      : $withAddr" . PHP_EOL);
fwrite(STDOUT, "Tanpa alamat          : " . ($cust - $withAddr) . PHP_EOL);
fwrite(STDOUT, "Total baris alamat    : $addrTotal" . PHP_EOL);
fwrite(STDOUT, "Customer >1 alamat    : $addrMulti" . PHP_EOL);
