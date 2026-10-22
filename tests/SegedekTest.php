<?php

declare(strict_types=1);

use IrszHnk\IrszHnk;
use IrszHnk\NevJavito;
use IrszHnk\Szam;

test('univerzális oszlopnevek, mint a readxl-ben', function (): void {
    expect(NevJavito::univerzalis(['Helység megnevezése', 'Terület  (hektár)', 'Lakó-népesség', 'Polgármesteri hivatal, közös önkormányzati hivatal kódja', 'IRSZ.', '', '1.SZÁM', 'bolgár', 'bolgár']))
        ->toBe(['Helység.megnevezése', 'Terület...hektár.', 'Lakó.népesség', 'Polgármesteri.hivatal..közös.önkormányzati.hivatal.kódja', 'IRSZ.', '...6', '.1.SZÁM', 'bolgár...8', 'bolgár...9']);
});

test('budapesti kerület a római számból', function (string|float|null $ker, string $vart): void {
    expect(IrszHnk::budapestiKerulet($ker))->toBe($vart);
})->with([
    ['I.', 'Budapest 01. ker.'],
    ['XVII.', 'Budapest 17. ker.'],
    ['XXIII.', 'Budapest 23. ker.'],
    ['0', 'Budapest 13. ker.'],
    [0.0, 'Budapest 13. ker.'],
    ['Margitsziget', 'Budapest 13. ker.'],
    [null, 'Budapest NA. ker.'],
]);

test('szám szövegként, mint az R-ben', function (float $szam, string $vart): void {
    expect(Szam::szoveg($szam, ','))->toBe($vart);
})->with([
    [8805.0, '8805'],
    [13222.999999999998, '13223'],
    [3.5, '3,5'],
    [0.0, '0'],
    [-12.25, '-12,25'],
]);
