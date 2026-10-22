<?php

declare(strict_types=1);

use IrszHnk\Kimenet;

/*
 * Az eredeti R-szkript kimenete (tests/fixtures/20260209/IrszHnk.csv és .json, 3569 sor) ugyanezekből a bemenetekből
 * készült: a PHP-átirat kimenetének soronként egyeznie kell vele.
 */

test('a CSV soronként egyezik az eredeti R-szkript kimenetével', function (): void {
    $vart = file_get_contents(__DIR__.'/fixtures/20260209/IrszHnk.csv');
    $kapott = Kimenet::csv(osszerendelt20260209());

    // Előbb az első eltérő sor (a teljes tömb diffje percekig tartana), aztán bájtra pontosan (BOM, CRLF)
    $vartSorok = explode("\r\n", $vart);
    $kapottSorok = explode("\r\n", $kapott);
    $elteres = null;

    foreach ($vartSorok as $i => $sor) {
        if (($kapottSorok[$i] ?? null) !== $sor) {
            $elteres = ['sor' => $i + 1, 'vart' => $sor, 'kapott' => $kapottSorok[$i] ?? null];
            break;
        }
    }

    expect($elteres)->toBeNull()
        ->and(count($kapottSorok))->toBe(count($vartSorok))
        ->and($kapott === $vart)->toBeTrue();
});

test('a JSON egyezik az eredeti R-szkript kimenetével', function (): void {
    $vart = file_get_contents(__DIR__.'/fixtures/20260209/IrszHnk.json');
    $kapott = Kimenet::json(osszerendelt20260209());

    $vartSorok = json_decode($vart, true);
    $kapottSorok = json_decode($kapott, true);
    $elteres = null;

    foreach ($vartSorok as $i => $sor) {
        if (($kapottSorok[$i] ?? null) !== $sor) {
            $elteres = ['sor' => $i + 1, 'vart' => $sor, 'kapott' => $kapottSorok[$i] ?? null];
            break;
        }
    }

    expect($elteres)->toBeNull()
        ->and(count($kapottSorok))->toBe(count($vartSorok))
        ->and($kapott === $vart)->toBeTrue();
});

test('3569 sor, az eredeti oszlopaival', function (): void {
    $tablazat = osszerendelt20260209();

    expect($tablazat->sorok)->toHaveCount(3569)
        ->and($tablazat->oszlopok)->toHaveCount(28)
        ->and(array_slice($tablazat->oszlopok, 0, 4))->toBe(['Helység.megnevezése', 'IRSZ', 'Településrész', 'Helység.KSH.kódja']);
});

test('a mentés mindkét fájlt kiírja', function (): void {
    $mappa = sys_get_temp_dir().'/irszhnk-teszt-'.bin2hex(random_bytes(4));

    $fajlok = Kimenet::ment(osszerendelt20260209(), $mappa);

    expect($fajlok)->toBe([$mappa.'/IrszHnk.csv', $mappa.'/IrszHnk.json'])
        ->and(file_get_contents($mappa.'/IrszHnk.csv') === Kimenet::csv(osszerendelt20260209()))->toBeTrue()
        ->and(file_get_contents($mappa.'/IrszHnk.json') === Kimenet::json(osszerendelt20260209()))->toBeTrue();

    array_map('unlink', $fajlok);
    rmdir($mappa);
});
