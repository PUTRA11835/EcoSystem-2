@extends('dashboard')

@section('title', 'ESS Settings')
@section('page-title', 'ESS Settings')

@section('content')
<div class="space-y-5">
    <!-- Header Card -->
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg primary-gradient text-white flex items-center justify-center text-sm shadow-sm">
                        <i class="fas fa-sliders-h"></i>
                    </span>
                    ESS Menu Settings
                </h2>
                <p class="text-xs text-gray-500 mt-1">
                    Control global visibility for ESS menu items displayed across the application sidebar.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" onclick="selectAll(true)"
                    class="px-3 py-1.5 text-xs font-medium bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all flex items-center gap-1.5">
                    <i class="fas fa-check-double text-[10px]"></i> Select All
                </button>
                <button type="button" onclick="selectAll(false)"
                    class="px-3 py-1.5 text-xs font-medium bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all flex items-center gap-1.5">
                    <i class="fas fa-undo text-[10px]"></i> Deselect All
                </button>
                <button type="button" onclick="saveEssSettings()" id="saveBtn"
                    class="px-4 py-1.5 primary-gradient text-white text-xs font-semibold rounded-lg shadow hover:opacity-90 transition-all flex items-center gap-1.5">
                    <i class="fas fa-save text-xs"></i> Save Changes
                </button>
            </div>
        </div>
    </div>

    <!-- ESS Menu Items Grid -->
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <form id="essSettingsForm" onsubmit="event.preventDefault(); saveEssSettings();">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                @foreach($items as $key => $item)
                    @php
                        $isEnabled = !empty($settings[$key]);
                    @endphp
                    <label for="item_{{ $key }}"
                        class="p-3.5 rounded-xl border border-gray-200 hover:border-red-300 hover:shadow-sm transition-all bg-white flex items-center justify-between cursor-pointer group">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg {{ $isEnabled ? 'bg-red-50 text-red-800' : 'bg-gray-100 text-gray-400' }} flex items-center justify-center text-sm transition-colors group-hover:bg-red-100 group-hover:text-red-800">
                                <i class="{{ $item['icon'] }}"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 leading-tight">
                                    {{ $item['name'] }}
                                </h4>
                                <p class="text-[11px] text-gray-400 mt-0.5">
                                    {{ $item['route'] ? 'Route: ' . $item['route'] : 'Module' }}
                                </p>
                            </div>
                        </div>

                        <!-- Toggle Switch -->
                        <div class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="enabled_items[]" value="{{ $key }}" id="item_{{ $key }}"
                                class="sr-only peer ess-toggle-cb" {{ $isEnabled ? 'checked' : '' }}>
                            <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-red-800">
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>

            <!-- Action Bar -->
            <div class="mt-6 pt-3.5 border-t border-gray-100 flex justify-end">
                <button type="submit"
                    class="px-5 py-2 primary-gradient text-white text-xs font-semibold rounded-lg shadow-md hover:opacity-95 transition-all flex items-center gap-2">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function selectAll(check) {
        document.querySelectorAll('.ess-toggle-cb').forEach(cb => {
            cb.checked = check;
        });
    }

    async function saveEssSettings() {
        const saveBtn = document.getElementById('saveBtn');
        const origHtml = saveBtn.innerHTML;
        saveBtn.disabled = true;
        saveBtn.innerHTML = `<i class="fas fa-circle-notch fa-spin"></i> Saving...`;

        try {
            const form = document.getElementById('essSettingsForm');
            const formData = new FormData(form);

            const res = await fetch("{{ route('management.ess-settings.update') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: formData,
            });

            const data = await res.json();
            if (data.success) {
                showToast(data.message || 'ESS Settings updated successfully.', 'success');
            } else {
                showToast(data.message || 'Failed to save settings.', 'error');
            }
        } catch (e) {
            showToast('An error occurred while saving ESS settings.', 'error');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = origHtml;
        }
    }
</script>
@endsection