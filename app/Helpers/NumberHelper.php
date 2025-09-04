<?php

namespace App\Helpers;

class NumberHelper
{
    /**
     * Convert number to Indonesian words (terbilang)
     *
     * @param int|float $number
     * @return string
     */
    public static function toWords($number): string
    {
        $number = abs($number);

        if ($number == 0) return 'nol';

        $words = [
            '', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan',
            'sepuluh', 'sebelas'
        ];

        if ($number < 12) {
            return $words[$number];
        } elseif ($number < 20) {
            return $words[$number - 10] . ' belas';
        } elseif ($number < 100) {
            $tens = intval($number / 10);
            $ones = $number % 10;
            return $words[$tens] . ' puluh' . ($ones ? ' ' . $words[$ones] : '');
        } elseif ($number < 200) {
            $remainder = $number - 100;
            return 'seratus' . ($remainder ? ' ' . self::toWords($remainder) : '');
        } elseif ($number < 1000) {
            $hundreds = intval($number / 100);
            $remainder = $number % 100;
            return $words[$hundreds] . ' ratus' . ($remainder ? ' ' . self::toWords($remainder) : '');
        } elseif ($number < 2000) {
            $remainder = $number - 1000;
            return 'seribu' . ($remainder ? ' ' . self::toWords($remainder) : '');
        } elseif ($number < 1000000) {
            $thousands = intval($number / 1000);
            $remainder = $number % 1000;
            return self::toWords($thousands) . ' ribu' . ($remainder ? ' ' . self::toWords($remainder) : '');
        } elseif ($number < 1000000000) {
            $millions = intval($number / 1000000);
            $remainder = $number % 1000000;
            return self::toWords($millions) . ' juta' . ($remainder ? ' ' . self::toWords($remainder) : '');
        } elseif ($number < 1000000000000) {
            $billions = intval($number / 1000000000);
            $remainder = $number % 1000000000;
            return self::toWords($billions) . ' miliar' . ($remainder ? ' ' . self::toWords($remainder) : '');
        } else {
            return 'angka terlalu besar';
        }
    }

    /**
     * Format currency to Indonesian Rupiah
     *
     * @param int|float $amount
     * @param bool $showSymbol
     * @return string
     */
    public static function toRupiah($amount, bool $showSymbol = true): string
    {
        $formatted = number_format($amount, 0, ',', '.');
        return $showSymbol ? 'Rp ' . $formatted : $formatted;
    }

    /**
     * Convert number to currency words with "Rupiah"
     *
     * @param int|float $amount
     * @return string
     */
    public static function toCurrencyWords($amount): string
    {
        return self::toWords($amount) . ' rupiah';
    }
}
