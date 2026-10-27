{{--
    The list of letter template designs — a step of Letter Templates → Settings (Template Designs).
    Needs $designs, $usage (template_key → letters made), $systemOnes; $canDo comes from the layout.
--}}
@php
    $canCreate = $canDo('general.letter-templates', 'create');
    $canEdit   = $canDo('general.letter-templates', 'edit');
    $canDelete = $canDo('general.letter-templates', 'delete');
    $action = 'hr-general.recruitment.components.icon-action';
@endphp
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-5 py-3.5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h3 class="text-sm font-bold text-gray-800">Template Designs</h3>
            <p class="text-[10px] text-gray-400 mt-0.5">
                Click <strong>ID</strong> / <strong>EN</strong> to read a template as a PDF with sample data, <i class="fas fa-pen text-[9px]"></i> to change it in the Word-style editor.
                Letters already generated keep the wording they were made with.
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if($canCreate)
                <a href="{{ route('general.letters.settings.designs.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">
                    <i class="fas fa-plus text-[10px]"></i> New Template
                </a>
            @endif
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-xs text-left">
            <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 w-12">No.</th>
                    <th class="px-4 py-3 min-w-56">Template</th>
                    <th class="px-4 py-3 min-w-64">Used for</th>
                    <th class="px-4 py-3">Fields</th>
                    <th class="px-4 py-3">Wording</th>
                    <th class="px-4 py-3 text-center">Letters made</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-gray-700">
                @forelse($designs as $design)
                    <tr class="hover:bg-gray-50 align-top">
                        <td class="px-4 py-3 text-gray-400">{{ $loop->iteration }}</td>
                        <td class="px-4 py-3">
                            <span class="font-semibold text-gray-800">{{ $design->label }}</span>
                            <span class="block text-[10px] text-gray-400">{{ $design->label_en }} · <span class="font-mono">{{ $design->template_key }}</span></span>
                            @if($design->is_system)
                                <span class="inline-block mt-1 px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 text-[9px] font-bold">BUILT-IN</span>
                            @else
                                <span class="inline-block mt-1 px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 text-[9px] font-bold">CUSTOM</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $design->description ?: '-' }}</td>
                        <td class="px-4 py-3">
                            @forelse((array) $design->fields as $key => $field)
                                <span class="inline-block mr-1 mb-1 px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 text-[10px]" title="{{ $field['label'] ?? $key }}">{{ $key }}</span>
                            @empty
                                <span class="text-gray-400">None</span>
                            @endforelse
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @foreach(['id' => ['ID', 'body_id'], 'en' => ['EN', 'body_en']] as $lang => [$tag, $col])
                                @if(trim(strip_tags((string) $design->{$col})) !== '')
                                    <a href="{{ route('general.letters.settings.designs.show', ['design' => $design->id, 'lang' => $lang]) }}" target="_blank"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-green-50 text-green-700 hover:bg-green-100 text-[10px] font-bold"
                                        title="Read the {{ $lang === 'id' ? 'Bahasa Indonesia' : 'English' }} wording as a PDF">
                                        <i class="fas fa-eye text-[9px]"></i> {{ $tag }}
                                    </a>
                                @else
                                    <span class="inline-block px-2 py-0.5 rounded bg-gray-100 text-gray-400 text-[10px] font-bold" title="Not written yet">{{ $tag }}</span>
                                @endif
                            @endforeach
                        </td>
                        <td class="px-4 py-3 text-center">{{ $usage[$design->template_key] ?? 0 }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1.5">
                                @if($canEdit)
                                    @include($action, ['icon' => 'pen', 'tone' => 'blue', 'label' => 'Edit the wording and fields', 'href' => route('general.letters.settings.designs.edit', $design->id)])
                                @endif
                                @if($canDelete && !$design->is_system)
                                    @include($action, [
                                        'icon' => 'trash', 'tone' => 'red', 'label' => 'Delete this template',
                                        'post' => route('general.letters.settings.designs.destroy', $design->id),
                                        'confirm' => "Delete the template “{$design->label}”? This cannot be undone.", 'confirmTitle' => 'Delete Template', 'confirmOk' => 'Delete',
                                    ])
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-gray-400">No templates yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($systemOnes->isNotEmpty())
    <div class="text-[11px] text-gray-600 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 leading-relaxed">
        <p class="font-semibold mb-1"><i class="fas fa-lock text-[10px] mr-1"></i> Made by other menus</p>
        {{ $systemOnes->map(fn ($t) => $t['label_en'])->implode(' · ') }} are created from Finance → BPJS and External, with their own wording and data; they are not edited here.
    </div>
@endif
