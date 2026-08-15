<?php

namespace App\Utils;

use DateTimeImmutable;
use Exception;

final class BillingDateUtils
{
    public static function format(?string $date, string $locale = 'en'): string
    {
        if ($date === null || trim($date) === '') {
            return '';
        }

        try {
            $dt = new DateTimeImmutable($date);
        } catch (Exception $exception) {
            return '';
        }

        $locale = in_array($locale, ['en', 'es', 'fr', 'pt'], true) ? $locale : 'en';
        $weekdays = [
            'en' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            'es' => ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'],
            'fr' => ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'],
            'pt' => ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'],
        ];
        $months = [
            'en' => [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'],
            'es' => [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'],
            'fr' => [1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'],
            'pt' => [1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril', 5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto', 9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'],
        ];

        $day = (int) $dt->format('j');
        $weekday = $weekdays[$locale][(int) $dt->format('w')];
        $month = $months[$locale][(int) $dt->format('n')];

        if ($locale === 'es') {
            return sprintf('%s, %d de %s de %s', $weekday, $day, $month, $dt->format('Y'));
        }

        if ($locale === 'fr') {
            return sprintf('%s %d %s %s', $weekday, $day, $month, $dt->format('Y'));
        }

        if ($locale === 'pt') {
            return sprintf('%s, %d de %s de %s', $weekday, $day, $month, $dt->format('Y'));
        }

        return sprintf(
            '%s, %s %d%s, %s',
            $weekday,
            $month,
            $day,
            self::ordinalSuffix($day),
            $dt->format('Y')
        );
    }

    private static function ordinalSuffix(int $day): string
    {
        if ($day >= 11 && $day <= 13) {
            return 'th';
        }

        return match ($day % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}
