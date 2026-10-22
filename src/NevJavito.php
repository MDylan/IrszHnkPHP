<?php

declare(strict_types=1);

namespace IrszHnk;

/**
 * Oszlopnevek úgy, ahogy a readxl adja (vctrs::vec_as_names).
 *
 * - egyedi ("unique", a readxl alapértelmezése): az üres név "...N", az ismétlődő "név...N" (N az oszlop sorszáma);
 * - univerzális ("universal"): ezen felül szintaktikus R-név: minden betűn, számjegyen, ponton és aláhúzáson kívüli
 *   karakter pont lesz ("Terület  (hektár)" -> "Terület...hektár."), a számjeggyel vagy aláhúzással kezdődő név
 *   elé pont kerül.
 */
final class NevJavito
{
    /** Az R foglalt szavai: univerzális névként ponttal kezdődnek */
    private const FOGLALT = ['if', 'else', 'repeat', 'while', 'function', 'for', 'next', 'break', 'TRUE', 'FALSE', 'NULL',
        'Inf', 'NaN', 'NA', 'NA_integer_', 'NA_real_', 'NA_character_', 'NA_complex_', 'in'];

    /**
     * @param  list<string>  $nevek
     * @return list<string>
     */
    public static function egyedi(array $nevek): array
    {
        $nevek = array_map(fn (string $nev): string => (string) preg_replace('/\.\.\.\d+$/', '', $nev), $nevek);
        $darab = array_count_values($nevek);

        return array_map(
            fn (string $nev, int $i): string => $nev === '' || $darab[$nev] > 1 ? $nev.'...'.($i + 1) : $nev,
            $nevek,
            array_keys($nevek),
        );
    }

    /**
     * @param  list<string>  $nevek
     * @return list<string>
     */
    public static function univerzalis(array $nevek): array
    {
        $nevek = array_map(function (string $nev): string {
            $nev = (string) preg_replace('/\.\.\.\d+$/', '', $nev);

            if ($nev === '') {
                return '';
            }

            $nev = (string) preg_replace('/[^\p{L}\p{N}._]/u', '.', $nev);

            if (preg_match('/^(\d|_|\.\d)/u', $nev) === 1 || in_array($nev, self::FOGLALT, true)) {
                $nev = '.'.$nev;
            }

            return $nev;
        }, $nevek);

        return self::egyedi($nevek);
    }
}
