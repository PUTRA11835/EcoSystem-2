{{--
    The archive table and pagination of External → Assignment Letter / Employment Certificate / Reference Letter. Rendered with the
    page, and alone (without the layout) when the live filters refresh it (ExternalDocumentController::index answers an XMLHttpRequest).

    Parameters: $rows (paginator of Letter) · $doc · $creators (id → name) · $perPage · $perPageOptions · $filterForm
--}}
@php use App\Models\Letters\Letter; @endphp
<div class="overflow-x-auto">
    <table class="w-full text-xs text-left">
        <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
            <tr>
                <th class="px-4 py-3 w-10">No</th>
                <th class="px-4 py-3">Document</th>
                <th class="px-4 py-3">Consultant</th>
                <th class="px-4 py-3">External contract</th>
                <th class="px-4 py-3">Issued</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 text-gray-700">
            @forelse($rows as $row)
                @php $status = $row->status(); @endphp
                <tr class="hover:bg-gray-50 align-top {{ $status === 'void' ? 'opacity-60' : '' }}">
                    <td class="px-4 py-3 text-gray-400">{{ $rows->firstItem() + $loop->index }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-start gap-3">
                            <span class="w-9 h-9 rounded-lg tone-primary flex items-center justify-center shrink-0"><i class="far fa-file-pdf"></i></span>
                            <div class="min-w-0">
                                <span class="font-semibold text-gray-800">{{ $doc['label'] }}</span>
                                <span class="block text-[11px] text-gray-400 whitespace-nowrap">{{ $row->letter_number }}</span>
                                @if(!empty($row->fields['purpose'] ?? $row->fields['task'] ?? null))
                                    <span class="block text-[11px] text-gray-400 truncate max-w-xs">{{ $row->fields['purpose'] ?? $row->fields['task'] }}</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="font-medium text-gray-800">{{ $row->counterparty ?: '-' }}</span>
                        <span class="block text-[11px] text-gray-400">{{ $row->employee?->eci ?? '-' }}</span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">{{ $row->fields['contract_no'] ?? '' ?: '—' }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        {{ $row->letter_date?->format('d M Y') }}
                        <span class="block text-[11px] text-gray-400">{{ $creators[$row->created_by] ?? '' }}</span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ Letter::STATUS_BADGES[$status] ?? '' }}">{{ Letter::STATUSES[$status] ?? $status }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end">
                            <div class="seg">
                                <a href="{{ route('general.external.documents.pdf', $row) }}" target="_blank" class="seg-view" title="Open the PDF"><i class="far fa-eye"></i> View</a>
                                <a href="{{ route('general.external.documents.pdf', $row) }}?download=1" class="seg-print" title="Download the PDF"><i class="fas fa-download"></i> PDF</a>
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-12 text-center text-gray-400">
                    <i class="far fa-folder-open text-lg mb-2 block"></i>No {{ strtolower($doc['plural']) }} yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('hr-general.recruitment.components.pagination', ['paginator' => $rows, 'form' => $filterForm])
