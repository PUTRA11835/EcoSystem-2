<?php
$fa = DB::table('customer_address')->selectRaw('MIN(address_id) as address_id, customer_id')->groupBy('customer_id');
$n = DB::table('customer as c')
    ->leftJoin('customer_basic_data as b', 'c.customer_id', '=', 'b.customer_id')
    ->leftJoinSub($fa, 'fa', 'c.customer_id', '=', 'fa.customer_id')
    ->leftJoin('customer_address as a', 'fa.address_id', '=', 'a.address_id')
    ->select('c.customer_id', 'a.telephone', 'a.building_name', 'a.full_address')
    ->count();
fwrite(STDOUT, 'EXPORT_QUERY_OK rows=' . $n . PHP_EOL);
