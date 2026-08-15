-- Fields collected by Google Address autocomplete and printed on the universal package label.
ALTER TABLE store_orders
    ADD COLUMN shipping_country VARCHAR(100) NULL AFTER shipping_zip,
    ADD COLUMN shipping_instructions TEXT NULL AFTER shipping_country;
