-- DEMO ONLY: every catalog product at $1.00 (IVA 15% included) with stock, so the
-- full purchase and promotions flow can be shown. Replace with real prices before selling.
-- Promotions: the 8 home products get a 15% offer (struck price), like syf.ec.
SET NAMES utf8mb4;
UPDATE ps_product SET price = ROUND(1 / 1.15, 6), available_for_order = 1, show_price = 1 WHERE reference LIKE 'CANVA-%';
UPDATE ps_product_shop ps JOIN ps_product p ON p.id_product = ps.id_product
  SET ps.price = p.price, ps.available_for_order = 1, ps.show_price = 1 WHERE p.reference LIKE 'CANVA-%';
UPDATE ps_stock_available sa JOIN ps_product p ON p.id_product = sa.id_product
  SET sa.quantity = 10 WHERE p.reference LIKE 'CANVA-%' AND sa.id_product_attribute = 0;

DELETE sp FROM ps_specific_price sp JOIN ps_product p ON p.id_product = sp.id_product WHERE p.reference LIKE 'CANVA-%';
INSERT INTO ps_specific_price (id_product, id_shop, id_shop_group, id_currency, id_country, id_group, id_customer, id_product_attribute, price, from_quantity, reduction, reduction_tax, reduction_type, `from`, `to`, id_specific_price_rule, id_cart)
SELECT id_product, 1, 1, 0, 0, 0, 0, 0, -1, 1, 0.15, 1, 'percentage', '2000-01-01 00:00:00', '2099-12-31 23:59:59', 0, 0
FROM ps_product WHERE reference IN (
  'CANVA-insane-whey-5-lb','CANVA-iso-hd-bpi','CANVA-dragon-whey-phorm','CANVA-100-creatine-muscletech',
  'CANVA-psychotic-dorado','CANVA-hydroxycut-hardcore-muscletech','CANVA-carnivor-mass-6-lb','CANVA-gat-nitraflex-pre-workout'
);
SELECT COUNT(*) AS priced FROM ps_product WHERE reference LIKE 'CANVA-%' AND price > 0;
SELECT COUNT(*) AS offers FROM ps_specific_price sp JOIN ps_product p ON p.id_product = sp.id_product WHERE p.reference LIKE 'CANVA-%';

-- PrestaShop skips every specific price unless this flag is on (the back office sets it
-- when an offer is created there; rows inserted by SQL need it set explicitly).
UPDATE ps_configuration SET value = '1' WHERE name = 'PS_SPECIFIC_PRICE_FEATURE_ACTIVE';
