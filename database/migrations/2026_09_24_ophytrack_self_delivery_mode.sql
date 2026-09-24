-- Repair seller-owned delivery assignments. INTERNAL means the seller's own team;
-- EXTERNAL_CARRIER is reserved for cross-company carrier custody.
UPDATE `store_packages`
SET `logistics_mode`='INTERNAL', `updated_at`=NOW()
WHERE (`logistics_mode`='' OR `logistics_mode` IS NULL)
  AND `current_custodian_owner_id`=`id_owner`;
