<?php

declare(strict_types=1);

use IrszHnk\IrszHnk;
use IrszHnk\Tablazat;

/**
 * A 2026-02-09-i bemenetekből (az eredeti repó archiv.zip-je) készült táblázat; a tesztek között egyszer számolódik.
 */
function osszerendelt20260209(): Tablazat
{
    static $tablazat = null;

    return $tablazat ??= IrszHnk::fajlokbol(
        __DIR__.'/fixtures/20260209/Iranyitoszam-Internet_uj_20260209.xlsx',
        __DIR__.'/fixtures/20260209/hnt_letoltes_2025.xlsx',
    );
}
