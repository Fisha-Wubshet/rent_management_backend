<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Normalize a phone number to E.164 format (e.g. +251912345678).
     *
     * Rules:
     *   - Strip all non-digit chars except a leading '+'.
     *   - If starts with '+', keep as-is (already E.164).
     *   - If starts with '0' (national trunk prefix), drop the 0 and prepend
     *     the given country dial code.
     *   - Otherwise, bare digits — prepend the country dial code.
     *
     * @param  string|null  $input         raw user input
     * @param  string       $countryCode   e.g. '+251' for Ethiopia. Must start with '+'.
     */
    public static function normalize(?string $input, string $countryCode = '+251'): ?string
    {
        if ($input === null) return null;
        $trimmed = trim($input);
        if ($trimmed === '') return null;

        // Preserve leading '+', strip all other non-digit characters.
        $hasPlus = str_starts_with($trimmed, '+');
        $digits  = preg_replace('/\D+/', '', $trimmed);
        if ($digits === '') return null;

        if ($hasPlus) {
            return '+' . $digits;
        }

        // Strip a single leading '0' (national trunk prefix like Ethiopia's 09xxxxxxxx).
        if (str_starts_with($digits, '0')) {
            $digits = ltrim($digits, '0');
            if ($digits === '') return null;
        }

        // Country code must start with '+' and contain digits.
        $cc = trim($countryCode);
        if (!str_starts_with($cc, '+')) $cc = '+' . preg_replace('/\D+/', '', $cc);

        return $cc . $digits;
    }

    /**
     * Validate that a normalized phone number looks well-formed (E.164).
     * Not a strict regional validator — just guards against obviously bad input.
     */
    public static function isValid(?string $normalized): bool
    {
        if ($normalized === null) return false;
        return (bool) preg_match('/^\+[1-9]\d{6,14}$/', $normalized);
    }
}
