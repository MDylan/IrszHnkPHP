<?php

declare(strict_types=1);

namespace IrszHnk;

use RuntimeException;

/**
 * Az eredmény mentése az eredetivel azonos alakban:
 *
 * - CSV (R data.table::fwrite, dec = ",", sep = ";", bom = TRUE): UTF-8 BOM, pontosvessző, tizedesvessző, az NA
 *   üres mező, idézőjel csak ha kell (pontosvessző, idézőjel vagy sortörés a mezőben); a sorvég alapból CRLF, mert
 *   az eredeti Windowson készült;
 * - JSON (R jsonlite::toJSON): egy sorban, soronként egy objektum az oszlopok sorrendjében, az NA mező kimarad, a
 *   szám legfeljebb 4 tizedesjegyre kerekítve; a végén sortörés.
 */
final class Kimenet
{
    public static function csv(Tablazat $tablazat, string $sorveg = "\r\n"): string
    {
        $mezo = function (string|float|bool|null $ertek): string {
            $szoveg = match (true) {
                $ertek === null => '',
                is_bool($ertek) => $ertek ? 'TRUE' : 'FALSE',
                is_float($ertek) => Szam::szoveg($ertek, ','),
                default => $ertek,
            };

            return preg_match('/[;"\r\n]/', $szoveg) === 1 ? '"'.str_replace('"', '""', $szoveg).'"' : $szoveg;
        };

        $sorok = [implode(';', array_map($mezo, $tablazat->oszlopok))];

        foreach ($tablazat->sorok as $sor) {
            $sorok[] = implode(';', array_map($mezo, array_values($sor)));
        }

        return "\u{FEFF}".implode($sorveg, $sorok).$sorveg;
    }

    public static function json(Tablazat $tablazat): string
    {
        $sorok = array_map(function (array $sor): array {
            $objektum = [];

            foreach ($sor as $oszlop => $ertek) {
                if ($ertek === null) {
                    continue;
                }

                if (is_float($ertek)) {
                    $kerekitett = round($ertek, 4);
                    $ertek = floor($kerekitett) === $kerekitett && abs($kerekitett) < PHP_INT_MAX ? (int) $kerekitett : $kerekitett;
                }

                $objektum[$oszlop] = $ertek;
            }

            return $objektum;
        }, $tablazat->sorok);

        return json_encode($sorok, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * Mindkét fájl (IrszHnk.csv, IrszHnk.json) a megadott mappába.
     *
     * @return list<string> a mentett fájlok útja
     */
    public static function ment(Tablazat $tablazat, string $mappa, string $sorveg = "\r\n"): array
    {
        if (! is_dir($mappa) && ! mkdir($mappa, 0775, true) && ! is_dir($mappa)) {
            throw new RuntimeException("A mappa nem hozható létre: {$mappa}");
        }

        $fajlok = [
            rtrim($mappa, '/').'/IrszHnk.csv' => self::csv($tablazat, $sorveg),
            rtrim($mappa, '/').'/IrszHnk.json' => self::json($tablazat),
        ];

        foreach ($fajlok as $ut => $tartalom) {
            if (file_put_contents($ut, $tartalom) === false) {
                throw new RuntimeException("A fájl nem írható: {$ut}");
            }
        }

        return array_keys($fajlok);
    }
}
