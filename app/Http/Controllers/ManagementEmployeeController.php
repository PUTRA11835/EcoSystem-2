<?php

namespace App\Http\Controllers;

use App\Http\Controllers\HR_General\Concerns\HandlesRecruitmentTables;
use App\Services\Contracts\ContractService;
use App\Support\Contracts\ContractRules;
use Illuminate\Http\Request;

class ManagementEmployeeController extends Controller
{
    use HandlesRecruitmentTables;

    public function basicData()     { return view('management.employee.basic-data.index'); }
    public function address()       { return view('management.employee.address.index'); }
    public function identification(){ return view('management.employee.identification.index'); }
    public function family()        { return view('management.employee.family.index'); }
    public function education()      { return view('management.employee.education.index'); }
    public function qualification() { return view('management.employee.qualification.index'); }
    public function bank()          { return view('management.employee.bank.index'); }
    public function payment()       { return view('management.employee.payment.index'); }
    public function attachment()    { return view('management.employee.attachment.index'); }

    /**
     * Management → Employee → Contract. Two tabs on one route: `?tab=dashboard` (default) shows whose contract
     * ends soon and the figures around it; `?tab=list` is the table, filtered from the funnel of each column header.
     */
    public function contract(Request $request, ContractService $contracts)
    {
        if ($request->query('tab') !== 'list') {
            return view('management.employee.contract.index', [
                'tab'  => 'dashboard',
                'dash' => $contracts->dashboard((int) $request->query('window', 30)),
            ]);
        }

        $date = fn (string $key) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query($key)) ? (string) $request->query($key) : '';
        $text = fn (string $key) => trim((string) $request->query($key));

        $filters = [
            'employee'   => $text('employee'),
            'number'     => $text('number'),
            'type'       => $text('type'),
            'department' => $text('department'),
            'status'     => $text('status'),
            'end_from'   => $date('end_from'),
            'end_to'     => $date('end_to'),
        ];
        $perPage = $this->perPage($request);

        return view('management.employee.contract.index', [
            'tab'            => 'list',
            'rows'           => $contracts->listing($filters, $perPage),
            'filters'        => $filters,
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'types'          => ContractRules::TYPES,
            'statuses'       => ContractRules::STATUS_LABELS + ['none' => 'No contract'],
            'departments'    => $contracts->departments(),
        ]);
    }
}
