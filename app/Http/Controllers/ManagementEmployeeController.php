<?php

namespace App\Http\Controllers;

class ManagementEmployeeController extends Controller
{
    public function basicData()     { return view('management.employee.basic-data.index'); }
    public function address()       { return view('management.employee.address.index'); }
    public function identification(){ return view('management.employee.identification.index'); }
    public function family()        { return view('management.employee.family.index'); }
    public function education()     { return view('management.employee.education.index'); }
    public function contract()      { return view('management.employee.contract.index'); }
    public function bank()          { return view('management.employee.bank.index'); }
    public function payment()       { return view('management.employee.payment.index'); }
    public function attachment()    { return view('management.employee.attachment.index'); }
}
