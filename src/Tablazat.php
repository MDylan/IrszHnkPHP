<?php

declare(strict_types=1);

namespace IrszHnk;

/**
 * Egyszerű táblázat (mint az R data.frame-je): oszlopnevek, oszloptípusok és sorok.
 *
 * A cella értéke null (R: NA), string, float (szám) vagy bool (logikai).
 */
final class Tablazat
{
    public const SZOVEG = 'szoveg';

    public const SZAM = 'szam';

    public const LOGIKAI = 'logikai';

    /**
     * @param  list<string>  $oszlopok
     * @param  array<string, string>  $tipusok  oszlopnév => SZOVEG / SZAM / LOGIKAI
     * @param  list<array<string, string|float|bool|null>>  $sorok
     */
    public function __construct(
        public readonly array $oszlopok,
        public readonly array $tipusok,
        public readonly array $sorok,
    ) {}

    /**
     * A megadott oszlopok, ebben a sorrendben.
     *
     * @param  list<string>  $oszlopok
     */
    public function oszlopok(array $oszlopok): self
    {
        return new self(
            $oszlopok,
            array_intersect_key($this->tipusok, array_flip($oszlopok)),
            array_map(fn (array $sor): array => array_combine($oszlopok, array_map(fn (string $oszlop) => $sor[$oszlop] ?? null, $oszlopok)), $this->sorok),
        );
    }

    /**
     * Az ismétlődő sorok nélkül; az első előfordulás marad (R: x[!duplicated(x), ]).
     */
    public function egyediSorok(): self
    {
        $latott = [];
        $sorok = [];

        foreach ($this->sorok as $sor) {
            $kulcs = serialize(array_values($sor));

            if (! isset($latott[$kulcs])) {
                $latott[$kulcs] = true;
                $sorok[] = $sor;
            }
        }

        return new self($this->oszlopok, $this->tipusok, $sorok);
    }
}
