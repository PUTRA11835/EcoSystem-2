<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Salinan satu lampiran internal note di OneDrive, yang tampil sebagai kartu
 * berkas di group chat Teams.
 *
 * Lihat docs/teams-sync-parity-design.md §6c.
 */
class TicketTeamsFile extends Model
{
    protected $table = 'ticket_teams_file';

    protected $fillable = [
        'ticket_id',
        'ticket_attachment_id',
        'drive_item_id',
        'attachment_guid',
        'content_url',
        'name',
    ];

    /**
     * Bentuk lampiran `reference` yang diminta Graph pada POST pesan chat.
     *
     * @return array{id:string,contentType:string,contentUrl:string,name:string}
     */
    public function toGraphAttachment(): array
    {
        return [
            'id'          => $this->attachment_guid,
            'contentType' => 'reference',
            'contentUrl'  => $this->content_url,
            'name'        => $this->name,
        ];
    }
}
