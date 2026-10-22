<?php

declare(strict_types=1);

namespace IrszHnk;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Egy xlsx munkalap beolvasása úgy, ahogy az R readxl::read_excel() olvassa (az eredeti szkript ezzel dolgozik):
 *
 * - a `skip` sor után az üres sorok kimaradnak, az első nem üres sor a fejléc;
 * - a szövegek két végéről a szóköz lekerül (trim_ws), az üres cella NA (null);
 * - az oszlop típusa az első `guess_max` sor alapján: ha van benne szöveg, szöveg; ha csak szám, szám; ha üres,
 *   logikai. A szöveg oszlop számait szöveggé alakítja (15 értékes jegy), a szám oszlop szövegei NA-k lesznek;
 * - a `.name_repair = "universal"` oszlopneveket a NevJavito adja.
 */
final class XlsxOlvaso
{
    /** Ennyi oszlopig olvas (a Posta egyik lapján a XEK oszlopig tartó üres cellák vannak) */
    private const UTOLSO_OSZLOP = 'Z';

    private readonly Spreadsheet $munkafuzet;

    public function __construct(string $fajl)
    {
        if (! is_file($fajl)) {
            throw new RuntimeException("A fájl nem található: {$fajl}");
        }

        $olvaso = new Xlsx;
        $olvaso->setReadDataOnly(true);
        $olvaso->setReadFilter(new class(Coordinate::columnIndexFromString(self::UTOLSO_OSZLOP)) implements IReadFilter
        {
            public function __construct(private readonly int $utolsoOszlop) {}

            /**
             * Típus nélküli paraméterek: a PhpSpreadsheet 1.x interfészében sincs típus (így mindkét főverzióval megy).
             *
             * @param  string  $columnAddress
             * @param  int  $row
             * @param  string  $worksheetName
             */
            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return Coordinate::columnIndexFromString((string) $columnAddress) <= $this->utolsoOszlop;
            }
        });

        $this->munkafuzet = $olvaso->load($fajl);
    }

    /**
     * @param  string|int  $lap  a munkalap neve, vagy a sorszáma (1-től, mint az R-ben)
     */
    public function lap(string|int $lap, int $kihagy = 0, int $talalgatas = 1000, bool $univerzalisNevek = false): Tablazat
    {
        $munkalap = is_int($lap) ? $this->munkafuzet->getSheet($lap - 1) : $this->munkafuzet->getSheetByName($lap);

        if (! $munkalap instanceof Worksheet) {
            throw new RuntimeException("Nincs ilyen munkalap: {$lap}");
        }

        $nyers = $this->nyersSorok($munkalap, $kihagy);

        if ($nyers === []) {
            return new Tablazat([], [], []);
        }

        $fejlec = array_shift($nyers);
        $oszlopSzam = count($fejlec);
        $nevek = array_map(fn (mixed $ertek): string => $ertek === null ? '' : $this->szovegge($ertek), $fejlec);
        $nevek = $univerzalisNevek ? NevJavito::univerzalis($nevek) : NevJavito::egyedi($nevek);

        $tipusok = [];
        for ($i = 0; $i < $oszlopSzam; $i++) {
            $tipusok[$nevek[$i]] = $this->tipus(array_column(array_slice($nyers, 0, $talalgatas), $i));
        }

        $sorok = array_map(function (array $nyersSor) use ($nevek, $tipusok): array {
            $sor = [];

            foreach ($nevek as $i => $nev) {
                $sor[$nev] = $this->ertek($nyersSor[$i] ?? null, $tipusok[$nev]);
            }

            return $sor;
        }, $nyers);

        return new Tablazat($nevek, $tipusok, $sorok);
    }

    /**
     * A munkalap sorai a `kihagy` sor után, a vezető üres sorok nélkül, a záró üres sorok és oszlopok nélkül.
     *
     * @return list<list<string|float|bool|null>>
     */
    private function nyersSorok(Worksheet $munkalap, int $kihagy): array
    {
        $utolsoSor = $munkalap->getHighestDataRow();
        $utolsoOszlop = min(
            Coordinate::columnIndexFromString($munkalap->getHighestDataColumn()),
            Coordinate::columnIndexFromString(self::UTOLSO_OSZLOP),
        );

        if ($utolsoSor <= $kihagy) {
            return [];
        }

        $tartomany = 'A'.($kihagy + 1).':'.Coordinate::stringFromColumnIndex($utolsoOszlop).$utolsoSor;
        $sorok = array_map(
            fn (array $sor): array => array_map(fn (mixed $ertek): mixed => $this->tisztit($ertek), array_values($sor)),
            array_values($munkalap->rangeToArray($tartomany, null, false, false, false)),
        );

        $ures = fn (array $sor): bool => array_filter($sor, fn (mixed $ertek): bool => $ertek !== null) === [];

        while ($sorok !== [] && $ures($sorok[0])) {
            array_shift($sorok);
        }

        while ($sorok !== [] && $ures($sorok[count($sorok) - 1])) {
            array_pop($sorok);
        }

        // A jobb szélső, végig üres oszlopok (fejléc sincs) nem részei a táblázatnak
        $oszlopSzam = 0;
        foreach ($sorok as $sor) {
            foreach ($sor as $i => $ertek) {
                if ($ertek !== null) {
                    $oszlopSzam = max($oszlopSzam, $i + 1);
                }
            }
        }

        return array_map(fn (array $sor): array => array_slice($sor, 0, $oszlopSzam), $sorok);
    }

    /** Szöveg: a két végén szóköz nélkül, az üres szöveg NA */
    private function tisztit(mixed $ertek): string|float|bool|null
    {
        return match (true) {
            $ertek === null => null,
            is_string($ertek) => trim($ertek) === '' ? null : trim($ertek),
            is_int($ertek), is_float($ertek) => (float) $ertek,
            is_bool($ertek) => $ertek,
            default => trim((string) $ertek) === '' ? null : trim((string) $ertek),
        };
    }

    /**
     * @param  list<string|float|bool|null>  $ertekek
     */
    private function tipus(array $ertekek): string
    {
        $tipus = null;

        foreach ($ertekek as $ertek) {
            if (is_string($ertek)) {
                return Tablazat::SZOVEG;
            }

            if (is_float($ertek)) {
                $tipus = Tablazat::SZAM;
            } elseif (is_bool($ertek)) {
                $tipus ??= Tablazat::LOGIKAI;
            }
        }

        return $tipus ?? Tablazat::LOGIKAI;
    }

    private function ertek(string|float|bool|null $ertek, string $tipus): string|float|bool|null
    {
        if ($ertek === null) {
            return null;
        }

        return match ($tipus) {
            Tablazat::SZOVEG => $this->szovegge($ertek),
            Tablazat::SZAM => is_float($ertek) ? $ertek : (is_bool($ertek) ? (float) $ertek : null),
            default => is_bool($ertek) ? $ertek : (is_float($ertek) ? $ertek != 0.0 : null),
        };
    }

    private function szovegge(string|float|bool $ertek): string
    {
        return match (true) {
            is_string($ertek) => $ertek,
            is_bool($ertek) => $ertek ? 'TRUE' : 'FALSE',
            default => Szam::szoveg($ertek),
        };
    }
}
