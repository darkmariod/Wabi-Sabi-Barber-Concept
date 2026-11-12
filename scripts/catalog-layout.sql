-- Catalog layout for the demo: move supplements to the right categories and hide empty ones from the menu.
-- Idempotent. Apply with: mysql ... prestashop < scripts/catalog-layout.sql

-- Vitamins and minerals -> Medicina natural (16)
CREATE TEMPORARY TABLE tmp_medicina AS
  SELECT id_product FROM ps_product
  WHERE reference LIKE 'CANVA-windmill-%' OR reference IN ('CANVA-sascha-restore','CANVA-sascha-collagen','CANVA-dragon-omega-3');
UPDATE ps_product SET id_category_default=16 WHERE id_product IN (SELECT id_product FROM tmp_medicina);
UPDATE ps_product_shop SET id_category_default=16 WHERE id_product IN (SELECT id_product FROM tmp_medicina);
DELETE FROM ps_category_product WHERE id_category=17 AND id_product IN (SELECT id_product FROM tmp_medicina);
INSERT IGNORE INTO ps_category_product (id_category, id_product, position) SELECT 16, id_product, 0 FROM tmp_medicina;
DROP TEMPORARY TABLE tmp_medicina;

-- Shakers -> Implementos (20)
CREATE TEMPORARY TABLE tmp_impl AS SELECT id_product FROM ps_product WHERE reference = 'CANVA-mezcladores-shake';
UPDATE ps_product SET id_category_default=20 WHERE id_product IN (SELECT id_product FROM tmp_impl);
UPDATE ps_product_shop SET id_category_default=20 WHERE id_product IN (SELECT id_product FROM tmp_impl);
DELETE FROM ps_category_product WHERE id_category=17 AND id_product IN (SELECT id_product FROM tmp_impl);
INSERT IGNORE INTO ps_category_product (id_category, id_product, position) SELECT 20, id_product, 0 FROM tmp_impl;
DROP TEMPORARY TABLE tmp_impl;

-- Menu: only categories that have products
UPDATE ps_configuration SET value='CAT10,CAT16,CAT17,CAT18,CAT20' WHERE name='MOD_BLOCKTOPMENU_ITEMS';
