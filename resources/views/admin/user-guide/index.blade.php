@extends('dashboard')

@section('title', 'User Guide Editor')
@section('page-title', 'User Guide Editor')
@section('page-subtitle', 'Edit the manual employees see at User Guide. Bundled topics stay as the default you can always restore.')

@push('styles')
<style>
    .uge-scope { --uge-accent: rgb(var(--primary-rgb, 153, 27, 27)); --uge-accent-dark: rgb(var(--primary-dark-rgb, 127, 29, 29)); --uge-ring: rgba(var(--primary-rgb, 153, 27, 27), 0.18); --uge-tint: rgba(var(--primary-rgb, 153, 27, 27), 0.08); font-family: inherit; }
    .uge-scope input, .uge-scope select, .uge-scope textarea, .uge-scope button { font-family: inherit; }
    .uge-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
    .uge-tabs { display: inline-flex; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; background: #fff; }
    .uge-tabs a { padding: 8px 16px; font-size: 13px; color: #4b5563; text-decoration: none; }
    .uge-tabs a.is-on { background: var(--uge-accent); color: #fff; font-weight: 600; }
    .uge-btn { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; text-decoration: none; background: var(--uge-accent); color: #fff; }
    .uge-btn:hover { background: var(--uge-accent-dark); }
    .uge-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
    .uge-chapter { padding: 10px 20px; background: #f9fafb; border-bottom: 1px solid #f3f4f6; font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #6b7280; }
    .uge-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 20px; border-bottom: 1px solid #f3f4f6; }
    .uge-row:last-child { border-bottom: 0; }
    .uge-title { font-size: 14px; font-weight: 600; color: #111827; text-decoration: none; }
    .uge-title:hover { color: var(--uge-accent); }
    .uge-meta { font-size: 12px; color: #6b7280; margin-top: 2px; }
    .uge-pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; margin-left: 6px; }
    .uge-pill.default { background: #f3f4f6; color: #4b5563; }
    .uge-pill.edited { background: #fecaca; color: #7f1d1d; }
    .uge-pill.custom { background: #dcfce7; color: #166534; }
    .uge-pill.stale { background: #fef3c7; color: #92400e; }
    .uge-pill.todo { background: #fee2e2; color: #991b1b; }
    .uge-actions { display: flex; gap: 14px; font-size: 13px; flex-shrink: 0; }
    .uge-actions a { color: var(--uge-accent); text-decoration: none; }
    .uge-note { font-size: 12px; color: #4b5563; background: var(--uge-tint); border: 1px solid #fecaca; border-radius: 10px; padding: 10px 14px; line-height: 1.6; }
</style>
@endpush

@section('content')
<div class="space-y-5 uge-scope">
    <div class="uge-note">
        Employees only see topics that match their menu access or role, as set in each topic's <strong>Visible to</strong>.
        A topic marked <span class="uge-pill stale" style="margin-left:0">default changed</span> was edited here, and its bundled file has changed since,
        so review it. Images are files in <code>public/images/guide</code> and cannot be uploaded here.
    </div>

    <div class="uge-bar">
        <div class="uge-tabs">
            @foreach (config('user_guide.locales') as $code => $label)
                <a href="{{ route('admin.user-guide.index', ['lang' => $code]) }}" class="{{ $locale === $code ? 'is-on' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
        <a class="uge-btn" href="{{ route('admin.user-guide.create', ['lang' => $locale]) }}"><i class="fas fa-plus text-xs"></i> New topic</a>
    </div>

    @php($byChapter = collect($rows)->groupBy('chapter'))
    <div class="uge-card">
        @forelse ($chapters as $chapterId => $def)
            @continue(!$byChapter->has($chapterId))
            <div class="uge-chapter">{{ $def['title'][$locale] ?? $def['title']['en'] }}</div>
            @foreach ($byChapter[$chapterId] as $row)
                <div class="uge-row">
                    <div class="min-w-0">
                        <a class="uge-title" href="{{ route('admin.user-guide.edit', ['key' => $row['key'], 'lang' => $locale]) }}">{{ $row['title'] }}</a>
                        <span class="uge-pill {{ $row['state'] }}">{{ ['default' => 'Default', 'edited' => 'Edited', 'custom' => 'Custom'][$row['state']] }}</span>
                        @if ($row['stale'])<span class="uge-pill stale">default changed</span>@endif
                        @if (!$row['translated'])<span class="uge-pill todo">not translated</span>@endif
                        <div class="uge-meta">
                            {{ $row['key'] }}
                            &middot; {{ $row['requires'] ? 'Visible to: ' . implode(', ', array_map(fn ($r) => str_replace('@role:', 'role ', $r), $row['requires'])) : 'Visible to everyone' }}
                        </div>
                    </div>
                    <div class="uge-actions">
                        <a href="{{ route('user-guide.index', ['lang' => $locale]) }}#topic-{{ $row['key'] }}" target="_blank" rel="noopener">View</a>
                        <a href="{{ route('admin.user-guide.edit', ['key' => $row['key'], 'lang' => $locale]) }}">Edit</a>
                    </div>
                </div>
            @endforeach
        @empty
            <div class="uge-row">No topics found.</div>
        @endforelse
    </div>
</div>
@endsection
