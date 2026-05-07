<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OneDriveService
{
    private string $baseUrl;
    private string $userEmail;

    public function __construct()
    {
        $this->baseUrl   = rtrim(config('services.microsoft_graph.base_url', 'https://graph.microsoft.com/v1.0'), '/');
        $this->userEmail = config('services.microsoft_graph.sender_email');
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
            "{$this->baseUrl}/users/{$this->userEmail}/drive/root/children",
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
            "{$this->baseUrl}/users/{$this->userEmail}/drive/items/{$folderId}"
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
        $encodedParent = rawurlencode($parentPath);

        $response = Http::withToken($token)->post(
            "{$this->baseUrl}/users/{$this->userEmail}/drive/root:/{$encodedParent}:/children",
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
            ->put("{$this->baseUrl}/users/{$this->userEmail}/drive/items/{$folderId}:/{$encoded}:/content");

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
     * Create an anonymous share link for the given folder ID.
     * type='edit' grants upload + edit access to anyone with the link.
     * Returns the web URL of the share link.
     */
    public function createAnonymousLink(string $folderId, string $type = 'edit'): string
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)->post(
            "{$this->baseUrl}/users/{$this->userEmail}/drive/items/{$folderId}/createLink",
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
