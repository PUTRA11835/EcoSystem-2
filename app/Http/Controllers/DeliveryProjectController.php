<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DeliveryProject;
use App\Models\DeliveryProjectCost;
use App\Models\Employee;
use App\Models\EmployeeBasicData;
use App\Models\Document;
use App\Models\DeliveryProjectPlanning;
use App\Models\DeliveryProjectPhase;
use App\Models\DeliveryProjectActivity;
use App\Models\DeliveryProjectPaymentTerm;
use App\Services\OneDriveService;
use App\Services\ProjectReminderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class DeliveryProjectController extends Controller
{
    public function index()
    {
        $projects = DeliveryProject::with('client.basicData')->latest()->get();
        return view('delivery.project.projects.index', compact('projects'));
    }

    public function create()
    {
        // Hanya Business Partner bertipe Customer yang bisa dipilih sebagai client.
        $clients = Customer::with('basicData')->customers()->get()->sortBy(function($client) {
            return $client->basicData->name_1 ?? '';
        });
        $clientPicMap = $clients->pluck('pic', 'customer_id');
        // `qualifications.module` dipakai modal Add Team Member (Module bukan free text).
        $employees = Employee::with(['basicData', 'qualifications.module', 'addresses' => fn($q) => $q->where('is_primary', true)])->get()->sortBy(fn($e) => strtolower($e->basicData->full_name ?? 'zzz'))->values();

        // Dropdown "Vendor" pada Add Team Member (Business Partner bertipe Vendor).
        $vendors = Customer::with('basicData')->vendors()->get()->sortBy(fn($v) => strtolower($v->basicData->name_1 ?? $v->customer_code ?? 'zzz'))->values();

        // Account Executive dropdown: karyawan aktif ber-role family "Sales Operation"
        // selain "Sales Operation User" (lihat salesRoleEmployees()).
        $aeEmployees = $this->salesRoleEmployees();

        // IO number existing per company (client) — dipakai form Body Hire untuk
        // memilih IO yang sudah ada; hanya IO milik company yang sama yang muncul.
        $iosByClient = $this->existingIosByClient();

        return view('delivery.project.projects.create', compact('clients', 'clientPicMap', 'employees', 'vendors', 'aeEmployees', 'iosByClient'));
    }

    public function store(Request $request)
    {
        // Body Hire boleh berbagi satu IO number lintas delivery — tapi HANYA dalam
        // company yang sama. Type lain tetap wajib unik global.
        $ioNumberRule = ['required', 'string', 'max:255'];
        if ($request->input('project_type') === 'Body Hire') {
            $ioNumberRule[] = $this->sameCompanyIoRule($request->input('client_id'));
        } else {
            $ioNumberRule[] = Rule::unique('delivery_projects', 'io_number');
        }

        $request->validate([
            'client_id' => ['required', Rule::exists('customer', 'customer_id')->where('type', Customer::TYPE_CUSTOMER)],
            'project_owner' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'project_type' => 'required|string|max:255',
            'high_level_risk' => 'nullable|in:Low,Moderate,High',
            'contract_start_date' => 'required|date',
            'contract_end_date' => 'required|date|after_or_equal:contract_start_date',
            'io_number' => $ioNumberRule,
            'ae_type' => 'nullable|in:Internal,External',
            'ae_name' => 'nullable|string',
            'ae_phone' => 'nullable|string',
            'ae_email' => 'nullable|email',
            'delivery_owner_id' => 'nullable|exists:employee,employee_id',
            'delivery_manager_id' => 'nullable|exists:employee,employee_id',
            'project_manager_id' => 'nullable|exists:employee,employee_id',
            'co_pm_id' => 'nullable|exists:employee,employee_id',
            'project_admin_id' => 'nullable|exists:employee,employee_id',
            'revenue' => 'nullable|numeric|min:0',
            'plan_cost' => 'nullable|numeric|min:0',
            'gross_profit' => 'nullable|numeric',
            'gross_profit_percentage' => 'nullable|numeric|min:-100|max:100',
            'delivery_method' => 'nullable|in:Onsite,Hybrid,WFH',
            'warranty_period' => 'nullable|integer|min:0',
            'total_mandays' => 'nullable|integer|min:0',
            'location_name' => 'nullable|string|max:255',
            'location_type' => 'nullable|in:Head Office,Plant',
            'location_country' => 'nullable|string|max:255',
            'location_geographical' => 'nullable|string',
            'location_region' => 'nullable|string',
            'location_city' => 'nullable|string',
            'location_street' => 'nullable|string',
            // Anggota tim punya dua jalur: "employee" (master Employee) dan
            // "vendor" (orang vendor di luar master). Kelengkapan tiap jalur
            // diperiksa saat baris diproses — lihat loop attach di bawah.
            'team_members'                    => 'nullable|array',
            'team_members.*.member_source'    => 'nullable|in:employee,vendor',
            'team_members.*.employee_id'      => 'nullable|exists:employee,employee_id',
            'team_members.*.vendor_id'        => 'nullable|exists:customer,customer_id',
            'team_members.*.member_name'      => 'nullable|string|max:255',
            'team_members.*.member_position'  => 'nullable|string|max:255',
            'team_members.*.module'           => 'nullable|string|max:255',
            'team_members.*.role'             => 'required|in:Member,Lead,Project Manager,Co Project Manager,Project Admin',
            'team_members.*.start_date'       => 'required|date',
            'team_members.*.end_date'         => 'nullable|date|after_or_equal:team_members.*.start_date',
            'team_members.*.notes'            => 'nullable|string',
        ]);

        // Get employee_id from session (ECOSYSTEM uses session-based auth)
        $user = session('user');
        $createdById = $user['employee_id'] ?? null;

        $projectData = [
            'client_id' => $request->client_id,
            'project_owner' => $request->project_owner,
            'name' => $request->name,
            'description' => $request->description,
            'project_type' => $request->project_type,
            'high_level_risk' => $request->high_level_risk,
            'contract_start_date' => $request->contract_start_date,
            'contract_end_date' => $request->contract_end_date,
            'io_number' => $request->io_number,
            'category' => 'Open',
            'phase' => null,
            // No planning baseline yet → SPI is undefined → On Track (no variance).
            'status' => 'On Track',
            'created_by_id' => null,
        ];

        $projectData = array_merge($projectData, $request->only([
            'ae_type', 'ae_name', 'ae_phone', 'ae_email',
            'delivery_owner_id', 'delivery_manager_id',
            'project_manager_id', 'co_pm_id', 'project_admin_id',
            'revenue', 'plan_cost', 'gross_profit', 'gross_profit_percentage',
            'delivery_method', 'warranty_period', 'total_mandays',
            'location_name', 'location_type', 'location_country',
            'location_geographical', 'location_region', 'location_city', 'location_street'
        ]));

        try {
            $project = DeliveryProject::create($projectData);

            // Attach additional team members from the table UI
            if ($request->filled('team_members') && is_array($request->input('team_members'))) {
                $roleColsDirty = false;
                foreach ($request->input('team_members') as $tm) {
                    $common = [
                        'role'       => $tm['role'],
                        'start_date' => $tm['start_date'],
                        'end_date'   => $tm['end_date'] ?: null,
                        'notes'      => $tm['notes'] ?? null,
                    ];

                    if (($tm['member_source'] ?? 'employee') === 'vendor') {
                        // Orang vendor: tidak ada di master employee. Vendor wajib
                        // Business Partner bertipe Vendor; nama diketik manual.
                        $vendor = !empty($tm['vendor_id'])
                            ? Customer::with('basicData')->vendors()->where('customer_id', $tm['vendor_id'])->first()
                            : null;
                        $memberName = trim((string) ($tm['member_name'] ?? ''));

                        if (!$vendor || $memberName === '') {
                            continue;
                        }

                        $vendorModules = collect(explode(',', (string) ($tm['module'] ?? '')))
                            ->map(fn ($m) => trim($m))->filter()->unique()->values();

                        DB::table('delivery_project_employee')->insert($common + [
                            'delivery_projects_id' => $project->id,
                            'employee_id'          => null,
                            'vendor_id'            => $vendor->customer_id,
                            'vendor_name'          => $vendor->basicData->name_1 ?? $vendor->customer_code,
                            'member_name'          => $memberName,
                            'member_position'      => !empty($tm['member_position']) ? trim($tm['member_position']) : null,
                            'module'               => $vendorModules->isEmpty() ? null : $vendorModules->implode(', '),
                            'employee_type'        => 'Vendor',
                            'created_at'           => now(),
                            'updated_at'           => now(),
                        ]);

                        // Kolom FK project-role menunjuk master employee — dilewati.
                        continue;
                    }

                    if (empty($tm['employee_id'])) {
                        continue;
                    }

                    // Employee Type & Module turunan data employee, bukan input form.
                    [$moduleValue] = $this->sanitizeEmployeeModules($tm['employee_id'], $tm['module'] ?? null);

                    $project->teamMembers()->attach($tm['employee_id'], $common + [
                        'module'        => $moduleValue,
                        'employee_type' => EmployeeBasicData::where('employee_id', $tm['employee_id'])->value('employee_type') ?: 'Internal',
                        'vendor_id'     => null,
                        'vendor_name'   => null,
                    ]);

                    // Sync FK columns if a project-level role was assigned via the table
                    if (isset(self::PROJECT_ROLE_COLUMNS[$tm['role']])) {
                        $col = self::PROJECT_ROLE_COLUMNS[$tm['role']];
                        $project->$col = $tm['employee_id'];
                        $roleColsDirty = true;
                    }
                }
                if ($roleColsDirty) {
                    $project->save();
                }
            }
        } catch (\Exception $e) {
            Log::error('DeliveryProjectController@store', ['error' => $e->getMessage()]);
            return redirect()->back()
                             ->withInput()
                             ->with('error', 'Failed to create the project due to a server error. Please try again.');
        }

        // A freshly created project may already sit inside the contract-end window.
        app(ProjectReminderService::class)->syncAllQuietly();

        return redirect()->route('projects.index')
                         ->with('success', 'Project successfully created.');
    }

    public function show(DeliveryProject $project)
    {
        $project->load([
            'client.basicData',
            'phases',
            'updates',
            'deliveryOwner.basicData',
            'deliveryManager.basicData',
            'createdBy',
            'closedBy.basicData',
            'documents',
            'teamMembers.basicData',
            'plannings.phase'
        ]);

        $planning = $project->plannings()
            ->whereNotNull('start_date')
            ->get();

        $firstStartDate = null;
        $lastEndDate = null;
        $goLiveDate = null;

        foreach ($planning as $plan) {
            if (!empty($plan->start_date)) {
                $startDate = Carbon::parse($plan->start_date);
                if (!$firstStartDate || $startDate < $firstStartDate) {
                    $firstStartDate = $startDate;
                }
            }

            if (!empty($plan->end_date)) {
                $endDate = Carbon::parse($plan->end_date);
                if (!$lastEndDate || $endDate > $lastEndDate) {
                    $lastEndDate = $endDate;
                }
            }

            // Go-Live ditandai di level activity (planning leaf). Estimasi = Planned
            // Start Date activity tsb (tanggal otoritatif di tabel activities; planning
            // leaf bisa lag). Hanya satu yang boleh go-live per project.
            if ($plan->is_golive && !$plan->is_group) {
                $goLiveDate = $plan->activity?->start_date ?: $plan->start_date;
            }
        }

        $project->go_live_estimated = $goLiveDate ?: null;

        if (!$project->location_valid_from && $firstStartDate) {
            $project->location_valid_from = $firstStartDate->toDateString();
        }
        if (!$project->location_valid_to && $lastEndDate) {
            $project->location_valid_to = $lastEndDate->toDateString();
        }
        
        try {
            $project->save();
            $project->updateStatusAutomatically();
        } catch (\Exception $e) {
            Log::error('DeliveryProjectController@show: failed to auto-update project', [
                'project_id' => $project->id,
                'error'      => $e->getMessage(),
            ]);
        }

        // `qualifications.module` ikut di-eager-load: dropdown Module pada modal
        // Add Team Member mengambil pilihannya dari kualifikasi employee
        // (bukan lagi free text).
        $employees = Employee::with(['basicData', 'addresses', 'qualifications.module'])->get()->sortBy(fn($e) => strtolower($e->basicData->full_name ?? 'zzz'))->values();

        // AE dropdown: karyawan aktif ber-role "Sales Operation" (lihat salesRoleEmployees()).
        $aeEmployees = $this->salesRoleEmployees();

        $clients = Customer::with('basicData')->customers()->get()->sortBy(fn($c) => strtolower($c->basicData->name_1 ?? ''))->values();

        // Dropdown "Vendor" pada Add Team Member — master-nya sama dengan customer
        // (tabel `customer`), dibedakan kolom `type` = Vendor.
        $vendors = Customer::with('basicData')->vendors()->get()->sortBy(fn($v) => strtolower($v->basicData->name_1 ?? $v->customer_code ?? 'zzz'))->values();

        // IO number existing milik company yang sama (untuk pilihan Body Hire).
        $sameCompanyIos = DeliveryProject::where('client_id', $project->client_id)
            ->where('id', '!=', $project->id)
            ->whereNotNull('io_number')
            ->where('io_number', '!=', '')
            ->pluck('io_number')
            ->filter()
            ->unique()
            ->values();

        $hasPlanning = DeliveryProjectPlanning::where('delivery_projects_id', $project->id)->exists();

        $phases = collect();
        $deliveryActivities = collect();
        $finalPhaseWeights = [];

        if ($hasPlanning) {
            $phases = $project->phases()->with([
                'activities' => function ($query) use ($project) {
                    $query->with(['plannings' => function ($q) use ($project) {
                        $q->where('delivery_projects_id', $project->id);
                    }]);
                }
            ])->get();

            $deliveryActivities = $project->activities()
                ->with(['delivery_plannings' => function ($q) use ($project) {
                    $q->where('delivery_projects_id', $project->id);
                }])
                ->get()
                ->groupBy('delivery_project_phase_id');

            $phaseWeights = $project->phases()
                ->pluck('weight', 'id')
                ->toArray();

            $defaultPhaseWeights = DeliveryProjectPhase::where('is_system_default', true)
                ->whereNull('delivery_projects_id')
                ->pluck('weight', 'id')
                ->toArray();

            $finalPhaseWeights = $phaseWeights + $defaultPhaseWeights;
        }

        // Raw pivot rows — one entry per row in the junction table.
        // belongsToMany deduplicates by employee_id, so this is the only
        // reliable way to surface every role entry (including multiple roles
        // for the same employee).
        $teamPivotRows = DB::table('delivery_project_employee')
            ->where('delivery_projects_id', $project->id)
            ->orderBy('created_at')
            ->get();

        // Actual Cost = total actual spending across all cost items (mirrors the
        // "Total Actual" summary in the Plan Cost section). Parent rows store a
        // null actual (aggregate only), so summing every row yields the leaf
        // total. Used to seed Delivery Information → Actual Cost / GP / % on
        // first paint; the frontend keeps it live as expenses change.
        $actualCost = (float) DeliveryProjectCost::where('delivery_projects_id', $project->id)
            ->sum('actual_amount');

        // Project Owner project ini dapat izin tambahan pada section tertentu
        // (mis. Risk Register) walau role-nya tidak punya slug-nya. Grant ini
        // hanya berlaku di project ini — lihat CheckMenuOrProjectOwner.
        $isProjectOwner = $project->isOwnedByEmployee(session('user.id'));

        return view('delivery.project.projects.show', compact(
            'project',
            'employees',
            'aeEmployees',
            'clients',
            'vendors',
            'hasPlanning',
            'phases',
            'deliveryActivities',
            'finalPhaseWeights',
            'teamPivotRows',
            'actualCost',
            'sameCompanyIos',
            'isProjectOwner'
        ));
    }

    public function updateField(Request $request, DeliveryProject $project)
    {
        $field = $request->input('field');
        $value = $request->input('value');

        $rules = [];
        if ($field === 'category') {
            $rules['value'] = ['required', Rule::in(['Open', 'In Process', 'Closed'])];
        } elseif ($field === 'status') {
            $rules['value'] = ['required', Rule::in(['On Track', 'At Risk', 'At Critical'])];
        } elseif ($field === 'phase') {
            $rules['value'] = ['required', Rule::in(['Prepare', 'Explore', 'Realize', 'Deploy'])];
        } elseif ($field === 'project_owner') {
            $rules['value'] = 'nullable|string|max:255';
        } elseif ($field === 'high_level_risk') {
            $rules['value'] = ['nullable', Rule::in(['Low', 'Moderate', 'High'])];
        } elseif ($field === 'io_number') {
            // Body Hire: IO boleh dipakai ulang dalam company yang sama; type lain unik.
            $rules['value'] = ['required', 'string', 'max:255'];
            if ($project->project_type === 'Body Hire') {
                $rules['value'][] = $this->sameCompanyIoRule($project->client_id, $project->id);
            } else {
                $rules['value'][] = Rule::unique('delivery_projects', 'io_number')->ignore($project->id);
            }
        } elseif ($field === 'description') {
            $rules['value'] = 'nullable|string|max:5000';
        } elseif ($field === 'name') {
            $rules['value'] = 'required|string|max:255';
        } elseif ($field === 'project_type') {
            $rules['value'] = ['nullable', Rule::in(['Implementation', 'Roll Out', 'Migration', 'Upgrade', 'WRICEF', 'Body Hire'])];
        } elseif ($field === 'client_id') {
            $rules['value'] = ['nullable', Rule::exists('customer', 'customer_id')->where('type', Customer::TYPE_CUSTOMER)];
        } else {
            $rules['value'] = 'nullable|string|max:255';
        }
        
        $request->validate($rules);

        if ($field === 'project_owner') {
            $project->update(['project_owner' => $value]);
            return back()->with('success', 'Project Owner updated successfully.');
        }

        if ($field === 'description') {
            $project->update(['description' => $value]);
            return back()->with('success', 'Description updated successfully.');
        }

        if ($field === 'name') {
            $project->update(['name' => $value]);
            return back()->with('success', 'Project Name updated successfully.');
        }

        if ($field === 'client_id') {
            $project->update(['client_id' => $value ?: null]);
            return back()->with('success', 'Customer updated successfully.');
        }

        if ($field === 'project_type') {
            $project->update(['project_type' => $value ?: null]);
            return back()->with('success', 'Project Type updated successfully.');
        }

        $project->$field = $value;
        $project->save();

        return back()->with('success', 'Project ' . ucfirst($field) . ' updated successfully.');
    }

    public function updateGeneralInfo(Request $request, DeliveryProject $project)
    {
        // Body Hire: IO boleh dipakai ulang dalam company yang sama; type lain unik.
        // Pakai type/client yang dikirim; fallback ke nilai project saat ini.
        $incomingType   = $request->input('project_type', $project->project_type);
        $incomingClient = $request->input('client_id', $project->client_id);
        $ioNumberRule   = ['required', 'string', 'max:255'];
        if ($incomingType === 'Body Hire') {
            $ioNumberRule[] = $this->sameCompanyIoRule($incomingClient, $project->id);
        } else {
            $ioNumberRule[] = Rule::unique('delivery_projects', 'io_number')->ignore($project->id);
        }

        $validated = $request->validate([
            'client_id'           => ['nullable', Rule::exists('customer', 'customer_id')->where('type', Customer::TYPE_CUSTOMER)],
            'name'                => 'required|string|max:255',
            'project_owner'       => 'nullable|string|max:255',
            'project_type'        => ['nullable', Rule::in(['Implementation','Roll Out','Migration','Upgrade','WRICEF','Body Hire'])],
            'high_level_risk'     => ['nullable', Rule::in(['Low','Moderate','High'])],
            'contract_start_date' => 'required|date',
            'contract_end_date'   => 'required|date|after_or_equal:contract_start_date',
            'io_number'           => $ioNumberRule,
            'description'         => 'nullable|string|max:5000',
        ]);

        $project->update($validated);

        // Contract end date changed → re-evaluate the contract-deadline bell reminders now
        // instead of waiting for the next daily run.
        app(ProjectReminderService::class)->syncAllQuietly();

        // The new contract window is allowed to fall outside existing planning, but we
        // surface a clear warning so the user can reconcile against the contract document.
        $warning = $this->planningOutsideContractWarning($project);

        $message = 'General information updated successfully.';
        if ($request->expectsJson()) {
            $payload = ['success' => true, 'message' => $message];
            if ($warning) {
                $payload['warning'] = $warning; // structured: count, window, items[]
            }
            return response()->json($payload);
        }
        $flashWarning = $warning
            ? $warning['count'] . ' planning item(s) fall outside the contract window (' . $warning['window'] . '). Please review.'
            : null;
        return back()->with('success', $message)->with('warning', $flashWarning);
    }

    /**
     * Build a structured warning when existing (leaf) planning items fall outside the
     * project's contract window. Returns null when everything is within range, otherwise
     * ['count' => int, 'window' => string, 'items' => string[]] so the client modal can
     * render the full (expandable) list.
     */
    private function planningOutsideContractWarning(DeliveryProject $project): ?array
    {
        $cStart = $project->contract_start_date;
        $cEnd   = $project->contract_end_date;
        if (!$cStart && !$cEnd) {
            return null;
        }

        $offenders = $project->plannings()
            ->where('is_group', false)
            ->where(function ($q) {
                $q->whereNotNull('start_date')->orWhereNotNull('end_date');
            })
            ->get()
            ->filter(function ($p) use ($cStart, $cEnd) {
                $startsTooEarly = $cStart && $p->start_date && Carbon::parse($p->start_date)->lt(Carbon::parse($cStart));
                $endsTooLate    = $cEnd && $p->end_date && Carbon::parse($p->end_date)->gt(Carbon::parse($cEnd));
                return $startsTooEarly || $endsTooLate;
            });

        if ($offenders->isEmpty()) {
            return null;
        }

        $items = $offenders->map(function ($p) {
            $s = $p->start_date ? Carbon::parse($p->start_date)->format('d M Y') : '—';
            $e = $p->end_date ? Carbon::parse($p->end_date)->format('d M Y') : '—';
            return ($p->name ?: 'Planning #' . $p->id) . ' (' . $s . ' → ' . $e . ')';
        })->values()->all();

        $window = ($cStart ? Carbon::parse($cStart)->format('d M Y') : '—')
                . ' → ' . ($cEnd ? Carbon::parse($cEnd)->format('d M Y') : '—');

        return [
            'count'  => $offenders->count(),
            'window' => $window,
            'items'  => $items,
        ];
    }

    public function updateDeliveryInfo(Request $request, DeliveryProject $project)
    {
        $validatedData = $request->validate([
            'project_type' => 'nullable|string',
            'ae_type' => 'nullable|in:Internal,External',
            'ae_name' => 'nullable|string',
            'ae_phone' => 'nullable|string',
            'ae_email' => 'nullable|email',
            'revenue' => 'nullable|numeric|min:0',
            'plan_cost' => 'nullable|numeric|min:0',
            'gross_profit' => 'nullable|numeric',
            'gross_profit_percentage' => 'nullable|numeric|min:-100|max:100',
            'project_manager_id' => 'nullable|exists:employee,employee_id',
            'co_pm_id' => 'nullable|exists:employee,employee_id',
            'project_admin_id' => 'nullable|exists:employee,employee_id',
            'delivery_method' => 'nullable|in:Onsite,Hybrid,WFH',
            'warranty_period' => 'nullable|integer|min:0',
            'total_mandays' => 'nullable|integer|min:0',
            'approval_date' => 'nullable|date',
            'approval_name' => 'nullable|string',
        ]);

        // Normalize AE name against AE type. When no type is selected the AE Name
        // input is disabled and not submitted, so without this guard a previously
        // stored name would linger without a type — causing confusion on next edit.
        // Tying the name to the type keeps the record consistent whether the user
        // clears the type intentionally or by accident.
        if (empty($validatedData['ae_type'])) {
            $validatedData['ae_type'] = null;
            $validatedData['ae_name'] = null;
        }

        $project->update($validatedData);

        // Payment term amounts are derived (amount = revenue × % / 100). We resync on
        // every save — not just when revenue changes on this request — so terms that
        // went stale from an earlier revenue edit are corrected too. It's idempotent.
        $this->syncPaymentTermAmounts($project);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Delivery information updated successfully.']);
        }
        return back()->with('success', 'Delivery information updated successfully.');
    }

    // Delivery Data (warranty / delivery method / mandays) berdiri sebagai section
    // & form tersendiri di halaman detail, terpisah dari Sales Data + Term Of Payment,
    // agar tombol simpannya tidak rancu dengan "Update Information" milik Delivery
    // Information. Endpoint ini sengaja tidak menyentuh field AE/finansial.
    public function updateDeliveryData(Request $request, DeliveryProject $project)
    {
        $validatedData = $request->validate([
            'delivery_method' => 'nullable|in:Onsite,Hybrid,WFH',
            'warranty_period' => 'nullable|integer|min:0',
            'total_mandays'   => 'nullable|integer|min:0',
        ]);

        $project->update($validatedData);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Delivery data updated successfully.']);
        }
        return back()->with('success', 'Delivery data updated successfully.');
    }

    public function updateFinancialInfo(Request $request, DeliveryProject $project)
    {
        $validatedData = $request->validate([
            'revenue' => 'nullable|numeric|min:0',
            'plan_cost' => 'nullable|numeric|min:0',
            'gross_profit' => 'nullable|numeric',
            'gross_profit_percentage' => 'nullable|numeric|min:-100|max:100',
        ]);

        $project->update($validatedData);

        // Keep derived payment term amounts in sync with the current revenue (see above).
        $this->syncPaymentTermAmounts($project);

        return back()->with('success', 'Financial information updated successfully.');
    }

    /**
     * Karyawan aktif kandidat "Account Executive" (dropdown create & detail
     * project) = pemegang role family "Sales Operation" SELAIN "Sales Operation
     * User": yaitu Administrator, Director, Account Executive, dan Head.
     * Role di-resolve via NAMA (`Sales Operation%` minus "Sales Operation User")
     * karena ID role diverge antar environment. Pengecualian berada di closure
     * `whereHas` yang sama, jadi karyawan yang juga punya role Sales Operation
     * lain tetap muncul. Diurutkan berdasarkan nama lengkap agar konsisten
     * dengan daftar karyawan lain di form.
     */
    /**
     * IO number yang sudah ada, dikelompokkan per company (client_id):
     * [client_id => ['IO-1', 'IO-2', ...]]. Dipakai form Body Hire agar user
     * bisa memilih IO existing milik company yang sama.
     */
    private function existingIosByClient(): array
    {
        return DeliveryProject::whereNotNull('io_number')
            ->where('io_number', '!=', '')
            ->get(['io_number', 'client_id'])
            ->groupBy('client_id')
            ->map(fn($g) => $g->pluck('io_number')->filter()->unique()->values()->all())
            ->all();
    }

    /**
     * Rule IO number untuk project Body Hire: IO boleh dipakai ulang, TETAPI hanya
     * dalam company (client) yang sama. Gagal bila IO yang sama sudah dipakai oleh
     * project company lain. $ignoreId mengecualikan project yang sedang diedit.
     */
    private function sameCompanyIoRule($clientId, $ignoreId = null): \Closure
    {
        return function ($attribute, $value, $fail) use ($clientId, $ignoreId) {
            $query = DeliveryProject::where('io_number', $value)
                ->where('client_id', '!=', $clientId);
            if ($ignoreId) {
                $query->where('id', '!=', $ignoreId);
            }
            if ($query->exists()) {
                $fail('IO Number tersebut sudah digunakan company lain. Untuk Body Hire, IO Number hanya boleh dipakai ulang dalam company yang sama.');
            }
        };
    }

    private function salesRoleEmployees()
    {
        return Employee::with('basicData')
            ->where('is_active', true)
            ->whereHas('roles', fn($q) => $q
                ->where('employee_role.name', 'like', 'Sales Operation%')
                ->where('employee_role.name', '!=', 'Sales Operation User'))
            ->get()
            ->sortBy(fn($e) => strtolower($e->basicData->full_name ?? 'zzz'))
            ->values();
    }

    /**
     * Hitung ulang amount setiap payment term dari revenue project terkini,
     * mengikuti rumus amount = revenue × payment_percentage / 100 yang juga dipakai
     * saat term dibuat/diedit (lihat DeliveryProjectPaymentTermController::computeAmount).
     */
    private function syncPaymentTermAmounts(DeliveryProject $project): void
    {
        $revenue = (float) ($project->revenue ?? 0);

        DeliveryProjectPaymentTerm::where('delivery_projects_id', $project->id)
            ->get()
            ->each(function (DeliveryProjectPaymentTerm $term) use ($revenue) {
                $amount = round($revenue * ((float) $term->payment_percentage) / 100, 2);
                if (abs((float) $term->amount - $amount) > 0.001) {
                    $term->update(['amount' => $amount]);
                }
            });
    }

    public function updateLocationInfo(Request $request, DeliveryProject $project)
    {
        $validatedData = $request->validate([
            'location_name' => 'nullable|string|max:255',
            'location_type' => 'nullable|in:Head Office,Plant',
            'location_country' => 'nullable|string|max:255',
            'location_geographical' => 'nullable|string',
            'location_region' => 'nullable|string',
            'location_city' => 'nullable|string',
            'location_street' => 'nullable|string',
            'location_valid_from' => 'nullable|date',
            'location_valid_to' => 'nullable|date|after_or_equal:location_valid_from',
        ]);

        $project->update($validatedData);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Location information updated successfully.']);
        }
        return back()->with('success', 'Location information updated successfully.');
    }

    // ============================================
    // DOCUMENT MANAGEMENT WITH AJAX SUPPORT
    // ============================================
    
    public function storeDocument(Request $request, DeliveryProject $project)
    {
        $request->validate([
            'document_name' => 'required|string|max:255',
            'link_document' => 'required|url',
            'document_type' => 'required|string|max:100',
        ]);

        $document = $project->documents()->create($request->only(['document_name', 'link_document', 'document_type']));

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Document added successfully',
                'document' => $document
            ]);
        }

        return back()->with('success', 'Document added successfully.');
    }

    public function updateDocument(Request $request, Document $document)
    {
        $request->validate([
            'document_name' => 'required|string|max:255',
            'document_type' => 'required|string|max:100',
            'file'          => 'nullable|file|max:102400',
        ], [
            'file.max' => 'Ukuran file maksimal 100 MB.',
        ]);

        $updateData = $request->only(['document_name', 'document_type']);

        if ($request->hasFile('file')) {
            $project = DeliveryProject::find($document->delivery_projects_id);

            if (!$project || !$project->onedrive_folder_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'OneDrive folder has not been created for this project.',
                ], 422);
            }

            $oneDrive     = new OneDriveService();
            $file         = $request->file('file');
            $originalName = $file->getClientOriginalName();

            $result   = $oneDrive->uploadFile(
                $project->onedrive_folder_id,
                $originalName,
                file_get_contents($file->getRealPath()),
                $file->getMimeType() ?: 'application/octet-stream'
            );

            $updateData['link_document'] = $oneDrive->createAnonymousLink($result['id'], 'view');
        }

        $document->update($updateData);
        $document->refresh();

        return response()->json([
            'success'  => true,
            'message'  => 'Document updated successfully.',
            'document' => [
                'id'            => $document->id,
                'document_name' => $document->document_name,
                'document_type' => $document->document_type,
                'link_document' => $document->link_document,
            ],
        ]);
    }

    public function destroyDocument(Document $document)
    {
        $document->delete();
        
        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully'
            ]);
        }
        
        return back()->with('success', 'Document deleted successfully.');
    }

    // ============================================
    // TEAM MEMBER MANAGEMENT
    // ============================================

    /**
     * Get all team members for a project (API endpoint)
     */
    public function getTeamMembers(DeliveryProject $project)
    {
        // One entry per pivot row so that members holding multiple roles (e.g.
        // someone who is both Project Manager AND an MM Member) all surface in
        // the dropdown. belongsToMany() deduplicates by employee_id, so we query
        // the junction table directly and join the name from employee_basic_data.
        $rows = DB::table('delivery_project_employee as dpe')
            ->leftJoin('employee_basic_data as ebd', 'ebd.employee_id', '=', 'dpe.employee_id')
            ->where('dpe.delivery_projects_id', $project->id)
            ->orderBy('dpe.created_at')
            ->get([
                'dpe.employee_id',
                'dpe.role',
                'dpe.module',
                'dpe.employee_type',
                'dpe.member_name',
                'ebd.first_name',
                'ebd.last_name',
            ]);

        $teamMembers = $rows->map(function ($r) {
            // Anggota vendor tidak ada di master employee — namanya dari member_name.
            $name = trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? ''));
            if ($name === '') {
                $name = trim((string) ($r->member_name ?? ''));
            }
            return [
                'employee_id'   => $r->employee_id,
                'name'          => $name !== '' ? $name : ('Employee #' . $r->employee_id),
                'role'          => $r->role,
                'module'        => $r->module,
                'employee_type' => $r->employee_type,
            ];
        });

        // FK-fallback roles: PM / Co PM / Project Admin stored only on the project
        // row (no pivot entry, e.g. projects created before the pivot flow). Mirror
        // the Team Members table on the show page so the dropdown is complete.
        $pivotIds = $rows->pluck('employee_id')->map(fn($id) => (string) $id)->all();
        $fkRoles = [
            ['id' => $project->project_manager_id, 'role' => 'Project Manager'],
            ['id' => $project->co_pm_id,            'role' => 'Co Project Manager'],
            ['id' => $project->project_admin_id,    'role' => 'Project Admin'],
        ];
        foreach ($fkRoles as $fk) {
            if (!$fk['id'] || in_array((string) $fk['id'], $pivotIds, true)) {
                continue;
            }
            $ebd = DB::table('employee_basic_data')
                ->where('employee_id', $fk['id'])
                ->first(['first_name', 'last_name']);
            $name = $ebd ? trim(($ebd->first_name ?? '') . ' ' . ($ebd->last_name ?? '')) : '';
            $teamMembers->push([
                'employee_id'   => $fk['id'],
                'name'          => $name !== '' ? $name : ('Employee #' . $fk['id']),
                'role'          => $fk['role'],
                'module'        => null,
                'employee_type' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'team_members' => $teamMembers->values(),
        ]);
    }

    // ── project-role FK map ──────────────────────────────────────────────────
    private const PROJECT_ROLE_COLUMNS = [
        'Project Manager'    => 'project_manager_id',
        'Co Project Manager' => 'co_pm_id',
        'Project Admin'      => 'project_admin_id',
    ];

    /**
     * Sync project_manager_id / co_pm_id / project_admin_id on the project row
     * after a team-member role change.
     *
     * @param DeliveryProject $project
     * @param string|int      $oldEmpId  – employee that previously held the URL param slot
     * @param string|int      $newEmpId  – employee that will now be attached
     * @param string          $newRole   – new role value
     */
    private function syncRoleColumns(DeliveryProject $project, $oldEmpId, $newEmpId, string $newRole): void
    {
        // Clear the old employee from any project-role column they held
        foreach (self::PROJECT_ROLE_COLUMNS as $col) {
            if ((string) $project->$col === (string) $oldEmpId) {
                $project->$col = null;
            }
        }

        // Assign the new employee to the matching column (if role is a project role)
        if (isset(self::PROJECT_ROLE_COLUMNS[$newRole])) {
            $project->{self::PROJECT_ROLE_COLUMNS[$newRole]} = $newEmpId ?: null;
        }

        $project->save();
    }

    /**
     * Tambah anggota tim. Dua jalur, dibedakan `member_source`:
     *
     *  - "employee" → orang dari master Employee. Employee Type TIDAK dikirim
     *    dari form; diturunkan dari employee_basic_data.employee_type
     *    (Internal/External, lihat EmployeeBasicData::deriveEmployeeType).
     *    Module dipilih dari kualifikasi employee tersebut.
     *
     *  - "vendor" → orang vendor yang tidak ada di master Employee. Vendor
     *    dipilih dari master Business Partner bertipe Vendor; nama, posisi, dan
     *    module diisi manual. Employee Type selalu "Vendor".
     */
    public function storeTeamMember(Request $request, DeliveryProject $project)
    {
        $source = $request->input('member_source') === 'vendor' ? 'vendor' : 'employee';

        $request->validate([
            'member_source' => 'nullable|in:employee,vendor',
            'role'          => 'required|in:Member,Lead,Project Manager,Co Project Manager,Project Admin',
            'start_date'    => 'required|date',
            'end_date'      => 'nullable|date|after_or_equal:start_date',
            'notes'         => 'nullable|string',
            'module'        => 'nullable|string|max:255',

            // Jalur employee
            'employee_id'   => [Rule::requiredIf($source === 'employee'), 'nullable', 'exists:employee,employee_id'],

            // Jalur vendor
            'vendor_id'     => [Rule::requiredIf($source === 'vendor'), 'nullable', 'exists:customer,customer_id'],
            'member_name'   => [Rule::requiredIf($source === 'vendor'), 'nullable', 'string', 'max:255'],
            'member_position' => 'nullable|string|max:255',
        ]);

        $pivotData = [
            'role'       => $request->role,
            'start_date' => $request->start_date,
            'end_date'   => $request->end_date,
            'notes'      => $request->notes,
        ];

        if ($source === 'vendor') {
            $vendor = Customer::with('basicData')->vendors()->where('customer_id', $request->vendor_id)->first();
            if (!$vendor) {
                return $this->teamMemberError($request, 'The selected vendor is not a Business Partner of type Vendor.');
            }

            $memberName = trim((string) $request->member_name);

            // Duplikat vendor: nama orang yang sama pada vendor & role yang sama
            // dan masih aktif (end_date NULL).
            $existsActive = DB::table('delivery_project_employee')
                ->where('delivery_projects_id', $project->id)
                ->whereNull('employee_id')
                ->where('vendor_id', $vendor->customer_id)
                ->where('member_name', $memberName)
                ->where('role', $request->role)
                ->whereNull('end_date')
                ->exists();

            if ($existsActive) {
                return $this->teamMemberError($request, 'This vendor person already has the same active role. Please set an end date on the previous entry first.');
            }

            // Module vendor bebas diketik — hanya dirapikan formatnya.
            $moduleValue = collect(explode(',', (string) $request->module))
                ->map(fn ($m) => trim($m))->filter()->unique()->values();

            DB::table('delivery_project_employee')->insert($pivotData + [
                'delivery_projects_id' => $project->id,
                'employee_id'          => null,
                'vendor_id'            => $vendor->customer_id,
                'vendor_name'          => $vendor->basicData->name_1 ?? $vendor->customer_code,
                'member_name'          => $memberName,
                'member_position'      => $request->member_position ? trim($request->member_position) : null,
                'module'               => $moduleValue->isEmpty() ? null : $moduleValue->implode(', '),
                'employee_type'        => 'Vendor',
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            // Kolom FK project-role (project_manager_id dkk) menunjuk ke master
            // employee, jadi TIDAK bisa diisi orang vendor — sengaja dilewati.

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Team member added successfully']);
            }
            return back()->with('success', 'Team member added successfully.');
        }

        // ── Jalur employee ───────────────────────────────────────────────────
        // Employee Type turunan data employee, bukan input form.
        $employeeType = EmployeeBasicData::where('employee_id', $request->employee_id)->value('employee_type') ?: 'Internal';

        [$moduleValue, $unknownModules] = $this->sanitizeEmployeeModules($request->employee_id, $request->module);

        if ($unknownModules->isNotEmpty()) {
            return $this->teamMemberError(
                $request,
                'Module "' . $unknownModules->implode('", "') . '" is not part of this employee\'s qualification.'
            );
        }

        // Cek duplikat: satu employee boleh punya role sama selama entry sebelumnya
        // sudah memiliki end_date. Jika masih aktif (end_date NULL), tolak.
        $existsActive = DB::table('delivery_project_employee')
            ->where('delivery_projects_id', $project->id)
            ->where('employee_id', $request->employee_id)
            ->where('role', $request->role)
            ->whereNull('end_date')
            ->exists();

        if ($existsActive) {
            return $this->teamMemberError($request, 'This employee already has the same active role. Please set an end date on the previous entry first.');
        }

        $project->teamMembers()->attach($request->employee_id, $pivotData + [
            'module'        => $moduleValue,
            'employee_type' => $employeeType,
            'vendor_id'     => null,
            'vendor_name'   => null,
        ]);

        // Sync project-role FK column if applicable
        if (isset(self::PROJECT_ROLE_COLUMNS[$request->role])) {
            $project->{self::PROJECT_ROLE_COLUMNS[$request->role]} = $request->employee_id;
            $project->save();
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Team member added successfully']);
        }
        return back()->with('success', 'Team member added successfully.');
    }

    /**
     * Module anggota tim BUKAN free text: hanya modul yang ada pada kualifikasi
     * employee (employee_qualification → modules) yang boleh dipakai. Dipanggil
     * di jalur BACA input mana pun (create project & add team member) supaya
     * request manual tidak bisa menyelundupkan modul asing.
     *
     * @return array{0: ?string, 1: \Illuminate\Support\Collection} [nilai siap simpan, modul tak dikenal]
     */
    private function sanitizeEmployeeModules($employeeId, $moduleInput): array
    {
        $allowed = DB::table('employee_qualification as eq')
            ->join('modules as m', 'm.id', '=', 'eq.module_id')
            ->where('eq.employee_id', $employeeId)
            ->pluck('m.name')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values();

        $selected = collect(explode(',', (string) $moduleInput))
            ->map(fn ($m) => trim($m))
            ->filter()
            ->unique()
            ->values();

        $unknown = $selected->reject(
            fn ($m) => $allowed->contains(fn ($a) => strcasecmp($a, $m) === 0)
        )->values();

        return [$selected->isEmpty() ? null : $selected->implode(', '), $unknown];
    }

    /**
     * Balasan error seragam untuk aksi team member (AJAX 422 / redirect back).
     */
    private function teamMemberError(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }
        return back()->with('error', $message);
    }

    public function destroyTeamMember(Request $request, DeliveryProject $project, $employeeId)
    {
        $role = $request->input('role');

        if ($role) {
            // Targeted delete: remove only the specific role entry.
            DB::table('delivery_project_employee')
                ->where('delivery_projects_id', $project->id)
                ->where('employee_id', $employeeId)
                ->where('role', $role)
                ->delete();

            // Sync FK column only for this specific role
            if (isset(self::PROJECT_ROLE_COLUMNS[$role])) {
                $col = self::PROJECT_ROLE_COLUMNS[$role];
                if ((string) $project->$col === (string) $employeeId) {
                    $project->$col = null;
                    $project->save();
                }
            }
        } else {
            // Legacy / bulk: remove all pivot entries for this employee
            $project->teamMembers()->detach($employeeId);

            // Clear all FK columns that referenced this employee
            $dirty = false;
            foreach (self::PROJECT_ROLE_COLUMNS as $col) {
                if ((string) $project->$col === (string) $employeeId) {
                    $project->$col = null;
                    $dirty = true;
                }
            }
            if ($dirty) $project->save();
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Team member removed successfully']);
        }
        return back()->with('success', 'Team member removed successfully.');
    }

    /**
     * Update satu baris penugasan tim (identifikasi via ID baris pivot).
     *
     * Baris diidentifikasi lewat ID pivot — bukan employee_id — karena anggota
     * vendor tidak punya employee_id sama sekali (lihat storeTeamMember).
     * Hanya role, end_date, dan notes yang boleh berubah.
     */
    public function updateTeamRow(Request $request, DeliveryProject $project, $rowId)
    {
        $request->validate([
            'role'      => 'required|in:Member,Lead,Project Manager,Co Project Manager,Project Admin',
            'end_date'  => 'nullable|date',
            'notes'     => 'nullable|string',
        ]);

        $row = DB::table('delivery_project_employee')
            ->where('delivery_projects_id', $project->id)
            ->where('id', $rowId)
            ->first();

        if (!$row) {
            return $this->teamMemberError($request, 'Team member entry not found.');
        }

        $oldRole = (string) $row->role;
        $newRole = $request->role;

        // Kalau role berubah, pastikan orang yang sama belum punya entri AKTIF
        // (end_date NULL) dengan role baru tersebut di baris lain.
        if ($newRole !== $oldRole) {
            $conflictQuery = DB::table('delivery_project_employee')
                ->where('delivery_projects_id', $project->id)
                ->where('id', '!=', $row->id)
                ->where('role', $newRole)
                ->whereNull('end_date');

            if ($row->employee_id !== null) {
                $conflictQuery->where('employee_id', $row->employee_id);
            } else {
                $conflictQuery->whereNull('employee_id')
                    ->where('vendor_id', $row->vendor_id)
                    ->where('member_name', $row->member_name);
            }

            if ($conflictQuery->exists()) {
                return $this->teamMemberError($request, 'This person already has an active entry with the selected role. Please set an end date on that entry first.');
            }
        }

        DB::table('delivery_project_employee')
            ->where('id', $row->id)
            ->update([
                'role'       => $newRole,
                'end_date'   => $request->end_date ?: null,
                'notes'      => $request->notes,
                'updated_at' => now(),
            ]);

        // Sinkronkan FK column project-role. Kolom ini menunjuk master employee,
        // jadi hanya relevan untuk baris employee (baris vendor dilewati).
        if ($row->employee_id !== null) {
            if (isset(self::PROJECT_ROLE_COLUMNS[$oldRole])) {
                $oldCol = self::PROJECT_ROLE_COLUMNS[$oldRole];
                if ((string) $project->$oldCol === (string) $row->employee_id) {
                    $project->$oldCol = null;
                }
            }

            if (isset(self::PROJECT_ROLE_COLUMNS[$newRole])) {
                $newCol = self::PROJECT_ROLE_COLUMNS[$newRole];
                $project->$newCol = $row->employee_id;
            }

            $project->save();
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Team member updated successfully']);
        }
        return back()->with('success', 'Team member updated successfully.');
    }

    /**
     * Close a project manually. Overrides the auto-derived category (kept 'Closed'
     * regardless of planning progress) and locks the project read-only until it is
     * reopened. Gated by menu:delivery-project.close-project.
     */
    public function close(Request $request, DeliveryProject $project)
    {
        if (!$project->is_closed) {
            $project->update([
                'is_closed' => true,
                'closed_at' => now(),
                'closed_by' => session('user.id'),
                'category'  => 'Closed',
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Project closed successfully.']);
        }
        return back()->with('success', 'Project closed successfully.');
    }

    /**
     * Reopen a closed project. Clears the manual-close flag and lets the category
     * be recomputed from planning progress. Gated by menu:delivery-project.close-project.
     */
    public function reopen(Request $request, DeliveryProject $project)
    {
        if ($project->is_closed) {
            $project->is_closed = false;
            $project->closed_at = null;
            $project->closed_by = null;
            $project->save();

            // Category was pinned to 'Closed' while locked — recompute from planning now.
            $project->updateFromPlanning();
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Project reopened successfully.']);
        }
        return back()->with('success', 'Project reopened successfully.');
    }

    public function destroy(Request $request, $project)
    {
        // NOTE: We resolve the model manually (instead of route-model binding) so
        // the operation is idempotent. A double-submitted DELETE (the second request
        // arriving after the row is already gone) previously threw ModelNotFound and
        // surfaced a scary "No query results" error even though the delete succeeded.
        // Treating an already-deleted project as success matches the desired end state.
        $model = DeliveryProject::find($project);

        if ($model) {
            try {
                $model->delete();
            } catch (\Throwable $e) {
                Log::error('DeliveryProjectController@destroy', ['id' => $project, 'error' => $e->getMessage()]);
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to delete project. It may still have related records.',
                    ], 500);
                }
                return redirect()->route('projects.index')->with('error', 'Failed to delete the project.');
            }
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Project deleted successfully.']);
        }
        return redirect()->route('projects.index')->with('success', 'Project deleted successfully.');
    }
    
    // Helper methods for region/city dropdowns
    public function getRegions(Request $request)
    {
        $geographical = $request->get('geographical');
        $regions = $this->getIndonesiaRegions($geographical);
        return response()->json($regions);
    }
    
    public function getCities(Request $request)
    {
        $region = $request->get('region');
        $cities = $this->getIndonesiaCities($region);
        return response()->json($cities);
    }
    
    private function getIndonesiaRegions($geographical = null)
    {
        $regions = [
            'Jawa' => [
                'DKI Jakarta', 'Jawa Barat', 'Jawa Tengah', 'DI Yogyakarta', 'Jawa Timur', 'Banten'
            ],
            'Sumatera' => [
                'Aceh', 'Sumatera Utara', 'Sumatera Barat', 'Riau', 'Kepulauan Riau', 
                'Jambi', 'Sumatera Selatan', 'Bengkulu', 'Lampung', 'Kepulauan Bangka Belitung'
            ],
            'Bali & Nusa Tenggara' => [
                'Bali', 'Nusa Tenggara Barat', 'Nusa Tenggara Timur'
            ],
            'Kalimantan' => [
                'Kalimantan Barat', 'Kalimantan Tengah', 'Kalimantan Selatan', 
                'Kalimantan Timur', 'Kalimantan Utara'
            ],
            'Sulawesi' => [
                'Sulawesi Utara', 'Sulawesi Tengah', 'Sulawesi Selatan', 
                'Sulawesi Tenggara', 'Gorontalo', 'Sulawesi Barat'
            ],
            'Maluku' => [
                'Maluku', 'Maluku Utara'
            ],
            'Papua' => [
                'Papua', 'Papua Barat', 'Papua Selatan', 'Papua Tengah', 
                'Papua Pegunungan', 'Papua Barat Daya'
            ]
        ];
        
        return $geographical ? ($regions[$geographical] ?? []) : $regions;
    }
    
    private function getIndonesiaCities($region)
    {
        $cities = [
            'DKI Jakarta' => [
                'Jakarta Pusat', 'Jakarta Utara', 'Jakarta Barat', 'Jakarta Selatan', 'Jakarta Timur',
                'Kepulauan Seribu'
            ],
            
            'Jawa Barat' => [
                'Bandung', 'Bekasi', 'Bogor', 'Cirebon', 'Depok', 'Sukabumi', 'Tasikmalaya',
                'Banjar', 'Cimahi', 'Garut', 'Indramayu', 'Karawang', 'Kuningan', 'Majalengka',
                'Purwakarta', 'Subang', 'Sumedang', 'Ciamis', 'Cianjur', 'Pangandaran'
            ],
            
            'Jawa Tengah' => [
                'Semarang', 'Solo', 'Magelang', 'Salatiga', 'Pekalongan', 'Tegal',
                'Banyumas', 'Cilacap', 'Purbalingga', 'Banjarnegara', 'Kebumen', 'Purworejo',
                'Wonosobo', 'Klaten', 'Boyolali', 'Sukoharjo', 'Wonogiri', 'Karanganyar',
                'Sragen', 'Grobogan', 'Blora', 'Rembang', 'Pati', 'Kudus', 'Jepara',
                'Demak', 'Kendal', 'Temanggung', 'Batang', 'Pemalang', 'Brebes'
            ],
            
            'DI Yogyakarta' => [
                'Yogyakarta', 'Bantul', 'Sleman', 'Gunungkidul', 'Kulon Progo'
            ],
            
            'Jawa Timur' => [
                'Surabaya', 'Malang', 'Sidoarjo', 'Gresik', 'Mojokerto', 'Kediri', 'Jember',
                'Batu', 'Blitar', 'Madiun', 'Pasuruan', 'Probolinggo',
                'Bangkalan', 'Banyuwangi', 'Bojonegoro', 'Bondowoso', 'Jombang',
                'Lamongan', 'Lumajang', 'Magetan', 'Nganjuk', 'Ngawi', 'Pacitan',
                'Pamekasan', 'Ponorogo', 'Sampang', 'Situbondo', 'Sumenep',
                'Trenggalek', 'Tuban', 'Tulungagung'
            ],
            
            'Banten' => [
                'Serang', 'Tangerang', 'Tangerang Selatan', 'Cilegon',
                'Pandeglang', 'Lebak'
            ],
            
            'Aceh' => [
                'Banda Aceh', 'Sabang', 'Langsa', 'Lhokseumawe', 'Subulussalam',
                'Aceh Besar', 'Aceh Jaya', 'Aceh Selatan', 'Aceh Singkil', 'Aceh Tengah',
                'Aceh Tenggara', 'Aceh Timur', 'Aceh Utara', 'Bener Meriah', 'Bireuen',
                'Gayo Lues', 'Nagan Raya', 'Pidie', 'Pidie Jaya', 'Simeulue'
            ],
            
            'Sumatera Utara' => [
                'Medan', 'Binjai', 'Pematangsiantar', 'Tanjungbalai', 'Tebing Tinggi',
                'Padang Sidempuan', 'Gunungsitoli', 'Sibolga',
                'Asahan', 'Batubara', 'Dairi', 'Deli Serdang', 'Humbang Hasundutan',
                'Karo', 'Labuhanbatu', 'Labuhanbatu Selatan', 'Labuhanbatu Utara',
                'Langkat', 'Mandailing Natal', 'Nias', 'Nias Barat', 'Nias Selatan',
                'Nias Utara', 'Padang Lawas', 'Padang Lawas Utara', 'Pakpak Bharat',
                'Samosir', 'Serdang Bedagai', 'Simalungun', 'Tapanuli Selatan',
                'Tapanuli Tengah', 'Tapanuli Utara', 'Toba Samosir'
            ],
            
            'Sumatera Barat' => [
                'Padang', 'Bukittinggi', 'Padang Panjang', 'Pariaman', 'Payakumbuh',
                'Sawahlunto', 'Solok',
                'Agam', 'Dharmasraya', 'Kepulauan Mentawai', 'Lima Puluh Kota',
                'Padang Pariaman', 'Pasaman', 'Pasaman Barat', 'Pesisir Selatan',
                'Sijunjung', 'Solok Selatan', 'Tanah Datar'
            ],
            
            'Riau' => [
                'Pekanbaru', 'Dumai',
                'Bengkalis', 'Indragiri Hilir', 'Indragiri Hulu', 'Kampar',
                'Kepulauan Meranti', 'Kuantan Singingi', 'Pelalawan', 'Rokan Hilir',
                'Rokan Hulu', 'Siak'
            ],
            
            'Kepulauan Riau' => [
                'Batam', 'Tanjung Pinang',
                'Bintan', 'Karimun', 'Kepulauan Anambas', 'Lingga', 'Natuna'
            ],
            
            'Jambi' => [
                'Jambi', 'Sungai Penuh',
                'Batang Hari', 'Bungo', 'Kerinci', 'Merangin', 'Muaro Jambi',
                'Sarolangun', 'Tanjung Jabung Barat', 'Tanjung Jabung Timur', 'Tebo'
            ],
            
            'Sumatera Selatan' => [
                'Palembang', 'Lubuklinggau', 'Pagar Alam', 'Prabumulih',
                'Banyuasin', 'Empat Lawang', 'Lahat', 'Muara Enim', 'Musi Banyuasin',
                'Musi Rawas', 'Musi Rawas Utara', 'Ogan Ilir', 'Ogan Komering Ilir',
                'Ogan Komering Ulu', 'Ogan Komering Ulu Selatan', 'Ogan Komering Ulu Timur',
                'Penukal Abab Lematang Ilir'
            ],
            
            'Bengkulu' => [
                'Bengkulu',
                'Bengkulu Selatan', 'Bengkulu Tengah', 'Bengkulu Utara', 'Kaur',
                'Kepahiang', 'Lebong', 'Mukomuko', 'Rejang Lebong', 'Seluma'
            ],
            
            'Lampung' => [
                'Bandar Lampung', 'Metro',
                'Lampung Barat', 'Lampung Selatan', 'Lampung Tengah', 'Lampung Timur',
                'Lampung Utara', 'Mesuji', 'Pesawaran', 'Pesisir Barat', 'Pringsewu',
                'Tanggamus', 'Tulang Bawang', 'Tulang Bawang Barat', 'Way Kanan'
            ],
            
            'Kepulauan Bangka Belitung' => [
                'Pangkal Pinang',
                'Bangka', 'Bangka Barat', 'Bangka Selatan', 'Bangka Tengah',
                'Belitung', 'Belitung Timur'
            ],
            
            'Bali' => [
                'Denpasar',
                'Badung', 'Bangli', 'Buleleng', 'Gianyar', 'Jembrana', 'Karangasem',
                'Klungkung', 'Tabanan'
            ],
            
            'Nusa Tenggara Barat' => [
                'Mataram', 'Bima',
                'Dompu', 'Lombok Barat', 'Lombok Tengah', 'Lombok Timur', 'Lombok Utara',
                'Sumbawa', 'Sumbawa Barat'
            ],
            
            'Nusa Tenggara Timur' => [
                'Kupang',
                'Alor', 'Belu', 'Ende', 'Flores Timur', 'Kupang', 'Lembata',
                'Manggarai', 'Manggarai Barat', 'Manggarai Timur', 'Nagekeo', 'Ngada',
                'Rote Ndao', 'Sabu Raijua', 'Sikka', 'Sumba Barat', 'Sumba Barat Daya',
                'Sumba Tengah', 'Sumba Timur', 'Timor Tengah Selatan', 'Timor Tengah Utara'
            ],
            
            'Kalimantan Barat' => [
                'Pontianak', 'Singkawang',
                'Bengkayang', 'Kapuas Hulu', 'Kayong Utara', 'Ketapang', 'Kubu Raya',
                'Landak', 'Melawi', 'Mempawah', 'Sambas', 'Sanggau', 'Sekadau', 'Sintang'
            ],
            
            'Kalimantan Tengah' => [
                'Palangka Raya',
                'Barito Selatan', 'Barito Timur', 'Barito Utara', 'Gunung Mas',
                'Kapuas', 'Katingan', 'Kotawaringin Barat', 'Kotawaringin Timur',
                'Lamandau', 'Murung Raya', 'Pulang Pisau', 'Seruyan', 'Sukamara'
            ],
            
            'Kalimantan Selatan' => [
                'Banjarmasin', 'Banjarbaru',
                'Balangan', 'Banjar', 'Barito Kuala', 'Hulu Sungai Selatan',
                'Hulu Sungai Tengah', 'Hulu Sungai Utara', 'Kotabaru', 'Tabalong',
                'Tanah Bumbu', 'Tanah Laut', 'Tapin'
            ],
            
            'Kalimantan Timur' => [
                'Balikpapan', 'Bontang', 'Samarinda',
                'Berau', 'Kutai Barat', 'Kutai Kartanegara', 'Kutai Timur',
                'Mahakam Ulu', 'Paser', 'Penajam Paser Utara'
            ],
            
            'Kalimantan Utara' => [
                'Tarakan',
                'Bulungan', 'Malinau', 'Nunukan', 'Tana Tidung'
            ],
            
            'Sulawesi Utara' => [
                'Manado', 'Bitung', 'Kotamobagu', 'Tomohon',
                'Bolaang Mongondow', 'Bolaang Mongondow Selatan', 'Bolaang Mongondow Timur',
                'Bolaang Mongondow Utara', 'Kepulauan Sangihe', 'Kepulauan Siau Tagulandang Biaro',
                'Kepulauan Talaud', 'Minahasa', 'Minahasa Selatan', 'Minahasa Tenggara',
                'Minahasa Utara'
            ],
            
            'Sulawesi Tengah' => [
                'Palu',
                'Banggai', 'Banggai Kepulauan', 'Banggai Laut', 'Buol', 'Donggala',
                'Morowali', 'Morowali Utara', 'Parigi Moutong', 'Poso', 'Sigi',
                'Tojo Una-Una', 'Toli-Toli'
            ],
            
            'Sulawesi Selatan' => [
                'Makassar', 'Palopo', 'Parepare',
                'Bantaeng', 'Barru', 'Bone', 'Bulukumba', 'Enrekang', 'Gowa',
                'Jeneponto', 'Kepulauan Selayar', 'Luwu', 'Luwu Timur', 'Luwu Utara',
                'Maros', 'Pangkajene dan Kepulauan', 'Pinrang', 'Sidenreng Rappang',
                'Sinjai', 'Soppeng', 'Takalar', 'Tana Toraja', 'Toraja Utara', 'Wajo'
            ],
            
            'Sulawesi Tenggara' => [
                'Kendari', 'Baubau',
                'Bombana', 'Buton', 'Buton Selatan', 'Buton Tengah', 'Buton Utara',
                'Kolaka', 'Kolaka Timur', 'Kolaka Utara', 'Konawe', 'Konawe Kepulauan',
                'Konawe Selatan', 'Konawe Utara', 'Muna', 'Muna Barat', 'Wakatobi'
            ],
            
            'Gorontalo' => [
                'Gorontalo',
                'Boalemo', 'Bone Bolango', 'Gorontalo', 'Gorontalo Utara', 'Pohuwato'
            ],
            
            'Sulawesi Barat' => [
                'Mamuju',
                'Majene', 'Mamasa', 'Mamuju', 'Mamuju Tengah', 'Mamuju Utara', 'Polewali Mandar'
            ],
            
            'Maluku' => [
                'Ambon', 'Tual',
                'Buru', 'Buru Selatan', 'Kepulauan Aru', 'Maluku Barat Daya',
                'Maluku Tengah', 'Maluku Tenggara', 'Maluku Tenggara Barat', 'Seram Bagian Barat',
                'Seram Bagian Timur'
            ],
            
            'Maluku Utara' => [
                'Ternate', 'Tidore Kepulauan',
                'Halmahera Barat', 'Halmahera Selatan', 'Halmahera Tengah', 'Halmahera Timur',
                'Halmahera Utara', 'Kepulauan Sula', 'Pulau Morotai', 'Pulau Taliabu'
            ], 
            'Papua' => [
                'Jayapura', 'Biak Numfor', 'Jayapura', 'Keerom', 'Kepulauan Yapen',
                'Mamberamo Raya', 'Sarmi', 'Supiori', 'Waropen'
            ],
            'Papua Barat' => [
                'Manokwari', 'Fakfak', 'Kaimana', 'Manokwari Selatan', 'Pegunungan Arfak',
                'Teluk Bintuni', 'Teluk Wondama'
            ],
            'Papua Selatan' => [
                'Merauke', 'Asmat', 'Boven Digoel', 'Mappi'
            ],
            'Papua Tengah' => [
                'Nabire', 'Mimika', 'Paniai', 'Puncak Jaya', 'Puncak', 'Dogiyai', 
                'Intan Jaya', 'Deiyai'
            ],
            'Papua Pegunungan' => [
                'Jayawijaya', 'Lanny Jaya', 'Tolikara', 'Mamberamo Tengah', 'Yalimo',
                'Nduga', 'Pegunungan Bintang', 'Yahukimo'
            ],
            'Papua Barat Daya' => [
                'Sorong', 'Sorong Selatan', 'Raja Ampat', 'Maybrat', 'Tambrauw'
            ],
        ];
        
        return $cities[$region] ?? [];
    }

    /**
     * Generate (or re-generate) an OneDrive folder + anonymous edit link for a project.
     * Struktur hierarki: DELIVERY PROJECT / {Customer} / {ProjectFolder}
     * Idempotent: if a folder already exists, only the share link is recreated.
     */
    public function generateFolder(Request $request, DeliveryProject $project)
    {
        $request->validate([
            'folder_name' => ['nullable', 'string', 'max:255', 'not_regex:~[\\\\/:*?"<>|]~'],
        ], [
            'folder_name.not_regex' => 'Folder name cannot contain: \\ / : * ? " < > |',
        ]);

        try {
            $oneDrive   = new OneDriveService();
            $folderName = trim($request->input('folder_name') ?: ($project->name ?? 'Project-' . $project->id));

            if ($project->onedrive_folder_id) {
                // Folder already exists — just recreate the share link
                $link = $oneDrive->createShareLink($project->onedrive_folder_id);
                $project->applyOneDriveShareLink($link);
            } else {
                // Hierarki: DELIVERY PROJECT > Customer > Project Folder
                $project->load('client.basicData');
                $deliveryProjectPath = env('ONEDRIVE_DELIVERY_PROJECT_PATH', 'DELIVERY PROJECT');
                $customerName        = strtoupper(trim($project->client->basicData->name_1 ?? 'UNKNOWN'));

                // Find or create customer folder inside DELIVERY PROJECT
                $customerFolderId = $oneDrive->findOrCreateFolderInPath($deliveryProjectPath, $customerName);

                // Create project sub-folder inside customer folder
                $folderId = $oneDrive->createSubFolder($customerFolderId, $folderName);
                $project->update(['onedrive_folder_id' => $folderId]);

                $link = $oneDrive->createShareLink($folderId);
                $project->applyOneDriveShareLink($link);
            }

            return response()->json([
                'success'          => true,
                'message'          => 'OneDrive folder ready.',
                'folder_url'       => $link['url'],
                'link_scope'       => $link['scope'],
                'link_scope_label' => $project->refresh()->onedrive_link_scope_label,
                'link_expires_at'  => $link['expires_at']?->toIso8601String(),
                // Ditampilkan sebagai peringatan di UI: link bukan "Anyone with the link",
                // jadi customer di luar tenant tidak akan bisa membukanya.
                'link_warning'     => $project->onedrive_link_warning,
            ]);

        } catch (\Exception $e) {
            Log::error('OneDrive generateFolder failed (project)', [
                'project_id' => $project->id,
                'error'      => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate OneDrive folder: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload a file to the project's OneDrive folder and save metadata to DB.
     */
    public function uploadDocument(Request $request, DeliveryProject $project)
    {
        if (!$project->onedrive_folder_id) {
            return response()->json([
                'success' => false,
                'message' => 'OneDrive folder has not been created. Please create the folder first.',
            ], 422);
        }

        $request->validate([
            'file'          => 'required|file|max:102400',
            'document_name' => 'nullable|string|max:255',
            'document_type' => 'required|string|max:100',
        ], [
            'file.max' => 'Ukuran file maksimal 100 MB.',
        ]);

        $file         = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $docName      = $request->input('document_name') ?: $originalName;

        try {
            $oneDrive = new OneDriveService();

            $result   = $oneDrive->uploadFile(
                $project->onedrive_folder_id,
                $originalName,
                file_get_contents($file->getRealPath()),
                $file->getMimeType() ?: 'application/octet-stream'
            );

            // Create anonymous view link for the uploaded file
            $shareUrl = $oneDrive->createAnonymousLink($result['id'], 'view');

            $document = $project->documents()->create([
                'document_name' => $docName,
                'link_document' => $shareUrl,
                'document_type' => $request->document_type,
            ]);

            return response()->json([
                'success'  => true,
                'message'  => 'Document uploaded successfully.',
                'document' => [
                    'id'            => $document->id,
                    'document_name' => $document->document_name,
                    'link_document' => $document->link_document,
                    'document_type' => $document->document_type,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Document upload to OneDrive failed', [
                'project_id' => $project->id,
                'error'      => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a OneDrive upload session so the client can upload the file directly.
     * Returns only an uploadUrl — no file data passes through this server.
     */
    public function createDocumentUploadSession(Request $request, DeliveryProject $project)
    {
        if (!$project->onedrive_folder_id) {
            return response()->json([
                'success' => false,
                'message' => 'OneDrive folder has not been created for this project.',
            ], 422);
        }

        $request->validate([
            'filename'      => 'required|string|max:255',
            'document_name' => 'nullable|string|max:255',
            'document_type' => 'required|string|max:100',
        ]);

        try {
            $oneDrive  = new OneDriveService();
            $uploadUrl = $oneDrive->createUploadSession(
                $project->onedrive_folder_id,
                $request->input('filename')
            );

            return response()->json([
                'success'    => true,
                'upload_url' => $uploadUrl,
            ]);
        } catch (\Exception $e) {
            Log::error('createDocumentUploadSession failed', [
                'project_id' => $project->id,
                'error'      => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create upload session: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * After the client has uploaded the file directly to OneDrive,
     * create a share link and save the document metadata to the database.
     */
    public function finalizeDocumentUpload(Request $request, DeliveryProject $project)
    {
        if (!$project->onedrive_folder_id) {
            return response()->json([
                'success' => false,
                'message' => 'OneDrive folder not found.',
            ], 422);
        }

        $request->validate([
            'onedrive_item_id' => 'required|string',
            'document_name'    => 'nullable|string|max:255',
            'document_type'    => 'required|string|max:100',
            'filename'         => 'required|string|max:255',
        ]);

        try {
            $oneDrive = new OneDriveService();
            $shareUrl = $oneDrive->createAnonymousLink($request->input('onedrive_item_id'), 'view');

            $docName  = $request->input('document_name') ?: $request->input('filename');
            $document = $project->documents()->create([
                'document_name' => $docName,
                'link_document' => $shareUrl,
                'document_type' => $request->input('document_type'),
            ]);

            return response()->json([
                'success'  => true,
                'message'  => 'Document uploaded successfully.',
                'document' => [
                    'id'            => $document->id,
                    'document_name' => $document->document_name,
                    'link_document' => $document->link_document,
                    'document_type' => $document->document_type,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('finalizeDocumentUpload failed', [
                'project_id' => $project->id,
                'error'      => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to finalize upload: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function deleteFolder(Request $request, DeliveryProject $project)
    {
        if (!$project->onedrive_folder_id) {
            return response()->json(['success' => false, 'message' => 'No folder to delete.'], 400);
        }

        try {
            (new OneDriveService())->deleteFolder($project->onedrive_folder_id);
            $project->update([
                'onedrive_folder_id'       => null,
                'onedrive_folder_url'      => null,
                'onedrive_link_scope'      => null,
                'onedrive_link_expires_at' => null,
                'onedrive_link_checked_at' => null,
            ]);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('OneDrive deleteFolder failed (project)', [
                'project_id' => $project->id,
                'error'      => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}