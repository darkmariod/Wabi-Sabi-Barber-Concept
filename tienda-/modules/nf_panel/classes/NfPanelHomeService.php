<?php
/**
 * "Portada" section of Mi Tienda: the home banners (module ps_imageslider) and the products
 * shown in "Promociones Exclusivas" (products in the Home category, ps_featuredproducts).
 * Uses the slider's own model (Ps_HomeSlide) so positions and image files stay consistent.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class NfPanelHomeService
{
    const MAX_BANNER_BYTES = 5242880; // 5 MB
    const BANNER_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    const SLIDER_DIR = 'ps_imageslider/images/';

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
        require_once _PS_MODULE_DIR_ . 'ps_imageslider/Ps_HomeSlide.php';
    }

    // --- Banners ---------------------------------------------------------------

    /**
     * @return array<int, array{id: int, title: string, url: string, image_url: string, active: bool, first: bool, last: bool}>
     */
    public function listBanners(): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT s.id_homeslider_slides AS id, s.active, s.position, l.title, l.url, l.image'
            . ' FROM `' . _DB_PREFIX_ . 'homeslider_slides` s'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'homeslider` hs ON hs.id_homeslider_slides = s.id_homeslider_slides AND hs.id_shop = ' . $this->idShop
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'homeslider_slides_lang` l ON l.id_homeslider_slides = s.id_homeslider_slides AND l.id_lang = ' . $this->idLang
            . ' ORDER BY s.position ASC, s.id_homeslider_slides ASC'
        ) ?: [];

        $banners = [];
        $last = count($rows) - 1;
        foreach ($rows as $i => $row) {
            $banners[] = [
                'id' => (int) $row['id'],
                'title' => (string) $row['title'],
                'url' => (string) $row['url'],
                'image_url' => $row['image'] ? _MODULE_DIR_ . self::SLIDER_DIR . $row['image'] : '',
                'active' => (bool) $row['active'],
                'first' => $i === 0,
                'last' => $i === $last,
            ];
        }

        return $banners;
    }

    /**
     * @param array|null $file one entry of $_FILES
     * @param string[] $errors
     */
    public function addBanner(?array $file, string $title, string $url, array &$errors): bool
    {
        if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Elige la imagen del banner.';

            return false;
        }
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::BANNER_EXTENSIONS, true)) {
            $errors[] = 'La imagen debe ser JPG, PNG o WEBP.';

            return false;
        }
        if ((int) $file['size'] > self::MAX_BANNER_BYTES) {
            $errors[] = 'La imagen pesa más de 5 MB. Redúcela y vuelve a subirla.';

            return false;
        }
        $uploadError = ImageManager::validateUpload($file, self::MAX_BANNER_BYTES);
        if ($uploadError) {
            $errors[] = $uploadError;

            return false;
        }
        $url = trim($url);
        if ($url !== '' && !Validate::isUrl($url)) {
            $errors[] = 'El enlace no es válido. Pega la dirección completa o déjalo vacío.';

            return false;
        }
        $title = trim(strip_tags($title));

        $filename = 'nf-' . date('YmdHis') . '-' . Tools::passwdGen(6, 'NUMERIC') . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], _PS_MODULE_DIR_ . self::SLIDER_DIR . $filename)) {
            $errors[] = 'No se pudo guardar la imagen. Inténtalo de nuevo.';

            return false;
        }

        $slide = new Ps_HomeSlide();
        $slide->active = true;
        $slide->position = (int) Db::getInstance()->getValue(
            'SELECT IFNULL(MAX(s.position), -1) + 1 FROM `' . _DB_PREFIX_ . 'homeslider_slides` s'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'homeslider` hs ON hs.id_homeslider_slides = s.id_homeslider_slides AND hs.id_shop = ' . $this->idShop
        );
        foreach (Language::getIDs(false) as $lang) {
            $slide->title[$lang] = $title;
            $slide->legend[$lang] = $title;
            $slide->description[$lang] = '';
            $slide->url[$lang] = $url;
            $slide->image[$lang] = $filename;
        }
        if (!$slide->add()) {
            @unlink(_PS_MODULE_DIR_ . self::SLIDER_DIR . $filename);
            $errors[] = 'No se pudo crear el banner.';

            return false;
        }
        $this->clearHomeCache();

        return true;
    }

    public function toggleBanner(int $id): bool
    {
        $slide = $this->loadSlide($id);
        if (!$slide) {
            return false;
        }
        $slide->active = !$slide->active;
        $ok = (bool) $slide->update();
        $this->clearHomeCache();

        return $ok;
    }

    public function deleteBanner(int $id): bool
    {
        $slide = $this->loadSlide($id);
        if (!$slide) {
            return false;
        }
        $ok = (bool) $slide->delete();
        $this->clearHomeCache();

        return $ok;
    }

    /**
     * Swaps the banner with its neighbour ($direction -1 = up, +1 = down).
     */
    public function moveBanner(int $id, int $direction): bool
    {
        $ids = array_column($this->listBanners(), 'id');
        $index = array_search($id, $ids, true);
        $target = $index === false ? false : $index + ($direction < 0 ? -1 : 1);
        if ($index === false || $target < 0 || $target >= count($ids)) {
            return false;
        }
        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
        foreach ($ids as $position => $slideId) {
            Db::getInstance()->update('homeslider_slides', ['position' => (int) $position], 'id_homeslider_slides = ' . (int) $slideId);
        }
        $this->clearHomeCache();

        return true;
    }

    private function loadSlide(int $id): ?Ps_HomeSlide
    {
        $linked = (int) Db::getInstance()->getValue(
            'SELECT id_homeslider_slides FROM `' . _DB_PREFIX_ . 'homeslider` WHERE id_shop = ' . $this->idShop . ' AND id_homeslider_slides = ' . $id
        );
        if (!$linked) {
            return null;
        }
        $slide = new Ps_HomeSlide($id);

        return Validate::isLoadedObject($slide) ? $slide : null;
    }

    // --- Home promotions -------------------------------------------------------

    /**
     * Products shown in "Promociones Exclusivas" (Home category), with their offer.
     *
     * @return array<int, array{id: int, name: string, image_url: string, price: string, offer: string, active: bool}>
     */
    public function listHomePromos(): array
    {
        $homeId = (int) Configuration::get('PS_HOME_CATEGORY');
        $rows = Db::getInstance()->executeS(
            'SELECT p.id_product, pl.name, pl.link_rewrite, ps.active, i.id_image'
            . ' FROM `' . _DB_PREFIX_ . 'category_product` cp'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'product` p ON p.id_product = cp.id_product'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON ps.id_product = p.id_product AND ps.id_shop = ' . $this->idShop
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.id_product = p.id_product AND pl.id_lang = ' . $this->idLang . ' AND pl.id_shop = ' . $this->idShop
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'image_shop` i ON i.id_product = p.id_product AND i.id_shop = ' . $this->idShop . ' AND i.cover = 1'
            . ' WHERE cp.id_category = ' . $homeId
            // Only what customers can see: the old hidden Canva import stays out of the list.
            . ' AND ps.active = 1 AND p.reference NOT LIKE "CANVA-%"'
            . ' ORDER BY cp.position ASC, p.id_product ASC'
        ) ?: [];

        $promos = [];
        foreach ($rows as $row) {
            $id = (int) $row['id_product'];
            $regular = Product::getPriceStatic($id, true, null, 2, null, false, false);
            $final = Product::getPriceStatic($id, true, null, 2);
            $promos[] = [
                'id' => $id,
                'name' => (string) $row['name'],
                'image_url' => $row['id_image'] ? $this->context->link->getImageLink((string) $row['link_rewrite'], $id . '-' . (int) $row['id_image'], 'small_default') : '',
                'price' => Tools::displayPrice($regular),
                'offer' => $final < $regular ? Tools::displayPrice($final) : '',
                'active' => (bool) $row['active'],
            ];
        }

        return $promos;
    }

    public function removeFromHome(int $idProduct): bool
    {
        $product = new Product($idProduct);
        if (!Validate::isLoadedObject($product)) {
            return false;
        }
        $ok = (bool) $product->deleteCategory((int) Configuration::get('PS_HOME_CATEGORY'));
        $this->clearHomeCache();

        return $ok;
    }

    private function clearHomeCache(): void
    {
        Tools::clearSmartyCache();
    }
}
