-- Structured proof of delivery captured by the assigned delivery user.
ALTER TABLE `store_order_workflow`
  ADD COLUMN `delivery_receiver_type` VARCHAR(40) NULL AFTER `delivery_photo_url`,
  ADD COLUMN `delivery_receiver_name` VARCHAR(150) NULL AFTER `delivery_receiver_type`,
  ADD COLUMN `delivery_document_type` VARCHAR(30) NULL AFTER `delivery_receiver_name`,
  ADD COLUMN `delivery_document_number` VARCHAR(80) NULL AFTER `delivery_document_type`;
