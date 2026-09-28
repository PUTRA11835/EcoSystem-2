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

    {{-- 🔴 D182 — Menu Groups: fold selected ESS items into a named sidebar
         dropdown (e.g. "My Activity" containing My Attendance, My Leave and
         Permit, My KPI). Purely optional — an item with no group assignment
         below renders exactly as before, as a flat top-level sidebar entry.

         `events_calendar` and `my_timesheet` are not offered here: they
         already live inside the built-in "Calendar" dropdown, and `logout`
         is not offered because its visibility switch above was never wired
         to anything in the sidebar (a pre-existing gap, unrelated to this
         feature). --}}
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg primary-gradient text-white flex items-center justify-center text-sm shadow-sm">
                        <i class="fas fa-layer-group"></i>
                    </span>
                    Menu Groups
                </h2>
                <p class="text-xs text-gray-500 mt-1">
                    Fold related ESS items into a named dropdown in the sidebar. Ungrouped items stay as they are today.
                </p>
            </div>
            <button type="button" onclick="document.getElementById('createGroupForm').classList.toggle('hidden')"
                class="px-3 py-1.5 text-xs font-semibold primary-gradient text-white rounded-lg hover:opacity-90 transition-all flex items-center gap-1.5 whitespace-nowrap">
                <i class="fas fa-plus text-[10px]"></i> Create Group
            </button>
        </div>

        <form id="createGroupForm" action="{{ route('management.ess-settings.groups.store') }}" method="POST"
            class="hidden mb-4 p-3.5 rounded-xl border border-dashed border-gray-300 bg-gray-50 flex flex-col sm:flex-row gap-2.5 items-start sm:items-end">
            @csrf
            <div class="flex-1 w-full">
                <label class="block text-[11px] font-medium text-gray-500 mb-1">Group name</label>
                <input type="text" name="label" required maxlength="60" placeholder="e.g. My Activity"
                    class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
            </div>
            <div class="w-full sm:w-48">
                <label class="block text-[11px] font-medium text-gray-500 mb-1">Icon <span class="text-gray-400">(Font Awesome class)</span></label>
                <input type="text" name="icon" maxlength="60" placeholder="fas fa-briefcase"
                    class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
            </div>
            <button type="submit"
                class="px-4 py-1.5 bg-gray-800 text-white text-sm font-semibold rounded-lg hover:bg-gray-900 transition-all whitespace-nowrap">
                Add
            </button>
        </form>

        @if(count($groups['groups']) === 0)
            <p class="text-sm text-gray-400 italic">No groups yet — every item below is shown flat in the sidebar.</p>
        @else
            <div class="space-y-2">
                @foreach($groups['groups'] as $group)
                    @php
                        $memberCount = collect($groups['assignments'])->filter(fn ($gid) => $gid === $group['id'])->count();
                    @endphp
                    <div class="flex items-center justify-between gap-3 p-2.5 rounded-lg border border-gray-200">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-7 h-7 rounded-lg bg-gray-100 text-gray-600 flex items-center justify-center text-xs shrink-0">
                                <i class="{{ $group['icon'] }}"></i>
                            </span>
                            <span class="text-sm font-medium text-gray-900 truncate">{{ $group['label'] }}</span>
                            <span class="text-[11px] text-gray-400 shrink-0">{{ $memberCount }} item(s)</span>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <button type="button"
                                onclick="renameGroup('{{ $group['id'] }}', {{ Js::from($group['label']) }}, {{ Js::from($group['icon']) }})"
                                class="px-2 py-1 text-xs text-gray-500 hover:bg-gray-100 rounded transition-all" title="Rename">
                                <i class="fas fa-pen text-[10px]"></i>
                            </button>
                            <form method="POST" action="{{ route('management.ess-settings.groups.destroy', $group['id']) }}"
                                class="js-delete-group" data-name="{{ $group['label'] }}">
                                @csrf
                                <button type="submit" class="px-2 py-1 text-xs text-red-600 hover:bg-red-50 rounded transition-all" title="Delete">
                                    <i class="fas fa-trash text-[10px]"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <form id="renameGroupForm" method="POST" class="hidden mt-3 p-3.5 rounded-xl border border-dashed border-gray-300 bg-gray-50 flex flex-col sm:flex-row gap-2.5 items-start sm:items-end">
                @csrf
                <div class="flex-1 w-full">
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Group name</label>
                    <input type="text" name="label" id="renameGroupLabel" required maxlength="60"
                        class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                </div>
                <div class="w-full sm:w-48">
                    <label class="block text-[11px] font-medium text-gray-500 mb-1">Icon</label>
                    <input type="text" name="icon" id="renameGroupIcon" maxlength="60"
                        class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-1 primary-border">
                </div>
                <button type="submit"
                    class="px-4 py-1.5 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all whitespace-nowrap">
                    Save Name
                </button>
            </form>
        @endif
    </div>

    <!-- ESS Menu Items Grid -->
    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <form id="essSettingsForm" onsubmit="event.preventDefault(); saveEssSettings();">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                @foreach($items as $key => $item)
                    @php
                        $isEnabled = !empty($settings[$key]);
                        $isGroupable = in_array($key, $groupableItems, true);
                        $currentGroup = $groups['assignments'][$key] ?? '';
                    @endphp
                    <div class="p-3.5 rounded-xl border border-gray-200 hover:border-red-300 hover:shadow-sm transition-all bg-white group">
                        <label for="item_{{ $key }}" class="flex items-center justify-between cursor-pointer">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-8 h-8 rounded-lg {{ $isEnabled ? 'bg-red-50 text-red-800' : 'bg-gray-100 text-gray-400' }} flex items-center justify-center text-sm transition-colors group-hover:bg-red-100 group-hover:text-red-800 shrink-0">
                                    <i class="{{ $item['icon'] }}"></i>
                                </div>
                                <div class="min-w-0">
                                    <h4 class="text-sm font-medium text-gray-900 leading-tight truncate">
                                        {{ $item['name'] }}
                                    </h4>
                                    <p class="text-[11px] text-gray-400 mt-0.5 truncate">
                                        {{ $item['route'] ? 'Route: ' . $item['route'] : 'Module' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Toggle Switch -->
                            <div class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="checkbox" name="enabled_items[]" value="{{ $key }}" id="item_{{ $key }}"
                                    class="sr-only peer ess-toggle-cb" {{ $isEnabled ? 'checked' : '' }}>
                                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-red-800">
                                </div>
                            </div>
                        </label>

                        @if($isGroupable && count($groups['groups']) > 0)
                            <div class="mt-2.5 pt-2.5 border-t border-gray-100">
                                <select name="group_assignments[{{ $key }}]"
                                    class="w-full px-2 py-1 border border-gray-200 rounded-md text-[11px] text-gray-600 focus:outline-none focus:ring-1 primary-border">
                                    <option value="">— Ungrouped —</option>
                                    @foreach($groups['groups'] as $group)
                                        <option value="{{ $group['id'] }}" @selected($currentGroup === $group['id'])>{{ $group['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                @endforeach
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

    // D182 — form ganti nama grup dipakai ulang untuk semua baris; hanya
    // action dan nilai awalnya yang berganti saat tombol pensil ditekan.
    function renameGroup(groupId, label, icon) {
        const form = document.getElementById('renameGroupForm');
        form.action = '{{ url('/management/ess-settings/groups') }}/' + groupId + '/update';
        document.getElementById('renameGroupLabel').value = label;
        document.getElementById('renameGroupIcon').value = icon;
        form.classList.remove('hidden');
        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    document.querySelectorAll('.js-delete-group').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
            if (form.dataset.confirmed === 'yes') return;
            event.preventDefault();

            const ok = await showConfirm(
                `Delete the group "${form.dataset.name}"? Its items will go back to being shown flat in the sidebar — nothing is hidden.`,
                'Delete Group',
                'danger',
                { okText: 'Delete', cancelText: 'Cancel' }
            );

            if (!ok) return;
            form.dataset.confirmed = 'yes';
            form.submit();
        });
    });

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