<?php
/**
 * Product operations for the "Mi Tienda" panel: listing, loading, saving
 * (price with IVA, offer price, stock, category, brand, photos) and deleting.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class NfPanelProductService
{
    const PAGE_SIZE = 20;
    const MAX_PHOTO_BYTES = 5242880; // 5 MB
    const PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    const PHOTO_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    const TAX_GROUP_NAMES = ['15' => 'EC IVA 15%', '0' => 'EC IVA 0%'];
    const DEFAULT_IVA = '15';
    const MAX_NAME_LENGTH = 128;

    /** @var Context */
    private $context;

    /** @var int */
    private $idLang;

    /** @var int */
    private $idShop;

    public function __construct(Context $context)
    {
        $this->context = $context;
        $this->idLang = (int) $context->language->id;
        $this->idShop = (int) $context->shop->id;
    }

    // --- Reading -------------------------------------------------------------

    /**
     * @return array{items: array, total: int, page: int, pages: int}
     */
    public function listProducts(string $search, int $page): array
    {
        $db = Db::getInstance();
        $where = 'WHERE p.state = ' . (int) Product::STATE_SAVED;
        if ($search !== '') {
            $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $where .= ' AND pl.name LIKE "%' . pSQL($like) . '%"';
        }
        $from = ' FROM `' . _DB_PREFIX_ . 'product` p'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.id_product = p.id_product AND ps.id_shop = ' . $this->idShop
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.id_product = p.id_product AND pl.id_lang = ' . $this->idLang . ' AND pl.id_shop = ' . $this->idShop
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.id_category = ps.id_category_default AND cl.id_lang = ' . $this->idLang . ' AND cl.id_shop = ' . $this->idShop . ' ';

        $total = (int) $db->getValue('SELECT COUNT(*)' . $from . $where);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min(max(1, $page), $pages);

        $rows = $db->executeS(
            'SELECT p.id_product, pl.name, pl.link_rewrite, ps.active, cl.name AS category_name' . $from . $where
            . ' ORDER BY p.date_add DESC, p.id_product DESC'
            . ' LIMIT ' . (int) (($page - 1) * self::PAGE_SIZE) . ', ' . (int) self::PAGE_SIZE
        ) ?: [];

        $items = [];
        foreach ($rows as $row) {
            $id = (int) $row['id_product'];
            $regular = Product::getPriceStatic($id, true, null, 2, null, false, false);
            $final = Product::getPriceStatic($id, true, null, 2, null, false, true);
            $cover = Image::getCover($id);
            $items[] = [
                'id' => $id,
                'name' => (string) $row['name'],
                'category' => (string) $row['category_name'],
                'active' => (bool) $row['active'],
                'price' => $regular,
                'offer' => $final < $regular ? $final : null,
                'stock' => (int) StockAvailable::getQuantityAvailableByProduct($id, 0, $this->idShop),
                'has_combinations' => Product::getProductAttributesIds($id) ? true : false,
                'thumb' => $cover ? $this->context->link->getImageLink((string) $row['link_rewrite'], (string) $cover['id_image'], ImageType::getFormattedName('small')) : null,
            ];
        }

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /**
     * Data for the product form (empty defaults for a new product).
     */
    public function getFormData(int $idProduct): array
    {
        $data = [
            'id' => 0,
            'name' => '',
            'price' => '',
            'iva' => self::DEFAULT_IVA,
            'offer' => '',
            'stock' => '0',
            'id_category' => 0,
            'show_home' => false,
            'id_manufacturer' => 0,
            'description' => '',
            'active' => true,
            'images' => [],
            'has_combinations' => false,
        ];
        if (!$idProduct) {
            return $data;
        }

        $product = new Product($idProduct, false, $this->idLang, $this->idShop);
        if (!Validate::isLoadedObject($product)) {
            return $data;
        }

        $ivaKey = $this->ivaKeyForGroup((int) $product->id_tax_rules_group);
        $priceIncl = $this->addTaxes((float) $product->price, (int) $product->id_tax_rules_group);
        $offer = $this->findPanelSpecificPrice($idProduct);
        $offerIncl = '';
        if ($offer) {
            $offerValue = $offer->reduction_type === 'percentage'
                ? $priceIncl * (1 - (float) $offer->reduction)
                : $priceIncl - (float) $offer->reduction;
            $offerIncl = number_format($offerValue, 2, '.', '');
        }
        $plainDescription = $this->htmlToPlain((string) $product->description);

        $images = [];
        foreach (Image::getImages($this->idLang, $idProduct) as $image) {
            $images[] = [
                'id' => (int) $image['id_image'],
                'cover' => (bool) $image['cover'],
                'url' => $this->context->link->getImageLink((string) $product->link_rewrite, (string) $image['id_image'], ImageType::getFormattedName('home')),
            ];
        }

        return [
            'id' => (int) $product->id,
            'name' => (string) $product->name,
            'price' => number_format($priceIncl, 2, '.', ''),
            'iva' => $ivaKey,
            'offer' => $offerIncl,
            'stock' => (string) StockAvailable::getQuantityAvailableByProduct($idProduct, 0, $this->idShop),
            'id_category' => (int) $product->id_category_default,
            'show_home' => in_array((int) Configuration::get('PS_HOME_CATEGORY'), array_map('intval', Product::getProductCategories($idProduct)), true),
            'id_manufacturer' => (int) $product->id_manufacturer,
            'description' => $plainDescription,
            'active' => (bool) $product->active,
            'images' => $images,
            'has_combinations' => $product->hasCombinations(),
        ];
    }

    /**
     * Categories under the home category, depth-first, with an indented label.
     *
     * @return array<int, array{id: int, label: string}>
     */
    public function getCategoryOptions(): array
    {
        $home = new Category((int) Configuration::get('PS_HOME_CATEGORY'));
        $rows = Db::getInstance()->executeS(
            'SELECT c.id_category, c.level_depth, cl.name FROM `' . _DB_PREFIX_ . 'category` c'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.id_category = c.id_category AND cl.id_lang = ' . $this->idLang . ' AND cl.id_shop = ' . $this->idShop
            . ' WHERE c.nleft > ' . (int) $home->nleft . ' AND c.nright < ' . (int) $home->nright
            . ' ORDER BY c.nleft'
        ) ?: [];

        $options = [];
        foreach ($rows as $row) {
            $indent = max(0, (int) $row['level_depth'] - (int) $home->level_depth - 1);
            $options[] = [
                'id' => (int) $row['id_category'],
                'label' => str_repeat('— ', $indent) . $row['name'],
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function getManufacturerOptions(): array
    {
        $options = [];
        foreach (Manufacturer::getManufacturers(false, $this->idLang, false) as $manufacturer) {
            $options[] = ['id' => (int) $manufacturer['id_manufacturer'], 'name' => (string) $manufacturer['name']];
        }

        return $options;
    }

    // --- Writing -------------------------------------------------------------

    /**
     * Validates and saves the panel product form.
     *
     * @param array $input raw form values
     * @param array $files normalized uploaded photos (name, type, tmp_name, error, size)
     * @param string[] $errors filled with user-facing messages on failure
     *
     * @return int product id, 0 on validation error
     */
    public function save(array $input, array $files, array &$errors): int
    {
        $translator = $this->context->getTranslator();
        $t = function (string $message, array $params = []) use ($translator): string {
            return $translator->trans($message, $params, 'Modules.Nfpanel.Admin');
        };

        $idProduct = (int) ($input['id_product'] ?? 0);
        $product = new Product($idProduct ?: null, false, null, $this->idShop);
        if ($idProduct && !Validate::isLoadedObject($product)) {
            $errors[] = $t('El producto ya no existe.');

            return 0;
        }
        if ($idProduct && $product->hasCombinations()) {
            $errors[] = $t('Este producto tiene variantes y solo se puede editar en el editor completo.');

            return 0;
        }

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors[] = $t('Escribe el nombre del producto.');
        } elseif (Tools::strlen($name) > self::MAX_NAME_LENGTH || !Validate::isCatalogName($name)) {
            $errors[] = $t('El nombre tiene caracteres no permitidos (por ejemplo < > ; = # { }) o es muy largo.');
        }

        $priceIncl = $this->parseMoney($input['price'] ?? '');
        if ($priceIncl === null || $priceIncl <= 0) {
            $errors[] = $t('Escribe un precio de venta válido, por ejemplo 25.90.');
        }

        $ivaKey = (string) ($input['iva'] ?? self::DEFAULT_IVA);
        $taxGroupId = isset(self::TAX_GROUP_NAMES[$ivaKey]) ? (int) TaxRulesGroup::getIdByName(self::TAX_GROUP_NAMES[$ivaKey]) : 0;
        if (!$taxGroupId) {
            $errors[] = $t('Elige el IVA del producto.');
        }

        $offerRaw = trim((string) ($input['offer'] ?? ''));
        $offerIncl = null;
        if ($offerRaw !== '') {
            $offerIncl = $this->parseMoney($offerRaw);
            if ($offerIncl === null || $offerIncl <= 0) {
                $errors[] = $t('Escribe un precio de oferta válido o deja el campo vacío.');
            } elseif ($priceIncl !== null && $offerIncl >= $priceIncl) {
                $errors[] = $t('El precio de oferta debe ser menor que el precio de venta.');
            }
        }

        $stockRaw = trim((string) ($input['stock'] ?? '0'));
        if ($stockRaw === '') {
            $stockRaw = '0';
        }
        if (!preg_match('/^\d{1,7}$/', $stockRaw)) {
            $errors[] = $t('El stock debe ser un número entero (0 o más).');
        }

        $idCategory = (int) ($input['id_category'] ?? 0);
        if (!in_array($idCategory, array_column($this->getCategoryOptions(), 'id'), true)) {
            $errors[] = $t('Elige una categoría.');
        }

        $idManufacturer = (int) ($input['id_manufacturer'] ?? 0);
        if ($idManufacturer && !in_array($idManufacturer, array_column($this->getManufacturerOptions(), 'id'), true)) {
            $errors[] = $t('Elige una marca de la lista.');
        }

        $description = str_replace("\r\n", "\n", (string) ($input['description'] ?? ''));
        if (Tools::strlen($description) > 20000) {
            $errors[] = $t('La descripción es demasiado larga.');
        }

        foreach ($files as $file) {
            $error = $this->validatePhoto($file);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        if ($errors) {
            return 0;
        }

        $isNew = !$idProduct;
        $languages = Language::getLanguages(false);
        foreach ($languages as $language) {
            $idLang = (int) $language['id_lang'];
            $product->name[$idLang] = $name;
            if ($isNew || empty($product->link_rewrite[$idLang])) {
                $product->link_rewrite[$idLang] = Tools::str2url($name) ?: 'producto';
            }
        }

        // Only replace the description when the owner changed the plain text,
        // so rich HTML written in the native editor survives untouched saves.
        $currentPlain = $isNew ? '' : $this->htmlToPlain((string) ($product->description[$this->idLang] ?? ''));
        if ($isNew || trim($description) !== trim($currentPlain)) {
            $html = trim($description) === '' ? '' : '<p>' . nl2br(htmlspecialchars(trim($description), ENT_QUOTES, 'UTF-8')) . '</p>';
            foreach ($languages as $language) {
                $product->description[(int) $language['id_lang']] = $html;
            }
        }

        $product->id_tax_rules_group = $taxGroupId;
        $product->price = round($this->removeTaxes((float) $priceIncl, $taxGroupId), 6);
        $product->id_category_default = $idCategory;
        $product->id_manufacturer = $idManufacturer;
        $product->active = !empty($input['active']);

        if ($isNew) {
            $product->id_shop_default = $this->idShop;
            $product->visibility = 'both';
            $product->condition = 'new';
            $product->show_price = true;
            $product->available_for_order = true;
            $product->minimal_quantity = 1;
            $product->state = Product::STATE_SAVED;
            if (!$product->add()) {
                $errors[] = $t('No se pudo crear el producto. Inténtalo de nuevo.');

                return 0;
            }
        }

        if (trim((string) $product->reference) === '') {
            $product->reference = 'NF' . str_pad((string) $product->id, 5, '0', STR_PAD_LEFT);
        }

        $categories = [$idCategory];
        $homeId = (int) Configuration::get('PS_HOME_CATEGORY');
        if (!empty($input['show_home']) && $homeId !== $idCategory) {
            $categories[] = $homeId;
        }
        $product->updateCategories($categories);

        StockAvailable::setQuantity((int) $product->id, 0, (int) $stockRaw, $this->idShop);
        $this->syncOffer((int) $product->id, (float) $priceIncl, $offerIncl);

        $this->deleteImages((int) $product->id, array_map('intval', (array) ($input['delete_images'] ?? [])));
        foreach ($files as $file) {
            if (!$this->addPhoto($product, $file)) {
                $errors[] = $t('No se pudo guardar la foto %name%.', ['%name%' => $file['name']]);
            }
        }
        $this->applyCover((int) $product->id, (int) ($input['cover_image'] ?? 0));

        // Final update: persists the reference and fires actionProductUpdate once
        // everything (categories, photos, offer) is in place, so module caches refresh.
        if (!$product->update()) {
            $errors[] = $t('No se pudo guardar el producto. Inténtalo de nuevo.');

            return 0;
        }
        Search::indexation(false, (int) $product->id);
        $this->refreshStorefrontCache();

        return $errors ? 0 : (int) $product->id;
    }

    public function toggle(int $idProduct): bool
    {
        $product = new Product($idProduct, false, null, $this->idShop);
        $ok = Validate::isLoadedObject($product) && $product->toggleStatus();
        $this->refreshStorefrontCache();

        return $ok;
    }

    public function delete(int $idProduct): bool
    {
        $product = new Product($idProduct, false, null, $this->idShop);
        $ok = Validate::isLoadedObject($product) && $product->delete();
        $this->refreshStorefrontCache();

        return $ok;
    }

    /**
     * Product hooks ask modules such as ps_featuredproducts to clear their cached
     * blocks, but from a back-office request the template key does not match the
     * one cached by the storefront, so homepage blocks could keep showing hidden or
     * deleted products. Clearing the rendered-template cache (not the compiled
     * templates) is cheap and keeps the storefront in sync with the panel.
     */
    private function refreshStorefrontCache(): void
    {
        Tools::clearCache($this->context->smarty);
    }

    // --- Prices --------------------------------------------------------------

    private function taxCalculator(int $taxGroupId): TaxCalculator
    {
        $address = new Address();
        $address->id_country = (int) Country::getByIso('EC') ?: (int) Configuration::get('PS_COUNTRY_DEFAULT');

        return TaxManagerFactory::getManager($address, $taxGroupId)->getTaxCalculator();
    }

    private function addTaxes(float $priceExcl, int $taxGroupId): float
    {
        return $taxGroupId ? $this->taxCalculator($taxGroupId)->addTaxes($priceExcl) : $priceExcl;
    }

    private function removeTaxes(float $priceIncl, int $taxGroupId): float
    {
        return $taxGroupId ? $this->taxCalculator($taxGroupId)->removeTaxes($priceIncl) : $priceIncl;
    }

    private function ivaKeyForGroup(int $taxGroupId): string
    {
        foreach (self::TAX_GROUP_NAMES as $key => $groupName) {
            if ($taxGroupId && (int) TaxRulesGroup::getIdByName($groupName) === $taxGroupId) {
                return (string) $key;
            }
        }

        return $taxGroupId ? self::DEFAULT_IVA : '0';
    }

    private function parseMoney($raw): ?float
    {
        $value = str_replace([' ', '$'], '', trim((string) $raw));
        $value = str_replace(',', '.', $value);
        if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * Specific price managed by the panel: product-wide, all customers, no dates.
     */
    private function findPanelSpecificPrice(int $idProduct): ?SpecificPrice
    {
        $ids = $this->panelSpecificPriceIds($idProduct);

        return $ids ? new SpecificPrice($ids[0]) : null;
    }

    /**
     * @return int[]
     */
    private function panelSpecificPriceIds(int $idProduct): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT id_specific_price FROM `' . _DB_PREFIX_ . 'specific_price`'
            . ' WHERE id_product = ' . (int) $idProduct
            . ' AND id_product_attribute = 0 AND id_cart = 0 AND id_specific_price_rule = 0'
            . ' AND id_customer = 0 AND id_group = 0 AND id_country = 0 AND id_currency = 0'
            . ' AND from_quantity = 1'
            . ' ORDER BY id_specific_price'
        ) ?: [];

        return array_map('intval', array_column($rows, 'id_specific_price'));
    }

    private function syncOffer(int $idProduct, float $priceIncl, ?float $offerIncl): void
    {
        foreach ($this->panelSpecificPriceIds($idProduct) as $id) {
            (new SpecificPrice($id))->delete();
        }
        if ($offerIncl === null) {
            return;
        }

        $specificPrice = new SpecificPrice();
        $specificPrice->id_product = $idProduct;
        $specificPrice->id_product_attribute = 0;
        $specificPrice->id_shop = 0;
        $specificPrice->id_shop_group = 0;
        $specificPrice->id_cart = 0;
        $specificPrice->id_currency = 0;
        $specificPrice->id_country = 0;
        $specificPrice->id_group = 0;
        $specificPrice->id_customer = 0;
        $specificPrice->id_specific_price_rule = 0;
        $specificPrice->from_quantity = 1;
        $specificPrice->price = -1;
        $specificPrice->reduction_type = 'amount';
        $specificPrice->reduction_tax = 1;
        $specificPrice->reduction = round($priceIncl - $offerIncl, 6);
        $specificPrice->from = '0000-00-00 00:00:00';
        $specificPrice->to = '0000-00-00 00:00:00';
        $specificPrice->add();
    }

    // --- Photos --------------------------------------------------------------

    /**
     * Normalizes $_FILES['photos'] (multiple input) into a flat list, skipping empty slots.
     */
    public static function normalizeUploads(?array $field): array
    {
        if (!$field || !isset($field['name']) || !is_array($field['name'])) {
            return [];
        }
        $files = [];
        foreach ($field['name'] as $i => $name) {
            if ((int) $field['error'][$i] === UPLOAD_ERR_NO_FILE || $name === '') {
                continue;
            }
            $files[] = [
                'name' => (string) $name,
                'type' => (string) $field['type'][$i],
                'tmp_name' => (string) $field['tmp_name'][$i],
                'error' => (int) $field['error'][$i],
                'size' => (int) $field['size'][$i],
            ];
        }

        return $files;
    }

    private function validatePhoto(array $file): ?string
    {
        $translator = $this->context->getTranslator();
        $name = $file['name'];
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || $file['size'] > self::MAX_PHOTO_BYTES) {
            return $translator->trans('La foto %name% pesa más de 5 MB.', ['%name%' => $name], 'Modules.Nfpanel.Admin');
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return $translator->trans('La foto %name% no se pudo subir. Inténtalo de nuevo.', ['%name%' => $name], 'Modules.Nfpanel.Admin');
        }
        if (ImageManager::validateUpload($file, self::MAX_PHOTO_BYTES, self::PHOTO_EXTENSIONS, self::PHOTO_MIME_TYPES)) {
            return $translator->trans('La foto %name% debe ser JPG, PNG o WEBP.', ['%name%' => $name], 'Modules.Nfpanel.Admin');
        }
        if (!ImageManager::checkImageMemoryLimit($file['tmp_name'])) {
            return $translator->trans('La foto %name% es demasiado grande en píxeles. Usa una foto más pequeña.', ['%name%' => $name], 'Modules.Nfpanel.Admin');
        }

        return null;
    }

    /**
     * Same flow as the core product image upload: Image object, original file,
     * then one resized file per product image type (and extra formats if enabled).
     */
    private function addPhoto(Product $product, array $file): bool
    {
        $idProduct = (int) $product->id;
        $image = new Image();
        $image->id_product = $idProduct;
        $image->position = Image::getHighestPosition($idProduct) + 1;
        $image->cover = Image::getCover($idProduct) ? false : true;
        foreach (Language::getLanguages(false) as $language) {
            $legend = is_array($product->name) ? ($product->name[(int) $language['id_lang']] ?? '') : (string) $product->name;
            $image->legend[(int) $language['id_lang']] = Tools::substr($legend, 0, 128);
        }
        if (!$image->add()) {
            return false;
        }

        $tmpFile = tempnam(_PS_TMP_IMG_DIR_, 'nfp');
        $ok = $tmpFile && move_uploaded_file($file['tmp_name'], $tmpFile);
        $basePath = $ok ? $image->getPathForCreation() : '';
        if ($ok) {
            $ok = ImageManager::resize($tmpFile, $basePath . '.jpg');
        }
        if ($ok) {
            $formats = $this->generationFormats();
            $highDpi = (bool) Configuration::get('PS_HIGHT_DPI');
            foreach (ImageType::getImagesTypes('products') as $type) {
                foreach ($formats as $format) {
                    $force = $format !== 'jpg';
                    $target = $basePath . '-' . stripslashes($type['name']);
                    $ok = ImageManager::resize($basePath . '.jpg', $target . '.' . $format, (int) $type['width'], (int) $type['height'], $format, $force) && $ok;
                    if ($highDpi) {
                        $ok = ImageManager::resize($basePath . '.jpg', $target . '2x.' . $format, (int) $type['width'] * 2, (int) $type['height'] * 2, $format, $force) && $ok;
                    }
                }
            }
        }
        if ($tmpFile && is_file($tmpFile)) {
            @unlink($tmpFile);
        }
        if (!$ok) {
            $image->delete();

            return false;
        }

        Hook::exec('actionWatermark', ['id_image' => (int) $image->id, 'id_product' => $idProduct]);
        $this->clearCachedThumbnails($idProduct);

        return true;
    }

    /**
     * @return string[] extensions to generate for each image type
     */
    private function generationFormats(): array
    {
        $formats = ['jpg'];
        $configured = array_filter(array_map('trim', explode(',', (string) Configuration::get('PS_IMAGE_FORMAT'))));
        foreach ($configured as $format) {
            if (in_array($format, ['webp', 'avif'], true)) {
                $formats[] = $format;
            }
        }

        return array_values(array_unique($formats));
    }

    private function deleteImages(int $idProduct, array $imageIds): void
    {
        foreach (array_unique($imageIds) as $idImage) {
            $image = new Image($idImage);
            if (Validate::isLoadedObject($image) && (int) $image->id_product === $idProduct) {
                $image->delete();
            }
        }
        $this->clearCachedThumbnails($idProduct);
    }

    /**
     * Sets the chosen image as cover, or the first image when there is no valid cover.
     */
    private function applyCover(int $idProduct, int $chosenImageId): void
    {
        $images = Image::getImages($this->idLang, $idProduct);
        if (!$images) {
            return;
        }
        $ids = array_map('intval', array_column($images, 'id_image'));
        $currentCover = Image::getCover($idProduct);
        $target = in_array($chosenImageId, $ids, true) ? $chosenImageId : 0;
        if (!$target) {
            if ($currentCover && in_array((int) $currentCover['id_image'], $ids, true)) {
                return;
            }
            $target = $ids[0];
        }
        if ($currentCover && (int) $currentCover['id_image'] === $target) {
            return;
        }

        Image::deleteCover($idProduct);
        $image = new Image($target);
        $image->cover = true;
        $image->update();
        $this->clearCachedThumbnails($idProduct);
    }

    private function clearCachedThumbnails(int $idProduct): void
    {
        foreach (['product_' . $idProduct . '.jpg', 'product_mini_' . $idProduct . '_' . $this->idShop . '.jpg'] as $cached) {
            if (is_file(_PS_TMP_IMG_DIR_ . $cached)) {
                @unlink(_PS_TMP_IMG_DIR_ . $cached);
            }
        }
    }

    private function htmlToPlain(string $html): string
    {
        $text = preg_replace(['#<br\s*/?>\r?\n?#i', '#</p>\s*<p[^>]*>#i'], ["\n", "\n\n"], $html);

        return trim(html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
