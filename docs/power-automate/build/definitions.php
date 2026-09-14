<?php

/**
 * Sumber tunggal definisi ketiga flow Power Automate EcoSystem.
 *
 * Dipakai dua pengemas:
 *   - build-solution.php        -> Dataverse solution (.zip)  [dipakai saat ini]
 *   - build-legacy-packages.php -> legacy import package (.zip)
 *
 * Tenant PT Eclectic mengaktifkan "Create in Dataverse solutions", sehingga
 * legacy package DITOLAK importer. Berkas legacy tetap dipertahankan karena
 * formatnya jauh lebih sederhana untuk dibaca saat mencocokkan definisi.
 */

const SECRET_PLACEHOLDER = 'GANTI_DENGAN_POWER_AUTOMATE_SECRET';

const API_TEAMS   = '/providers/Microsoft.PowerApps/apis/shared_teams';
const API_OUTLOOK = '/providers/Microsoft.PowerApps/apis/shared_office365';

// ─── Helper umum ─────────────────────────────────────────────────────────────

function j($data): string
{
    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function put(string $path, string $contents): void
{
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, $contents);
}

/** Kemas folder menjadi zip dengan pemisah path "/" (Compress-Archive menulis "\"). */
function zipDirectory(string $base, string $zipPath): int
{
    @unlink($zipPath);
    @mkdir(dirname($zipPath), 0777, true);

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        fwrite(STDERR, "Gagal membuat $zipPath\n");
        exit(1);
    }

    $count = 0;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) {
            continue;
        }
        $local = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
        $zip->addFile($file->getPathname(), $local);
        $count++;
    }

    $zip->close();

    return $count;
}

// ─── Skema payload ───────────────────────────────────────────────────────────

/**
 * Field yang boleh null (modul/prioritas/scale belum diisi saat validasi)
 * ditulis sebagai union type. Kalau ditulis "string" saja, payload dengan null
 * ditolak trigger dan flow gagal sebelum aksi pertama.
 */
function nullableString(): array
{
    return ['type' => ['string', 'null']];
}

function ticketSchema(): array
{
    return [
        'type'       => 'object',
        'properties' => [
            'id'           => ['type' => 'integer'],
            'number'       => ['type' => 'string'],
            'subject'      => nullableString(),
            'status'       => ['type' => 'string'],
            'status_label' => ['type' => 'string'],
            'priority'     => nullableString(),
            'type'         => nullableString(),
            'scale'        => nullableString(),
            'channel'      => nullableString(),
            'customer'     => nullableString(),
            'end_customer' => nullableString(),
            'module'       => nullableString(),
            'module_id'    => ['type' => ['integer', 'null']],
            'submitted_by' => [
                'type'       => 'object',
                'properties' => [
                    'name'  => nullableString(),
                    'email' => nullableString(),
                    'phone' => nullableString(),
                ],
            ],
            'pic'         => nullableString(),
            'members'     => ['type' => 'array', 'items' => ['type' => 'string']],
            'is_assigned' => ['type' => 'boolean'],
            'created_at'  => nullableString(),
            'age_minutes' => ['type' => 'integer'],
            'url'         => ['type' => 'string'],
        ],
    ];
}

function leadsSchema(): array
{
    return [
        'type'  => 'array',
        'items' => [
            'type'       => 'object',
            'properties' => [
                'employee_id' => ['type' => 'integer'],
                'name'        => nullableString(),
                'email'       => nullableString(),
            ],
        ],
    ];
}

// ─── Blok penyusun definisi ──────────────────────────────────────────────────

function requestTrigger(array $schema): array
{
    return [
        'manual' => [
            'type'   => 'Request',
            'kind'   => 'Http',
            'inputs' => [
                'schema' => $schema,
                'method' => 'POST',
            ],
        ],
    ];
}

/**
 * Bungkus isi flow dalam Condition pemeriksa header X-EcoSystem-Secret.
 * Cabang else langsung Terminate(Cancelled) supaya panggilan tanpa secret tidak
 * pernah menghasilkan pesan Teams / email.
 */
function secretGate(array $innerActions): array
{
    return [
        'Cek_secret_EcoSystem' => [
            'type'       => 'If',
            'expression' => [
                'equals' => [
                    "@triggerOutputs()?['headers']?['X-EcoSystem-Secret']",
                    SECRET_PLACEHOLDER,
                ],
            ],
            'actions' => $innerActions,
            'else'    => [
                'actions' => [
                    'Hentikan_secret_tidak_cocok' => [
                        'type'     => 'Terminate',
                        'inputs'   => ['runStatus' => 'Cancelled'],
                        'runAfter' => new stdClass(),
                    ],
                ],
            ],
            'runAfter' => new stdClass(),
        ],
    ];
}

function workflow(array $trigger, array $actions): array
{
    return [
        '$schema'        => 'https://schema.management.azure.com/providers/Microsoft.Logic/schemas/2016-06-01/workflowdefinition.json#',
        'contentVersion' => '1.0.0.0',
        'parameters'     => [
            '$connections'    => ['defaultValue' => new stdClass(), 'type' => 'Object'],
            '$authentication' => ['defaultValue' => new stdClass(), 'type' => 'SecureObject'],
        ],
        'triggers' => $trigger,
        'actions'  => $actions,
        'outputs'  => new stdClass(),
    ];
}

/** runAfter kosong WAJIB objek {} — json_encode PHP menulis array kosong sebagai []. */
function connectorAction(string $connectionName, string $apiId, string $operationId, array $parameters, array $runAfter = []): array
{
    return [
        'type'   => 'OpenApiConnection',
        'inputs' => [
            'host' => [
                'connectionName' => $connectionName,
                'operationId'    => $operationId,
                'apiId'          => $apiId,
            ],
            'parameters'     => $parameters,
            'authentication' => "@parameters('\$authentication')",
        ],
        'runAfter' => $runAfter ?: new stdClass(),
    ];
}

/** Kartu Teams ke channel tim. Team/Channel sengaja kosong — dipilih di designer. */
function postCardToChannel(string $name, string $card, array $runAfter = []): array
{
    return [$name => connectorAction('shared_teams', API_TEAMS, 'PostCardToConversation', [
        'poster'                   => 'Flow bot',
        'location'                 => 'Channel',
        'body/recipient/groupId'   => '',
        'body/recipient/channelId' => '',
        'body/messageBody'         => $card,
    ], $runAfter)];
}

/** Kartu Teams ke chat pribadi tiap lead, dibungkus Apply to each atas lead_emails. */
function postCardToEachLead(string $foreachName, string $actionName, string $card, array $runAfter = []): array
{
    return [
        $foreachName => [
            'type'    => 'Foreach',
            'foreach' => "@triggerBody()?['lead_emails']",
            'actions' => [
                $actionName => connectorAction('shared_teams', API_TEAMS, 'PostCardToConversation', [
                    'poster'            => 'Flow bot',
                    'location'          => 'Chat with Flow bot',
                    'body/recipient/to' => "@items('" . $foreachName . "')",
                    'body/messageBody'  => $card,
                ]),
            ],
            'runAfter' => $runAfter ?: new stdClass(),
        ],
    ];
}

// ─── Adaptive Card ───────────────────────────────────────────────────────────

function cardTicketValidatedChannel(): string
{
    return j([
        'type'    => 'AdaptiveCard',
        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
        'version' => '1.4',
        'body'    => [
            // Judul memakai format penamaan tiket "#nomor - subject": kartu ini
            // jadi kepala thread di channel, jadi barisnya sekaligus nama "ruang"
            // diskusi tiket tersebut.
            [
                'type'   => 'TextBlock',
                'text'   => "#@{triggerBody()?['ticket']?['number']} - @{triggerBody()?['ticket']?['subject']}",
                'weight' => 'Bolder',
                'size'   => 'Large',
                'wrap'   => true,
            ],
            [
                'type'     => 'TextBlock',
                'text'     => "Tiket baru divalidasi - balas di thread ini untuk membahasnya.",
                'wrap'     => true,
                'spacing'  => 'None',
                'isSubtle' => true,
            ],
            [
                'type'  => 'FactSet',
                'facts' => [
                    ['title' => 'Customer',        'value' => "@{coalesce(triggerBody()?['ticket']?['customer'], '-')}"],
                    ['title' => 'Modul',           'value' => "@{coalesce(triggerBody()?['ticket']?['module'], 'Belum ditentukan')}"],
                    ['title' => 'Prioritas',       'value' => "@{coalesce(triggerBody()?['ticket']?['priority'], '-')}"],
                    ['title' => 'Tipe',            'value' => "@{coalesce(triggerBody()?['ticket']?['type'], '-')}"],
                    ['title' => 'Pelapor',         'value' => "@{coalesce(triggerBody()?['ticket']?['submitted_by']?['name'], '-')}"],
                    ['title' => 'Divalidasi oleh', 'value' => "@{coalesce(triggerBody()?['validated_by']?['name'], '-')}"],
                    ['title' => 'Lead Modul',      'value' => "@{if(empty(triggerBody()?['lead_emails']), 'Belum ada lead', join(triggerBody()?['lead_emails'], ', '))}"],
                ],
            ],
        ],
        'actions' => [
            ['type' => 'Action.OpenUrl', 'title' => 'Buka di EcoSystem', 'url' => "@{triggerBody()?['ticket']?['url']}"],
        ],
    ]);
}

function cardTicketValidatedChat(): string
{
    return j([
        'type'    => 'AdaptiveCard',
        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
        'version' => '1.4',
        'body'    => [
            ['type' => 'TextBlock', 'text' => 'Tiket baru di modul Anda', 'weight' => 'Bolder', 'size' => 'Medium'],
            [
                'type' => 'TextBlock',
                'text' => "**@{triggerBody()?['ticket']?['number']}** - @{triggerBody()?['ticket']?['subject']}",
                'wrap' => true,
            ],
            [
                'type'  => 'FactSet',
                'facts' => [
                    ['title' => 'Customer',  'value' => "@{coalesce(triggerBody()?['ticket']?['customer'], '-')}"],
                    ['title' => 'Modul',     'value' => "@{coalesce(triggerBody()?['ticket']?['module'], '-')}"],
                    ['title' => 'Prioritas', 'value' => "@{coalesce(triggerBody()?['ticket']?['priority'], '-')}"],
                ],
            ],
            ['type' => 'TextBlock', 'text' => 'Mohon tentukan PIC atau ambil tiket ini.', 'wrap' => true],
        ],
        'actions' => [
            ['type' => 'Action.OpenUrl', 'title' => 'Assign / Ambil Tiket', 'url' => "@{triggerBody()?['ticket']?['url']}"],
        ],
    ]);
}

function cardOpenReminder(): string
{
    return j([
        'type'    => 'AdaptiveCard',
        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
        'version' => '1.4',
        'body'    => [
            ['type' => 'TextBlock', 'text' => 'Tiket masih Open', 'weight' => 'Bolder', 'size' => 'Medium', 'color' => 'Attention'],
            [
                'type' => 'TextBlock',
                'text' => "**@{triggerBody()?['ticket']?['number']}** - @{triggerBody()?['ticket']?['subject']}",
                'wrap' => true,
            ],
            [
                'type'  => 'FactSet',
                'facts' => [
                    ['title' => 'Customer',       'value' => "@{coalesce(triggerBody()?['ticket']?['customer'], '-')}"],
                    ['title' => 'Modul',          'value' => "@{coalesce(triggerBody()?['ticket']?['module'], '-')}"],
                    ['title' => 'Prioritas',      'value' => "@{coalesce(triggerBody()?['ticket']?['priority'], '-')}"],
                    ['title' => 'Terbuka selama', 'value' => "@{triggerBody()?['reminder']?['open_for_minutes']} menit"],
                    ['title' => 'Pengingat ke-',  'value' => "@{triggerBody()?['reminder']?['count']}"],
                ],
            ],
            [
                'type' => 'TextBlock',
                'text' => 'Tiket ini belum diproses. Mohon assign member atau ambil sendiri - pengingat berhenti otomatis begitu statusnya berubah dari Open.',
                'wrap' => true,
            ],
        ],
        'actions' => [
            ['type' => 'Action.OpenUrl', 'title' => 'Buka Tiket', 'url' => "@{triggerBody()?['ticket']?['url']}"],
        ],
    ]);
}

function greetingBody(): string
{
    return implode("\n", [
        "<p>Halo @{coalesce(triggerBody()?['staging']?['sender_name'], 'Bapak/Ibu')},</p>",
        '',
        '<p>Terima kasih telah menghubungi Helpdesk PT Eclectic Consulting. Email Anda dengan subjek',
        "\"<b>@{triggerBody()?['staging']?['subject']}</b>\" sudah kami terima dan sedang diverifikasi oleh tim kami.</p>",
        '',
        '<p>Anda akan menerima nomor tiket resmi begitu laporan ini selesai divalidasi. Untuk mempercepat',
        'penanganan, mohon balas email ini bila ada informasi tambahan seperti tangkapan layar atau nomor',
        'dokumen terkait.</p>',
        '',
        '<p>Salam,<br>Helpdesk PT Eclectic Consulting</p>',
    ]);
}

// ─── Ketiga flow ─────────────────────────────────────────────────────────────

/**
 * @return list<array{
 *   slug:string, zip:string, displayName:string, schemaName:string,
 *   description:string, flowGuid:string, resourceGuid:string, connGuid:string,
 *   connName:string, connApiId:string, connDisplay:string, definition:array
 * }>
 */
function ecosystemFlows(): array
{
    return [
        [
            'slug'         => 'flow-1-email-greeting',
            'zip'          => 'EcoSystem-1-Email-Greeting.zip',
            'displayName'  => 'EcoSystem - Email Greeting',
            'schemaName'   => 'EcoSystemEmailGreeting',
            'description'  => 'Balas greeting otomatis ke pengirim saat email customer baru masuk ke staging ticket EcoSystem.',
            'flowGuid'     => '3f1a7c20-0001-4a11-9b01-a1b2c3d40001',
            'resourceGuid' => '3f1a7c20-0001-4a11-9b01-a1b2c3d4000a',
            'connGuid'     => '3f1a7c20-0001-4a11-9b01-a1b2c3d4000b',
            'connName'     => 'shared_office365',
            'connApiId'    => API_OUTLOOK,
            'connDisplay'  => 'Office 365 Outlook',
            'definition'   => workflow(
                requestTrigger([
                    'type'       => 'object',
                    'properties' => [
                        'event'   => ['type' => 'string'],
                        'sent_at' => ['type' => 'string'],
                        'source'  => ['type' => 'string'],
                        'staging' => [
                            'type'       => 'object',
                            'properties' => [
                                'id'                  => ['type' => 'integer'],
                                'subject'             => nullableString(),
                                'status'              => ['type' => 'string'],
                                'channel'             => ['type' => 'string'],
                                'customer'            => nullableString(),
                                'customer_id'         => ['type' => ['integer', 'null']],
                                'sender_name'         => nullableString(),
                                'sender_email'        => nullableString(),
                                'cc_emails'           => ['type' => 'array', 'items' => ['type' => 'string']],
                                'graph_message_id'    => nullableString(),
                                'internet_message_id' => nullableString(),
                                'conversation_id'     => nullableString(),
                                'has_attachments'     => ['type' => 'boolean'],
                                'received_at'         => nullableString(),
                            ],
                        ],
                    ],
                ]),
                secretGate([
                    'Balas_greeting_ke_pengirim' => connectorAction(
                        'shared_office365',
                        API_OUTLOOK,
                        'ReplyToV3',
                        [
                            'id'            => "@triggerBody()?['staging']?['graph_message_id']",
                            'body/Body'     => greetingBody(),
                            'body/ReplyAll' => false,
                        ]
                    ),
                ])
            ),
        ],
        [
            'slug'         => 'flow-2-ticket-validated',
            'zip'          => 'EcoSystem-2-Ticket-Validated.zip',
            'displayName'  => 'EcoSystem - Ticket Validated',
            'schemaName'   => 'EcoSystemTicketValidated',
            'description'  => 'Saat staging ticket di-approve: kartu tiket ke channel Teams + chat pribadi ke setiap lead modul.',
            'flowGuid'     => '3f1a7c20-0002-4a11-9b01-a1b2c3d40001',
            'resourceGuid' => '3f1a7c20-0002-4a11-9b01-a1b2c3d4000a',
            'connGuid'     => '3f1a7c20-0002-4a11-9b01-a1b2c3d4000b',
            'connName'     => 'shared_teams',
            'connApiId'    => API_TEAMS,
            'connDisplay'  => 'Microsoft Teams',
            'definition'   => workflow(
                requestTrigger([
                    'type'       => 'object',
                    'properties' => [
                        'event'        => ['type' => 'string'],
                        'sent_at'      => ['type' => 'string'],
                        'source'       => ['type' => 'string'],
                        'ticket'       => ticketSchema(),
                        'module_leads' => leadsSchema(),
                        'lead_emails'  => ['type' => 'array', 'items' => ['type' => 'string']],
                        // Bahan aksi Teams "Create a chat": nama grup dan anggota
                        // sudah dirakit EcoSystem supaya designer tidak perlu
                        // membangun string sendiri.
                        'chat'         => [
                            'type'       => 'object',
                            'properties' => [
                                'topic'       => ['type' => 'string'],
                                'members'     => ['type' => 'array', 'items' => ['type' => 'string']],
                                'members_csv' => ['type' => 'string'],
                                'has_lead'    => ['type' => 'boolean'],
                            ],
                        ],
                        'validated_by' => [
                            'type'       => 'object',
                            'properties' => [
                                'id'    => ['type' => ['integer', 'null']],
                                'name'  => nullableString(),
                                'email' => nullableString(),
                            ],
                        ],
                    ],
                ]),
                secretGate(array_merge(
                    postCardToChannel('Kartu_tiket_ke_channel', cardTicketValidatedChannel()),
                    postCardToEachLead(
                        'Untuk_setiap_lead_modul',
                        'Kartu_tiket_ke_chat_lead',
                        cardTicketValidatedChat(),
                        ['Kartu_tiket_ke_channel' => ['Succeeded']]
                    )
                ))
            ),
        ],
        [
            'slug'         => 'flow-3-open-ticket-reminder',
            'zip'          => 'EcoSystem-3-Open-Ticket-Reminder.zip',
            'displayName'  => 'EcoSystem - Open Ticket Reminder',
            'schemaName'   => 'EcoSystemOpenTicketReminder',
            'description'  => 'Reminder berulang ke chat pribadi lead modul selama tiket masih berstatus Open.',
            'flowGuid'     => '3f1a7c20-0003-4a11-9b01-a1b2c3d40001',
            'resourceGuid' => '3f1a7c20-0003-4a11-9b01-a1b2c3d4000a',
            'connGuid'     => '3f1a7c20-0003-4a11-9b01-a1b2c3d4000b',
            'connName'     => 'shared_teams',
            'connApiId'    => API_TEAMS,
            'connDisplay'  => 'Microsoft Teams',
            'definition'   => workflow(
                requestTrigger([
                    'type'       => 'object',
                    'properties' => [
                        'event'        => ['type' => 'string'],
                        'sent_at'      => ['type' => 'string'],
                        'source'       => ['type' => 'string'],
                        'ticket'       => ticketSchema(),
                        'module_leads' => leadsSchema(),
                        'lead_emails'  => ['type' => 'array', 'items' => ['type' => 'string']],
                        'reminder'     => [
                            'type'       => 'object',
                            'properties' => [
                                'count'            => ['type' => 'integer'],
                                'interval_minutes' => ['type' => 'integer'],
                                'open_for_minutes' => ['type' => 'integer'],
                                'is_first'         => ['type' => 'boolean'],
                            ],
                        ],
                    ],
                ]),
                secretGate(postCardToEachLead(
                    'Untuk_setiap_lead_modul',
                    'Kartu_reminder_ke_chat_lead',
                    cardOpenReminder()
                ))
            ),
        ],
    ];
}
