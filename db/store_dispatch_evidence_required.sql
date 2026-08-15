ALTER TABLE `store_order_workflow`
  ADD COLUMN IF NOT EXISTS `dispatch_photo_url` TEXT NULL AFTER `sent_at`;
