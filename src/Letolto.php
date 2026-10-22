<?php

declare(strict_types=1);

namespace IrszHnk;

use RuntimeException;

/**
 * A forrásfájl helyi útja: a helyi fájl maga, az URL-t ideiglenes fájlba tölti (a hívó törli: torol()).
 */
final class Letolto
{
    /** @var list<string> */
    private array $ideiglenes = [];

    public function fajl(string $forras): string
    {
        if (! preg_match('#^https?://#i', $forras)) {
            return $forras;
        }

        $tartalom = @file_get_contents($forras, false, stream_context_create([
            'http' => ['timeout' => 120, 'follow_location' => 1, 'header' => "User-Agent: IrszHnkPHP\r\n"],
        ]));

        if ($tartalom === false || $tartalom === '') {
            throw new RuntimeException("A letöltés nem sikerült: {$forras}");
        }

        $ut = tempnam(sys_get_temp_dir(), 'irszhnk_');

        if ($ut === false || file_put_contents($ut, $tartalom) === false) {
            throw new RuntimeException('Az ideiglenes fájl nem írható.');
        }

        return $this->ideiglenes[] = $ut;
    }

    public function torol(): void
    {
        foreach ($this->ideiglenes as $ut) {
            @unlink($ut);
        }

        $this->ideiglenes = [];
    }
}
