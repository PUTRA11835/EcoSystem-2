<?php

namespace App\Http\Controllers;

use App\Services\HolidayService;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function index(Request $request, HolidayService $service)
    {
        $currentYear = (int) date('Y');
        $from = (int) $request->query('from', $currentYear);
        $to   = (int) $request->query('to',   $currentYear + 2);

        if ($to < $from) {
            $to = $from;
        }
        if ($to - $from > 5) {
            $to = $from + 5;
        }

        return response()->json([
            'from'     => $from,
            'to'       => $to,
            'holidays' => $service->getHolidays($from, $to),
        ]);
    }
}
