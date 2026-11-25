<?php
/**
 * Promo cards: images with a link that the owner shows under the home carousel
 * (e.g. "2x1 en creatinas"). Managed in Mi Tienda > Portada; shown by the theme's index.tpl
 * through $modules.nf_panel.promo_cards (Nf_Panel::hookActionFrontControllerSetVariables).
 * Images live in img/nf_promos/ so they survive module updates.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class NfPanelPromoCardService
{
    const TABLE = 'nf_promo_card';
    const IMAGE_SUBDIR = 'nf_promos/';
    const MAX_BYTES = 5242880; // 5 MB
    const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public static function ensureTable(): bool
    {
        return (bool) Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE . '` (
                `id_nf_promo_card` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL,
                `image` VARCHAR(128) NOT NULL,
                `title` VARCHAR(120) NOT NULL DEFAULT \'\',
                `url` VARCHAR(255) NOT NULL DEFAULT \'\',
                `position` INT UNSIGNED NOT NULL DEFAULT 0,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (`id_nf_promo_card`),
                KEY `shop_position` (`id_shop`, `position`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4'
        );
    }

    /** @var int */
    private $idShop;

    /**
     * @param bool $ensureTable false on storefront requests (no DDL on every page view)
     */
    public function __construct(Context $context, bool $ensureTable = true)
    {
        $this->idShop = (int) $context->shop->id;
        if ($ensureTable) {
            self::ensureTable();
        }
    }

    /**
     * @return array<int, array{id: int, title: string, url: string, image_url: string, active: bool, first: bool, last: bool}>
     */
    public function listCards(bool $activeOnly = false): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE id_shop = ' . $this->idShop
            . ($activeOnly ? ' AND active = 1' : '') . ' ORDER BY position ASC, id_nf_promo_card ASC'
        ) ?: [];
        $cards = [];
        $last = count($rows) - 1;
        foreach ($rows as $i => $row) {
            $cards[] = [
                'id' => (int) $row['id_nf_promo_card'],
                'title' => (string) $row['title'],
                'url' => (string) $row['url'],
                'image_url' => _PS_IMG_ . self::IMAGE_SUBDIR . $row['image'],
                'active' => (bool) $row['active'],
                'first' => $i === 0,
                'last' => $i === $last,
            ];
        }

        return $cards;
    }

    /**
     * @param string[] $errors
     */
    public function addCard(?array $file, string $title, string $url, array &$errors): bool
    {
        if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Elige la imagen de la promoción.';

            return false;
        }
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::EXTENSIONS, true)) {
            $errors[] = 'La imagen debe ser JPG, PNG o WEBP.';

            return false;
        }
        if ((int) $file['size'] > self::MAX_BYTES) {
            $errors[] = 'La imagen pesa más de 5 MB. Redúcela y vuelve a subirla.';

            return false;
        }
        $uploadError = ImageManager::validateUpload($file, self::MAX_BYTES);
        if ($uploadError) {
            $errors[] = $uploadError;

            return false;
        }
        $url = trim($url);
        if ($url !== '' && !Validate::isUrl($url)) {
            $errors[] = 'El enlace no es válido. Pega la dirección completa o déjalo vacío.';

            return false;
        }

        $dir = _PS_IMG_DIR_ . self::IMAGE_SUBDIR;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            $errors[] = 'No se pudo crear la carpeta de imágenes.';

            return false;
        }
        $filename = 'promo-' . date('YmdHis') . '-' . Tools::passwdGen(6, 'NUMERIC') . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
            $errors[] = 'No se pudo guardar la imagen. Inténtalo de nuevo.';

            return false;
        }

        $position = (int) Db::getInstance()->getValue(
            'SELECT IFNULL(MAX(position), -1) + 1 FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE id_shop = ' . $this->idShop
        );
        $ok = Db::getInstance()->insert(self::TABLE, [
            'id_shop' => $this->idShop,
            'image' => pSQL($filename),
            'title' => pSQL(Tools::substr(trim(strip_tags($title)), 0, 120)),
            'url' => pSQL($url),
            'position' => $position,
            'active' => 1,
        ]);
        if (!$ok) {
            @unlink($dir . $filename);
            $errors[] = 'No se pudo guardar la promoción.';

            return false;
        }
        Tools::clearSmartyCache();

        return true;
    }

    public function toggleCard(int $id): bool
    {
        $ok = Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . self::TABLE . '` SET active = 1 - active WHERE id_nf_promo_card = ' . $id . ' AND id_shop = ' . $this->idShop
        ) && Db::getInstance()->Affected_Rows() > 0;
        Tools::clearSmartyCache();

        return $ok;
    }

    public function deleteCard(int $id): bool
    {
        $image = (string) Db::getInstance()->getValue(
            'SELECT image FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE id_nf_promo_card = ' . $id . ' AND id_shop = ' . $this->idShop
        );
        if ($image === '') {
            return false;
        }
        Db::getInstance()->delete(self::TABLE, 'id_nf_promo_card = ' . $id . ' AND id_shop = ' . $this->idShop);
        $path = _PS_IMG_DIR_ . self::IMAGE_SUBDIR . basename($image);
        if (is_file($path)) {
            @unlink($path);
        }
        $this->renumber(array_column($this->listCards(), 'id'));
        Tools::clearSmartyCache();

        return true;
    }

    /**
     * Swaps the card with its neighbour ($direction -1 = up, +1 = down).
     */
    public function moveCard(int $id, int $direction): bool
    {
        $ids = array_column($this->listCards(), 'id');
        $index = array_search($id, $ids, true);
        $target = $index === false ? false : $index + ($direction < 0 ? -1 : 1);
        if ($index === false || $target < 0 || $target >= count($ids)) {
            return false;
        }
        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
        $this->renumber($ids);
        Tools::clearSmartyCache();

        return true;
    }

    private function renumber(array $ids): void
    {
        foreach (array_values($ids) as $position => $cardId) {
            Db::getInstance()->update(self::TABLE, ['position' => (int) $position], 'id_nf_promo_card = ' . (int) $cardId);
        }
    }
}
