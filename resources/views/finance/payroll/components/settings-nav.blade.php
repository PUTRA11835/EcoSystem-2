{{-- Mini sidebar of Payroll Settings (shared behaviour lives in partials/page-nav). --}}
@include('partials.page-nav', ['navLabel' => 'Payroll settings', 'navItems' => [
    ['id' => 'payroll-module', 'label' => 'Payroll module', 'icon' => 'fa-power-off'],
    ['id' => 'work-schedule', 'label' => 'Work schedule & proration', 'icon' => 'fa-calendar-days'],
    ['id' => 'attendance', 'label' => 'Attendance deductions', 'icon' => 'fa-user-clock'],
    ['id' => 'earnings', 'label' => 'Earnings & overtime', 'icon' => 'fa-money-bill-wave'],
    ['id' => 'bpjs-tax', 'label' => 'BPJS & PPh 21', 'icon' => 'fa-landmark'],
    ['id' => 'net-rounding', 'label' => 'Net pay rounding', 'icon' => 'fa-calculator'],
    ['id' => 'cutoff-loans', 'label' => 'Cut-off, timesheet & loans', 'icon' => 'fa-hourglass-half'],
    ['id' => 'data-readiness', 'label' => 'Data readiness', 'icon' => 'fa-clipboard-check'],
    ['id' => 'queueCard', 'label' => 'Background check', 'icon' => 'fa-server'],
    ['id' => 'signerCard', 'label' => 'Payslip', 'icon' => 'fa-file-invoice'],
    ['id' => 'other-rules', 'label' => 'Where other rules live', 'icon' => 'fa-link'],
]])
