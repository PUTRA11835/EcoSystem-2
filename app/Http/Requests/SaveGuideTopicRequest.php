<?php

namespace App\Http\Requests;

use App\Services\Guide\GuideTopicEditor;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi form editor User Guide. Bentuk data diperiksa di sini; aturan isi
 * (tanda hubung, HTML, gambar, tautan, requires) ada di GuideContentRules.
 *
 * Akses dijaga oleh middleware menu:control-center.user-guide pada route.
 */
class SaveGuideTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key'        => ['required', 'string', 'max:100'],
            'locale'     => ['required', 'in:' . implode(',', array_keys(config('user_guide.locales')))],
            'chapter'    => ['required', 'string', 'max:50'],
            'order'      => ['required', 'integer', 'between:0,9999'],
            'title'      => ['required', 'string', 'max:200'],
            'summary'    => ['nullable', 'string', 'max:500'],
            'requires'   => ['nullable', 'array'],
            'requires.*' => ['string', 'max:150'],
            'body'       => ['required', 'string', 'max:200000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            // Pada update, key diambil dari URL dan tidak bisa diubah lewat form.
            'key'      => $this->route('key') ?? $this->input('key'),
            'summary'  => (string) $this->input('summary', ''),
            'requires' => array_values(array_filter((array) $this->input('requires', []), 'is_string')),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $editor = app(GuideTopicEditor::class);
            $data = $v->validated();

            // Topik baru tidak boleh memakai key yang sudah ada.
            if ($this->route('key') === null && $editor->keyExists($data['key'])) {
                $v->errors()->add('key', 'This key is already used by another topic.');

                return;
            }

            foreach ($editor->rules()->violations($data) as $field => $messages) {
                foreach ($messages as $message) {
                    $v->errors()->add($field, $message);
                }
            }
        });
    }

    /** @return array{key:string,locale:string,chapter:string,order:int,title:string,summary:string,requires:string[],body:string} */
    public function topic(): array
    {
        $d = $this->validated();

        return [
            'key'      => (string) $d['key'],
            'locale'   => $d['locale'],
            'chapter'  => $d['chapter'],
            'order'    => (int) $d['order'],
            'title'    => $d['title'],
            'summary'  => (string) ($d['summary'] ?? ''),
            'requires' => $d['requires'] ?? [],
            'body'     => $d['body'],
        ];
    }
}
