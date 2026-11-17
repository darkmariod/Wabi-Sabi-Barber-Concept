-- Demo home: feature catalog products in the "Promociones Exclusivas" carousel
-- (ps_featuredproducts shows the Inicio category). Same effect as ticking
-- "Mostrar en Promociones de la portada" in Mi Tienda. Idempotent.
INSERT IGNORE INTO ps_category_product (id_category, id_product, position)
SELECT 2, id_product, 0 FROM ps_product WHERE reference IN (
  'CANVA-insane-whey-5-lb','CANVA-iso-hd-bpi','CANVA-dragon-whey-phorm','CANVA-100-creatine-muscletech',
  'CANVA-psychotic-dorado','CANVA-hydroxycut-hardcore-muscletech','CANVA-carnivor-mass-6-lb','CANVA-gat-nitraflex-pre-workout'
);
