<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\Legal\LegalDashboardCards;

/**
 * Legal → Dashboard tab: the summary cards of what the Legal sub menus hold. Each card follows the viewer's own slugs (a card never
 * reveals a page the person could not open). The other tab of the Legal page, Legal Basis (the register), is LegalBasisController.
 * This action changes nothing.
 */
class LegalDashboardController extends Controller
{
    public function index(LegalDashboardCards $cards)
    {
        return view('legal.legal', ['cards' => $cards->for(Employee::find((int) session('user.id')))]);
    }
}
