<?php

namespace App\Services\Guide;

/**
 * Aturan isi topik User Guide yang disunting lewat UI. Sama ketatnya dengan pemeriksa berkas
 * (docs/HRGA/tools/guide-check.php), supaya hasil sunting tidak merusak panduan.
 *
 * Kelas ini murni: semua data acuan (key topik, slug menu, nama role) diberikan lewat konstruktor,
 * jadi bisa diuji tanpa database.
 */
final class GuideContentRules
{
    public const APPROVER = '@approver';
    public const ROLE_PREFIX = '@role:';

    /**
     * @param  string[]  $topicKeys   semua key topik yang ada (untuk tautan [[key]])
     * @param  string[]  $chapterIds
     * @param  string[]  $menuSlugs
     * @param  string[]  $roleNames
     * @param  string    $imageDir    folder gambar panduan di disk
     */
    public function __construct(
        private readonly array $topicKeys,
        private readonly array $chapterIds,
        private readonly array $menuSlugs,
        private readonly array $roleNames,
        private readonly string $imageDir,
    ) {
    }

    /**
     * @param  array{key:string,chapter:string,title:string,summary:string,requires:string[],body:string}  $draft
     * @return array<string,string[]> field => daftar pesan (kosong bila lolos)
     */
    public function violations(array $draft): array
    {
        $errors = [];
        $add = function (string $field, string $msg) use (&$errors) {
            $errors[$field][] = $msg;
        };

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $draft['key']) || strlen($draft['key']) > 100) {
            $add('key', 'Key may only contain lowercase letters, digits and hyphens (max 100 characters).');
        }
        if (!in_array($draft['chapter'], $this->chapterIds, true)) {
            $add('chapter', 'Unknown chapter.');
        }
        if (trim($draft['title']) === '') {
            $add('title', 'Title is required.');
        }

        foreach (['title', 'summary', 'body'] as $field) {
            if (preg_match('/[\x{2013}\x{2014}]/u', $draft[$field])) {
                $add($field, 'Remove em dashes and en dashes; use a comma, colon or full stop instead.');
            }
        }
        if (trim($draft['body']) === '') {
            $add('body', 'Content is required.');
        }

        foreach ($this->requiresProblems($draft['requires']) as $msg) {
            $add('requires', $msg);
        }
        foreach ($this->bodyProblems($draft['body'], $draft['key']) as $msg) {
            $add('body', $msg);
        }

        return $errors;
    }

    /** @param string[] $requires @return string[] */
    private function requiresProblems(array $requires): array
    {
        $out = [];
        foreach ($requires as $req) {
            if ($req === self::APPROVER) {
                continue;
            }
            if (str_starts_with($req, self::ROLE_PREFIX)) {
                if (!in_array(substr($req, strlen(self::ROLE_PREFIX)), $this->roleNames, true)) {
                    $out[] = "Unknown role in \"$req\".";
                }
                continue;
            }
            if (!in_array($req, $this->menuSlugs, true)) {
                $out[] = "Unknown menu permission \"$req\".";
            }
        }

        return $out;
    }

    /** @return string[] */
    private function bodyProblems(string $body, string $ownKey): array
    {
        $out = [];

        // Tanpa HTML mentah (komentar HTML tetap boleh) dan tanpa tautan keluar.
        $withoutComments = preg_replace('/<!--.*?-->/s', '', $body);
        if (preg_match('/<\/?[a-z][^>]*>/i', $withoutComments)) {
            $out[] = 'Raw HTML is not allowed; use Markdown.';
        }
        if (preg_match('/\]\(\s*(?:[a-z][a-z0-9+.\-]*:|\/\/)/i', $body) || preg_match('/^\s*\[[^\]]+\]:\s*(?:[a-z][a-z0-9+.\-]*:|\/\/)/im', $body)) {
            $out[] = 'External links are not allowed in the guide.';
        }

        preg_match_all('/!\[([^\]]*)\]\(([^)\s]*)[^)]*\)/', $body, $images, PREG_SET_ORDER);
        foreach ($images as [$whole, $alt, $src]) {
            if (trim($alt) === '') {
                $out[] = "Image \"$src\" needs alt text.";
            }
            if (!preg_match('#^images/guide/([A-Za-z0-9._\-]+\.(?:png|jpg))$#', $src, $m) || !is_file($this->imageDir . DIRECTORY_SEPARATOR . $m[1])) {
                $out[] = "Image \"$src\" must be an existing images/guide/*.png or *.jpg file.";
            }
        }

        preg_match_all('/\[\[([^\]|]+)(?:\|[^\]]*)?\]\]/', $body, $links);
        foreach (array_unique($links[1]) as $target) {
            if ($target === $ownKey || !in_array($target, $this->topicKeys, true)) {
                $out[] = $target === $ownKey ? "Topic links to itself: [[$target]]." : "Link [[$target]] points to a topic that does not exist.";
            }
        }

        return $out;
    }
}
