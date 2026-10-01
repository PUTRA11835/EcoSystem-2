<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\Letterhead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * HR & General → Letter Templates → Settings: the letterheads the system's
 * letters are printed on, and which letters each one is used for.
 */
class LetterTemplateController extends Controller
{
    public function index()
    {
        return view('hr-general.letter-templates.index', [
            'letterheads' => Letterhead::orderBy('name')->get(),
            'letterTypes' => Letterhead::LETTER_TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        if (!$request->hasFile('header') && !$request->hasFile('footer')) {
            return back()->withInput()->with('error', 'Upload a header image, a footer image, or both.');
        }

        DB::transaction(function () use ($request, $data) {
            $letterhead = Letterhead::create(['name' => $data['name']]);
            $this->save($request, $letterhead, $data);
        });

        return back()->with('success', 'Letterhead added.');
    }

    public function update(Request $request, Letterhead $letterhead)
    {
        $data = $this->validated($request, $letterhead);

        DB::transaction(fn () => $this->save($request, $letterhead, $data));

        return back()->with('success', "Letterhead \"{$letterhead->name}\" saved.");
    }

    public function destroy(Letterhead $letterhead)
    {
        $letterhead->delete();

        return back()->with('success', 'Letterhead deleted.');
    }

    /** The images live on a private disk; this is how the page shows them. */
    public function image(Letterhead $letterhead, string $part)
    {
        abort_unless($path = $letterhead->pathOf($part), 404);

        return Storage::disk(Letterhead::DISK)->response($path);
    }

    private function validated(Request $request, ?Letterhead $letterhead = null): array
    {
        return $request->validate([
            'name'           => ['required', 'string', 'max:100', Rule::unique('letterheads', 'name')->ignore($letterhead?->id)],
            'header'         => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'footer'         => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'letter_types'   => 'nullable|array',
            'letter_types.*' => [Rule::in(array_keys(Letterhead::LETTER_TYPES))],
        ], ['name.unique' => 'A letterhead with that name already exists.']);
    }

    private function save(Request $request, Letterhead $letterhead, array $data): void
    {
        $disk = Storage::disk(Letterhead::DISK);
        $types = array_values($data['letter_types'] ?? []);

        foreach (Letterhead::PARTS as $part) {
            $column = "{$part}_path";
            $file = $request->file($part);

            if ($file || $request->boolean("remove_{$part}")) {
                if ($letterhead->{$column}) {
                    $disk->delete($letterhead->{$column});
                }
                $letterhead->{$column} = $file?->store("letterheads/{$letterhead->id}", Letterhead::DISK);
            }
        }

        $letterhead->fill(['name' => $data['name'], 'letter_types' => $types])->save();

        // A letter is printed on one letterhead: ticking it here takes it off the others.
        Letterhead::whereKeyNot($letterhead->id)->get()
            ->filter(fn (Letterhead $other) => array_intersect($other->letter_types ?? [], $types))
            ->each(fn (Letterhead $other) => $other->update([
                'letter_types' => array_values(array_diff($other->letter_types, $types)),
            ]));
    }
}
