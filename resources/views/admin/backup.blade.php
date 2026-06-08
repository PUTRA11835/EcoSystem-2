@extends('dashboard')

@section('title', 'Backup & Export')
@section('page-title', 'Backup & Export')
@section('page-subtitle', 'Database backup, data export, and CSV import — admin only')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="space-y-5">

    {{-- ── Row 1: DB Backup + Ticket Export ── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- DB Backup --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm flex flex-col">
            <div class="px-5 pt-5 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                        <i class="fas fa-database text-blue-600"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Database Backup</h3>
                        <p class="text-xs text-gray-400">Full mysqldump backup</p>
                    </div>
                </div>
            </div>
            <div class="p-5 flex-1 flex flex-col gap-3">
                <button id="btnCreateBackup"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition">
                    <i class="fas fa-play text-xs"></i> Create Backup Now
                </button>

                <div id="backupProgress" class="hidden items-center gap-2 text-sm text-blue-600">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>Running backup, please wait...</span>
                </div>

                <div class="border-t border-gray-100 pt-3">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-medium text-gray-500">Existing Backups</p>
                        <button onclick="loadBackups()" class="text-xs text-blue-600 hover:underline">Refresh</button>
                    </div>
                    <div id="backupList" class="space-y-2 max-h-56 overflow-y-auto">
                        <p class="text-xs text-gray-400 py-2 text-center">Loading...</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Ticket Export --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm flex flex-col">
            <div class="px-5 pt-5 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center">
                        <i class="fas fa-ticket-alt text-orange-500"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Ticket Export</h3>
                        <p class="text-xs text-gray-400">Filter by period, download CSV</p>
                    </div>
                </div>
            </div>
            <div class="p-5 flex-1 flex flex-col gap-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Year</label>
                        <select id="ticketYear" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                            @for($y = date('Y'); $y >= 2024; $y--)
                                <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Month</label>
                        <select id="ticketMonth" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                            <option value="0">All Months</option>
                            <option value="1">January</option><option value="2">February</option>
                            <option value="3">March</option><option value="4">April</option>
                            <option value="5">May</option><option value="6">June</option>
                            <option value="7">July</option><option value="8">August</option>
                            <option value="9">September</option><option value="10">October</option>
                            <option value="11">November</option><option value="12">December</option>
                        </select>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-xl p-4 text-sm text-gray-600 space-y-1">
                    <p class="flex items-center gap-2"><i class="fas fa-check text-orange-400 text-xs"></i> Ticket number, subject, status, priority</p>
                    <p class="flex items-center gap-2"><i class="fas fa-check text-orange-400 text-xs"></i> Type, category, customer & company</p>
                    <p class="flex items-center gap-2"><i class="fas fa-check text-orange-400 text-xs"></i> PIC info, created & updated date</p>
                </div>
                <div class="mt-auto">
                    <button id="btnExportTickets"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-orange-500 text-white text-sm font-medium hover:bg-orange-600 transition">
                        <i class="fas fa-download text-xs"></i> Download CSV
                    </button>
                </div>
            </div>
        </div>

    </div>

    {{-- ── Row 2: Ticket Migration (full ZIP with history & attachments) ── --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm flex flex-col">
        <div class="px-5 pt-5 pb-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center">
                    <i class="fas fa-archive text-red-600"></i>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Ticket Migration (Full ZIP)</h3>
                    <p class="text-xs text-gray-400">Export/import tiket beserta chat history, lampiran, dan semua metadata</p>
                </div>
            </div>
        </div>
        <div class="p-5 grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Export --}}
            <div class="flex flex-col gap-3">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Export ZIP</p>
                <div class="bg-gray-50 rounded-xl p-3 text-xs text-gray-600 space-y-1">
                    <p class="flex items-center gap-2"><i class="fas fa-check text-red-400"></i> Semua data tiket (status, prioritas, PIC, customer)</p>
                    <p class="flex items-center gap-2"><i class="fas fa-check text-red-400"></i> Chat history lengkap (pesan publik & internal note)</p>
                    <p class="flex items-center gap-2"><i class="fas fa-check text-red-400"></i> File lampiran (gambar, dokumen) disertakan dalam ZIP</p>
                    <p class="flex items-center gap-2"><i class="fas fa-check text-red-400"></i> Timestamp asli, member, metadata CC &amp; mention</p>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Tahun</label>
                        <select id="migYear" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                            <option value="0">Semua Tahun</option>
                            @for($y = date('Y'); $y >= 2024; $y--)
                                <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Bulan</label>
                        <select id="migMonth" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-300">
                            <option value="0">Semua Bulan</option>
                            <option value="1">Januari</option><option value="2">Februari</option>
                            <option value="3">Maret</option><option value="4">April</option>
                            <option value="5">Mei</option><option value="6">Juni</option>
                            <option value="7">Juli</option><option value="8">Agustus</option>
                            <option value="9">September</option><option value="10">Oktober</option>
                            <option value="11">November</option><option value="12">Desember</option>
                        </select>
                    </div>
                </div>
                <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-700">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Proses ekspor bisa memakan waktu beberapa menit tergantung jumlah tiket dan ukuran lampiran.
                </div>
                <button id="btnExportZip"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-red-600 text-white text-sm font-medium hover:bg-red-700 transition">
                    <i class="fas fa-file-archive text-xs"></i> Download ZIP
                </button>
            </div>

            {{-- Import --}}
            <div class="flex flex-col gap-3">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Import ZIP</p>
                <div class="bg-gray-50 rounded-xl p-3 text-xs text-gray-600 space-y-1">
                    <p class="flex items-center gap-2"><i class="fas fa-info-circle text-blue-400"></i> Upload file ZIP hasil export di atas</p>
                    <p class="flex items-center gap-2"><i class="fas fa-info-circle text-blue-400"></i> Tiket yang sudah ada (ticket_number sama) akan diperbarui</p>
                    <p class="flex items-center gap-2"><i class="fas fa-info-circle text-blue-400"></i> Pesan yang sudah ada tidak akan digandakan</p>
                    <p class="flex items-center gap-2"><i class="fas fa-info-circle text-blue-400"></i> File lampiran akan dipulihkan ke storage</p>
                </div>
                <div id="migDropzone"
                    class="border-2 border-dashed border-gray-200 rounded-xl p-4 text-center cursor-pointer hover:border-red-400 hover:bg-red-50 transition-colors"
                    onclick="document.getElementById('migFileInput').click()"
                    ondragover="event.preventDefault();this.classList.add('border-red-400','bg-red-50')"
                    ondragleave="this.classList.remove('border-red-400','bg-red-50')"
                    ondrop="handleMigDrop(event)">
                    <i class="fas fa-cloud-upload-alt text-gray-300 text-2xl mb-1"></i>
                    <p class="text-xs text-gray-400">Drop ZIP di sini atau <span class="text-red-600 font-medium">pilih file</span></p>
                    <p id="migFileName" class="text-xs text-gray-500 mt-1 hidden"></p>
                </div>
                <input type="file" id="migFileInput" accept=".zip" class="hidden" onchange="onMigFileSelect(event)">
                <button id="btnImportZip" onclick="runMigImport()"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-red-100 text-red-700 text-sm font-medium hover:bg-red-200 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    disabled>
                    <i class="fas fa-upload text-xs"></i> Import ZIP
                </button>
                <div id="migResult" class="hidden"></div>
            </div>

        </div>
    </div>

    {{-- ── Row 3: Import dari Sistem Lama (API pull) ── --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm flex flex-col">
        <div class="px-5 pt-5 pb-4 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center">
                    <i class="fas fa-cloud-download-alt text-indigo-600"></i>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Import Tiket dari Sistem Lama (API)</h3>
                    <p class="text-xs text-gray-400">Tarik data tiket + chat history langsung dari API sistem lama</p>
                </div>
            </div>
        </div>
        <div class="p-5 flex flex-col gap-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Left: config --}}
                <div class="flex flex-col gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">URL Pattern Endpoint API <span class="text-red-500">*</span></label>
                        <input type="text" id="apiUrlPattern" placeholder="https://domain.com/sap/zapi?sap-client=100&pgmna=ZJAR009&P_OBJID={ticket_number}"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 font-mono">
                        <p class="text-xs text-gray-400 mt-1">Ganti <code class="bg-gray-100 px-1 rounded">domain.com</code> dengan domain sistem lama. <code class="bg-gray-100 px-1 rounded">{ticket_number}</code> akan diganti otomatis per tiket.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Status Default Tiket yang Diimport</label>
                        <select id="apiDefaultStatus" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                            <option value="closed">Closed</option>
                            <option value="open">Open</option>
                            <option value="inprocess">In Process</option>
                            <option value="waiting_to_confirmation">Waiting to Confirmation</option>
                        </select>
                    </div>
                    <div class="bg-indigo-50 rounded-xl p-3 text-xs text-indigo-700 space-y-1">
                        <p class="font-semibold mb-1">Yang akan diimport:</p>
                        <p class="flex items-center gap-2"><i class="fas fa-check text-indigo-400"></i> Metadata tiket: nama, divisi, modul, no HP, email, prioritas</p>
                        <p class="flex items-center gap-2"><i class="fas fa-check text-indigo-400"></i> Chat history lengkap dengan timestamp asli</p>
                        <p class="flex items-center gap-2"><i class="fas fa-check text-indigo-400"></i> Deteksi otomatis pesan dari customer vs support team</p>
                    </div>
                </div>
                {{-- Right: CSV upload --}}
                <div class="flex flex-col gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">CSV Daftar Nomor Tiket <span class="text-red-500">*</span></label>
                        <div id="apiDropzone"
                            class="border-2 border-dashed border-gray-200 rounded-xl p-4 text-center cursor-pointer hover:border-indigo-400 hover:bg-indigo-50 transition-colors"
                            onclick="document.getElementById('apiFileInput').click()"
                            ondragover="event.preventDefault();this.classList.add('border-indigo-400','bg-indigo-50')"
                            ondragleave="this.classList.remove('border-indigo-400','bg-indigo-50')"
                            ondrop="handleApiDrop(event)">
                            <i class="fas fa-file-csv text-gray-300 text-2xl mb-1"></i>
                            <p class="text-xs text-gray-400">Drop CSV di sini atau <span class="text-indigo-600 font-medium">pilih file</span></p>
                            <p class="text-xs text-gray-300 mt-0.5">Satu kolom berisi nomor tiket, satu baris satu nomor</p>
                            <p id="apiFileName" class="text-xs text-gray-500 mt-1 hidden font-medium"></p>
                        </div>
                        <input type="file" id="apiFileInput" accept=".csv,.txt" class="hidden" onchange="onApiFileSelect(event)">
                    </div>
                    <div class="bg-gray-50 rounded-xl p-3 text-xs text-gray-500">
                        <p class="font-semibold text-gray-600 mb-1">Format CSV:</p>
                        <pre class="font-mono text-gray-500">ticket_number
100000123
100000124
100000125</pre>
                    </div>
                    <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-700">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Proses bisa memakan waktu — ±1–3 detik per tiket tergantung kecepatan koneksi ke API lama.
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button id="btnApiImport" onclick="runApiImport()"
                    class="flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    disabled>
                    <i class="fas fa-cloud-download-alt text-xs"></i> Mulai Import dari API
                </button>
                <span id="apiProgress" class="hidden text-xs text-indigo-600 flex items-center gap-1">
                    <svg class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                    Sedang menarik data dari API...
                </span>
            </div>
            <div id="apiResult" class="hidden"></div>
        </div>
    </div>

    {{-- ── Row 4: Employee + Customer (Export & Import) ── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- Employee Card --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm flex flex-col">
            <div class="px-5 pt-5 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center">
                        <i class="fas fa-users text-emerald-600"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Employee Data</h3>
                        <p class="text-xs text-gray-400">Export CSV or import from CSV</p>
                    </div>
                </div>
            </div>
            <div class="p-5 flex-1 flex flex-col gap-4">

                {{-- Export section --}}
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Export</p>
                    <div class="bg-gray-50 rounded-xl p-3 text-xs text-gray-600 space-y-1 mb-3">
                        <p class="flex items-center gap-2"><i class="fas fa-check text-emerald-500"></i> Employee ID, ECI, status, role</p>
                        <p class="flex items-center gap-2"><i class="fas fa-check text-emerald-500"></i> Full name, gender, birth, position</p>
                        <p class="flex items-center gap-2"><i class="fas fa-check text-emerald-500"></i> Division, department, since date</p>
                    </div>
                    <a href="{{ route('admin.export.employees') }}"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 transition">
                        <i class="fas fa-download text-xs"></i> Download CSV
                    </a>
                </div>

                <div class="border-t border-gray-100"></div>

                {{-- Import section --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Import</p>
                        <a href="{{ route('admin.import.template.employees') }}" class="text-xs text-emerald-600 hover:underline flex items-center gap-1">
                            <i class="fas fa-file-csv text-xs"></i> Download Template
                        </a>
                    </div>
                    <div id="empDropzone"
                        class="border-2 border-dashed border-gray-200 rounded-xl p-4 text-center cursor-pointer hover:border-emerald-400 hover:bg-emerald-50 transition-colors"
                        onclick="document.getElementById('empFileInput').click()"
                        ondragover="event.preventDefault();this.classList.add('border-emerald-400','bg-emerald-50')"
                        ondragleave="this.classList.remove('border-emerald-400','bg-emerald-50')"
                        ondrop="handleDrop(event,'emp')">
                        <i class="fas fa-cloud-upload-alt text-gray-300 text-2xl mb-1"></i>
                        <p class="text-xs text-gray-400">Drop CSV here or <span class="text-emerald-600 font-medium">browse</span></p>
                        <p id="empFileName" class="text-xs text-gray-500 mt-1 hidden"></p>
                    </div>
                    <input type="file" id="empFileInput" accept=".csv,.txt" class="hidden" onchange="onFileSelect(event,'emp')">
                    <button id="btnImportEmp" onclick="runImport('emp')"
                        class="w-full mt-2 flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-emerald-100 text-emerald-700 text-sm font-medium hover:bg-emerald-200 transition disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled>
                        <i class="fas fa-upload text-xs"></i> Import Employee
                    </button>
                    <div id="empResult" class="hidden mt-3"></div>
                </div>

            </div>
        </div>

        {{-- Customer Card --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm flex flex-col">
            <div class="px-5 pt-5 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-violet-50 flex items-center justify-center">
                        <i class="fas fa-building text-violet-600"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Customer Data</h3>
                        <p class="text-xs text-gray-400">Export CSV or import from CSV</p>
                    </div>
                </div>
            </div>
            <div class="p-5 flex-1 flex flex-col gap-4">

                {{-- Export section --}}
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Export</p>
                    <div class="bg-gray-50 rounded-xl p-3 text-xs text-gray-600 space-y-1 mb-3">
                        <p class="flex items-center gap-2"><i class="fas fa-check text-violet-500"></i> Customer code, email, status</p>
                        <p class="flex items-center gap-2"><i class="fas fa-check text-violet-500"></i> Company name, group, category</p>
                        <p class="flex items-center gap-2"><i class="fas fa-check text-violet-500"></i> Industry sector, account executives</p>
                    </div>
                    <a href="{{ route('admin.export.customers') }}"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-violet-600 text-white text-sm font-medium hover:bg-violet-700 transition">
                        <i class="fas fa-download text-xs"></i> Download CSV
                    </a>
                </div>

                <div class="border-t border-gray-100"></div>

                {{-- Import section --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Import</p>
                        <a href="{{ route('admin.import.template.customers') }}" class="text-xs text-violet-600 hover:underline flex items-center gap-1">
                            <i class="fas fa-file-csv text-xs"></i> Download Template
                        </a>
                    </div>
                    <div id="custDropzone"
                        class="border-2 border-dashed border-gray-200 rounded-xl p-4 text-center cursor-pointer hover:border-violet-400 hover:bg-violet-50 transition-colors"
                        onclick="document.getElementById('custFileInput').click()"
                        ondragover="event.preventDefault();this.classList.add('border-violet-400','bg-violet-50')"
                        ondragleave="this.classList.remove('border-violet-400','bg-violet-50')"
                        ondrop="handleDrop(event,'cust')">
                        <i class="fas fa-cloud-upload-alt text-gray-300 text-2xl mb-1"></i>
                        <p class="text-xs text-gray-400">Drop CSV here or <span class="text-violet-600 font-medium">browse</span></p>
                        <p id="custFileName" class="text-xs text-gray-500 mt-1 hidden"></p>
                    </div>
                    <input type="file" id="custFileInput" accept=".csv,.txt" class="hidden" onchange="onFileSelect(event,'cust')">
                    <button id="btnImportCust" onclick="runImport('cust')"
                        class="w-full mt-2 flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-violet-100 text-violet-700 text-sm font-medium hover:bg-violet-200 transition disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled>
                        <i class="fas fa-upload text-xs"></i> Import Customer
                    </button>
                    <div id="custResult" class="hidden mt-3"></div>
                </div>

            </div>
        </div>

    </div>

</div>

{{-- ── Import Error Detail Modal ── --}}
<div id="errorModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black bg-opacity-40">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Import Errors</h3>
            <button onclick="closeErrorModal()" class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
        <div id="errorModalBody" class="p-5 max-h-80 overflow-y-auto space-y-1.5"></div>
        <div class="px-5 pb-4">
            <button onclick="closeErrorModal()" class="w-full px-4 py-2 rounded-xl bg-gray-100 text-gray-600 text-sm font-medium hover:bg-gray-200 transition">Close</button>
        </div>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const empFiles  = {};
const custFiles = {};

// ── File selection & drag-drop ────────────────────────────────────────────────

function onFileSelect(e, type) {
    const file = e.target.files[0];
    if (file) setFile(type, file);
}

function handleDrop(e, type) {
    e.preventDefault();
    const dropzone = document.getElementById(type + 'Dropzone');
    dropzone.classList.remove('border-emerald-400', 'bg-emerald-50', 'border-violet-400', 'bg-violet-50');
    const file = e.dataTransfer.files[0];
    if (file && (file.name.endsWith('.csv') || file.name.endsWith('.txt'))) {
        setFile(type, file);
    } else {
        showToast('Only CSV files are accepted', 'error');
    }
}

function setFile(type, file) {
    if (type === 'emp') {
        empFiles.file = file;
    } else {
        custFiles.file = file;
    }
    const nameEl = document.getElementById(type + 'FileName');
    nameEl.textContent = file.name + ' (' + formatBytes(file.size) + ')';
    nameEl.classList.remove('hidden');

    const btn = document.getElementById(type === 'emp' ? 'btnImportEmp' : 'btnImportCust');
    btn.disabled = false;

    // Clear previous result
    const result = document.getElementById(type + 'Result');
    result.classList.add('hidden');
    result.innerHTML = '';
}

function formatBytes(b) {
    if (b >= 1048576) return (b / 1048576).toFixed(1) + ' MB';
    if (b >= 1024)    return (b / 1024).toFixed(1) + ' KB';
    return b + ' B';
}

// ── Run Import ────────────────────────────────────────────────────────────────

async function runImport(type) {
    const file    = type === 'emp' ? empFiles.file : custFiles.file;
    const btnId   = type === 'emp' ? 'btnImportEmp' : 'btnImportCust';
    const resultId = type + 'Result';
    const endpoint = type === 'emp' ? '/api/admin/import/employees' : '/api/admin/import/customers';

    if (!file) return;

    const btn = document.getElementById(btnId);
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> Importing...';

    const form = new FormData();
    form.append('file', file);

    try {
        const res  = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: form,
        });
        if (!res.ok && res.status !== 422) {
            const text = await res.text();
            throw new Error(`Server error ${res.status}: ${text.substring(0, 200)}`);
        }
        const json = await res.json();
        showImportResult(type, json);
    } catch (e) {
        showToast('Import failed: ' + e.message, 'error');
    } finally {
        const color = type === 'emp' ? 'emerald' : 'violet';
        btn.disabled = false;
        btn.innerHTML = `<i class="fas fa-upload text-xs"></i> Import ${type === 'emp' ? 'Employee' : 'Customer'}`;
    }
}

function showImportResult(type, json) {
    const el    = document.getElementById(type + 'Result');
    const color = json.success ? 'green' : 'red';
    const errors = json.errors ?? [];

    el.classList.remove('hidden');

    if (json.success) {
        el.innerHTML = `
            <div class="bg-green-50 border border-green-200 rounded-xl p-3">
                <p class="text-xs font-semibold text-green-700 mb-1"><i class="fas fa-check-circle mr-1"></i>${htmlEsc(json.message)}</p>
                <div class="flex gap-3 text-xs text-gray-600">
                    <span class="flex items-center gap-1"><i class="fas fa-plus-circle text-green-500"></i> ${json.imported} ditambahkan</span>
                    <span class="flex items-center gap-1"><i class="fas fa-sync text-blue-500"></i> ${json.updated} diperbarui</span>
                    ${errors.length ? `<button onclick="openErrorModal(${JSON.stringify(errors).replace(/"/g,'&quot;')})" class="flex items-center gap-1 text-orange-500 hover:underline"><i class="fas fa-exclamation-triangle"></i> ${errors.length} error</button>` : ''}
                </div>
            </div>`;
    } else {
        el.innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-xl p-3">
                <p class="text-xs font-semibold text-red-700"><i class="fas fa-times-circle mr-1"></i>${htmlEsc(json.message)}</p>
            </div>`;
    }
}

// ── Error Modal ───────────────────────────────────────────────────────────────

function openErrorModal(errors) {
    const body = document.getElementById('errorModalBody');
    body.innerHTML = errors.map(e =>
        `<div class="flex items-start gap-2 bg-red-50 rounded-lg px-3 py-2">
            <i class="fas fa-exclamation-circle text-red-400 text-xs mt-0.5 shrink-0"></i>
            <p class="text-xs text-gray-700">${htmlEsc(e)}</p>
        </div>`
    ).join('');
    document.getElementById('errorModal').classList.remove('hidden');
}

function closeErrorModal() {
    document.getElementById('errorModal').classList.add('hidden');
}

// ── DB Backup ─────────────────────────────────────────────────────────────────

async function loadBackups() {
    const list = document.getElementById('backupList');
    list.innerHTML = '<p class="text-xs text-gray-400 py-2 text-center">Loading...</p>';
    try {
        const res  = await fetch('/api/admin/backup/list', { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message);

        if (!json.data.length) {
            list.innerHTML = '<p class="text-xs text-gray-400 py-2 text-center">No backups yet</p>';
            return;
        }

        list.innerHTML = '';
        json.data.forEach(b => {
            const row = document.createElement('div');
            row.className = 'flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2';
            row.innerHTML = `
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium text-gray-700 truncate">${htmlEsc(b.filename)}</p>
                    <p class="text-xs text-gray-400">${htmlEsc(b.size)} · ${htmlEsc(b.created_at)}</p>
                </div>
                <div class="flex gap-1 ml-2 shrink-0">
                    <a href="/admin/backup/download/${encodeURIComponent(b.filename)}"
                        class="w-7 h-7 flex items-center justify-center rounded-lg bg-blue-100 text-blue-600 hover:bg-blue-200 transition" title="Download">
                        <i class="fas fa-download text-xs"></i>
                    </a>
                    <button onclick='deleteBackup("${htmlEsc(b.filename)}")'
                        class="w-7 h-7 flex items-center justify-center rounded-lg bg-red-100 text-red-500 hover:bg-red-200 transition" title="Delete">
                        <i class="fas fa-trash text-xs"></i>
                    </button>
                </div>`;
            list.appendChild(row);
        });
    } catch (e) {
        list.innerHTML = '<p class="text-xs text-red-400 py-2 text-center">' + htmlEsc(e.message) + '</p>';
    }
}

document.getElementById('btnCreateBackup').addEventListener('click', async () => {
    const btn      = document.getElementById('btnCreateBackup');
    const progress = document.getElementById('backupProgress');
    btn.disabled = true;
    btn.classList.add('opacity-50');
    progress.classList.remove('hidden');
    progress.classList.add('flex');
    try {
        const res  = await fetch('/api/admin/backup/create', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': CSRF },
        });
        const json = await res.json();
        showToast(json.message, json.success ? 'success' : 'error');
        if (json.success) loadBackups();
    } catch (e) {
        showToast('Backup failed: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.classList.remove('opacity-50');
        progress.classList.add('hidden');
        progress.classList.remove('flex');
    }
});

async function deleteBackup(filename) {
    if (!confirm('Delete backup: ' + filename + '?')) return;
    try {
        const res  = await fetch('/api/admin/backup/' + encodeURIComponent(filename), {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': CSRF },
        });
        const json = await res.json();
        showToast(json.message, json.success ? 'success' : 'error');
        if (json.success) loadBackups();
    } catch (e) {
        showToast('Delete failed', 'error');
    }
}

// ── Ticket Export (CSV) ───────────────────────────────────────────────────────

document.getElementById('btnExportTickets').addEventListener('click', () => {
    const year  = document.getElementById('ticketYear').value;
    const month = document.getElementById('ticketMonth').value;
    window.location.href = `/admin/export/tickets?year=${year}&month=${month}`;
});

// ── Ticket Migration (ZIP) ────────────────────────────────────────────────────

document.getElementById('btnExportZip').addEventListener('click', () => {
    const year  = document.getElementById('migYear').value;
    const month = document.getElementById('migMonth').value;
    window.location.href = `/admin/export/tickets/zip?year=${year}&month=${month}`;
});

let _migFile = null;

function onMigFileSelect(e) {
    const file = e.target.files[0];
    if (file) setMigFile(file);
}

function handleMigDrop(e) {
    e.preventDefault();
    document.getElementById('migDropzone').classList.remove('border-red-400', 'bg-red-50');
    const file = e.dataTransfer.files[0];
    if (file && file.name.endsWith('.zip')) {
        setMigFile(file);
    } else {
        showToast('Hanya file ZIP yang diterima', 'error');
    }
}

function setMigFile(file) {
    _migFile = file;
    const nameEl = document.getElementById('migFileName');
    nameEl.textContent = file.name + ' (' + formatBytes(file.size) + ')';
    nameEl.classList.remove('hidden');
    document.getElementById('btnImportZip').disabled = false;
    const result = document.getElementById('migResult');
    result.classList.add('hidden');
    result.innerHTML = '';
}

async function runMigImport() {
    if (!_migFile) return;

    const btn    = document.getElementById('btnImportZip');
    const result = document.getElementById('migResult');
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> Importing...';

    const form = new FormData();
    form.append('file', _migFile);

    try {
        const res  = await fetch('/api/admin/import/tickets/zip', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: form,
        });
        const json = await res.json();

        result.classList.remove('hidden');
        if (json.success) {
            result.innerHTML = `
                <div class="bg-green-50 border border-green-200 rounded-xl p-3">
                    <p class="text-xs font-semibold text-green-700 mb-2"><i class="fas fa-check-circle mr-1"></i>${htmlEsc(json.message)}</p>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-gray-600">
                        <span class="flex items-center gap-1"><i class="fas fa-plus-circle text-green-500"></i> ${json.imported} tiket baru</span>
                        <span class="flex items-center gap-1"><i class="fas fa-sync text-blue-500"></i> ${json.updated} diperbarui</span>
                        <span class="flex items-center gap-1"><i class="fas fa-comment text-indigo-500"></i> ${json.messages} pesan</span>
                        <span class="flex items-center gap-1"><i class="fas fa-file text-orange-500"></i> ${json.files} file</span>
                    </div>
                    ${json.errors && json.errors.length ? `<button onclick="openErrorModal(${JSON.stringify(json.errors).replace(/"/g,'&quot;')})" class="mt-2 text-xs text-orange-500 hover:underline flex items-center gap-1"><i class="fas fa-exclamation-triangle"></i> ${json.errors.length} error</button>` : ''}
                </div>`;
        } else {
            result.innerHTML = `
                <div class="bg-red-50 border border-red-200 rounded-xl p-3">
                    <p class="text-xs font-semibold text-red-700"><i class="fas fa-times-circle mr-1"></i>${htmlEsc(json.message)}</p>
                </div>`;
        }
    } catch (e) {
        showToast('Import gagal: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-upload text-xs"></i> Import ZIP';
    }
}

// ── Import dari API Sistem Lama ───────────────────────────────────────────────

let _apiFile = null;

function onApiFileSelect(e) {
    const file = e.target.files[0];
    if (file) setApiFile(file);
}

function handleApiDrop(e) {
    e.preventDefault();
    document.getElementById('apiDropzone').classList.remove('border-indigo-400', 'bg-indigo-50');
    const file = e.dataTransfer.files[0];
    if (file && (file.name.endsWith('.csv') || file.name.endsWith('.txt'))) {
        setApiFile(file);
    } else {
        showToast('Hanya file CSV yang diterima', 'error');
    }
}

function setApiFile(file) {
    _apiFile = file;
    const nameEl = document.getElementById('apiFileName');
    nameEl.textContent = file.name + ' (' + formatBytes(file.size) + ')';
    nameEl.classList.remove('hidden');
    updateApiImportBtn();
    document.getElementById('apiResult').classList.add('hidden');
}

function updateApiImportBtn() {
    const url = document.getElementById('apiUrlPattern').value.trim();
    document.getElementById('btnApiImport').disabled = !(_apiFile && url.includes('{ticket_number}'));
}

document.getElementById('apiUrlPattern').addEventListener('input', updateApiImportBtn);

async function runApiImport() {
    const url = document.getElementById('apiUrlPattern').value.trim();
    if (!_apiFile || !url.includes('{ticket_number}')) return;

    const btn      = document.getElementById('btnApiImport');
    const progress = document.getElementById('apiProgress');
    const result   = document.getElementById('apiResult');

    btn.disabled = true;
    progress.classList.remove('hidden');
    result.classList.add('hidden');

    const form = new FormData();
    form.append('file',           _apiFile);
    form.append('url_pattern',    url);
    form.append('default_status', document.getElementById('apiDefaultStatus').value);

    try {
        const res  = await fetch('/api/admin/import/tickets/from-api', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': CSRF },
            body: form,
        });
        const json = await res.json();

        result.classList.remove('hidden');
        if (json.success) {
            result.innerHTML = `
                <div class="bg-green-50 border border-green-200 rounded-xl p-3">
                    <p class="text-xs font-semibold text-green-700 mb-2"><i class="fas fa-check-circle mr-1"></i>${htmlEsc(json.message)}</p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-1 text-xs text-gray-600">
                        <span class="flex items-center gap-1"><i class="fas fa-plus-circle text-green-500"></i> ${json.imported} tiket baru</span>
                        <span class="flex items-center gap-1"><i class="fas fa-sync text-blue-500"></i> ${json.updated} diperbarui</span>
                        <span class="flex items-center gap-1"><i class="fas fa-forward text-gray-400"></i> ${json.skipped} dilewati</span>
                        <span class="flex items-center gap-1"><i class="fas fa-comment text-indigo-500"></i> ${json.messages} pesan</span>
                    </div>
                    ${json.errors && json.errors.length ? `<button onclick="openErrorModal(${JSON.stringify(json.errors).replace(/"/g,'&quot;')})" class="mt-2 text-xs text-orange-500 hover:underline flex items-center gap-1"><i class="fas fa-exclamation-triangle"></i> ${json.errors.length} error — lihat detail</button>` : ''}
                </div>`;
        } else {
            result.innerHTML = `
                <div class="bg-red-50 border border-red-200 rounded-xl p-3">
                    <p class="text-xs font-semibold text-red-700"><i class="fas fa-times-circle mr-1"></i>${htmlEsc(json.message)}</p>
                </div>`;
        }
    } catch (e) {
        showToast('Import gagal: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        progress.classList.add('hidden');
    }
}

// ── Util ──────────────────────────────────────────────────────────────────────

function htmlEsc(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

loadBackups();
</script>
@endsection
