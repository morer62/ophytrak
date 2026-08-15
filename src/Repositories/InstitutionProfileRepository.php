<?php

namespace App\Repositories;

class InstitutionProfileRepository extends BaseRepository
{
    private const RESERVED_SLUGS = [
        'admin',
        'panel',
        'login',
        'api',
        'public',
        'assets',
        'uploads',
        'ophyra',
        'app',
        'track',
        'portal',
        'blog',
        'events',
        'contact',
        'quote',
        'estimate',
    ];

    public function __construct()
    {
        $this->table = "institution_profile";
        $this->db = new Connection();
    }

public function getByOwner(int $id): ?object
{
    $this->db->query("SELECT *, 
        CASE 
            WHEN logo_path LIKE 'http%' THEN logo_path
            WHEN logo_path LIKE '%/v%' THEN logo_path 
            ELSE CONCAT(:base_url, logo_path) 
        END as logo_path
        FROM {$this->table} WHERE id_owner = :id LIMIT 1");
    
    $this->db->bind(":id", $id);
    $this->db->bind(":base_url", $_ENV['APP_URL']);
    
    $result = $this->db->fetchOne();
    return $result ?: null;
}

public function getById(int $id): ?object
{
    $this->db->query("SELECT *, 
        CASE 
            WHEN logo_path LIKE 'http%' THEN logo_path
            WHEN logo_path LIKE '%/v%' THEN logo_path 
            ELSE CONCAT(:base_url, logo_path) 
        END as logo_path
        FROM {$this->table} WHERE id = :id LIMIT 1");
    
    $this->db->bind(":id", $id);
    $this->db->bind(":base_url", $_ENV['APP_URL']);
    
    $result = $this->db->fetchOne();
    return $result ?: null;
}

public function getBySlug(string $slug): ?object
{
    $this->db->query("SELECT *,
        CASE
            WHEN logo_path IS NULL OR logo_path = '' THEN logo_path
            WHEN logo_path LIKE 'http%' THEN logo_path
            WHEN logo_path LIKE '%/v%' THEN logo_path
            ELSE CONCAT(:base_url, logo_path)
        END as logo_path
        FROM {$this->table} WHERE slug = :slug LIMIT 1");

    $this->db->bind(":slug", $slug);
    $this->db->bind(":base_url", rtrim($_ENV['APP_URL'] ?? '', '/'));

    $result = $this->db->fetchOne();
    return $result ?: null;
}

public function getByApprovedDomain(string $domain): ?object
{
    $this->db->query("SELECT * FROM {$this->table}
        WHERE custom_domain = :domain AND custom_domain_status = 'APPROVED'
        LIMIT 1");
    $this->db->bind(":domain", self::normalizeDomain($domain));

    $result = $this->db->fetchOne();
    return $result ?: null;
}

public function getCustomDomainRequests(): array
{
    $this->db->query("SELECT ip.*, u.name, u.lastname, u.email AS owner_email
        FROM {$this->table} ip
        LEFT JOIN users u ON u.id = ip.id_owner
        WHERE ip.custom_domain IS NOT NULL AND ip.custom_domain != ''
        ORDER BY
            CASE ip.custom_domain_status
                WHEN 'PENDING' THEN 1
                WHEN 'APPROVED' THEN 2
                WHEN 'REJECTED' THEN 3
                ELSE 4
            END,
            ip.custom_domain_requested_at DESC,
            ip.updated_at DESC");

    return $this->db->fetchAll();
}

public function normalizeSlug(string $value): string
{
    $value = strtolower(trim($value));
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string)$value, '-');

    return substr($value ?: 'business-profile', 0, 150);
}

public static function normalizeDomain(?string $domain): ?string
{
    if (!$domain) {
        return null;
    }

    $domain = strtolower(trim($domain));
    $domain = preg_replace('#^https?://#', '', $domain);
    $domain = preg_replace('#/.*$#', '', (string)$domain);
    $domain = trim((string)$domain);

    return $domain ?: null;
}

public function isSlugReserved(string $slug): bool
{
    return in_array($slug, self::RESERVED_SLUGS, true);
}

public function isSlugAvailable(string $slug, ?int $currentProfileId = null): bool
{
    $extra = $currentProfileId ? "AND id != :id" : "";
    $this->db->query("SELECT id FROM {$this->table} WHERE slug = :slug {$extra} LIMIT 1");
    $this->db->bind(":slug", $slug);
    if ($currentProfileId) {
        $this->db->bind(":id", $currentProfileId);
    }

    return !$this->db->fetchOne();
}

public function generateUniqueSlug(string $value, ?int $currentProfileId = null, ?string $fallback = null): string
{
    $baseValue = trim($value) !== '' ? $value : ($fallback ?: 'business-profile');
    $base = $this->normalizeSlug($baseValue);
    if ($this->isSlugReserved($base)) {
        $base .= '-business';
    }

    $slug = $base;
    $suffix = 2;

    while ($this->isSlugReserved($slug) || !$this->isSlugAvailable($slug, $currentProfileId)) {
        $slug = substr($base, 0, 140) . '-' . $suffix;
        $suffix++;
    }

    return $slug;
}

public function isDomainAvailable(?string $domain, ?int $currentProfileId = null): bool
{
    $domain = self::normalizeDomain($domain);
    if (!$domain) {
        return true;
    }

    $extra = $currentProfileId ? "AND id != :id" : "";
    $this->db->query("SELECT id FROM {$this->table} WHERE custom_domain = :domain {$extra} LIMIT 1");
    $this->db->bind(":domain", $domain);
    if ($currentProfileId) {
        $this->db->bind(":id", $currentProfileId);
    }

    return !$this->db->fetchOne();
}

public function buildDefaultSlug(string $companyName, int $ownerId): string
{
    return $this->generateUniqueSlug($companyName, null, 'business-' . $ownerId);
}

public function getMissingProfileItems(?object $profile): array
{
    if (!$profile) {
        return ['Company name', 'Business nature', 'Contact phone or email', 'City and state', 'Public slug'];
    }

    $missing = [];
    if (empty(trim((string)($profile->company_name ?? '')))) {
        $missing[] = 'Company name';
    }
    if (empty(trim((string)($profile->business_nature ?? '')))) {
        $missing[] = 'Business nature';
    }
    if (empty(trim((string)($profile->phone ?? ''))) && empty(trim((string)($profile->email ?? '')))) {
        $missing[] = 'Contact phone or email';
    }
    if (empty(trim((string)($profile->city ?? ''))) || empty(trim((string)($profile->state ?? '')))) {
        $missing[] = 'City and state';
    }
    if (empty(trim((string)($profile->slug ?? '')))) {
        $missing[] = 'Public slug';
    }

    return $missing;
}

public function isProfileComplete(?object $profile): bool
{
    return count($this->getMissingProfileItems($profile)) === 0;
}

public function getPublicUrl(?object $profile): ?string
{
    if (!$profile || empty($profile->slug)) {
        return null;
    }

    return rtrim($_ENV['APP_URL'] ?? '', '/') . '/business-profile?slug=' . urlencode($profile->slug);
}

public function saveBuilderProfile(int $ownerId, array $data, ?int $profileId = null): bool
{
    $allowed = [
        'company_name',
        'business_nature',
        'business_operation_type',
        'phone',
        'email',
        'address_line1',
        'city',
        'state',
        'zip',
        'country',
        'payment_method_accepted',
        'slug',
        'profile_layout',
        'show_map_section',
        'show_events_section',
        'show_quote_form',
        'show_services_section',
        'show_contact_section',
        'show_gallery_section',
        'show_description_section',
        'short_description',
        'rich_description',
        'custom_domain',
        'custom_domain_status',
        'custom_domain_requested_at',
        'custom_domain_notes',
        'profile_completed_at',
        'logo_path',
    ];

    $payload = array_intersect_key($data, array_flip($allowed));
    $payload['id_owner'] = $ownerId;

    if ($profileId) {
        unset($payload['id_owner']);
        return $this->update($payload, ['id' => $profileId]);
    }

    return $this->add($payload);
}

public function updateCustomDomainStatus(int $profileId, string $status, ?string $notes = null): bool
{
    $status = strtoupper($status);
    if (!in_array($status, ['APPROVED', 'REJECTED', 'PENDING', 'NONE'], true)) {
        return false;
    }

    $data = [
        'custom_domain_status' => $status,
        'custom_domain_notes' => $notes,
    ];

    if ($status === 'APPROVED') {
        $data['custom_domain_approved_at'] = date('Y-m-d H:i:s');
        $data['custom_domain_rejected_at'] = null;
    } elseif ($status === 'REJECTED') {
        $data['custom_domain_rejected_at'] = date('Y-m-d H:i:s');
        $data['custom_domain_approved_at'] = null;
    }

    return $this->update($data, ['id' => $profileId]);
}

public function getOwnerId(int $institutionId): ?int
{
    $this->db->query("SELECT id_owner FROM {$this->table} WHERE id = :id LIMIT 1");
    $this->db->bind(":id", $institutionId);
    
    $result = $this->db->fetchOne();
    return $result ? (int)$result->id_owner : null;
}

public function upsertBasicBusinessProfile(
    int $ownerId,
    string $companyName,
    ?string $businessNature = null,
    ?string $businessOperationType = null,
    ?string $phone = null,
    ?string $email = null
): bool {
    $existing = $this->getByOwner($ownerId);

    $data = [
        'company_name' => $companyName,
        'business_nature' => $businessNature,
        'business_operation_type' => $businessOperationType,
        'phone' => $phone,
        'email' => $email,
    ];

    if ($existing) {
        if (empty(trim((string)($existing->slug ?? '')))) {
            $data['slug'] = $this->generateUniqueSlug($companyName, (int)$existing->id, 'business-' . $ownerId);
        }

        return $this->update($data, ['id_owner' => $ownerId]);
    }

    return $this->add([
        'id_owner' => $ownerId,
        ...$data,
        'slug' => $this->generateUniqueSlug($companyName, null, 'business-' . $ownerId),
        'payment_method_accepted' => '',
    ]);
}

    
}
