<?php

declare(strict_types=1);

namespace IrszHnk;

use Collator;
use RuntimeException;

/**
 * Irányítószámok és települések összerendelése: Ferenci Tamás IrszHnk.R szkriptjének PHP-átirata
 * (https://github.com/ferenci-tamas/IrszHnk). Lépésről lépésre ugyanazt csinálja, mint az eredeti, hogy a kimenet
 * soronként egyezzen:
 *
 * 1. Posta (iranyitoszamok()): a „Települések” lap a *-os települések nélkül, a „Bp.u.” lap kerületenként
 *    („Budapest 05. ker.”), a Miskolc / Debrecen / Szeged / Pécs / Győr utcalapok a város nevével; ismétlődés nélkül.
 * 2. KSH Helységnévtár (helysegnevtar()): az első lap 2 sor kihagyásával; a 14-26. oszlop neve
 *    „NemzetisegiOnkormanyzat_” előtagot kap.
 * 3. Összefésülés (osszefesul()): a település nevén (R merge: belső illesztés, a kulcs szerint rendezve, egyezésnél
 *    a Posta sorrendjében), ismétlődés nélkül.
 */
final class IrszHnk
{
    public const POSTA_URL = 'https://www.posta.hu/static/internet/download/Iranyitoszam-Internet_uj.xlsx';

    public const KSH_URL = 'https://www.ksh.hu/docs/helysegnevtar/hnt_letoltes_2025.xlsx';

    /** A Posta utcajegyzéklapjai: ezeknél a városoknál a „Települések” lap csak *-gal jelöli a várost */
    public const UTCALAPOS_VAROSOK = ['Miskolc', 'Debrecen', 'Szeged', 'Pécs', 'Győr'];

    public const KULCS = 'Helység.megnevezése';

    /**
     * A két forrásfájlból az összerendelt táblázat.
     */
    public static function fajlokbol(string $postaFajl, string $kshFajl): Tablazat
    {
        return self::osszefesul(self::iranyitoszamok($postaFajl), self::helysegnevtar($kshFajl));
    }

    /**
     * A Posta irányítószám-táblázata: IRSZ, Település, Településrész.
     */
    public static function iranyitoszamok(string $postaFajl): Tablazat
    {
        $olvaso = new XlsxOlvaso($postaFajl);
        $oszlopok = ['IRSZ', 'Település', 'Településrész'];

        $telepulesek = $olvaso->lap('Települések', univerzalisNevek: true);
        $sorok = array_values(array_filter(
            $telepulesek->oszlopok($oszlopok)->sorok,
            // R: !grepl("*", Település, fixed = TRUE) – az NA település marad
            fn (array $sor): bool => ! is_string($sor['Település']) || ! str_contains($sor['Település'], '*'),
        ));

        foreach ($olvaso->lap('Bp.u.', talalgatas: 10000)->sorok as $sor) {
            $sorok[] = ['IRSZ' => $sor['IRSZ'], 'Település' => self::budapestiKerulet($sor['KER']), 'Településrész' => null];
        }

        foreach (self::UTCALAPOS_VAROSOK as $varos) {
            $lap = $olvaso->lap($varos.' u.', univerzalisNevek: true);
            $irszOszlop = in_array('IRSZ', $lap->oszlopok, true) ? 'IRSZ' : 'IRSZ.';

            foreach ($lap->sorok as $sor) {
                $sorok[] = ['IRSZ' => $sor[$irszOszlop], 'Település' => $varos, 'Településrész' => null];
            }
        }

        // Az R rbind a számot és a szöveget egy szöveg oszlopba teszi
        $sorok = array_map(fn (array $sor): array => array_map(
            fn (mixed $ertek): ?string => $ertek === null ? null : (is_float($ertek) ? Szam::szoveg($ertek) : (string) $ertek),
            $sor,
        ), $sorok);

        return (new Tablazat($oszlopok, array_fill_keys($oszlopok, Tablazat::SZOVEG), $sorok))->egyediSorok();
    }

    /**
     * A KSH Helységnévtár települési adatai (az első lap).
     */
    public static function helysegnevtar(string $kshFajl): Tablazat
    {
        $hnk = (new XlsxOlvaso($kshFajl))->lap(1, kihagy: 2, univerzalisNevek: true);

        // R: colnames(Hnk)[14:26] <- paste0("NemzetisegiOnkormanyzat_", ...)
        $nevek = $hnk->oszlopok;
        foreach (range(13, 25) as $i) {
            if (isset($nevek[$i])) {
                $nevek[$i] = 'NemzetisegiOnkormanyzat_'.$nevek[$i];
            }
        }

        $atnevez = array_combine($hnk->oszlopok, $nevek);

        return new Tablazat(
            $nevek,
            array_combine(array_map(fn (string $nev): string => $atnevez[$nev], array_keys($hnk->tipusok)), array_values($hnk->tipusok)),
            array_map(fn (array $sor): array => array_combine($nevek, array_values($sor)), $hnk->sorok),
        );
    }

    /**
     * Belső illesztés a település nevén (az irányítószám-táblázat 2. oszlopa = a Helységnévtár 1. oszlopa), a kulcs
     * szerint rendezve (magyar ábécé), egyezésnél az irányítószámok sorrendjében, ismétlődés nélkül.
     */
    public static function osszefesul(Tablazat $irsz, Tablazat $hnk): Tablazat
    {
        $kulcs = $hnk->oszlopok[0] ?? throw new RuntimeException('A Helységnévtár táblázata üres.');
        $irszKulcs = $irsz->oszlopok[1];
        $irszTobbi = array_values(array_diff($irsz->oszlopok, [$irszKulcs]));
        $hnkTobbi = array_slice($hnk->oszlopok, 1);

        $xKulcsok = array_map(fn (array $sor): ?string => $sor[$irszKulcs] === null ? null : (string) $sor[$irszKulcs], $irsz->sorok);
        $yKulcsok = array_map(fn (array $sor): ?string => $sor[$kulcs] === null ? null : (string) $sor[$kulcs], $hnk->sorok);

        $sorok = [];
        foreach (self::illesztes($xKulcsok, $yKulcsok) as [$x, $y]) {
            $irszSor = $irsz->sorok[$x];
            $hnkSor = $hnk->sorok[$y];
            $sor = [$kulcs => $irszSor[$irszKulcs]];

            foreach ($irszTobbi as $oszlop) {
                $sor[$oszlop] = $irszSor[$oszlop];
            }

            foreach ($hnkTobbi as $oszlop) {
                $sor[$oszlop] = $hnkSor[$oszlop];
            }

            $sorok[] = $sor;
        }

        // R: sort.list(bx[m$xi]) – stabil rendezés a kulcs szerint, a magyar területi beállítás ábécéjével
        $rendezo = new Collator('hu_HU');
        $sorszamozott = array_map(null, array_keys($sorok), $sorok);
        usort($sorszamozott, fn (array $a, array $b): int => $rendezo->compare((string) $a[1][$kulcs], (string) $b[1][$kulcs]) ?: $a[0] <=> $b[0]);

        $oszlopok = [$kulcs, ...$irszTobbi, ...$hnkTobbi];
        $tipusok = [$kulcs => Tablazat::SZOVEG] + array_intersect_key($irsz->tipusok, array_flip($irszTobbi)) + array_intersect_key($hnk->tipusok, array_flip($hnkTobbi));

        return (new Tablazat($oszlopok, $tipusok, array_column($sorszamozott, 1)))->egyediSorok();
    }

    /**
     * Az R merge() belső illesztése (do_merge, src/main/unique.c), hogy az egyező kulcsú sorok sorrendje is az
     * eredetié legyen: a kulcsok sorszáma az illeszkedő x-kulcsok listájában az első előfordulás helye; az x és az y
     * sorait e sorszám szerint az R isort_with_index() Shell-rendezése rendezi, ami NEM stabil (ezért nem a Posta
     * sorrendje marad egy településen belül), majd kulcsonként minden x-sor minden y-sorral párt alkot.
     *
     * @param  list<?string>  $xKulcsok
     * @param  list<?string>  $yKulcsok
     * @return list<array{0: int, 1: int}> [x sor, y sor] párok (0-tól)
     */
    public static function illesztes(array $xKulcsok, array $yKulcsok): array
    {
        $yHalmaz = array_flip(array_filter($yKulcsok, 'is_string'));
        $kulcsSorszam = [];
        $sorszam = 0;

        // bxy <- bx[match(bx, by, 0) > 0]; a sorszám: match(kulcs, bxy)
        foreach ($xKulcsok as $kulcs) {
            if ($kulcs !== null && isset($yHalmaz[$kulcs])) {
                $sorszam++;
                $kulcsSorszam[$kulcs] ??= $sorszam;
            }
        }

        $xInd = array_map(fn (?string $kulcs): int => $kulcs === null ? 0 : ($kulcsSorszam[$kulcs] ?? 0), $xKulcsok);
        $yInd = array_map(fn (?string $kulcs): int => $kulcs === null ? 0 : ($kulcsSorszam[$kulcs] ?? 0), $yKulcsok);
        $xSorrend = range(0, count($xKulcsok) - 1);
        $ySorrend = $yKulcsok === [] ? [] : range(0, count($yKulcsok) - 1);

        self::rendezIndexszel($xInd, $xSorrend);
        self::rendezIndexszel($yInd, $ySorrend);

        $parok = [];
        $nx = count($xInd);
        $ny = count($yInd);
        $j = 0;

        for ($i = 0; $i < $nx && $xInd[$i] === 0; $i++);

        while ($i < $nx) {
            $ertek = $xInd[$i];

            for ($iVeg = $i; $iVeg < $nx && $xInd[$iVeg] === $ertek; $iVeg++);
            for (; $j < $ny && $yInd[$j] < $ertek; $j++);
            for ($jVeg = $j; $jVeg < $ny && $yInd[$jVeg] === $ertek; $jVeg++);

            for ($i0 = $i; $i0 < $iVeg; $i0++) {
                for ($j0 = $j; $j0 < $jVeg; $j0++) {
                    $parok[] = [$xSorrend[$i0], $ySorrend[$j0]];
                }
            }

            $i = $iVeg;
        }

        return $parok;
    }

    /**
     * Az R isort_with_index(): Shell-rendezés (lépésköz 1, 4, 13, …) növekvő sorrendbe, az indexet is mozgatva.
     *
     * @param  list<int>  $ertekek
     * @param  list<int>  $indexek
     */
    private static function rendezIndexszel(array &$ertekek, array &$indexek): void
    {
        $n = count($ertekek);

        for ($h = 1; $h <= intdiv($n, 9); $h = 3 * $h + 1);

        for (; $h > 0; $h = intdiv($h, 3)) {
            for ($i = $h; $i < $n; $i++) {
                $v = $ertekek[$i];
                $iv = $indexek[$i];
                $j = $i;

                while ($j >= $h && $ertekek[$j - $h] > $v) {
                    $ertekek[$j] = $ertekek[$j - $h];
                    $indexek[$j] = $indexek[$j - $h];
                    $j -= $h;
                }

                $ertekek[$j] = $v;
                $indexek[$j] = $iv;
            }
        }
    }

    /**
     * „XVII.” -> „Budapest 17. ker.”; a „0” és a „Margitsziget” a XIII. kerület (mint az eredetiben).
     */
    public static function budapestiKerulet(string|float|bool|null $ker): string
    {
        $ker = $ker === null ? null : (is_float($ker) ? Szam::szoveg($ker) : (string) $ker);

        if ($ker === '0' || $ker === 'Margitsziget') {
            $ker = 'XIII.';
        }

        $szam = $ker === null ? null : self::romaiSzam(str_replace('.', '', $ker));

        return 'Budapest '.($szam === null ? 'NA' : str_pad((string) $szam, 2, '0', STR_PAD_LEFT)).'. ker.';
    }

    /** Római szám értéke (R as.roman), vagy null, ha nem római szám */
    public static function romaiSzam(string $romai): ?int
    {
        $romai = strtoupper(trim($romai));

        if ($romai === '' || preg_match('/^M{0,3}(CM|CD|D?C{0,3})(XC|XL|L?X{0,3})(IX|IV|V?I{0,3})$/', $romai) !== 1) {
            return null;
        }

        $ertekek = ['M' => 1000, 'D' => 500, 'C' => 100, 'L' => 50, 'X' => 10, 'V' => 5, 'I' => 1];
        $osszeg = 0;
        $hossz = strlen($romai);

        for ($i = 0; $i < $hossz; $i++) {
            $ertek = $ertekek[$romai[$i]];
            $kovetkezo = $i + 1 < $hossz ? $ertekek[$romai[$i + 1]] : 0;
            $osszeg += $ertek < $kovetkezo ? -$ertek : $ertek;
        }

        return $osszeg;
    }
}
