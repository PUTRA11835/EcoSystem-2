<?php

/**
 * Membangun tiga legacy import package (satu zip per flow).
 *
 * CATATAN: tenant PT Eclectic mengaktifkan "Create in Dataverse solutions",
 * sehingga importer menolak paket ini dengan pesan "Importing packages with
 * flows is disabled". Pakai build-solution.php sebagai gantinya. Berkas ini
 * dipertahankan karena formatnya jauh lebih ringkas untuk dibaca, dan tetap
 * berguna bila suatu saat kebijakan environment berubah.
 *
 * Jalankan dari root repo:
 *   php docs/power-automate/build/build-legacy-packages.php
 */

require __DIR__ . '/definitions.php';

$srcRoot = 'docs/power-automate/src';
$zipRoot = 'docs/power-automate/legacy';

foreach (ecosystemFlows() as $flow) {
    $base = "$srcRoot/{$flow['slug']}";
    $dir  = "$base/Microsoft.Flow/flows/{$flow['flowGuid']}";

    put("$dir/definition.json", j([
        'name'       => $flow['flowGuid'],
        'id'         => '/providers/Microsoft.Flow/flows/' . $flow['flowGuid'],
        'type'       => 'Microsoft.Flow/flows',
        'properties' => [
            'apiId'                => '/providers/Microsoft.PowerApps/apis/shared_logicflows',
            'displayName'          => $flow['displayName'],
            'definition'           => $flow['definition'],
            'connectionReferences' => [
                $flow['connName'] => [
                    'connectionName' => $flow['connName'],
                    'source'         => 'Embedded',
                    'id'             => $flow['connApiId'],
                    'tier'           => 'NotSpecified',
                ],
            ],
            'flowFailureAlertSubscribed' => false,
        ],
        'schemaVersion' => '1.0.0.0',
    ]));

    put("$dir/apisMap.json", j([
        $flow['connName'] => [
            'apiName'     => $flow['connName'],
            'apiId'       => $flow['connApiId'],
            'displayName' => $flow['connDisplay'],
            'tier'        => 'Standard',
        ],
    ]));

    put("$base/manifest.json", j([
        'schema'  => '1.0',
        'details' => [
            'displayName'        => $flow['displayName'],
            'description'        => $flow['description'],
            'createdTime'        => '2026-09-02T00:00:00.0000000Z',
            'packageTelemetryId' => $flow['resourceGuid'],
            'creator'            => 'EcoSystem',
            'sourceEnvironment'  => '',
        ],
        'resources' => [
            $flow['resourceGuid'] => [
                'id'                    => null,
                'name'                  => $flow['flowGuid'],
                'type'                  => 'Microsoft.Flow/flows',
                'suggestedCreationType' => 'New',
                'creationType'          => 'New, Existing, Update',
                'details'               => ['displayName' => $flow['displayName']],
                'configurableBy'        => 'User',
                'hierarchy'             => 'Root',
                'dependsOn'             => [$flow['connGuid']],
            ],
            $flow['connGuid'] => [
                'id'                    => $flow['connApiId'],
                'name'                  => $flow['connName'],
                'type'                  => 'Microsoft.PowerApps/apis',
                'suggestedCreationType' => 'Existing',
                'creationType'          => 'Existing',
                'details'               => [
                    'displayName'     => $flow['connDisplay'],
                    'type'            => 'Microsoft.PowerApps/apis',
                    'environmentName' => '',
                ],
                'configurableBy' => 'System',
                'hierarchy'      => 'Child',
                'dependsOn'      => [],
            ],
        ],
    ]));

    $count = zipDirectory($base, "$zipRoot/{$flow['zip']}");
    echo "OK  $zipRoot/{$flow['zip']} ($count berkas)\n";
}
