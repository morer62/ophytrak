-- Store + Logistics status expansion for preparation, delivery attempts, returns and closure.
-- Required because store_orders.status is an ENUM in the current Ophyra schema.

ALTER TABLE `store_orders`
  MODIFY `status` ENUM(
    'NEW',
    'CONFIRMED',
    'PROCESSING',
    'IN_PREPARATION',
    'READY',
    'READY_FOR_DELIVERY',
    'OUT_FOR_DELIVERY',
    'DELIVERY_ATTEMPTED',
    'RETURNED_TO_BUSINESS',
    'REDELIVERY_SCHEDULED',
    'DELIVERED',
    'COMPLETED',
    'CANCELLED',
    'RETURN_REQUESTED',
    'RETURN_APPROVED',
    'RETURN_REJECTED',
    'RETURNED',
    'CLOSED'
  ) NOT NULL DEFAULT 'NEW';
