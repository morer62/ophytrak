<?php

namespace App\Services;

final class ProductProfileService
{
    public const MODE = 'ophytrack';

    public static function mode(): string
    {
        return strtolower(trim((string)($_ENV['APP_PRODUCT'] ?? self::MODE))) ?: self::MODE;
    }

    public static function isOphytrack(): bool
    {
        return self::mode() === self::MODE;
    }

    public static function profile(): array
    {
        return [
            'mode' => self::mode(), 'name' => 'OPHYTRACK',
            'tagline' => TranslationService::trans('ophytrack_public.tagline'),
            'description' => TranslationService::trans('ophytrack_public.meta_description'),
            'logo' => 'assets/ophytrack/ophytrack-logo.svg',
            'mark' => 'assets/ophytrack/ophytrack-mark.svg',
            'hero_logo' => 'assets/ophytrack/ophytrack-logo-hero.png',
            'primary' => '#087cff', 'secondary' => '#03c6f4', 'dark' => '#020a16',
        ];
    }

    public static function allowedAddonSlugs(): array
    {
        return ['store_delivery_tracking', 'inventory_storage', 'marketplace_connectors'];
    }

    public static function allowsAddon(string $slug): bool
    {
        if (!self::isOphytrack()) return true;

        return in_array($slug, self::allowedAddonSlugs(), true);
    }

    public static function isDisabledRoute(string $path): bool
    {
        if (!self::isOphytrack()) return false;
        $path = trim($path, '/');
        foreach ([
            'panel/planner-hub/management/orders',
            'panel/event-invitations', 'panel/events', 'panel/shared/orders-calendar',
            'panel/service', 'panel/service-manager', 'panel/service-events', 'panel/service-promotions', 'panel/service-photos',
            'panel/venue-events', 'panel/venues', 'panel/vendors',
            'panel/planner-hub/management/chatia'
        ] as $prefix) {
            if (str_starts_with($path, $prefix)) return true;
        }
        return false;
    }

    public static function isDisabledPublicRoute(string $path): bool
    {
        if (!self::isOphytrack()) return false;
        $path = trim($path, '/');
        foreach (['venues','vendors','service/','event/','events/','modules/service-operations','modules/ticket-sales-rsvp','industries/venues-hospitality','industries/consultants-agencies','industries/service-businesses','industries/service-providers','industries/event-planners','industries/project-based-businesses','business/service-businesses'] as $prefix) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) return true;
        }
        return false;
    }
}
