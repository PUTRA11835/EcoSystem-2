<?php

namespace App\Services;

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
     * Upload a file into a OneDrive folder (identified by its item ID).
     * Uses the simple PUT upload endpoint (supports files up to 4 MB).
     * Returns an array with 'id', 'webUrl', and 'downloadUrl'.
     */
    public function uploadFile(string $folderId, string $fileName, string $fileContent, string $mimeType = 'application/octet-stream'): array
    {
        $token   = $this->getAccessToken();
        $encoded = rawurlencode($fileName);

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
            'webUrl'      => $response->json('webUrl'),
            'downloadUrl' => $response->json('@microsoft.graph.downloadUrl'),
        ];
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
        $token   = $this->getAccessToken();
        $encoded = rawurlencode($fileName);

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
     */
    public function createAnonymousLink(string $folderId, string $type = 'edit'): string
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)->post(
            "{$this->driveBase}/drive/items/{$folderId}/createLink",
            [
                'type'  => $type,
                'scope' => 'anonymous',
            ]
        );

        if (!$response->successful()) {
            Log::error('OneDrive createLink failed', [
                'folder_id' => $folderId,
                'type'      => $type,
                'status'    => $response->status(),
                'body'      => $response->body(),
            ]);
            throw new \RuntimeException('Failed to create OneDrive share link: ' . $response->body());
        }

        return $response->json('link.webUrl');
    }
}
