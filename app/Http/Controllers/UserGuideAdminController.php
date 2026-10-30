<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveGuideTopicRequest;
use App\Services\Guide\GuideTopicEditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

/**
 * Control Center → User Guide Editor. Hanya HTTP: aturan dan penyimpanan ada di
 * GuideTopicEditor, validasi isi di SaveGuideTopicRequest + GuideContentRules.
 */
class UserGuideAdminController extends Controller
{
    public function __construct(private readonly GuideTopicEditor $editor)
    {
    }

    public function index(Request $request): View
    {
        $locale = $this->locale($request->query('lang'));

        return view('admin.user-guide.index', [
            'locale'   => $locale,
            'rows'     => $this->editor->rows($locale),
            'chapters' => config('user_guide.chapters'),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($this->locale($request->query('lang')), [
            'key' => '', 'chapter' => 'getting-started', 'order' => 100, 'title' => '', 'summary' => '',
            'requires' => [], 'body' => '', 'state' => GuideTopicEditor::STATE_CUSTOM,
            'has_file' => false, 'stale' => false, 'borrowed' => false, 'is_new' => true,
        ]);
    }

    public function store(SaveGuideTopicRequest $request): RedirectResponse
    {
        return $this->persist($request, 'Topic created.');
    }

    public function edit(Request $request, string $key): View
    {
        $locale = $this->locale($request->query('lang'));
        $topic = $this->editor->find($key, $locale) ?? abort(404);

        return $this->form($locale, $topic + ['is_new' => false]);
    }

    public function update(SaveGuideTopicRequest $request, string $key): RedirectResponse
    {
        return $this->persist($request, 'Topic saved. Readers see the change immediately.');
    }

    public function destroy(Request $request, string $key): RedirectResponse
    {
        $locale = $this->locale($request->input('locale'));
        $topic = $this->editor->find($key, $locale);
        $wasCustom = $topic !== null && !$topic['has_file'];

        if (!$this->editor->reset($key, $locale)) {
            return back()->with('error', 'Nothing to reset: this topic has no edits.');
        }

        return redirect()->route('admin.user-guide.index', ['lang' => $locale])
            ->with('success', $wasCustom ? 'Custom topic deleted.' : 'Topic restored to the default version.');
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'body'   => ['required', 'string', 'max:200000'],
            'key'    => ['nullable', 'string', 'max:100'],
            'locale' => ['required', 'in:' . implode(',', array_keys(config('user_guide.locales')))],
        ]);

        return response()->json(['html' => $this->editor->preview($data['body'], (string) ($data['key'] ?? ''), $data['locale'])]);
    }

    private function persist(SaveGuideTopicRequest $request, string $message): RedirectResponse
    {
        $topic = $request->topic();
        $this->editor->save($topic['key'], $topic['locale'], $topic, (int) session('user.id') ?: null);

        return redirect()->route('admin.user-guide.edit', ['key' => $topic['key'], 'lang' => $topic['locale']])
            ->with('success', $message);
    }

    private function form(string $locale, array $topic): View
    {
        $body = (string) old('body', $topic['body']);

        return view('admin.user-guide.form', [
            'locale'        => $locale,
            'topic'         => $topic,
            'chapters'      => config('user_guide.chapters'),
            'options'       => $this->editor->requirementOptions(),
            // Penyunting visual: isi awal dirender server dengan aturan yang sama seperti halaman baca.
            'editorHtml'    => trim($body) === '' ? '<p><br></p>' : $this->editor->preview($body, (string) $topic['key'], $locale),
            'link'          => $this->editor->linkTargets($locale),
            'calloutTitles' => collect(['note', 'tip', 'important', 'warning'])
                ->mapWithKeys(fn ($t) => [$t => config("user_guide.ui.{$locale}.{$t}", ucfirst($t))])->all(),
        ]);
    }

    /** Bahasa yang sah, bila tidak bahasa bawaan. */
    private function locale(?string $requested): string
    {
        $allowed = array_keys(config('user_guide.locales'));

        return in_array($requested, $allowed, true) ? $requested : (string) config('user_guide.default_locale', 'en');
    }
}
