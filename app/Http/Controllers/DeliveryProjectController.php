<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DeliveryProject;
use App\Models\Employee;
use App\Models\Document;
use App\Models\DeliveryProjectPlanning;
use App\Models\DeliveryProjectPhase;
use App\Models\DeliveryProjectActivity;
use App\Services\OneDriveService;
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
        $clients = Customer::with('basicData')->get()->sortBy(function($client) {
            return $client->basicData->name_1 ?? '';
        });
        $clientPicMap = $clients->pluck('pic', 'customer_id');
        $employees = Employee::with('basicData')->get();

        // Get only Project Managers for PIC dropdown
        $projectManagers = Employee::with('basicData')
            ->whereHas('basicData', function($query) {
                $query->where('position', 'Project Manager');
            })
            ->get();

        return view('delivery.project.projects.create', compact('clients', 'clientPicMap', 'employees', 'projectManagers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:customer,customer_id',
            'pic' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'project_type' => 'required|string|max:255',
            'ae_type' => 'nullable|in:Internal,External',
            'ae_name' => 'nullable|string',
            'ae_phone' => 'nullable|string',
            'ae_email' => 'nullable|email',
            'delivery_owner_id' => 'nullable|exists:employee,employee_id',
            'delivery_manager_id' => 'nullable|exists:employee,employee_id',
            'delivery_method' => 'nullable|in:Onsite,Hybrid,WFH',
            'warranty_period' => 'nullable|integer|min:0',
            'total_mandays' => 'nullable|integer|min:0',
        ]);

        // Get employee_id from session (ECOSYSTEM uses session-based auth)
        $user = session('user');
        $createdById = $user['employee_id'] ?? null;

        $projectData = [
            'client_id' => $request->client_id,
            'pic' => $request->pic,
            'name' => $request->name,
            'description' => $request->description,
            'project_type' => $request->project_type,
            'category' => 'Open',
            'phase' => null, // Will be calculated from planning phases
            'status' => 'Monitoring',
            'created_by_id' => null, // ECOSYSTEM tidak menggunakan tabel users
        ];

        $projectData = array_merge($projectData, $request->only([
            'ae_type', 'ae_name', 'ae_phone', 'ae_email',
            'delivery_owner_id', 'delivery_manager_id',
            'delivery_method', 'warranty_period', 'total_mandays'
        ]));

        try {
            $project = DeliveryProject::create($projectData);
        } catch (\Exception $e) {
            Log::error('DeliveryProjectController@store', ['error' => $e->getMessage()]);
            return redirect()->back()
                             ->withInput()
                             ->with('error', 'Failed to create the project due to a server error. Please try again.');
        }

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

            if ($plan->phase && $plan->phase->is_golive_phase) {
                $goLiveDate = $plan->end_date;
            }
        }

        $project->start_date = $firstStartDate ? $firstStartDate->toDateString() : null;
        $project->end_date = $lastEndDate ? $lastEndDate->toDateString() : null;
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

        $employees = Employee::with(['basicData', 'addresses'])->get();

        $consultants = Employee::with(['basicData', 'addresses'])
            ->whereHas('basicData', function($query) {
                $query->where('position', 'Consultant');
            })
            ->get();

        $projectManagers = Employee::with('basicData')
            ->whereHas('basicData', function($query) {
                $query->where('position', 'Project Manager');
            })
            ->get();

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

        return view('delivery.project.projects.show', compact(
            'project',
            'employees',
            'consultants',
            'projectManagers',
            'hasPlanning',
            'phases',
            'deliveryActivities',
            'finalPhaseWeights'
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
            $rules['value'] = ['required', Rule::in(['On Track', 'Monitoring', 'At Risk'])];
        } elseif ($field === 'phase') {
            $rules['value'] = ['required', Rule::in(['Prepare', 'Explore', 'Realize', 'Deploy'])];
        } elseif ($field === 'pic') {
            $rules['value'] = 'nullable|string|max:255';
        } elseif ($field === 'description') {
            $rules['value'] = 'nullable|string|max:5000';
        } else {
            $rules['value'] = 'nullable|string|max:255';
        }
        
        $request->validate($rules);

        if ($field === 'pic') {
            $project->update(['pic' => $value]);
            return back()->with('success', 'PIC updated successfully.');
        }

        if ($field === 'description') {
            $project->update(['description' => $value]);
            return back()->with('success', 'Description updated successfully.');
        }

        $project->$field = $value;
        $project->save();

        return back()->with('success', 'Project ' . ucfirst($field) . ' updated successfully.');
    }

    public function updateDeliveryInfo(Request $request, DeliveryProject $project)
    {
        $validatedData = $request->validate([
            'project_type' => 'nullable|string',
            'ae_type' => 'nullable|in:Internal,External',
            'ae_name' => 'nullable|string',
            'ae_phone' => 'nullable|string',
            'ae_email' => 'nullable|email',
            'delivery_owner_id' => 'nullable|exists:employee,employee_id',
            'delivery_manager_id' => 'nullable|exists:employee,employee_id',
            'delivery_method' => 'nullable|in:Onsite,Hybrid,WFH',
            'warranty_period' => 'nullable|integer|min:0',
            'total_mandays' => 'nullable|integer|min:0',
            'approval_date' => 'nullable|date',
            'approval_name' => 'nullable|string',
        ]);

        $project->update($validatedData);

        return back()->with('success', 'Delivery information updated successfully.');
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
            'document_type' => 'required|in:BAST/BAPP,Contract,Justification,PR/PO,Others',
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
            'link_document' => 'required|url',
            'document_type' => 'required|in:BAST/BAPP,Contract,Justification,PR/PO,Others',
        ]);

        $document->update($request->only(['document_name', 'link_document', 'document_type']));

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Document updated successfully',
                'document' => $document
            ]);
        }

        return back()->with('success', 'Document updated successfully.');
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
        $teamMembers = $project->teamMembers()
            ->with('basicData')
            ->get();

        return response()->json([
            'success' => true,
            'team_members' => $teamMembers
        ]);
    }

    public function storeTeamMember(Request $request, DeliveryProject $project)
    {
        $request->validate([
            'employee_id' => 'required|exists:employee,employee_id',
            'module' => 'nullable|string|max:50',
            'assignment' => 'required|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $exists = $project->teamMembers()
            ->wherePivot('employee_id', $request->employee_id)
            ->wherePivot('assignment', $request->assignment)
            ->exists();

        if ($exists) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This employee already has the same assignment in this project.'
                ], 422);
            }
            return back()->withErrors(['error' => 'This employee already has the same assignment in this project.']);
        }

        $project->teamMembers()->attach($request->employee_id, [
            'module' => $request->module,
            'assignment' => $request->assignment,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Team member added successfully'
            ]);
        }

        return back()->with('success', 'Team member added successfully.');
    }

    public function destroyTeamMember(DeliveryProject $project, $employeeId)
    {
        $project->teamMembers()->detach($employeeId);

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Team member removed successfully'
            ]);
        }

        return back()->with('success', 'Team member removed successfully.');
    }

    public function updateTeamMember(Request $request, DeliveryProject $project, $employeeId)
    {
        $request->validate([
            'module' => 'nullable|string|max:50',
            'assignment' => 'required|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $project->teamMembers()->updateExistingPivot($employeeId, [
            'module' => $request->module,
            'assignment' => $request->assignment,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Team member updated successfully'
            ]);
        }

        return back()->with('success', 'Team member updated successfully.');
    }

    public function destroy(DeliveryProject $project)
    {
        $project->delete();
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
                $shareUrl = $oneDrive->createAnonymousLink($project->onedrive_folder_id);
                $project->update(['onedrive_folder_url' => $shareUrl]);
            } else {
                // First time — create folder then share link
                $folderId = $oneDrive->createFolder($folderName);
                $shareUrl = $oneDrive->createAnonymousLink($folderId);
                $project->update([
                    'onedrive_folder_id'  => $folderId,
                    'onedrive_folder_url' => $shareUrl,
                ]);
            }

            return response()->json([
                'success'    => true,
                'message'    => 'OneDrive folder ready.',
                'folder_url' => $shareUrl,
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

    public function deleteFolder(Request $request, DeliveryProject $project)
    {
        if (!$project->onedrive_folder_id) {
            return response()->json(['success' => false, 'message' => 'No folder to delete.'], 400);
        }

        try {
            (new OneDriveService())->deleteFolder($project->onedrive_folder_id);
            $project->update(['onedrive_folder_id' => null, 'onedrive_folder_url' => null]);
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