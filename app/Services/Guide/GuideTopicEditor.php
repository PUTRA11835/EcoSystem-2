<?php

namespace App\Services\Guide;

use App\Models\UserGuideTopic;
use Illuminate\Support\Facades\DB;

/**
 * Logika penyuntingan User Guide dari Control Center: daftar status topik, baca, simpan, kembalikan ke bawaan, pratinjau.
 * Controller hanya mengurus HTTP; semua aturan ada di sini dan di GuideContentRules.
 */
final class GuideTopicEditor
{
    public const STATE_DEFAULT = 'default';
    public const STATE_EDITED = 'edited';
    public const STATE_CUSTOM = 'custom';

    public function __construct(private readonly UserGuideService $guide)
    {
    }

    /**
     * Satu baris per topik untuk satu bahasa: bawaan berkas, bawaan yang disunting, atau topik baru dari admin.
     *
     * @return array<int,array{key:string,title:string,chapter:string,state:string,stale:bool,translated:bool,requires:string[]}>
     */
    public function rows(string $locale): array
    {
        $files = $this->guide->loadFiles($locale);
        $enFiles = $locale === 'en' ? $files : $this->guide->loadFiles('en');
        $saved = UserGuideTopic::query()->where('locale', $locale)->get()->keyBy('key');
        $keys = array_unique(array_merge(array_keys($enFiles), array_keys($files), $saved->keys()->all()));

        $rows = [];
        foreach ($keys as $key) {
            $row = $saved[$key] ?? null;
            $base = $files[$key] ?? $enFiles[$key] ?? null;
            $topic = $row?->toTopic() ?? $base;
            if ($topic === null) {
                continue;
            }
            $rows[] = [
                'key'        => $key,
                'title'      => $topic['title'],
                'chapter'    => $topic['chapter'],
                'order'      => $topic['order'],
                'requires'   => $topic['requires'],
                'state'      => $row === null ? self::STATE_DEFAULT : ($this->hasFile($key) ? self::STATE_EDITED : self::STATE_CUSTOM),
                'stale'      => $row !== null && $row->base_hash !== null && $row->base_hash !== $this->fileHash($key, $locale),
                'translated' => $row !== null || isset($files[$key]),
            ];
        }

        $chapterOrder = array_flip(array_keys(config('user_guide.chapters')));
        usort($rows, fn ($a, $b) => [$chapterOrder[$a['chapter']] ?? 99, $a['order'], $a['key']] <=> [$chapterOrder[$b['chapter']] ?? 99, $b['order'], $b['key']]);

        return $rows;
    }

    /**
     * Isi formulir untuk satu topik: hasil sunting bila ada, bila tidak versi berkas (bahasa itu, lalu Inggris sebagai titik awal terjemahan).
     *
     * @return array<string,mixed>|null
     */
    public function find(string $key, string $locale): ?array
    {
        $saved = UserGuideTopic::query()->where(['key' => $key, 'locale' => $locale])->first();
        $file = $this->guide->loadFiles($locale)[$key] ?? null;
        $topic = $saved?->toTopic() ?? $file ?? $this->guide->loadFiles('en')[$key] ?? null;
        if ($topic === null) {
            return null;
        }

        return $topic + [
            'state'    => $saved === null ? self::STATE_DEFAULT : ($this->hasFile($key) ? self::STATE_EDITED : self::STATE_CUSTOM),
            'has_file' => $this->hasFile($key),
            'stale'    => $saved !== null && $saved->base_hash !== null && $saved->base_hash !== $this->fileHash($key, $locale),
            'borrowed' => $saved === null && $file === null,
        ];
    }

    /** @param array{chapter:string,title:string,summary:string,requires:string[],body:string,order:int} $data */
    public function save(string $key, string $locale, array $data, ?int $employeeId): UserGuideTopic
    {
        $existing = UserGuideTopic::query()->where(['key' => $key, 'locale' => $locale])->first();

        return UserGuideTopic::query()->updateOrCreate(
            ['key' => $key, 'locale' => $locale],
            [
                'chapter'    => $data['chapter'],
                'sort_order' => $data['order'],
                'title'      => trim($data['title']),
                'summary'    => trim($data['summary']),
                'requires'   => array_values(array_unique($data['requires'])),
                'body'       => trim(str_replace("\r\n", "\n", $data['body'])),
                'updated_on' => now()->toDateString(),
                // Titik acuan perbandingan dengan berkas bawaan diambil sekali, saat topik pertama kali disunting.
                'base_hash'  => $existing?->base_hash ?? $this->fileHash($key, $locale),
                'edited_by'  => $employeeId,
            ]
        );
    }

    /** Hapus hasil sunting. Topik bawaan kembali ke berkas; topik baru dari admin ikut hilang. */
    public function reset(string $key, string $locale): bool
    {
        $row = UserGuideTopic::query()->where(['key' => $key, 'locale' => $locale])->first();

        return $row !== null && $row->delete();
    }

    /** Key sudah dipakai topik berkas atau topik admin di bahasa mana pun. */
    public function keyExists(string $key): bool
    {
        return $this->hasFile($key) || UserGuideTopic::query()->where('key', $key)->exists();
    }

    public function rules(): GuideContentRules
    {
        return new GuideContentRules(
            $this->allKeys(),
            array_keys(config('user_guide.chapters')),
            DB::table('menu')->pluck('slug')->all(),
            DB::table('employee_role')->pluck('name')->all(),
            public_path('images/guide'),
        );
    }

    /** Pratinjau Markdown dengan aturan render yang sama seperti halaman asli. */
    public function preview(string $markdown, string $key, string $locale): string
    {
        $titles = [];
        foreach ($this->rows($locale) as $row) {
            $titles[$row['key']] = $row['title'];
        }
        $html = $this->guide->render($markdown, $key ?: 'preview', $locale, $titles)['html'];

        // Gambar panduan dilayani dari public/, bukan relatif terhadap halaman editor.
        return str_replace('src="images/guide/', 'src="' . asset('images/guide') . '/', $html);
    }

    /**
     * Data untuk penyunting visual: judul tiap topik (mengubah [[key]] menjadi tautan bernama) dan daftar topik untuk pemilih tautan.
     *
     * @return array{titles:array<string,string>,topics:array<int,array{key:string,title:string,chapter:string}>}
     */
    public function linkTargets(string $locale): array
    {
        $chapters = config('user_guide.chapters');
        $titles = [];
        $topics = [];
        foreach ($this->rows($locale) as $row) {
            $titles[$row['key']] = $row['title'];
            $topics[] = [
                'key'     => $row['key'],
                'title'   => $row['title'],
                'chapter' => $chapters[$row['chapter']]['title'][$locale] ?? $chapters[$row['chapter']]['title']['en'] ?? $row['chapter'],
            ];
        }

        return ['titles' => $titles, 'topics' => $topics];
    }

    /** @return string[] */
    public function chapters(): array
    {
        return array_keys(config('user_guide.chapters'));
    }

    /** Pilihan untuk formulir: menu, role. @return array{menus:array<int,object>,roles:string[]} */
    public function requirementOptions(): array
    {
        return [
            'menus' => DB::table('menu')->orderBy('slug')->get(['slug', 'name'])->all(),
            'roles' => DB::table('employee_role')->orderBy('name')->pluck('name')->all(),
        ];
    }

    /** @return string[] */
    private function allKeys(): array
    {
        return array_values(array_unique(array_merge(
            array_keys($this->guide->loadFiles('en')),
            array_keys($this->guide->loadFiles('id')),
            UserGuideTopic::query()->pluck('key')->all(),
        )));
    }

    private function hasFile(string $key): bool
    {
        return $this->fileFor($key, 'en') !== null || $this->fileFor($key, 'id') !== null;
    }

    /** md5 isi berkas mentah topik di bahasa itu; null bila tidak ada berkasnya. */
    private function fileHash(string $key, string $locale): ?string
    {
        $file = $this->fileFor($key, $locale);

        return $file === null ? null : md5(str_replace("\r\n", "\n", (string) file_get_contents($file)));
    }

    /** @var array<string,array<string,string>> locale => key => path */
    private array $fileIndex = [];

    private function fileFor(string $key, string $locale): ?string
    {
        if (!isset($this->fileIndex[$locale])) {
            $this->fileIndex[$locale] = [];
            foreach ($this->guide->files($locale) as $file) {
                $parsed = $this->guide->parseFile($file);
                if ($parsed !== null) {
                    $this->fileIndex[$locale][$parsed['key']] = $file;
                }
            }
        }

        return $this->fileIndex[$locale][$key] ?? null;
    }
}
