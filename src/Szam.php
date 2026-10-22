<?php

declare(strict_types=1);

namespace IrszHnk;

/**
 * Számok szövegként, ahogy az R írja ki: legfeljebb 15 értékes jegy, záró nullák nélkül (a táblázatkezelő
 * 13222.999999999998 értékéből 13223 lesz).
 */
final class Szam
{
    public static function szoveg(float $szam, string $tizedesjel = '.'): string
    {
        if ($szam == 0.0) {
            return '0';
        }

        $szoveg = sprintf('%.15G', $szam);

        if (str_contains($szoveg, 'E')) {
            [$mantissza, $kitevo] = explode('E', $szoveg);
            $szoveg = rtrim(rtrim($mantissza, '0'), '.').'e'.($kitevo[0] === '-' ? '-' : '+').str_pad(ltrim($kitevo, '+-'), 2, '0', STR_PAD_LEFT);
        }

        return $tizedesjel === '.' ? $szoveg : str_replace('.', $tizedesjel, $szoveg);
    }
}
