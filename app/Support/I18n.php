<?php

declare(strict_types=1);

namespace App\Support;

final class I18n
{
    /**
     * @return array<string, string>
     */
    public static function locales(): array
    {
        return [
            'en' => 'English',
            'hi' => 'Hindi',
            'es' => 'Spanish',
        ];
    }

    public static function normalizeLocale(?string $locale): string
    {
        $loc = strtolower(trim((string) $locale));
        return array_key_exists($loc, self::locales()) ? $loc : 'en';
    }

    public static function normalizeTheme(?string $theme): string
    {
        $t = strtolower(trim((string) $theme));
        return in_array($t, ['dark', 'light'], true) ? $t : 'dark';
    }

    public static function translate(string $key, string $default = ''): string
    {
        $loc = self::normalizeLocale((string) ($_SESSION['locale'] ?? 'en'));
        $dict = self::dictionary()[$loc] ?? [];
        if (isset($dict[$key]) && is_string($dict[$key])) {
            return $dict[$key];
        }
        $en = self::dictionary()['en'] ?? [];
        if (isset($en[$key]) && is_string($en[$key])) {
            return $en[$key];
        }
        return $default !== '' ? $default : $key;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function dictionary(): array
    {
        return [
            'en' => [
                'nav.dashboard' => 'Dashboard',
                'nav.calls' => 'All calls',
                'nav.upload' => 'Upload',
                'nav.reports' => 'Reports',
                'shell.tagline' => 'Call intelligence',
                'shell.sign_out' => 'Sign out',
                'pref.theme' => 'Theme',
                'pref.language' => 'Language',
                'pref.dark' => 'Dark',
                'pref.light' => 'Light',
                'pref.saved' => 'Saved',
                'common.filters' => 'Filters',
                'calls.upload' => 'Upload call',
                'calls.filters_hint' => 'Narrow by date, owner, sentiment, or free text.',
                'reports.title' => 'Intelligence reports',
                'reports.subtitle' => 'Team performance, languages, playbook depth, and sentiment trends — filtered like the dashboard.',
            ],
            'hi' => [
                'nav.dashboard' => 'डैशबोर्ड',
                'nav.calls' => 'सभी कॉल',
                'nav.upload' => 'अपलोड',
                'nav.reports' => 'रिपोर्ट्स',
                'shell.tagline' => 'कॉल इंटेलिजेंस',
                'shell.sign_out' => 'साइन आउट',
                'pref.theme' => 'थीम',
                'pref.language' => 'भाषा',
                'pref.dark' => 'डार्क',
                'pref.light' => 'लाइट',
                'pref.saved' => 'सेव हो गया',
                'common.filters' => 'फ़िल्टर',
                'calls.upload' => 'कॉल अपलोड करें',
                'calls.filters_hint' => 'तारीख, एजेंट, सेंटिमेंट या खोज से परिणाम सीमित करें।',
                'reports.title' => 'इंटेलिजेंस रिपोर्ट्स',
                'reports.subtitle' => 'टीम प्रदर्शन, भाषाएं, प्लेबुक गहराई और सेंटिमेंट ट्रेंड — डैशबोर्ड जैसे फ़िल्टर के साथ।',
            ],
            'es' => [
                'nav.dashboard' => 'Panel',
                'nav.calls' => 'Todas las llamadas',
                'nav.upload' => 'Subir',
                'nav.reports' => 'Informes',
                'shell.tagline' => 'Inteligencia de llamadas',
                'shell.sign_out' => 'Cerrar sesión',
                'pref.theme' => 'Tema',
                'pref.language' => 'Idioma',
                'pref.dark' => 'Oscuro',
                'pref.light' => 'Claro',
                'pref.saved' => 'Guardado',
                'common.filters' => 'Filtros',
                'calls.upload' => 'Subir llamada',
                'calls.filters_hint' => 'Filtra por fecha, agente, sentimiento o texto libre.',
                'reports.title' => 'Informes de inteligencia',
                'reports.subtitle' => 'Rendimiento del equipo, idiomas, profundidad del playbook y tendencias de sentimiento, con los mismos filtros del panel.',
            ],
        ];
    }
}

