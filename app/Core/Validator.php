<?php
declare(strict_types=1);

namespace App\Core;

/** Server-side validation and normalization helpers. */
final class Validator
{
    /**
     * Normalize a Philippine mobile number to the 11-digit 09XXXXXXXXX form.
     * Accepts 09XXXXXXXXX, 9XXXXXXXXX, 639XXXXXXXXX and +639XXXXXXXXX with optional spaces/dashes.
     * Returns null when the value is not a valid PH mobile number.
     */
    public static function phMobile(string $value): ?string
    {
        $digits = preg_replace('/[\s\-().]/', '', trim($value)) ?? '';
        if (preg_match('/^\+?63(9\d{9})$/', $digits, $m)) {
            return '0' . $m[1];
        }
        if (preg_match('/^0?(9\d{9})$/', $digits, $m)) {
            return '0' . $m[1];
        }
        return null;
    }

    public static function email(string $value): ?string
    {
        $v = mb_strtolower(trim($value));
        return ($v !== '' && filter_var($v, FILTER_VALIDATE_EMAIL) !== false) ? $v : null;
    }

    /** DOJ ID: trimmed, internal whitespace collapsed, uppercase. Letters, digits, dash, slash, dot, underscore. */
    public static function dojId(string $value): ?string
    {
        $v = strtoupper(preg_replace('/\s+/', '', trim($value)) ?? '');
        return preg_match('/^[A-Z0-9][A-Z0-9._\/-]{0,49}$/', $v) ? $v : null;
    }

    public static function username(string $value): ?string
    {
        $v = trim($value);
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{3,49}$/', $v) ? $v : null;
    }

    public static function date(string $value): ?string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
        if (!$d || $d->format('Y-m-d') !== trim($value)) {
            return null;
        }
        $y = (int) $d->format('Y');
        return ($y >= 1900 && $y <= 2100) ? $d->format('Y-m-d') : null;
    }

    public static function intVal(mixed $value, int $min, int $max): ?int
    {
        if (is_int($value)) {
            $i = $value;
        } elseif (is_string($value) && preg_match('/^-?\d+$/', trim($value))) {
            $i = (int) trim($value);
        } else {
            return null;
        }
        return ($i >= $min && $i <= $max) ? $i : null;
    }

    /** Decimal with a fixed scale; returns a normalized string (safe for DECIMAL columns) or null. */
    public static function decimal(mixed $value, float $min, float $max, int $scale = 2): ?string
    {
        $s = is_scalar($value) ? str_replace(',', '', trim((string) $value)) : '';
        if (!preg_match('/^\d+(\.\d+)?$/', $s)) {
            return null;
        }
        $f = (float) $s;
        if ($f < $min || $f > $max) {
            return null;
        }
        return number_format($f, $scale, '.', '');
    }

    /** Remove control characters (except newlines/tabs) from free text. */
    public static function cleanText(string $value): string
    {
        return trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '');
    }
}
