@extends('dashboard')
@section('title', 'Add New Project')
@section('page-title', 'Add New Project')
@section('page-subtitle', 'Create a new delivery project')
@section('content')
<form action="{{ route('projects.store') }}" method="POST">
    @csrf
    
    {{-- Basic Project Information --}}
    <div class="bg-white overflow-hidden shadow-md sm:rounded-lg mb-6">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-xl font-semibold text-gray-700">New Project Form</h3>
            <p class="mt-1 text-sm text-gray-600">Fill in the project details below.</p>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="client_id" class="block font-medium text-sm text-gray-700">Customer/Client <span class="text-red-500">*</span></label>
                <select name="client_id" id="client_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                    <option value="">-- Select Client --</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->customer_id }}" {{ old('client_id') == $client->customer_id ? 'selected' : '' }}>
                            {{ $client->basicData->name_1 ?? $client->email ?? 'Unknown' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="pic" class="block font-medium text-sm text-gray-700">PIC / Project Manager <span class="text-red-500">*</span></label>
                <select name="pic" id="pic" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                    <option value="">-- Select Project Manager --</option>
                    @foreach($projectManagers as $pm)
                        <option value="{{ $pm->basicData->full_name ?? '-' }}" {{ old('pic') == ($pm->basicData->full_name ?? '-') ? 'selected' : '' }}>
                            {{ $pm->basicData->full_name ?? '-' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="project_type" class="block font-medium text-sm text-gray-700">Project Type <span class="text-red-500">*</span></label>
                <select name="project_type" id="project_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
                    <option value="Implementation" {{ old('project_type') == 'Implementation' ? 'selected' : '' }}>Implementation</option>
                    <option value="Roll Out" {{ old('project_type') == 'Roll Out' ? 'selected' : '' }}>Roll Out</option>
                    <option value="Migration" {{ old('project_type') == 'Migration' ? 'selected' : '' }}>Migration</option>
                    <option value="Upgrade" {{ old('project_type') == 'Upgrade' ? 'selected' : '' }}>Upgrade</option>
                    <option value="WRICEF" {{ old('project_type') == 'WRICEF' ? 'selected' : '' }}>WRICEF</option>
                </select>
            </div>
            <!-- Category (Readonly) -->
            <div>
                <label for="category" class="block font-medium text-sm text-gray-700">Category</label>
                <input type="text" 
                       name="category" 
                       id="category" 
                       class="mt-1 block w-full bg-gray-100 cursor-not-allowed border-gray-300 rounded-md shadow-sm" 
                       value="{{ old('category') }}"
                       readonly>
                <p class="mt-1 text-xs text-gray-500">Auto-filled from Project Planning.</p>
            </div>
            <!-- Phase (Readonly) -->
            <div>
                <label for="phase" class="block font-medium text-sm text-gray-700">Phase</label>
                <input type="text" 
                       name="phase" 
                       id="phase" 
                       class="mt-1 block w-full bg-gray-100 cursor-not-allowed border-gray-300 rounded-md shadow-sm" 
                       value="{{ old('phase') }}"
                       readonly>
                <p class="mt-1 text-xs text-gray-500">Auto-filled from Project Planning.</p>
            </div>
            <div class="md:col-span-2">
                <label for="name" class="block font-medium text-sm text-gray-700">Project Name <span class="text-red-500">*</span></label>
                <input type="text" 
                       name="name" 
                       id="name" 
                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" 
                       value="{{ old('name') }}"
                       required>
            </div>
            <div class="md:col-span-2">
                <label for="description" class="block font-medium text-sm text-gray-700">Description <span class="text-red-500">*</span></label>
                <textarea name="description" 
                          id="description" 
                          rows="4" 
                          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" 
                          required>{{ old('description') }}</textarea>
            </div>
            <div>
                <label for="start_date" class="block font-medium text-sm text-gray-700">Start Date</label>
                <input type="date" 
                       name="start_date" 
                       id="start_date" 
                       class="mt-1 block w-full bg-gray-100 cursor-not-allowed border-gray-300 rounded-md shadow-sm" 
                       value="{{ old('start_date') }}"
                       readonly>
                <p class="mt-1 text-xs text-gray-500">Auto-filled from Project Planning.</p>
            </div>
            <div>
                <label for="end_date" class="block font-medium text-sm text-gray-700">End Date</label>
                <input type="date" 
                       name="end_date" 
                       id="end_date" 
                       class="mt-1 block w-full bg-gray-100 cursor-not-allowed border-gray-300 rounded-md shadow-sm" 
                       value="{{ old('end_date') }}"
                       readonly>
                <p class="mt-1 text-xs text-gray-500">Auto-filled from Project Planning.</p>
            </div>
            <div>
                <label for="go_live_estimated" class="block font-medium text-sm text-gray-700">Go Live Estimated</label>
                <input type="date" 
                       name="go_live_estimated" 
                       id="go_live_estimated" 
                       class="mt-1 block w-full bg-gray-100 cursor-not-allowed border-gray-300 rounded-md shadow-sm" 
                       value="{{ old('go_live_estimated') }}"
                       readonly>
                <p class="mt-1 text-xs text-gray-500">Auto-filled from Project Planning.</p>
            </div>
        </div>
    </div>

    {{-- Delivery Information Section --}}
    <div class="bg-white overflow-hidden shadow-md sm:rounded-lg mb-6">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-xl font-semibold text-gray-700">Delivery Information</h3>
            <p class="mt-1 text-sm text-gray-600">Delivery and sales information (optional)</p>
        </div>
        <div class="p-6">
            {{-- Sales Data Section --}}
            <div class="border-t border-gray-200 pt-6 mb-6">
                <h4 class="text-lg font-medium text-gray-900 mb-4">Sales Data</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label for="ae_type" class="block text-sm font-medium text-gray-700 mb-1">
                            Account Executive Type
                        </label>
                        <select name="ae_type" id="ae_type" 
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                onchange="toggleAEFields()">
                            <option value="">-- Select Type --</option>
                            <option value="Internal" {{ old('ae_type') == 'Internal' ? 'selected' : '' }}>Internal</option>
                            <option value="External" {{ old('ae_type') == 'External' ? 'selected' : '' }}>External</option>
                        </select>
                    </div>
                    
                    <div id="ae_name_container">
                        <label for="ae_name" class="block text-sm font-medium text-gray-700 mb-1">
                            Account Executive Name
                        </label>
                        <select name="ae_employee_id" id="ae_employee_select"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                style="display: none;" onchange="fillAEInfo()">
                            <option value="">-- Select Employee --</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->employee_id }}"
                                        data-phone=""
                                        data-email="">
                                    {{ $employee->basicData->full_name ?? '-' }}
                                </option>
                            @endforeach
                        </select>
                        <input type="text" name="ae_name" id="ae_name_input" 
                               value="{{ old('ae_name') }}"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    
                    <div>
                        <label for="ae_phone" class="block text-sm font-medium text-gray-700 mb-1">
                            Phone Number
                        </label>
                        <input type="text" name="ae_phone" id="ae_phone" 
                               value="{{ old('ae_phone') }}"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    
                    <div>
                        <label for="ae_email" class="block text-sm font-medium text-gray-700 mb-1">
                            Email
                        </label>
                        <input type="email" name="ae_email" id="ae_email" 
                               value="{{ old('ae_email') }}"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>
            </div>

            {{-- Delivery Data Section --}}
            <div class="border-t border-gray-200 pt-6">
                <h4 class="text-lg font-medium text-gray-900 mb-4">Delivery Data</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label for="delivery_owner_id" class="block text-sm font-medium text-gray-700 mb-1">
                            Delivery Owner
                        </label>
                        <select name="delivery_owner_id" id="delivery_owner_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">-- Select Employee --</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->employee_id }}" {{ old('delivery_owner_id') == $employee->employee_id ? 'selected' : '' }}>
                                    {{ $employee->basicData->full_name ?? '-' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label for="delivery_manager_id" class="block text-sm font-medium text-gray-700 mb-1">
                            Delivery Manager
                        </label>
                        <select name="delivery_manager_id" id="delivery_manager_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">-- Select Employee --</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->employee_id }}" {{ old('delivery_manager_id') == $employee->employee_id ? 'selected' : '' }}>
                                    {{ $employee->basicData->full_name ?? '-' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label for="delivery_method" class="block text-sm font-medium text-gray-700 mb-1">
                            Delivery Method
                        </label>
                        <select name="delivery_method" id="delivery_method" 
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">-- Select Method --</option>
                            <option value="Onsite" {{ old('delivery_method') == 'Onsite' ? 'selected' : '' }}>Onsite</option>
                            <option value="Hybrid" {{ old('delivery_method') == 'Hybrid' ? 'selected' : '' }}>Hybrid</option>
                            <option value="WFH" {{ old('delivery_method') == 'WFH' ? 'selected' : '' }}>WFH</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="warranty_period" class="block text-sm font-medium text-gray-700 mb-1">
                            Warranty Period (Weeks)
                        </label>
                        <input type="number" name="warranty_period" id="warranty_period" 
                               value="{{ old('warranty_period') }}"
                               min="0"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    
                    <div>
                        <label for="total_mandays" class="block text-sm font-medium text-gray-700 mb-1">
                            Total Mandays
                        </label>
                        <input type="number" name="total_mandays" id="total_mandays" 
                               value="{{ old('total_mandays') }}"
                               min="0"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Location Information Section --}}
    <div class="bg-white overflow-hidden shadow-md sm:rounded-lg mb-6">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-xl font-semibold text-gray-700">Location Information</h3>
            <p class="mt-1 text-sm text-gray-600">Project location information (optional)</p>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                <div>
                    <label for="location_name" class="block text-sm font-medium text-gray-700 mb-1">
                        Location Name
                    </label>
                    <input type="text" name="location_name" id="location_name" 
                           value="{{ old('location_name') }}"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                
                <div>
                    <label for="location_type" class="block text-sm font-medium text-gray-700 mb-1">
                        Type of Address
                    </label>
                    <select name="location_type" id="location_type" 
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">-- Select Type --</option>
                        <option value="Head Office" {{ old('location_type') == 'Head Office' ? 'selected' : '' }}>Head Office</option>
                        <option value="Plant" {{ old('location_type') == 'Plant' ? 'selected' : '' }}>Plant</option>
                    </select>
                </div>
                
                <div>
                    <label for="location_country" class="block text-sm font-medium text-gray-700 mb-1">
                        Country
                    </label>
                    <input type="text" name="location_country" id="location_country" 
                           value="Indonesia"
                           readonly
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm bg-gray-50">
                </div>
                
                <div>
                    <label for="location_geographical" class="block text-sm font-medium text-gray-700 mb-1">
                        Geographical
                    </label>
                    <select name="location_geographical" id="location_geographical" 
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            onchange="updateRegions()">
                        <option value="">-- Select Geographical --</option>
                        <option value="Jawa" {{ old('location_geographical') == 'Jawa' ? 'selected' : '' }}>Jawa</option>
                        <option value="Sumatera" {{ old('location_geographical') == 'Sumatera' ? 'selected' : '' }}>Sumatera</option>
                        <option value="Bali & N.Tenggara" {{ old('location_geographical') == 'Bali & N.Tenggara' ? 'selected' : '' }}>Bali & N.Tenggara</option>
                        <option value="Kalimantan" {{ old('location_geographical') == 'Kalimantan' ? 'selected' : '' }}>Kalimantan</option>
                        <option value="Sulawesi" {{ old('location_geographical') == 'Sulawesi' ? 'selected' : '' }}>Sulawesi</option>
                        <option value="Maluku" {{ old('location_geographical') == 'Maluku' ? 'selected' : '' }}>Maluku</option>
                        <option value="Papua" {{ old('location_geographical') == 'Papua' ? 'selected' : '' }}>Papua</option>
                    </select>
                </div>
                
                <div>
                    <label for="location_region" class="block text-sm font-medium text-gray-700 mb-1">
                        Region / Province
                    </label>
                    <select name="location_region" id="location_region" 
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            onchange="updateCities()">
                        <option value="">-- Select Region --</option>
                    </select>
                </div>
                
                <div>
                    <label for="location_city" class="block text-sm font-medium text-gray-700 mb-1">
                        City
                    </label>
                    <select name="location_city" id="location_city" 
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">-- Select City --</option>
                    </select>
                </div>
                
                <div class="md:col-span-2 lg:col-span-3">
                    <label for="location_street" class="block text-sm font-medium text-gray-700 mb-1">
                        Street Address
                    </label>
                    <textarea name="location_street" id="location_street" rows="3"
                              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">{{ old('location_street') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- Submit Buttons --}}
    <div class="bg-white overflow-hidden shadow-md sm:rounded-lg">
        <div class="p-6 bg-gray-50 text-right">
            <a href="{{ route('projects.index') }}" 
               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 mr-3">
                <svg class="-ml-1 mr-2 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                Cancel
            </a>
            <button type="submit"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Save Project
            </button>
        </div>
    </div>
</form>

{{-- JavaScript for Dynamic Form --}}
<script>
// Delivery subtype data
const deliverySubtypes = {
    'Project': ['Implementation', 'Roll Out', 'Migration', 'Upgrade', 'WRICEF'],
    'Support': ['ATS', 'AMS', 'MO', 'CR']
};

const indonesiaRegions = {
    'Jawa': ['DKI Jakarta', 'Jawa Barat', 'Jawa Tengah', 'DI Yogyakarta', 'Jawa Timur', 'Banten'],
    'Sumatera': ['Aceh', 'Sumatera Utara', 'Sumatera Barat', 'Riau', 'Kepulauan Riau', 'Jambi', 'Sumatera Selatan', 'Bengkulu', 'Lampung', 'Kepulauan Bangka Belitung'],
    'Bali & N.Tenggara': ['Bali', 'Nusa Tenggara Barat', 'Nusa Tenggara Timur'],
    'Kalimantan': ['Kalimantan Barat', 'Kalimantan Tengah', 'Kalimantan Selatan', 'Kalimantan Timur', 'Kalimantan Utara'],
    'Sulawesi': ['Sulawesi Utara', 'Sulawesi Tengah', 'Sulawesi Selatan', 'Sulawesi Tenggara', 'Gorontalo', 'Sulawesi Barat'],
    'Maluku': ['Maluku', 'Maluku Utara'],
    'Papua': ['Papua', 'Papua Barat', 'Papua Selatan', 'Papua Tengah', 'Papua Pegunungan', 'Papua Barat Daya']
};

const indonesiaCities = {
    'DKI Jakarta': ['Jakarta Pusat', 'Jakarta Utara', 'Jakarta Barat', 'Jakarta Selatan', 'Jakarta Timur', 'Kepulauan Seribu'],

    'Banten' : [ 'Serang', 'Tangerang', 'Tangerang Selatan', 'Cilegon', 'Pandeglang', 'Lebak'],

    'Jawa Barat': ['Bandung', 'Bekasi', 'Bogor', 'Cirebon', 'Depok', 'Sukabumi', 'Tasikmalaya','Banjar', 'Cimahi', 'Garut', 'Indramayu', 'Karawang', 
                    'Kuningan', 'Majalengka', 'Purwakarta', 'Subang', 'Sumedang', 'Ciamis', 'Cianjur', 'Pangandaran'],

    'Jawa Tengah': ['Semarang', 'Solo', 'Magelang', 'Salatiga', 'Pekalongan', 'Tegal', 'Banyumas', 'Cilacap', 'Purbalingga', 'Banjarnegara', 'Kebumen', 
                    'Purworejo', 'Wonosobo', 'Klaten', 'Boyolali', 'Sukoharjo', 'Wonogiri', 'Karanganyar', 'Sragen', 'Grobogan', 'Blora', 'Rembang', 
                    'Pati', 'Kudus', 'Jepara', 'Demak', 'Kendal', 'Temanggung', 'Batang', 'Pemalang', 'Brebes'],

    'Jawa Timur': ['Surabaya', 'Malang', 'Sidoarjo', 'Gresik', 'Mojokerto', 'Kediri', 'Jember', 'Batu', 'Blitar', 'Madiun', 'Pasuruan', 'Probolinggo',
                    'Bangkalan', 'Banyuwangi', 'Bojonegoro', 'Bondowoso', 'Jombang', 'Lamongan', 'Lumajang', 'Magetan', 'Nganjuk', 'Ngawi', 'Pacitan',
                    'Pamekasan', 'Ponorogo', 'Sampang', 'Situbondo', 'Sumenep', 'Trenggalek', 'Tuban', 'Tulungagung'],

    'DI Yogyakarta' : ['Yogyakarta', 'Bantul', 'Sleman', 'Gunungkidul', 'Kulon Progo'],

    'Aceh' : [ 'Banda Aceh', 'Sabang', 'Langsa', 'Lhokseumawe', 'Subulussalam', 'Aceh Besar', 'Aceh Jaya', 'Aceh Selatan', 'Aceh Singkil', 'Aceh Tengah',
                'Aceh Tenggara', 'Aceh Timur', 'Aceh Utara', 'Bener Meriah', 'Bireuen', 'Gayo Lues', 'Nagan Raya', 'Pidie', 'Pidie Jaya', 'Simeulue'],
    
    'Sumatera Utara' : ['Medan', 'Binjai', 'Pematangsiantar', 'Tanjungbalai', 'Tebing Tinggi', 'Padang Sidempuan', 'Gunungsitoli', 'Sibolga',
                        'Asahan', 'Batubara', 'Dairi', 'Deli Serdang', 'Humbang Hasundutan', 'Karo', 'Labuhanbatu', 'Labuhanbatu Selatan', 'Labuhanbatu Utara',
                        'Langkat', 'Mandailing Natal', 'Nias', 'Nias Barat', 'Nias Selatan', 'Nias Utara', 'Padang Lawas', 'Padang Lawas Utara', 'Pakpak Bharat',
                        'Samosir', 'Serdang Bedagai', 'Simalungun', 'Tapanuli Selatan', 'Tapanuli Tengah', 'Tapanuli Utara', 'Toba Samosir'],
    
    'Sumatera Barat' : ['Padang', 'Bukittinggi', 'Padang Panjang', 'Pariaman', 'Payakumbuh', 'Sawahlunto', 'Solok', 'Agam', 'Dharmasraya', 'Kepulauan Mentawai', 'Lima Puluh Kota',
                        'Padang Pariaman', 'Pasaman', 'Pasaman Barat', 'Pesisir Selatan', 'Sijunjung', 'Solok Selatan', 'Tanah Datar'],
    
    'Riau' : ['Pekanbaru', 'Dumai', 'Bengkalis', 'Indragiri Hilir', 'Indragiri Hulu', 'Kampar', 'Kepulauan Meranti', 'Kuantan Singingi', 'Pelalawan', 'Rokan Hilir',
                'Rokan Hulu', 'Siak'],
    
    'Kepulauan Riau' : ['Batam', 'Tanjung Pinang', 'Bintan', 'Karimun', 'Kepulauan Anambas', 'Lingga', 'Natuna'],
    
    'Jambi': ['Jambi', 'Sungai Penuh', 'Batang Hari', 'Bungo', 'Kerinci', 'Merangin', 'Muaro Jambi', 'Sarolangun', 'Tanjung Jabung Barat', 'Tanjung Jabung Timur', 'Tebo'],
    
    'Sumatera Selatan' : ['Palembang', 'Lubuklinggau', 'Pagar Alam', 'Prabumulih', 'Banyuasin', 'Empat Lawang', 'Lahat', 'Muara Enim', 'Musi Banyuasin',
                            'Musi Rawas', 'Musi Rawas Utara', 'Ogan Ilir', 'Ogan Komering Ilir', 'Ogan Komering Ulu', 'Ogan Komering Ulu Selatan', 'Ogan Komering Ulu Timur',
                            'Penukal Abab Lematang Ilir'],
    
    'Bengkulu': ['Bengkulu', 'Bengkulu Selatan', 'Bengkulu Tengah', 'Bengkulu Utara', 'Kaur', 'Kepahiang', 'Lebong', 'Mukomuko', 'Rejang Lebong', 'Seluma'],
    
    'Lampung' :['Bandar Lampung', 'Metro', 'Lampung Barat', 'Lampung Selatan', 'Lampung Tengah', 'Lampung Timur', 'Lampung Utara', 'Mesuji', 'Pesawaran', 'Pesisir Barat', 'Pringsewu',
                'Tanggamus', 'Tulang Bawang', 'Tulang Bawang Barat', 'Way Kanan'],
    
    'Kepulauan Bangka Belitung': ['Pangkal Pinang', 'Bangka', 'Bangka Barat', 'Bangka Selatan', 'Bangka Tengah', 'Belitung', 'Belitung Timur'],
    
    'Bali' : ['Denpasar','Badung', 'Bangli', 'Buleleng', 'Gianyar', 'Jembrana', 'Karangasem', 'Klungkung', 'Tabanan'],
    
    'Nusa Tenggara Barat': ['Mataram', 'Bima', 'Dompu', 'Lombok Barat', 'Lombok Tengah', 'Lombok Timur', 'Lombok Utara', 'Sumbawa', 'Sumbawa Barat'],
    
    'Nusa Tenggara Timur' : ['Kupang', 'Alor', 'Belu', 'Ende', 'Flores Timur', 'Kupang', 'Lembata', 'Manggarai', 'Manggarai Barat', 'Manggarai Timur', 'Nagekeo', 'Ngada',
                                'Rote Ndao', 'Sabu Raijua', 'Sikka', 'Sumba Barat', 'Sumba Barat Daya', 'Sumba Tengah', 'Sumba Timur', 'Timor Tengah Selatan', 'Timor Tengah Utara'],
    
    'Kalimantan Barat': ['Pontianak', 'Singkawang', 'Bengkayang', 'Kapuas Hulu', 'Kayong Utara', 'Ketapang', 'Kubu Raya', 
                            'Landak', 'Melawi', 'Mempawah', 'Sambas', 'Sanggau', 'Sekadau', 'Sintang'],
    
    'Kalimantan Tengah' :['Palangka Raya', 'Barito Selatan', 'Barito Timur', 'Barito Utara', 'Gunung Mas', 'Kapuas', 'Katingan', 'Kotawaringin Barat', 'Kotawaringin Timur',
                            'Lamandau', 'Murung Raya', 'Pulang Pisau', 'Seruyan', 'Sukamara'],
    
    'Kalimantan Selatan': ['Banjarmasin', 'Banjarbaru', 'Balangan', 'Banjar', 'Barito Kuala', 'Hulu Sungai Selatan', 'Hulu Sungai Tengah', 'Hulu Sungai Utara', 'Kotabaru', 'Tabalong',
                            'Tanah Bumbu', 'Tanah Laut', 'Tapin'],
    
    'Kalimantan Timur' : ['Balikpapan', 'Bontang', 'Samarinda', 'Berau', 'Kutai Barat', 'Kutai Kartanegara', 'Kutai Timur', 'Mahakam Ulu', 'Paser', 'Penajam Paser Utara'],
    
    'Kalimantan Utara' :['Tarakan', 'Bulungan', 'Malinau', 'Nunukan', 'Tana Tidung'],
    
    'Sulawesi Utara' : ['Manado', 'Bitung', 'Kotamobagu', 'Tomohon', 'Bolaang Mongondow', 'Bolaang Mongondow Selatan', 'Bolaang Mongondow Timur', 'Bolaang Mongondow Utara', 
                        'Kepulauan Sangihe', 'Kepulauan Siau Tagulandang Biaro', 'Kepulauan Talaud', 'Minahasa', 'Minahasa Selatan', 'Minahasa Tenggara', 'Minahasa Utara'],
    
    'Sulawesi Tengah' : ['Palu', 'Banggai', 'Banggai Kepulauan', 'Banggai Laut', 'Buol', 'Donggala', 'Morowali', 'Morowali Utara', 'Parigi Moutong', 'Poso', 'Sigi',
                            'Tojo Una-Una', 'Toli-Toli'],
    
    'Sulawesi Selatan' : ['Makassar', 'Palopo', 'Parepare', 'Bantaeng', 'Barru', 'Bone', 'Bulukumba', 'Enrekang', 'Gowa', 'Jeneponto', 'Kepulauan Selayar', 'Luwu', 
                            'Luwu Timur', 'Luwu Utara', 'Maros', 'Pangkajene dan Kepulauan', 'Pinrang', 'Sidenreng Rappang', 'Sinjai', 'Soppeng', 'Takalar', 'Tana Toraja', 
                            'Toraja Utara', 'Wajo'],
    
    'Sulawesi Tenggara' : ['Kendari', 'Baubau', 'Bombana', 'Buton', 'Buton Selatan', 'Buton Tengah', 'Buton Utara', 'Kolaka', 'Kolaka Timur', 'Kolaka Utara', 'Konawe', 
                            'Konawe Kepulauan', 'Konawe Selatan', 'Konawe Utara', 'Muna', 'Muna Barat', 'Wakatobi'],
    
    'Gorontalo' : ['Gorontalo', 'Boalemo', 'Bone Bolango', 'Gorontalo', 'Gorontalo Utara', 'Pohuwato'],
    
    'Sulawesi Barat' : ['Mamuju', 'Majene', 'Mamasa', 'Mamuju', 'Mamuju Tengah', 'Mamuju Utara', 'Polewali Mandar'],
    
    'Maluku' : ['Ambon', 'Tual', 'Buru', 'Buru Selatan', 'Kepulauan Aru', 'Maluku Barat Daya', 'Maluku Tengah', 'Maluku Tenggara', 'Maluku Tenggara Barat', 
                'Seram Bagian Barat', 'Seram Bagian Timur'],
    
    'Maluku Utara' : ['Ternate', 'Tidore Kepulauan', 'Halmahera Barat', 'Halmahera Selatan', 'Halmahera Tengah', 'Halmahera Timur', 'Halmahera Utara', 'Kepulauan Sula', 
                        'Pulau Morotai', 'Pulau Taliabu'], 

    'Papua' : ['Jayapura', 'Biak Numfor', 'Jayapura', 'Keerom', 'Kepulauan Yapen', 'Mamberamo Raya', 'Sarmi', 'Supiori', 'Waropen'],

    'Papua Barat': ['Manokwari', 'Fakfak', 'Kaimana', 'Manokwari Selatan', 'Pegunungan Arfak', 'Teluk Bintuni', 'Teluk Wondama'],

    'Papua Selatan' : ['Merauke', 'Asmat', 'Boven Digoel', 'Mappi'],

    'Papua Tengah' : ['Nabire', 'Mimika', 'Paniai', 'Puncak Jaya', 'Puncak', 'Dogiyai', 'Intan Jaya', 'Deiyai'],

    'Papua Pegunungan' : ['Jayawijaya', 'Lanny Jaya', 'Tolikara', 'Mamberamo Tengah', 'Yalimo', 'Nduga', 'Pegunungan Bintang', 'Yahukimo'],

    'Papua Barat Daya' :['Sorong', 'Sorong Selatan', 'Raja Ampat', 'Maybrat', 'Tambrauw'],
};

// Update delivery subtype based on type selection
function updateDeliverySubtype() {
    const typeSelect = document.getElementById('delivery_type');
    const subtypeSelect = document.getElementById('delivery_subtype');
    const selectedType = typeSelect.value;
    const oldSubtype = '{{ old('delivery_subtype') }}';
    
    subtypeSelect.innerHTML = '<option value="">-- Select Sub-type --</option>';
    
    if (selectedType && deliverySubtypes[selectedType]) {
        deliverySubtypes[selectedType].forEach(subtype => {
            const option = document.createElement('option');
            option.value = subtype;
            option.textContent = subtype;
            if (oldSubtype === subtype) {
                option.selected = true;
            }
            subtypeSelect.appendChild(option);
        });
    }
}

// Toggle AE fields based on type (Internal/External)
function toggleAEFields() {
    const aeType = document.getElementById('ae_type').value;
    const aeEmployeeSelect = document.getElementById('ae_employee_select');
    const aeNameInput = document.getElementById('ae_name_input');
    const aePhone = document.getElementById('ae_phone');
    const aeEmail = document.getElementById('ae_email');
    
    if (aeType === 'Internal') {
        aeEmployeeSelect.style.display = 'block';
        aeEmployeeSelect.name = 'ae_name';
        aeNameInput.style.display = 'none';
        aeNameInput.name = '';
        aePhone.readOnly = false;
        aeEmail.readOnly = false;
    } else if (aeType === 'External') {
        aeEmployeeSelect.style.display = 'none';
        aeEmployeeSelect.name = '';
        aeNameInput.style.display = 'block';
        aeNameInput.name = 'ae_name';
        aePhone.readOnly = false;
        aeEmail.readOnly = false;
    } else {
        aeEmployeeSelect.style.display = 'none';
        aeNameInput.style.display = 'block';
        aeNameInput.name = 'ae_name';
        aePhone.readOnly = false;
        aeEmail.readOnly = false;
    }
}

// Fill AE info when Internal employee selected
function fillAEInfo() {
    const select = document.getElementById('ae_employee_select');
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value) {
        document.getElementById('ae_phone').value = selectedOption.dataset.phone || '';
        document.getElementById('ae_email').value = selectedOption.dataset.email || '';
        document.getElementById('ae_phone').readOnly = true;
        document.getElementById('ae_email').readOnly = true;
    } else {
        document.getElementById('ae_phone').value = '';
        document.getElementById('ae_email').value = '';
        document.getElementById('ae_phone').readOnly = false;
        document.getElementById('ae_email').readOnly = false;
    }
}

// Update regions based on geographical selection
function updateRegions() {
    const geoSelect = document.getElementById('location_geographical');
    const regionSelect = document.getElementById('location_region');
    const selectedGeo = geoSelect.value;
    const oldRegion = '{{ old('location_region') }}';
    
    regionSelect.innerHTML = '<option value="">-- Select Region --</option>';
    document.getElementById('location_city').innerHTML = '<option value="">-- Select City --</option>';
    
    if (selectedGeo && indonesiaRegions[selectedGeo]) {
        indonesiaRegions[selectedGeo].forEach(region => {
            const option = document.createElement('option');
            option.value = region;
            option.textContent = region;
            if (oldRegion === region) {
                option.selected = true;
            }
            regionSelect.appendChild(option);
        });
        
        // If there's an old region value, update cities too
        if (oldRegion && indonesiaRegions[selectedGeo].includes(oldRegion)) {
            updateCities();
        }
    }
}

// Update cities based on region selection
function updateCities() {
    const regionSelect = document.getElementById('location_region');
    const citySelect = document.getElementById('location_city');
    const selectedRegion = regionSelect.value;
    const oldCity = '{{ old('location_city') }}';
    
    citySelect.innerHTML = '<option value="">-- Select City --</option>';
    
    if (selectedRegion && indonesiaCities[selectedRegion]) {
        indonesiaCities[selectedRegion].forEach(city => {
            const option = document.createElement('option');
            option.value = city;
            option.textContent = city;
            if (oldCity === city) {
                option.selected = true;
            }
            citySelect.appendChild(option);
        });
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize delivery subtype if there's an old value
    @if(old('delivery_type'))
        updateDeliverySubtype();
    @endif
    
    // Initialize AE fields if there's an old value
    @if(old('ae_type'))
        toggleAEFields();
    @endif
    
    // Initialize location dropdowns if there are old values
    @if(old('location_geographical'))
        updateRegions();
    @endif
});
</script>

@endsection