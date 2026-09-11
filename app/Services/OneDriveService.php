<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OneDriveService
{
    private string $baseUrl;
    private string $driveBase;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.microsoft_graph.base_url', 'https://graph.microsoft.com/v1.0'), '/');

        $siteId = config('services.microsoft_graph.sharepoint_site_id');
        if ($siteId) {
            $this->driveBase = "{$this->baseUrl}/sites/{$siteId}";
        } else {
            $email = config('services.microsoft_graph.sender_email');
            $this->driveBase = "{$this->baseUrl}/users/{$email}";
        }
    }

    /**
     * Sanitize a single path segment (folder name) for OneDrive/SharePoint.
     * Replaces illegal characters ( \ / : * ? " < > | ) with a space,
     * collapses whitespace, and trims. Falls back to a safe default if empty.
     */
    public static function sanitizeSegment(string $name, string $fallback = 'Folder'): string
    {
        $clean = preg_replace('~[\\\\/:*?"<>|]~', ' ', $name);
        $clean = trim(preg_replace('~\s+~', ' ', $clean));
        return $clean !== '' ? $clean : $fallback;
    }

    /**
     * Bersihkan nama file untuk OneDrive/SharePoint dengan mempertahankan ekstensi.
     *
     * WAJIB dipakai sebelum menyentuh Graph. Selain karakter ilegal ( \ / : * ? " < > | )
     * ditolak SharePoint, karakter ":" juga MERUSAK path-addressing Graph
     * (`/drive/items/{id}:/{nama}:/content`) — request jadi diarahkan ke entitas
     * driveItem, dan Graph menjawab dengan error yang menyesatkan:
     *   "Entity only allows writes with a JSON Content-Type header"  (PUT /content)
     *   "invalidRequest / Invalid request"                            (createUploadSession)
     * Keduanya sebetulnya berarti: nama file tidak valid.
     */
    public static function sanitizeFileName(string $fileName, string $fallback = 'file'): string
    {
        // Buang komponen path yang mungkin ikut terbawa dari client.
        $name = basename(str_replace('\\', '/', $fileName));

        $name = preg_replace('~[\\\\/:*?"<>|]~', ' ', $name);   // karakter ilegal SharePoint
        $name = preg_replace('~[\x00-\x1F\x7F]~', '', $name);   // karakter kontrol
        $name = trim(preg_replace('~\s+~', ' ', $name));
        $name = ltrim($name, '~');                              // "~$xxx" = file lock Office
        $name = trim($name, ". \t");                            // titik/spasi di ujung ditolak

        if ($name === '') {
            return $fallback;
        }

        // Batasi panjang tanpa membuang ekstensi (batas nama OneDrive 255 karakter;
        // 180 menyisakan ruang untuk path folder yang panjang).
        $maxLength = 180;
        if (mb_strlen($name) > $maxLength) {
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            $base      = pathinfo($name, PATHINFO_FILENAME);
            $suffix    = $extension !== '' ? '.' . $extension : '';
            $name      = mb_substr($base, 0, $maxLength - mb_strlen($suffix)) . $suffix;
        }

        return $name;
    }

    private function getAccessToken(): string
    {
        $tenantId = config('services.microsoft_graph.tenant_id');

        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => config('services.microsoft_graph.client_id'),
                'client_secret' => config('services.microsoft_graph.client_secret'),
                'scope'         => 'https://graph.microsoft.com/.default',
            ]
        );

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to obtain Microsoft Graph access token: ' . $response->body());
        }

        return $response->json('access_token');
    }

    /**
     * Create a folder in the OneDrive root of the sender account.
     * Uses conflictBehavior=rename so duplicate names don't error out.
     * Returns the new folder's item ID.
     */
    public function createFolder(string $folderName): string
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)->post(
            "{$this->driveBase}/drive/root/children",
            [
                'name'                              => $folderName,
                'folder'                            => new \stdClass(),
                '@microsoft.graph.conflictBehavior' => 'rename',
            ]
        );

        if (!$response->successful()) {
            Log::error('OneDrive createFolder failed', [
                'folder_name' => $folderName,
                'status'      => $response->status(),
                'body'        => $response->body(),
            ]);
            throw new \RuntimeException('Failed to create OneDrive folder: ' . $response->body());
        }

        return $response->json('id');
    }

    /**
     * Permanently delete a folder by its item ID.
     * If the item is already gone (404) we treat it as success.
     */
    public function deleteFolder(string $folderId): void
    {
        $token    = $this->getAccessToken();
        $response = Http::withToken($token)->delete(
            "{$this->driveBase}/drive/items/{$folderId}"
        );

        if (!$response->successful() && $response->status() !== 404) {
            Log::error('OneDrive deleteFolder failed', [
                'folder_id' => $folderId,
                'status'    => $response->status(),
                'body'      => $response->body(),
            ]);
            throw new \RuntimeException('Failed to delete OneDrive folder: ' . $response->body());
        }
    }

    /**
     * Create a folder inside a parent folder specified by its path (e.g. "TICKETING").
     * Uses conflictBehavior=rename so duplicate names don't error out.
     * Returns the new folder's item ID.
     */
    public function createFolderInPath(string $folderName, string $parentPath): string
    {
        $token = $this->getAccessToken();
        $encodedParent = implode('/', array_map('rawurlencode', explode('/', $parentPath)));

        $response = Http::withToken($token)->post(
            "{$this->driveBase}/drive/root:/{$encodedParent}:/children",
            [
                'name'                              => $folderName,
                'folder'                            => new \stdClass(),
                '@microsoft.graph.conflictBehavior' => 'rename',
            ]
        );

        if (!$response->successful()) {
            Log::error('OneDrive createFolderInPath failed', [
                'folder_name' => $folderName,
                'parent_path' => $parentPath,
                'status'      => $response->status(),
                'body'        => $response->body(),
            ]);
            throw new \RuntimeException('Failed to create OneDrive folder in path: ' . $response->body());
        }

        return $response->json('id');
    }

    /**
     * List immediate children (folders only) of a folder specified by its path.
     * Returns an array of ['id' => ..., 'name' => ...] for each child folder.
     */
    public function listFolderChildrenByPath(string $folderPath): array
    {
        $token   = $this->getAccessToken();
        $encoded = implode('/', array_map('rawurlencode', explode('/', $folderPath)));

        $response = Http::withToken($token)->get(
            "{$this->driveBase}/drive/root:/{$encoded}:/children",
            ['$select' => 'id,name,folder,webUrl', '$top' => 200]
        );

        // Folder belum ada (belum pernah dibuat) → perlakukan sebagai kosong, bukan error.
        if ($response->status() === 404) {
            return [];
        }

        if (!$response->successful()) {
            $errorCode = $response->json('error.code', '');

            // Folder belum dibuat — return kosong, bukan error
            if ($response->status() === 404 && in_array($errorCode, ['itemNotFound', 'ItemNotFound'])) {
                return [];
            }

            Log::error('OneDrive listFolderChildrenByPath failed', [
                'path'   => $folderPath,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('Failed to list OneDrive folder children: ' . $response->body());
        }

        return collect($response->json('value', []))
            ->filter(fn($item) => isset($item['folder']))
            ->map(fn($item) => ['id' => $item['id'], 'name' => $item['name'], 'webUrl' => $item['webUrl'] ?? null])
            ->values()
            ->all();
    }

    /**
     * Find an existing folder by exact name inside a path, or create it if absent.
     * Comparison is case-insensitive.
     * Returns the folder's item ID.
     */
    public function findOrCreateFolderInPath(string $parentPath, string $folderName): string
    {
        $children = $this->listFolderChildrenByPath($parentPath);

        $needle = mb_strtolower(trim($folderName));
        foreach ($children as $child) {
            if (mb_strtolower($child['name']) === $needle) {
                return $child['id'];
            }
        }

        // Not found — create it
        return $this->createFolderInPath($folderName, $parentPath);
    }

    /**
     * Create a sub-folder inside a parent folder identified by its item ID.
     * Returns the new sub-folder's item ID.
     */
    public function createSubFolder(string $parentFolderId, string $folderName): string
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)->post(
            "{$this->driveBase}/drive/items/{$parentFolderId}/children",
            [
                'name'                              => $folderName,
                'folder'                            => new \stdClass(),
                '@microsoft.graph.conflictBehavior' => 'rename',
            ]
        );

        if (!$response->successful()) {
            Log::error('OneDrive createSubFolder failed', [
                'parent_folder_id' => $parentFolderId,
                'folder_name'      => $folderName,
                'status'           => $response->status(),
                'body'             => $response->body(),
            ]);
            throw new \RuntimeException('Failed to create OneDrive sub-folder: ' . $response->body());
        }

        return $response->json('id');
    }

    /**
     * Move an item (folder/file) to a new parent folder, optionally renaming it.
     * Item IDs are stable across moves, so cached child IDs remain valid afterwards.
     */
    public function moveItem(string $itemId, string $newParentId, ?string $newName = null): void
    {
        $token = $this->getAccessToken();

        $payload = ['parentReference' => ['id' => $newParentId]];
        if ($newName !== null) {
            $payload['name'] = $newName;
        }

        $response = Http::withToken($token)->patch(
            "{$this->driveBase}/drive/items/{$itemId}",
            $payload
        );

        if (!$response->successful()) {
            Log::error('OneDrive moveItem failed', [
                'item_id'       => $itemId,
                'new_parent_id' => $newParentId,
                'new_name'      => $newName,
                'status'        => $response->status(),
                'body'          => $response->body(),
            ]);
            throw new \RuntimeException('Failed to move OneDrive item: ' . $response->body());
        }
    }

    /**
     * Rename an item (folder/file) in place. The item ID is unchanged, so any
     * cached IDs/share links remain valid.
     */
    public function renameItem(string $itemId, string $newName): void
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)->patch(
            "{$this->driveBase}/drive/items/{$itemId}",
            ['name' => $newName]
        );

        if (!$response->successful()) {
            Log::error('OneDrive renameItem failed', [
                'item_id'  => $itemId,
                'new_name' => $newName,
                'status'   => $response->status(),
                'body'     => $response->body(),
            ]);
            throw new \RuntimeException('Failed to rename OneDrive item: ' . $response->body());
        }
    }

    /**
     * Upload a file into a OneDrive folder (identified by its item ID).
     * Uses the simple PUT upload endpoint (supports files up to 4 MB).
     * Returns an array with 'id', 'webUrl', and 'downloadUrl'.
     */
    public function uploadFile(string $folderId, string $fileName, string $fileContent, string $mimeType = 'application/octet-stream'): array
    {
        $token    = $this->getAccessToken();
        $fileName = self::sanitizeFileName($fileName);
        $encoded  = rawurlencode($fileName);

        $response = Http::withToken($token)
            ->withBody($fileContent, $mimeType)
            ->put("{$this->driveBase}/drive/items/{$folderId}:/{$encoded}:/content");

        if (!$response->successful()) {
            Log::error('OneDrive uploadFile failed', [
                'folder_id' => $folderId,
                'file_name' => $fileName,
                'status'    => $response->status(),
                'body'      => $response->body(),
            ]);
            throw new \RuntimeException('Failed to upload file to OneDrive: ' . $response->body());
        }

        return [
            'id'          => $response->json('id'),
            // Nama final di OneDrive — bisa berbeda dari nama asli (disanitasi, atau
            // di-rename otomatis saat bentrok). Caller sebaiknya menyimpan ini.
            'name'        => $response->json('name', $fileName),
            'webUrl'      => $response->json('webUrl'),
            'downloadUrl' => $response->json('@microsoft.graph.downloadUrl'),
        ];
    }

    /**
     * Permanently delete any drive item (file OR folder) by its item ID.
     * Sudah hilang (404) dianggap sukses — pemanggil hanya peduli "tidak ada lagi".
     */
    public function deleteItem(string $itemId): void
    {
        $response = Http::withToken($this->getAccessToken())->delete(
            "{$this->driveBase}/drive/items/{$itemId}"
        );

        if (!$response->successful() && $response->status() !== 404) {
            Log::error('OneDrive deleteItem failed', [
                'item_id' => $itemId,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);
            throw new \RuntimeException('Failed to delete OneDrive item: ' . $response->body());
        }
    }

    /**
     * Cari file (bukan folder) di dalam sebuah folder berdasarkan nama.
     * Dipakai untuk memulihkan item ID baris lama yang hanya menyimpan URL.
     * Nama dicocokkan case-insensitive setelah disanitasi seperti saat upload.
     *
     * @return array{id:string,name:string,webUrl:?string}|null
     */
    public function findFileInFolderByName(string $parentFolderId, string $fileName): ?array
    {
        $response = Http::withToken($this->getAccessToken())->get(
            "{$this->driveBase}/drive/items/{$parentFolderId}/children",
            ['$select' => 'id,name,file,webUrl', '$top' => 200]
        );

        if ($response->status() === 404) {
            return null;
        }

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to list OneDrive folder children: ' . $response->body());
        }

        $needle = mb_strtolower(self::sanitizeFileName($fileName));

        foreach ($response->json('value', []) as $child) {
            if (!isset($child['file'])) {
                continue;
            }
            if (mb_strtolower($child['name'] ?? '') === $needle) {
                return [
                    'id'     => $child['id'],
                    'name'   => $child['name'],
                    'webUrl' => $child['webUrl'] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * List immediate children (folders only) of a folder specified by its item ID.
     * Returns an array of ['id' => ..., 'name' => ...] for each child folder.
     */
    public function listSubFoldersByParentId(string $parentFolderId): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)->get(
            "{$this->driveBase}/drive/items/{$parentFolderId}/children",
            ['$select' => 'id,name,folder', '$top' => 200]
        );

        // Folder induk tidak ditemukan (ID basi/terhapus) → perlakukan sebagai kosong.
        if ($response->status() === 404) {
            return [];
        }

        if (!$response->successful()) {
            Log::error('OneDrive listSubFoldersByParentId failed', [
                'parent_id' => $parentFolderId,
                'status'    => $response->status(),
                'body'      => $response->body(),
            ]);
            throw new \RuntimeException('Failed to list OneDrive subfolder children: ' . $response->body());
        }

        return collect($response->json('value', []))
            ->filter(fn($item) => isset($item['folder']))
            ->map(fn($item) => ['id' => $item['id'], 'name' => $item['name']])
            ->values()
            ->all();
    }

    /**
     * Find an existing subfolder by name inside a parent folder (by item ID),
     * or create it if it does not exist.
     * Comparison is case-insensitive.
     * Returns the subfolder's item ID.
     */
    public function findOrCreateSubFolderById(string $parentFolderId, string $folderName): string
    {
        $children = $this->listSubFoldersByParentId($parentFolderId);

        $needle = mb_strtolower(trim($folderName));
        foreach ($children as $child) {
            if (mb_strtolower($child['name']) === $needle) {
                return $child['id'];
            }
        }

        // Not found — create it
        return $this->createSubFolder($parentFolderId, $folderName);
    }

    /**
     * Create an upload session for large files (>4 MB).
     * Returns the uploadUrl that the client can PUT chunks to directly.
     */
    public function createUploadSession(string $folderId, string $fileName): string
    {
        $token    = $this->getAccessToken();
        $fileName = self::sanitizeFileName($fileName);
        $encoded  = rawurlencode($fileName);

        $response = Http::withToken($token)->post(
            "{$this->driveBase}/drive/items/{$folderId}:/{$encoded}:/createUploadSession",
            [
                'item' => [
                    '@microsoft.graph.conflictBehavior' => 'rename',
                    'name'                              => $fileName,
                ],
            ]
        );

        if (!$response->successful()) {
            Log::error('OneDrive createUploadSession failed', [
                'folder_id' => $folderId,
                'file_name' => $fileName,
                'status'    => $response->status(),
                'body'      => $response->body(),
            ]);
            throw new \RuntimeException('Failed to create OneDrive upload session: ' . $response->body());
        }

        return $response->json('uploadUrl');
    }

    /**
     * Create an anonymous share link for the given folder ID.
     * type='edit' grants upload + edit access to anyone with the link.
     * Returns the web URL of the share link.
     *
     * Backward-compatible thin wrapper around createShareLink(). Prefer
     * createShareLink() when the caller needs to know the resulting scope /
     * expiry (see the "link tidak bisa diakses" notes in that method).
     */
    public function createAnonymousLink(string $folderId, string $type = 'edit'): string
    {
        return $this->createShareLink($folderId, $type)['url'];
    }

    /**
     * Create (or re-use) a sharing link and report exactly what Microsoft gave us.
     *
     * Kenapa tidak cukup mengembalikan webUrl saja: Graph TIDAK selalu menghormati
     * scope=anonymous. Bila kebijakan tenant/OneDrive melarang "Anyone" links, Graph
     * boleh (a) menolak permintaan, atau (b) mengembalikan link dengan scope lain
     * (organization/users). Kalau hasilnya disimpan begitu saja, link terlihat
     * normal di EcoSystem tapi meminta login/Request access di sisi penerima.
     * Method ini karena itu:
     *   - mendeteksi penurunan scope dan menandainya (`downgraded`),
     *   - fallback otomatis ke scope organization bila anonymous ditolak, supaya
     *     minimal orang internal tetap punya link yang valid (bukan gagal total),
     *   - mengembalikan expiry link (tenant bisa memaksa "Anyone links expire in N days"),
     *   - memvalidasi bahwa URL yang didapat benar-benar share link.
     *
     * @return array{url:string,scope:string,type:string,requested_scope:string,
     *               downgraded:bool,has_password:bool,expires_at:?\Illuminate\Support\Carbon,
     *               permission_id:?string}
     */
    public function createShareLink(string $itemId, string $type = 'edit', string $scope = 'anonymous'): array
    {
        $response = $this->postCreateLink($itemId, $type, $scope);

        // Anonymous ditolak (kebijakan tenant/site) → coba scope organization agar
        // pengguna internal tetap dapat link yang berfungsi; caller diberi tahu.
        if (!$response->successful() && $scope === 'anonymous') {
            Log::warning('OneDrive createLink anonymous rejected — falling back to organization scope', [
                'item_id' => $itemId,
                'type'    => $type,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);
            $response = $this->postCreateLink($itemId, $type, 'organization');
        }

        if (!$response->successful()) {
            Log::error('OneDrive createLink failed', [
                'item_id' => $itemId,
                'type'    => $type,
                'scope'   => $scope,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);
            throw new \RuntimeException('Failed to create OneDrive share link: ' . $response->body());
        }

        $url = $response->json('link.webUrl');
        if (!$url) {
            throw new \RuntimeException('OneDrive returned a share permission without a webUrl.');
        }

        $actualScope = $response->json('link.scope') ?: $scope;
        $expiresRaw  = $response->json('expirationDateTime');

        if ($actualScope !== $scope) {
            Log::warning('OneDrive share link scope downgraded by tenant policy', [
                'item_id'         => $itemId,
                'requested_scope' => $scope,
                'actual_scope'    => $actualScope,
            ]);
        }

        return [
            'url'             => $url,
            'scope'           => $actualScope,
            'type'            => $response->json('link.type') ?: $type,
            'requested_scope' => $scope,
            'downgraded'      => $actualScope !== $scope,
            'has_password'    => (bool) $response->json('hasPassword', false),
            'expires_at'      => $expiresRaw ? Carbon::parse($expiresRaw) : null,
            'permission_id'   => $response->json('id'),
        ];
    }

    private function postCreateLink(string $itemId, string $type, string $scope)
    {
        return Http::withToken($this->getAccessToken())->post(
            "{$this->driveBase}/drive/items/{$itemId}/createLink",
            [
                'type'  => $type,
                'scope' => $scope,
            ]
        );
    }

    /**
     * List every sharing permission currently attached to an item.
     * Returns [] when the item no longer exists (404).
     *
     * @return array<int,array{id:?string,scope:?string,type:?string,url:?string,expires_at:?\Illuminate\Support\Carbon}>
     */
    public function listItemPermissions(string $itemId): array
    {
        $response = Http::withToken($this->getAccessToken())->get(
            "{$this->driveBase}/drive/items/{$itemId}/permissions"
        );

        if ($response->status() === 404) {
            return [];
        }

        if (!$response->successful()) {
            Log::error('OneDrive listItemPermissions failed', [
                'item_id' => $itemId,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);
            throw new \RuntimeException('Failed to list OneDrive item permissions: ' . $response->body());
        }

        return collect($response->json('value', []))
            ->filter(fn($p) => isset($p['link']))
            ->map(fn($p) => [
                'id'         => $p['id'] ?? null,
                'scope'      => $p['link']['scope'] ?? null,
                'type'       => $p['link']['type'] ?? null,
                'url'        => $p['link']['webUrl'] ?? null,
                'expires_at' => isset($p['expirationDateTime']) ? Carbon::parse($p['expirationDateTime']) : null,
            ])
            ->values()
            ->all();
    }

    /**
     * True when the item still exists in the drive (cheap existence probe).
     */
    public function itemExists(string $itemId): bool
    {
        $response = Http::withToken($this->getAccessToken())->get(
            "{$this->driveBase}/drive/items/{$itemId}",
            ['$select' => 'id']
        );

        if ($response->status() === 404) {
            return false;
        }

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to probe OneDrive item: ' . $response->body());
        }

        return true;
    }

    /**
     * Bedakan share link (`/:f:/g/...`, `/:w:/g/...`) dari webUrl langsung
     * (`.../Documents/...` atau `_layouts/15/onedrive.aspx?id=...`).
     *
     * webUrl langsung HANYA bisa dibuka akun yang punya izin item tersebut —
     * kalau URL semacam itu tersimpan sebagai "share link", penerima eksternal
     * pasti kena halaman "Request access".
     */
    public static function isShareLinkUrl(?string $url): bool
    {
        if (!$url) {
            return false;
        }

        // Share link selalu memakai segmen ":<x>:/" (mis. /:f:/g/, /:b:/s/) setelah host.
        return (bool) preg_match('~/:[a-z]:/~i', $url);
    }
}
