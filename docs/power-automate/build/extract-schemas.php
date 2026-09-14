<?php

/**
 * Tarik "Request Body JSON Schema" tiap flow keluar dari solution, supaya bisa
 * ditempel manual ke kartu trigger di designer (schema tidak ikut terbawa saat
 * import solution).
 */

require 'docs/power-automate/build/definitions.php';

$out = 'docs/power-automate/schemas';
@mkdir($out, 0777, true);

$names = [
    'flow-1-email-greeting'       => 'EcoSystem - Email Greeting',
    'flow-2-ticket-validated'     => 'EcoSystem - Ticket Validated',
    'flow-3-open-ticket-reminder' => 'EcoSystem - Open Ticket Reminder',
];

foreach (ecosystemFlows() as $flow) {
    $schema = $flow['definition']['triggers']['manual']['inputs']['schema'];
    $file   = "$out/{$flow['slug']}-request-schema.json";
    file_put_contents($file, j($schema) . "\n");
    echo "OK  $file  ({$names[$flow['slug']]})\n";
}
