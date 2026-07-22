<?php

namespace App\Support;

use Illuminate\Support\Str;

class RecoveryCode
{
    /**
     * Generate a plain-text recovery code in the form XXXX-XXXX-XXXX-XXXX
     * (4 groups of 4 uppercase alphanumeric chars). Ambiguous chars I/O/0/1
     * are excluded so a user reading it off paper can't mistake them.
     */
    public static function generate(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no I, O, 0, 1
        $groups = [];
        for ($g = 0; $g < 4; $g++) {
            $s = '';
            for ($i = 0; $i < 4; $i++) {
                $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $groups[] = $s;
        }
        return implode('-', $groups);
    }

    /**
     * Normalize a user-entered recovery code: uppercase, strip anything
     * that isn't in the allowed alphabet, then re-insert the group hyphens.
     * Lets users type "xkq9 7l2m 8t4p 3b6h" or "xkq97l2m8t4p3b6h" and match.
     */
    public static function normalize(?string $input): ?string
    {
        if ($input === null) return null;
        $up = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input));
        if (strlen($up) !== 16) return null;
        return substr($up, 0, 4) . '-' . substr($up, 4, 4) . '-' . substr($up, 8, 4) . '-' . substr($up, 12, 4);
    }
}
