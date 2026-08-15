<?php
namespace App\Repositories;
class StoreUserRolesRepository extends StoreRepository {
    public function __construct(){ $this->table='store_user_roles';$this->db=new Connection(); }
    public function getUsersByOwnerAndRole(int $owner,string $role): array { $this->db->query("SELECT u.*,u.id AS id_user FROM users u INNER JOIN {$this->table} r ON r.id_user=u.id WHERE r.id_owner=:owner AND r.role=:role ORDER BY u.name,u.lastname");$this->db->bind(':owner',$owner);$this->db->bind(':role',$role);return $this->db->fetchAll(); }
    public function getUsersByOwner(int $owner): array {
        $this->db->query("SELECT DISTINCT u.*,u.id AS id_user,COALESCE(r.role,'general') AS store_role
            FROM users u
            LEFT JOIN {$this->table} r ON r.id_user=u.id AND r.id_owner=:owner_role
            LEFT JOIN user_institutions ui ON ui.user_id=u.id AND ui.is_active=1
            LEFT JOIN institution_profile ip_primary ON ip_primary.id=ui.institution_id
            LEFT JOIN institution_profile ip_secondary ON ip_secondary.id=ui.secondary_institution_id
            WHERE u.level=4 AND u.is_active=1
              AND (r.id_owner=:owner OR u.id_owner=:owner OR ip_primary.id_owner=:owner OR ip_secondary.id_owner=:owner)
            ORDER BY u.name,u.lastname");
        $this->db->bind(':owner_role',$owner);
        $this->db->bind(':owner',$owner);
        return $this->db->fetchAll();
    }
    public function userBelongsToOwner(int $owner,int $user): bool {
        $this->db->query("SELECT u.id
            FROM users u
            LEFT JOIN {$this->table} r ON r.id_user=u.id AND r.id_owner=:owner_role
            LEFT JOIN user_institutions ui ON ui.user_id=u.id AND ui.is_active=1
            LEFT JOIN institution_profile ip_primary ON ip_primary.id=ui.institution_id
            LEFT JOIN institution_profile ip_secondary ON ip_secondary.id=ui.secondary_institution_id
            WHERE u.id=:user AND u.level=4 AND u.is_active=1
              AND (r.id_owner=:owner OR u.id_owner=:owner OR ip_primary.id_owner=:owner OR ip_secondary.id_owner=:owner)
            LIMIT 1");
        $this->db->bind(':owner_role',$owner);
        $this->db->bind(':owner',$owner);
        $this->db->bind(':user',$user);
        return (bool)$this->db->fetchOne();
    }
}
