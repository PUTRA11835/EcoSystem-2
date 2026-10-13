<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;

/**
 * External → Timesheet & Payment (slug general.external.timesheet): tempat penampung "Coming soon".
 * Belum ada data maupun aksi; halaman ini hanya menjelaskan apa yang akan datang supaya menunya sudah ada di sidebar.
 */
class ExternalTimesheetController extends Controller
{
    public function index()
    {
        return view('hr-general.external.timesheet');
    }
}
