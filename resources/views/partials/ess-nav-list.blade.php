{{--
    Daftar item ESS (item mandiri + grup buatan admin, D182) untuk SATU/BEBERAPA seksi sidebar.
    Isinya adalah foreach lama dari partials/sidebar.blade.php, dipindah ke sini agar bisa dirender
    per seksi (Dashboard / My Workspace / AI Tools). Variabel: $essOutput, $essSecOf (dari sidebar),
    $essSecs (daftar seksi yang ingin dirender).
--}}
@php $essSecs = $essSecs ?? ['workspace']; @endphp
@foreach($essOutput as $essEntry)
    @continue(!in_array($essSecOf[$essEntry['key']] ?? 'workspace', $essSecs, true))
            @if($essEntry['type'] === 'item')
                @if($essEntry['item']['visible'])
                    @include('partials.ess-nav-item', [
                        'href'   => $essEntry['item']['href'],
                        'icon'   => $essEntry['item']['icon'],
                        'label'  => $essEntry['item']['label'],
                        'active' => $essEntry['item']['active'],
                        'nested' => false,
                    ])
                @endif
            @else
                @php
                    $essVisibleMembers = $essEntry['members']->filter(fn ($m) => $m['visible'])->values();
                    $essGroupActive    = $essVisibleMembers->contains('active', true);
                @endphp
                @if($essVisibleMembers->count() > 0)
                    <div class="mb-2">
                        <button onclick="toggleEssGroupDropdown('{{ $essEntry['group']['id'] }}')"
                            class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl w-full text-left {{ $essGroupActive ? 'active bg-white bg-opacity-20 text-white font-semibold' : 'text-white text-opacity-80 hover:bg-white hover:bg-opacity-10 hover:text-white' }} transition-all">
                            <span class="nav-icon w-5 h-5 flex items-center justify-center">
                                <i class="{{ $essEntry['group']['icon'] }}"></i>
                            </span>
                            <span class="nav-text flex-1 font-medium">{{ $essEntry['group']['label'] }}</span>
                            <i class="fas fa-chevron-down text-xs nav-text transition-transform {{ $essGroupActive ? 'rotate-180' : '' }}" id="essGroup{{ $essEntry['group']['id'] }}Chevron"></i>
                        </button>
                        <div id="essGroup{{ $essEntry['group']['id'] }}Dropdown"
                            class="nav-text {{ $essGroupActive ? '' : 'hidden' }} mt-1 ml-4 space-y-1">
                            @foreach($essVisibleMembers as $essMember)
                                @include('partials.ess-nav-item', [
                                    'href'   => $essMember['href'],
                                    'icon'   => $essMember['icon'],
                                    'label'  => $essMember['label'],
                                    'active' => $essMember['active'],
                                    'nested' => true,
                                ])
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
@endforeach
