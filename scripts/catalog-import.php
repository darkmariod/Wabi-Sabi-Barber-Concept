<?php
/**
 * Imports the Canva catalog products as HIDDEN products, without prices or photos.
 * The owner sets price, photo and stock in Mi Tienda, then switches them on.
 *
 * Idempotent: products are matched by reference (CANVA-<slug>); existing ones are skipped.
 * Usage: docker compose exec -T -u www-data prestashop php /opt/natufitness/scripts/catalog-import.php
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = getenv('PS_DOMAIN') ?: 'localhost';
require '/var/www/html/config/config.inc.php';

// [name, weight/presentation, category, category id]
$catalog = [
    ['BCAA XS', '', 'Aminoácidos y BCAA', 14],
    ['Creatina XS', '', 'Creatinas', 12],
    ['Glutamina XS', '', 'Ganadores de masa', 15],
    ['King Mass XL', 'Vainilla / Chocolate', 'Ganadores de masa', 15],
    ['Carnivor Mass 6 lb', 'Cookies & cream / Vainilla / Chocolate', 'Ganadores de masa', 15],
    ['Carnivor Mass 10 lb', 'Chocolate / Vainilla', 'Ganadores de masa', 15],
    ['Carnivor Mass 15 lb', 'Chocolate', 'Ganadores de masa', 15],
    ['Carnivor Isolate', '', 'Proteínas', 11],
    ['Creatina Carnivor', '60 servicios', 'Creatinas', 12],
    ['Crea 10', '30 servicios', 'Creatinas', 12],
    ['Pre-entreno Shaboom Pump', 'Ice pump / Fruit punch', 'Pre-entrenos', 13],
    ['Pure Whey', '', 'Proteínas', 11],
    ['Pure Whey Vegan', 'Chocolate & cookies', 'Proteínas', 11],
    ['Delux 100% Whey', 'Vainilla / Fresa', 'Proteínas', 11],
    ['Creatina Creapure', '', 'Creatinas', 12],
    ['Creatina Extreme Tank', '', 'Creatinas', 12],
    ['Burner Extreme Tank', '', 'Quemadores', 18],
    ['Define Body 12 Women', '60 cápsulas', 'Quemadores', 18],
    ['Energy Storm', '30 servicios', 'Pre-entrenos', 13],
    ['Mantequilla de maní', '', 'Salud y vitaminas', 17],
    ['Anabolic Iso Whey 2 lb', 'Vainilla', 'Proteínas', 11],
    ['Levrone Whey Supreme 10 lb', 'Vainilla / Chocolate', 'Proteínas', 11],
    ['Levro Whey 4.4 lb', 'Vainilla / Chocolate', 'Proteínas', 11],
    ['Anabolic PM Protein 1 lb', 'Vainilla', 'Proteínas', 11],
    ['Anabolic Iso Whey 5 lb', 'Vainilla / Chocolate', 'Proteínas', 11],
    ['Levro Legendary Mass 8 lb', 'Vainilla / Chocolate', 'Ganadores de masa', 15],
    ['Anabolic Mass 6 lb', 'Vainilla', 'Ganadores de masa', 15],
    ['Vitargo 4.4 lb', 'Revisar sabor', 'Pre-entrenos', 13],
    ['BCAA 5000', '30 servicios', 'Aminoácidos y BCAA', 14],
    ['Creatina 5000', '60 servicios', 'Creatinas', 12],
    ['BCAA Energy', '30 servicios', 'Aminoácidos y BCAA', 14],
    ['Glutamina 5000', '60 servicios', 'Ganadores de masa', 15],
    ['L-Arginina 1500', '100 cápsulas', 'Salud y vitaminas', 17],
    ['Whey Protein Evlution 5 lb', 'Chocolate / Vainilla', 'Proteínas', 11],
    ['L-Carnitina 1500', '', 'Quemadores', 18],
    ['Multivitamínico Vita XS', '', 'Salud y vitaminas', 17],
    ['Shadowhey Isolate 5 lb', 'Vainilla / Chocolate', 'Proteínas', 11],
    ['Shadowhey 5 lb', 'Vainilla / Chocolate', 'Proteínas', 11],
    ['Creatina Monohidratada Shadow', '60 servicios', 'Creatinas', 12],
    ['Blood & Guts Pre Workout', '30 servicios / Strawberry', 'Pre-entrenos', 13],
    ['Hit BCAA 10:1:1 8 lb', 'Vainilla / Chocolate / Pina', 'Aminoácidos y BCAA', 14],
    ['Pre Work M6Teen', 'Caja x 12', 'Pre-entrenos', 13],
    ['Beta-Alanina Insane Labz', '30 servicios', 'Aminoácidos y BCAA', 14],
    ['L-Citrulline Insane Labz', '60 servicios', 'Salud y vitaminas', 17],
    ['Possessed Pre-entreno', '30 servicios', 'Pre-entrenos', 13],
    ['Creating Monohydrate Insane Labz', '30 servicios', 'Creatinas', 12],
    ['Psychotic Negro', '35 servicios / Punch fruit', 'Pre-entrenos', 13],
    ['Psychotic Dorado', '30 servicios / Fruit punch', 'Pre-entrenos', 13],
    ['Psychotic Rojo', '35 servicios / Watermelon, uva, sandía, frutos tropicales', 'Pre-entrenos', 13],
    ['Insane Iso 5 lb', 'Vainilla / Chocolate', 'Proteínas', 11],
    ['Insane Whey Ripped 5 lb', 'Vainilla', 'Proteínas', 11],
    ['Insane Whey 5 lb', 'Vainilla / Chocolate', 'Proteínas', 11],
    ['Psychotic Saw', '30 servicios / Fruit punch, blueberry, grape', 'Pre-entrenos', 13],
    ['CLA + Carnitine BPI', '50 servicios / Snow cone, fruit punch', 'Quemadores', 18],
    ['Best BCAA BPI', '30–60 servicios / Blue berry', 'Aminoácidos y BCAA', 14],
    ['One More Rep BPI', '60 servicios / Punch fruit', 'Pre-entrenos', 13],
    ['Creatina BPI', '60 / 120 / 200 servicios', 'Creatinas', 12],
    ['ISO HD BPI', '1.6–5 lb / Vainilla, chocolate', 'Proteínas', 11],
    ['Veggie Protein BPI', '1.8 lb / Vainilla', 'Proteínas', 11],
    ['Whey HD BPI', '4.2 lb / Cookies & cream, vainilla, chocolate', 'Proteínas', 11],
    ['BCAA Energy Integralmédica', '30 servicios', 'Aminoácidos y BCAA', 14],
    ['Nutri Whey Integralmédica', '2–4 lb / Vainilla', 'Proteínas', 11],
    ['Crea Mass Integralmédica', '6.6 lb / Vainilla, chocolate, cookies & cream', 'Ganadores de masa', 15],
    ['Dark Bar', '8 unidades / Crema de coco, cookies, chispas de chocolate', 'Salud y vitaminas', 17],
    ['Protein Crisp Bar', '12 unidades / Avellana, limón, cookies & cream', 'Salud y vitaminas', 17],
    ['Gel Energético VO2', '10 unidades', 'Salud y vitaminas', 17],
    ['My Whey RTD', '24 unidades / Vainilla, chocolate', 'Proteínas', 11],
    ['Creatina Hardcore Integralmédica', '30–100 servicios', 'Creatinas', 12],
    ['Beta-Alanina Pure Integralmédica', '60 servicios', 'Aminoácidos y BCAA', 14],
    ['L-Carnitina ProSupps', '473 ml', 'Quemadores', 18],
    ['Whey Concentrate ProSupps', '5 lb / Vainilla, chocolate', 'Proteínas', 11],
    ['ME Hyde Pre Workout ProSupps', '30 servicios', 'Pre-entrenos', 13],
    ['Hydro BCAA Essencial ProSupps', '90 servicios', 'Aminoácidos y BCAA', 14],
    ['Beef Mass Red Rex', '6–10–22 lb / Vainilla, chocolate', 'Ganadores de masa', 15],
    ['Big Mass Red Rex', '6 lb / Vainilla, chocolate', 'Ganadores de masa', 15],
    ['Essential Pre-Workout 3UM', '30 servicios / Watermelon', 'Pre-entrenos', 13],
    ['Itholate Protein 3UM Birthday Cake', '2 lb / Birthday cake', 'Proteínas', 11],
    ['Itholate Protein 3UM Cookies & Cream', '2 lb / Cookies & cream, vainilla, chocolate', 'Proteínas', 11],
    ['Thavage Pre-Workout 3UM', '40 servicios / Limón', 'Pre-entrenos', 13],
    ['Itholate 3UM 5 lb', '5 lb / Vainilla, chocolate', 'Proteínas', 11],
    ['Creatina 3UM', '100 servicios', 'Creatinas', 12],
    ['Essential Fat Burner 3UM', '60 cápsulas', 'Quemadores', 18],
    ['100% Creatine MuscleTech', '80 servicios', 'Creatinas', 12],
    ['Hydroxycut Hardcore MuscleTech', '110 cápsulas', 'Quemadores', 18],
    ['Cre Creatine StarLabs', '60 servicios', 'Creatinas', 12],
    ['Hyogainer XXL StarLabs', '6 lb / Vainilla, fresa', 'Ganadores de masa', 15],
    ['Isolate CFM StarLabs', '2–4 lb / Chocolate, vainilla, fresa', 'Proteínas', 11],
    ['QuickMass Allmax', '10 lb / Vainilla', 'Ganadores de masa', 15],
    ['Isoflex Allmax', '2–5 lb / Vainilla, chocolate', 'Proteínas', 11],
    ['Beta Alanina Nutrión', '66 servicios', 'Aminoácidos y BCAA', 14],
    ['Creatina CMH Nutrión', '70 servicios', 'Creatinas', 12],
    ['Glutamina Nutrión', '60 servicios', 'Ganadores de masa', 15],
    ['Creatine Drive Nutrex', '300 g – 1 kg', 'Creatinas', 12],
    ['Glutamine Drive Nutrex', '60 servicios', 'Ganadores de masa', 15],
    ['BCAA 6000 Nutrex', '30 servicios / Fruit punch', 'Aminoácidos y BCAA', 14],
    ['Lipo 6 Pre Entreno Nutrex', '30 servicios / Tropical', 'Pre-entrenos', 13],
    ['Isofit Nutrex', '2.2–5 lb / Vainilla, chocolate, galleta, fresa', 'Proteínas', 11],
    ['HMB 1000 mg Nutrex', '120 cápsulas', 'Salud y vitaminas', 17],
    ['Tribulus 1400 mg Nutrex', '120 cápsulas', 'Salud y vitaminas', 17],
];

$langId = (int) Configuration::get('PS_LANG_DEFAULT');
$shopId = (int) Configuration::get('PS_SHOP_DEFAULT');
$taxGroupId = (int) TaxRulesGroup::getIdByName('EC IVA 15%');
$created = 0;
$skipped = 0;

foreach ($catalog as [$name, $presentation, $categoryName, $categoryId]) {
    $reference = 'CANVA-' . Tools::str2url($name);
    if (Product::getIdByReference($reference)) {
        $skipped++;
        continue;
    }

    $product = new Product();
    $product->reference = $reference;
    $product->price = 0;
    $product->id_tax_rules_group = $taxGroupId;
    $product->id_category_default = $categoryId;
    $product->active = false; // hidden until the owner sets price, photo and stock
    $product->visibility = 'none';
    $product->redirect_type = '404';
    $product->condition = 'new';
    $product->show_price = false;
    foreach (Language::getIDs(false) as $id) {
        $product->name[$id] = $name;
        $product->link_rewrite[$id] = Tools::str2url($name);
        $product->description_short[$id] = $presentation !== '' ? 'Presentación / sabor: ' . $presentation : '';
    }
    $product->add();
    $product->updateCategories([$categoryId]);
    StockAvailable::setQuantity((int) $product->id, 0, 0, $shopId);
    $created++;
    echo "Created hidden product: {$name} ({$categoryName})\n";
}

Tools::clearSmartyCache();
echo "Done: created {$created}, already existed {$skipped}\n";
