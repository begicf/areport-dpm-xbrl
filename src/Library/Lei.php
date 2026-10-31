<?php

namespace AReportDpmXBRL\Library;

/**
 * Legal Entity Identifier checks (ISO 17442): 18 alphanumeric characters followed by
 * two check digits, valid when the code read as a number (A=10 … Z=35) mod 97 is 1.
 */
class Lei
{
    public static function isValid(?string $lei): bool
    {
        $lei = strtoupper(trim((string) $lei));

        if (!preg_match('/^[A-Z0-9]{18}[0-9]{2}$/', $lei)) {
            return false;
        }

        $numeric = '';
        foreach (str_split($lei) as $char) {
            $numeric .= ctype_digit($char) ? $char : (string) (ord($char) - 55);
        }

        // mod 97 on a long digit string, a few digits at a time.
        $remainder = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) (($remainder . $chunk) % 97);
        }

        return $remainder === 1;
    }
}
