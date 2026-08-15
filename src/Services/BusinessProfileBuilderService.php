<?php

namespace App\Services;
use App\Repositories\BusinessProfileSectionsRepository;
use App\Repositories\InstitutionProfileRepository;
use App\Utils\FileUtils;

class BusinessProfileBuilderService
{
    private InstitutionProfileRepository $profileRepo;
    private BusinessProfileSectionsRepository $sectionsRepo;

    private const BUSINESS_NATURE_OPTIONS = [
        'event_operations' => 'Event planning',
        'service_business' => 'Service provider',
        'logistics_delivery' => 'Logistics / delivery',
        'agency_studio' => 'Consulting / agency',
        'venue_hospitality' => 'Venue / hospitality',
        'food_catering_meal_prep' => 'Food / catering / meal prep',
        'commerce_operations' => 'Commerce operations',
        'other' => 'Other',
    ];

    private const OPERATION_TYPE_OPTIONS = [
        'clients_orders_team' => 'Clients, orders and team',
        'inventory_fulfillment' => 'Inventory and fulfillment',
        'store_delivery_tracking' => 'Store, delivery and tracking',
        'contracts_services' => 'Contracts and services',
        'events_registrations' => 'Events and registrations',
        'mixed_operations' => 'Mixed operations',
    ];

    private const LAYOUT_OPTIONS = [
        'social_profile' => 'Social profile',
        'business_product' => 'Store / product profile',
    ];

    public function __construct()
    {
        $this->profileRepo = new InstitutionProfileRepository();
        $this->sectionsRepo = new BusinessProfileSectionsRepository();
    }

    public function getBuilderData(int $ownerId): array
    {
        $profile = $this->profileRepo->getByOwner($ownerId);
        $sections = [];

        if ($profile) {
            $this->sectionsRepo->ensureDefaults((int)$profile->id);
            $sections = $this->sectionsRepo->getByProfile((int)$profile->id);
        }

        return [
            'profile' => $profile,
            'sections' => $sections,
            'defaultSections' => $this->sectionsRepo->getDefaultSections(),
            'missingProfileItems' => self::translatedMissingItems($this->profileRepo->getMissingProfileItems($profile)),
            'profileComplete' => $this->profileRepo->isProfileComplete($profile),
            'publicProfileUrl' => $this->profileRepo->getPublicUrl($profile),
            'businessNatureOptions' => self::translatedOptions('business_profile_builder.nature', self::BUSINESS_NATURE_OPTIONS),
            'operationTypeOptions' => self::translatedOptions('business_profile_builder.operation', self::OPERATION_TYPE_OPTIONS),
            'layouts' => self::translatedOptions('business_profile_builder.layout', self::LAYOUT_OPTIONS),
        ];
    }

        private static function translatedOptions(string $prefix, array $options): array
    {
        $translated = [];
        foreach ($options as $value => $label) {
            $translated[$value] = TranslationService::trans($prefix . '.' . $value);
        }
        return $translated;
    }

    public static function translatedMissingItems(array $items): array
    {
        $map = [
            'Company name' => 'business_profile_builder.missing.company_name',
            'Business nature' => 'business_profile_builder.missing.business_nature',
            'Contact phone or email' => 'business_profile_builder.missing.contact_phone_or_email',
            'City and state' => 'business_profile_builder.missing.city_and_state',
            'Public slug' => 'business_profile_builder.missing.public_slug',
            'Business Profile' => 'business_profile_builder.missing.business_profile',
        ];

        return array_map(static function (string $item) use ($map): string {
            $key = $map[$item] ?? '';
            if ($key === '') {
                return $item;
            }

            $translated = TranslationService::trans($key);
            return $translated === $key ? $item : $translated;
        }, $items);
    }
public function save(int $ownerId, array $post, array $files = []): array
    {
        $existing = $this->profileRepo->getByOwner($ownerId);
        $profileId = $existing ? (int)$existing->id : null;

        $companyName = trim((string)($post['company_name'] ?? ''));
        $currentSlug = trim((string)($existing->slug ?? ''));
        $slugSource = $currentSlug !== '' ? $currentSlug : $companyName;
        $slug = $this->profileRepo->generateUniqueSlug($slugSource, $profileId, 'business-' . $ownerId);

        $businessNature = $this->normalizeOption((string)($post['business_nature'] ?? ''), self::BUSINESS_NATURE_OPTIONS);
        $operationType = $this->normalizeOption((string)($post['business_operation_type'] ?? ''), self::OPERATION_TYPE_OPTIONS);
        $layout = $this->normalizeOption((string)($post['profile_layout'] ?? 'social_profile'), self::LAYOUT_OPTIONS, 'social_profile');

        $sectionMap = $this->normalizePostedSections($post['sections'] ?? []);
        $show = fn(string $key): int => isset($sectionMap[$key]) && !empty($sectionMap[$key]['is_visible']) ? 1 : 0;

        $data = [
            'company_name' => $companyName,
            'business_nature' => $businessNature,
            'business_operation_type' => $operationType,
            'phone' => trim((string)($post['phone'] ?? '')),
            'email' => trim((string)($post['email'] ?? '')),
            'address_line1' => trim((string)($post['address_line1'] ?? '')),
            'city' => trim((string)($post['city'] ?? '')),
            'state' => trim((string)($post['state'] ?? '')),
            'zip' => trim((string)($post['zip'] ?? '')),
            'country' => trim((string)($post['country'] ?? 'USA')),
            'payment_method_accepted' => trim((string)($post['payment_method_accepted'] ?? '')),
            'slug' => $slug,
            'profile_layout' => $layout,
            'show_map_section' => $show('map'),
            'show_events_section' => $show('events'),
            'show_quote_form' => $show('quote_form'),
            'show_services_section' => $show('services'),
            'show_contact_section' => $show('contact'),
            'show_gallery_section' => $show('gallery'),
            'show_description_section' => $show('description'),
            'short_description' => trim((string)($post['short_description'] ?? '')),
            'rich_description' => trim(strip_tags((string)($post['rich_description'] ?? ''))),
        ];

        if (array_key_exists('custom_domain', $post)) {
            $customDomain = InstitutionProfileRepository::normalizeDomain($post['custom_domain'] ?? null);
            if (!$this->profileRepo->isDomainAvailable($customDomain, $profileId)) {
                return ['success' => false, 'message' => 'That custom domain is already assigned to another profile.'];
            }

            if ($customDomain) {
                $previousDomain = $existing ? InstitutionProfileRepository::normalizeDomain($existing->custom_domain ?? null) : null;
                $data['custom_domain'] = $customDomain;
                if ($customDomain !== $previousDomain) {
                    $data['custom_domain_status'] = 'PENDING';
                    $data['custom_domain_requested_at'] = date('Y-m-d H:i:s');
                    $data['custom_domain_notes'] = null;
                }
            } else {
                $data['custom_domain'] = null;
                $data['custom_domain_status'] = 'NONE';
                $data['custom_domain_requested_at'] = null;
                $data['custom_domain_notes'] = null;
            }
        }

        if (!empty($files['logo']['tmp_name'])) {
            $data['logo_path'] = FileUtils::saveFile($files['logo'], 'institution_profile/logo');
        }

        $tempProfile = (object)array_merge((array)($existing ?: new \stdClass()), $data);
        $data['profile_completed_at'] = $this->profileRepo->isProfileComplete($tempProfile) ? date('Y-m-d H:i:s') : null;

        $saved = $this->profileRepo->saveBuilderProfile($ownerId, $data, $profileId);
        if (!$saved) {
            return ['success' => false, 'message' => TranslationService::trans('business_profile_builder.alerts.save_failed')];
        }

        $profile = $this->profileRepo->getByOwner($ownerId);
        if ($profile) {
            $this->sectionsRepo->ensureDefaults((int)$profile->id);
            $this->sectionsRepo->saveSections((int)$profile->id, $sectionMap);
        }

        return ['success' => true, 'message' => TranslationService::trans('business_profile_builder.alerts.saved')];
    }

    private function normalizePostedSections(array $sections): array
    {
        $normalized = [];
        foreach ($this->sectionsRepo->getDefaultSections() as $default) {
            $key = $default['key'];
            $posted = $sections[$key] ?? [];
            $normalized[$key] = [
                'key' => $key,
                'label' => trim((string)($posted['label'] ?? $default['label'])),
                'is_visible' => isset($posted['is_visible']) ? 1 : 0,
                'sort_order' => (int)($posted['sort_order'] ?? $default['sort']),
            ];
        }

        return $normalized;
    }

    private function normalizeOption(string $value, array $options, ?string $fallback = null): string
    {
        $value = trim($value);
        if (array_key_exists($value, $options)) {
            return $value;
        }

        return $fallback ?? '';
    }
}

