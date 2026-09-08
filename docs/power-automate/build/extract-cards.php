<?php

/**
 * Tarik Adaptive Card tiap aksi Teams keluar dari definisi flow, supaya bisa
 * ditempel manual ke kotak "Adaptive Card" di designer.
 *
 * Parameter aksi konektor TIDAK ikut terbawa saat import solution (lihat
 * README bagian "Catatan lapangan"), jadi kartu harus diisi tangan. Menyalin
 * dari berkas ini — bukan dari chat/dokumen — menjaga apostrof tetap lurus.
 */

require 'docs/power-automate/build/definitions.php';

$out = 'docs/power-automate/cards';
@mkdir($out, 0777, true);

/** Cari semua aksi Teams (punya parameter body/messageBody) di seluruh kedalaman. */
function collectCards(array $actions, array &$found): void
{
    foreach ($actions as $name => $action) {
        $card = $action['inputs']['parameters']['body/messageBody'] ?? null;
        if (is_string($card)) {
            $found[$name] = $card;
        }
        foreach (['actions', 'else'] as $key) {
            if (isset($action[$key]['actions'])) {
                collectCards($action[$key]['actions'], $found);
            } elseif (isset($action[$key]) && is_array($action[$key]) && $key === 'actions') {
                collectCards($action[$key], $found);
            }
        }
    }
}

foreach (ecosystemFlows() as $flow) {
    $found = [];
    collectCards($flow['definition']['actions'], $found);

    foreach ($found as $actionName => $card) {
        $slug = strtolower(str_replace('_', '-', $actionName));
        $file = "$out/{$flow['slug']}-{$slug}.json";
        file_put_contents($file, rtrim($card) . "\n");
        echo "OK  $file\n";
    }
}
