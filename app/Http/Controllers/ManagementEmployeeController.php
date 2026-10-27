<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Management\EmployeeSettingsController;

/**
 * Management → Employee: one tab per section of the employee profile. They are SETTINGS, not lists of records — each tab manages what its
 * section of the profile offers (dropdown lists) and accepts (limits, what is required); the employee's own records are read and edited on
 * Master → Employee. See EmployeeSettingsController for the actions and App\Support\Management\EmployeeMasterSettings for what each tab holds.
 */
class ManagementEmployeeController extends Controller
{
    public function basicData()      { return $this->page('basic-data'); }
    public function address()        { return $this->page('address'); }
    public function identification() { return $this->page('identification'); }
    public function family()         { return $this->page('family'); }
    public function education()      { return $this->page('education'); }
    public function bank()           { return $this->page('bank'); }
    public function payment()        { return $this->page('payment'); }
    public function attachment()     { return $this->page('attachment'); }

    /** The Master Module list this tab always had, with the settings of the Qualification section under it. */
    public function qualification()
    {
        return view('management.employee.qualification.index', ['panel' => EmployeeSettingsController::panel('qualification')]);
    }

    /** What the Contract tab of My Profile shows. The dashboard and every contract list are HR & General → Contract, not repeated here. */
    public function contract() { return $this->page('contract'); }

    private function page(string $section)
    {
        return view('management.employee.settings', ['panel' => EmployeeSettingsController::panel($section)]);
    }
}
