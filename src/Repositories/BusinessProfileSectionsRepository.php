<?php

namespace App\Repositories;

class BusinessProfileSectionsRepository
{
    private Connection $db;
    private string $table = 'business_profile_sections';

    private array $defaultSections = [
        ['key' => 'description', 'label' => 'About', 'visible' => 1, 'sort' => 10, 'fixed' => 0],
        ['key' => 'contact', 'label' => 'Contact information', 'visible' => 1, 'sort' => 20, 'fixed' => 0],
        ['key' => 'map', 'label' => 'Map / Location', 'visible' => 1, 'sort' => 30, 'fixed' => 1],
        ['key' => 'quote_form', 'label' => 'Request services', 'visible' => 1, 'sort' => 40, 'fixed' => 0],
        ['key' => 'services', 'label' => 'Services', 'visible' => 1, 'sort' => 50, 'fixed' => 0],
        ['key' => 'events', 'label' => 'Events', 'visible' => 1, 'sort' => 60, 'fixed' => 0],
        ['key' => 'gallery', 'label' => 'Gallery / Photos', 'visible' => 1, 'sort' => 70, 'fixed' => 0],
        ['key' => 'business_hours', 'label' => 'Business hours', 'visible' => 1, 'sort' => 80, 'fixed' => 0],
        ['key' => 'payment_methods', 'label' => 'Payment methods', 'visible' => 1, 'sort' => 90, 'fixed' => 0],
        ['key' => 'social_links', 'label' => 'Social links', 'visible' => 1, 'sort' => 100, 'fixed' => 0],
    ];

    public function __construct()
    {
        $this->db = new Connection();
    }

    public function getDefaultSections(): array
    {
        return $this->defaultSections;
    }

    public function ensureDefaults(int $profileId): void
    {
        foreach ($this->defaultSections as $section) {
            $this->db->query("INSERT INTO {$this->table}
                (institution_profile_id, section_key, section_label, is_visible, sort_order, is_fixed, created_at, updated_at)
                VALUES (:profile_id, :section_key, :section_label, :is_visible, :sort_order, :is_fixed, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    section_label = section_label,
                    updated_at = updated_at");
            $this->db->bind(':profile_id', $profileId);
            $this->db->bind(':section_key', $section['key']);
            $this->db->bind(':section_label', $section['label']);
            $this->db->bind(':is_visible', (int)$section['visible']);
            $this->db->bind(':sort_order', (int)$section['sort']);
            $this->db->bind(':is_fixed', (int)$section['fixed']);
            $this->db->execute();
        }
    }

    public function getByProfile(int $profileId, bool $visibleOnly = false): array
    {
        $where = $visibleOnly ? 'AND is_visible = 1' : '';
        $this->db->query("SELECT * FROM {$this->table}
            WHERE institution_profile_id = :profile_id {$where}
            ORDER BY is_fixed DESC, sort_order ASC, id ASC");
        $this->db->bind(':profile_id', $profileId);

        return $this->db->fetchAll();
    }

    public function saveSections(int $profileId, array $sections): void
    {
        foreach ($sections as $section) {
            $key = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($section['key'] ?? '')));
            if ($key === '') {
                continue;
            }

            $label = trim((string)($section['label'] ?? ''));
            $label = $label !== '' ? substr($label, 0, 150) : ucfirst(str_replace('_', ' ', $key));
            $isVisible = !empty($section['is_visible']) ? 1 : 0;
            $sortOrder = (int)($section['sort_order'] ?? 100);

            $this->db->query("UPDATE {$this->table}
                SET section_label = :label,
                    is_visible = :is_visible,
                    sort_order = :sort_order,
                    updated_at = NOW()
                WHERE institution_profile_id = :profile_id AND section_key = :section_key");
            $this->db->bind(':label', $label);
            $this->db->bind(':is_visible', $isVisible);
            $this->db->bind(':sort_order', $sortOrder);
            $this->db->bind(':profile_id', $profileId);
            $this->db->bind(':section_key', $key);
            $this->db->execute();
        }
    }
}
