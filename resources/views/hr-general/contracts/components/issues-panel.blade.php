{{-- Butir data yang kurang (dari ContractDataException): tiap baris bisa diklik dan membuka tab yang tepat di halaman orangnya.
     Dibuka di tab baru agar isian form kontrak yang sedang diketik tidak hilang. $issuesCanOpen = pemakai berhak membuka halaman itu. --}}
@php($issues = session('issues', []))
@if(!empty($issues))
    @php($canOpenIssues = $issuesCanOpen ?? true)
    <section id="issuesPanel" tabindex="-1" role="alert" aria-labelledby="issuesTitle" class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3.5 text-amber-900 shadow-sm">
        <h3 id="issuesTitle" class="text-sm font-bold"><i class="fas fa-triangle-exclamation mr-1.5"></i> {{ count($issues) }} item{{ count($issues) === 1 ? '' : 's' }} to complete before this contract can be activated</h3>
        <p class="text-xs mt-0.5 text-amber-800">{{ $canOpenIssues ? 'Click an item to open the page where it is filled in (opens in a new tab, so what you typed here is kept).' : 'You do not have access to the person’s page; ask HR to complete these items.' }} The contract can still be saved as a Draft.</p>
        <ul class="mt-2.5 grid grid-cols-1 md:grid-cols-2 gap-1.5">
            @foreach($issues as $issue)
                <li>
                    @if($canOpenIssues && !empty($issue['url']))
                        <a href="{{ $issue['url'] }}" target="_blank" rel="noopener" class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg bg-white border border-amber-200 hover:border-amber-400 hover:bg-amber-100/40 text-xs">
                            <span><strong class="font-semibold text-gray-900">{{ $issue['label'] }}</strong> <span class="text-gray-500">— {{ $issue['tab_label'] }} tab</span></span>
                            <span class="shrink-0 font-semibold text-red-800">Open <i class="fas fa-arrow-up-right-from-square text-[10px]"></i></span>
                        </a>
                    @else
                        <span class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white border border-amber-200 text-xs"><strong class="font-semibold text-gray-900">{{ $issue['label'] }}</strong> <span class="text-gray-500">— {{ $issue['tab_label'] }} tab</span></span>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
    <script>document.addEventListener('DOMContentLoaded', function () { var p = document.getElementById('issuesPanel'); if (p) { p.scrollIntoView({ behavior: 'smooth', block: 'center' }); p.focus({ preventScroll: true }); } });</script>
@endif
