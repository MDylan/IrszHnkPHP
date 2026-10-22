# IrszHnkPHP

Magyar irányítószámok és települések összerendelése, a települések adataival együtt, hivatalos forrásokból:

- irányítószámok: Magyar Posta, <https://www.posta.hu/static/internet/download/Iranyitoszam-Internet_uj.xlsx>
- települési adatok: KSH Helységnévtár, <https://www.ksh.hu/docs/helysegnevtar/hnt_letoltes_2025.xlsx>

## Forrás

Ez a csomag **Ferenci Tamás** ([medstat.hu](https://www.medstat.hu/)) [IrszHnk](https://github.com/ferenci-tamas/IrszHnk)
R-szkriptjének PHP-átirata. Az összerendelés módja, a transzformációk és a kimenet alakja az övé; ez a csomag
ugyanazt csinálja PHP-ban, Laravel és R nélkül. Az eredeti repó README-je a két forrás eltéréseiről is ír (a
Helységnévtár irányítószámai nem teljesen egyeznek a Postáéval), érdemes elolvasni.

## Licenc

Még nincs megadva. Az eredeti repónak sincs licencfájlja.

## Mit csinál

Lépésről lépésre ugyanazt, mint az eredeti `IrszHnk.R`:

1. **Posta**: a „Települések” lap a `*`-gal jelölt települések nélkül; a „Bp.u.” lap kerületenként
   („Budapest 05. ker.”; a `0` és a „Margitsziget” a XIII. kerület); a Miskolc, Debrecen, Szeged, Pécs és Győr
   utcajegyzéklapja a város nevével; ismétlődő sorok nélkül.
2. **KSH**: az első lap, 2 sor kihagyásával; a 14–26. oszlop neve `NemzetisegiOnkormanyzat_` előtagot kap.
3. **Összefésülés** a település nevén (belső illesztés), ismétlődés nélkül, a település neve szerint rendezve.
4. **Kimenet**: `IrszHnk.csv` (pontosvessző, tizedesvessző, UTF-8 BOM, CRLF sorvég) és `IrszHnk.json`, az eredetivel
   azonos oszlopokkal (`Helység.megnevezése`, `IRSZ`, `Településrész`, a Helységnévtár oszlopai).

Az eredetivel bájtra azonos kimenethez a csomag az R viselkedését is követi, nem csak a lépéseit:

- az oszlopneveket úgy alakítja, mint a `readxl` `.name_repair = "universal"` beállítása (`Terület  (hektár)` →
  `Terület...hektár.`), a cellák típusát az oszlop első 1000 (a „Bp.u.” lapon 10 000) sora alapján találja ki, a
  szövegek két végéről levágja a szóközt;
- a számokat 15 értékes jegyre írja (a táblázat `13222.999999999998` értékéből `13223` lesz);
- az egy településhez tartozó irányítószámok sorrendje az R `merge()` belső, nem stabil Shell-rendezését követi
  (`IrszHnk::illesztes()`), a települések sorrendje a magyar ábécéé (`intl` Collator, `hu_HU`).

## Telepítés

```bash
composer install
```

PHP 8.3, `intl`, `mbstring`, `zip`, `xml` bővítmény; függőség: `phpoffice/phpspreadsheet` 1.30+ vagy 5.10+ (mindkettővel
tesztelve; az 1.x-et pl. a `maatwebsite/excel` 3.1 köti ki).

## Használat

Parancssorból (alapból letölti a két forrást, és az aktuális mappába ment):

```bash
bin/irszhnk
bin/irszhnk --posta=Iranyitoszam-Internet_uj.xlsx --ksh=hnt_letoltes_2025.xlsx --kimenet=kimenet
bin/irszhnk --lf    # a CSV sorvége LF (alapból CRLF, mint az eredetié)
```

A `--posta` és a `--ksh` helyi fájl vagy URL lehet.

PHP-ból:

```php
use IrszHnk\IrszHnk;
use IrszHnk\Kimenet;

$tablazat = IrszHnk::fajlokbol('Iranyitoszam-Internet_uj.xlsx', 'hnt_letoltes_2025.xlsx');

$tablazat->oszlopok;   // ['Helység.megnevezése', 'IRSZ', 'Településrész', …]
$tablazat->sorok;      // soronként oszlopnév => érték (null, ha nincs adat)

Kimenet::ment($tablazat, 'kimenet');   // IrszHnk.csv + IrszHnk.json
$csv = Kimenet::csv($tablazat);
$json = Kimenet::json($tablazat);
```

A részlépések külön is hívhatók: `IrszHnk::iranyitoszamok()`, `IrszHnk::helysegnevtar()`, `IrszHnk::osszefesul()`.

## Tesztek

```bash
composer test
```

A `tests/fixtures/20260209` mappában az eredeti repó `archiv.zip`-jének 2026-02-09-i bemenetei
(`Iranyitoszam-Internet_uj_20260209.xlsx`, `hnt_letoltes_2025.xlsx`) és az eredeti szkript ezekből készült kimenete
(`IrszHnk.csv`, `IrszHnk.json`, 3569 sor) vannak. A teszt a PHP-kimenetet ezekkel veti össze: soronként és bájtra
pontosan egyeznie kell.
