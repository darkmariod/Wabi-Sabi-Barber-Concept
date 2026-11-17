-- One-off fix: the Gold Standard on the Dymatize page of the 2026 catalog is Optimum Nutrition.
SET NAMES utf8mb4;
UPDATE ps_product p JOIN ps_manufacturer m ON m.name = 'Optimum Nutrition'
  SET p.id_manufacturer = m.id_manufacturer WHERE p.reference = 'C26-dymatize-whey-gold-standard';
UPDATE ps_product_lang pl JOIN ps_product p ON p.id_product = pl.id_product
  SET pl.name = 'Optimum Nutrition Whey Gold Standard', pl.link_rewrite = 'optimum-nutrition-whey-gold-standard'
  WHERE p.reference = 'C26-dymatize-whey-gold-standard';
