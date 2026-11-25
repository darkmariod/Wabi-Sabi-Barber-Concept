<?php
/**
 * "Catálogos" section of Mi Tienda: the owner creates, renames, hides and deletes product
 * categories and copies their store link. A new top-level catalog is added to the main
 * menu (ps_mainmenu); subcatalogs show automatically under their parent. Sidebar filters
 * (ps_facetedsearch) are refreshed so new catalog pages get them too.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class NfPanelCategoryService
{
    const MAX_NAME = 64;

    /** @var Context */
    private $context;
    /** @var int */
    private $idLang;
    /** @var int */
    private $homeId;

    public function __construct(Context $context)
    {
        $this->context = $context;
        $this->idLang = (int) $context->language->id;
        $this->homeId = (int) Configuration::get('PS_HOME_CATEGORY');
    }

    /**
     * Catalog tree under Home, parents first.
     *
     * @return array<int, array{id: int, name: string, level: int, parent: int, active: bool, products: int, children: int, url: string}>
     */
    public function listCatalogs(): array
    {
        $home = new Category($this->homeId);
        $rows = Db::getInstance()->executeS(
            'SELECT c.id_category, c.id_parent, c.level_depth, c.active, cl.name, cl.link_rewrite,'
            . ' (SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'category_product` cp'
            . '   INNER JOIN `' . _DB_PREFIX_ . 'product` p ON p.id_product = cp.id_product AND p.reference NOT LIKE "CANVA-%"'
            . '   WHERE cp.id_category = c.id_category) AS products,'
            . ' (SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'category` ch WHERE ch.id_parent = c.id_category) AS children'
            . ' FROM `' . _DB_PREFIX_ . 'category` c'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.id_category = c.id_category AND cl.id_lang = ' . $this->idLang . ' AND cl.id_shop = ' . (int) $this->context->shop->id
            . ' WHERE c.nleft > ' . (int) $home->nleft . ' AND c.nright < ' . (int) $home->nright
            . ' ORDER BY c.nleft'
        ) ?: [];

        $catalogs = [];
        foreach ($rows as $row) {
            $id = (int) $row['id_category'];
            $catalogs[] = [
                'id' => $id,
                'name' => (string) $row['name'],
                'level' => max(0, (int) $row['level_depth'] - (int) $home->level_depth - 1),
                'parent' => (int) $row['id_parent'],
                'active' => (bool) $row['active'],
                'products' => (int) $row['products'],
                'children' => (int) $row['children'],
                'url' => $this->context->link->getCategoryLink($id, (string) $row['link_rewrite']),
            ];
        }

        return $catalogs;
    }

    /**
     * Top-level catalogs, for the "Dentro de" selector.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function parentOptions(): array
    {
        return array_values(array_map(function ($c) {
            return ['id' => $c['id'], 'name' => $c['name']];
        }, array_filter($this->listCatalogs(), function ($c) {
            return $c['level'] === 0;
        })));
    }

    /**
     * @param string[] $errors
     */
    public function create(string $name, int $parentId, array &$errors): int
    {
        $name = $this->cleanName($name, $errors);
        if ($name === '') {
            return 0;
        }
        if ($parentId && !$this->isTopLevel($parentId)) {
            $errors[] = 'Elige una sección principal válida.';

            return 0;
        }
        $parent = $parentId ?: $this->homeId;
        if ($this->nameExists($name, $parent)) {
            $errors[] = 'Ya existe un catálogo con ese nombre en ese lugar.';

            return 0;
        }

        $category = new Category();
        $category->id_parent = $parent;
        $category->active = true;
        foreach (Language::getIDs(false) as $lang) {
            $category->name[$lang] = $name;
            $category->link_rewrite[$lang] = Tools::str2url($name);
        }
        if (!$category->add()) {
            $errors[] = 'No se pudo crear el catálogo.';

            return 0;
        }
        if ($parent === $this->homeId) {
            $this->setInMenu((int) $category->id, true);
        }
        $this->afterChange();

        return (int) $category->id;
    }

    /**
     * @param string[] $errors
     */
    public function rename(int $id, string $name, array &$errors): bool
    {
        $category = $this->load($id);
        $name = $this->cleanName($name, $errors);
        if (!$category || $name === '') {
            return false;
        }
        if ($this->nameExists($name, (int) $category->id_parent, $id)) {
            $errors[] = 'Ya existe un catálogo con ese nombre en ese lugar.';

            return false;
        }
        foreach (Language::getIDs(false) as $lang) {
            $category->name[$lang] = $name;
            $category->link_rewrite[$lang] = Tools::str2url($name);
        }
        $ok = (bool) $category->update();
        $this->afterChange();

        return $ok;
    }

    public function toggle(int $id): bool
    {
        $category = $this->load($id);
        if (!$category) {
            return false;
        }
        $category->active = !$category->active;
        $ok = (bool) $category->update();
        $this->afterChange();

        return $ok;
    }

    /**
     * Only empty catalogs can be deleted (no products, no subcatalogs), so nothing is lost.
     *
     * @param string[] $errors
     */
    public function delete(int $id, array &$errors): bool
    {
        $category = $this->load($id);
        if (!$category) {
            return false;
        }
        foreach ($this->listCatalogs() as $c) {
            if ($c['id'] === $id && ($c['products'] > 0 || $c['children'] > 0)) {
                $errors[] = 'Este catálogo tiene productos o subcatálogos. Muévelos a otro catálogo u oculta este en su lugar.';

                return false;
            }
        }
        $this->setInMenu($id, false);
        $ok = (bool) $category->delete();
        $this->afterChange();

        return $ok;
    }

    private function load(int $id): ?Category
    {
        if ($id === $this->homeId) {
            return null;
        }
        $category = new Category($id);
        $home = new Category($this->homeId);

        return Validate::isLoadedObject($category) && $category->nleft > $home->nleft && $category->nright < $home->nright ? $category : null;
    }

    private function isTopLevel(int $id): bool
    {
        return (int) Db::getInstance()->getValue('SELECT id_parent FROM `' . _DB_PREFIX_ . 'category` WHERE id_category = ' . $id) === $this->homeId;
    }

    private function nameExists(string $name, int $parent, int $exceptId = 0): bool
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT c.id_category FROM `' . _DB_PREFIX_ . 'category` c'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.id_category = c.id_category AND cl.id_lang = ' . $this->idLang
            . ' WHERE c.id_parent = ' . $parent . ' AND c.id_category <> ' . $exceptId . ' AND cl.name = \'' . pSQL($name) . '\''
        );
    }

    /**
     * @param string[] $errors
     */
    private function cleanName(string $name, array &$errors): string
    {
        $name = trim(preg_replace('/\s+/', ' ', strip_tags($name)));
        if ($name === '') {
            $errors[] = 'Escribe el nombre del catálogo.';

            return '';
        }
        if (Tools::strlen($name) > self::MAX_NAME || !Validate::isCatalogName($name)) {
            $errors[] = 'El nombre es muy largo o tiene caracteres no permitidos (< > ; = # { }).';

            return '';
        }

        return $name;
    }

    /** Adds or removes "CAT<id>" in the main menu items. */
    private function setInMenu(int $id, bool $present): void
    {
        $items = array_values(array_filter(explode(',', (string) Configuration::get('MOD_BLOCKTOPMENU_ITEMS'))));
        $key = 'CAT' . $id;
        $has = in_array($key, $items, true);
        if ($present && !$has) {
            $items[] = $key;
        } elseif (!$present && $has) {
            $items = array_values(array_diff($items, [$key]));
        } else {
            return;
        }
        Configuration::updateValue('MOD_BLOCKTOPMENU_ITEMS', implode(',', $items));
    }

    private function afterChange(): void
    {
        $this->refreshFilters();
        $menu = Module::getInstanceByName('ps_mainmenu');
        if ($menu && method_exists($menu, 'clearMenuCache')) {
            // clearMenuCache() is protected in ps_mainmenu; call it in the module's scope.
            \Closure::bind(function () {
                $this->clearMenuCache();
            }, $menu, get_class($menu))();
        }
        Tools::clearSmartyCache();
    }

    /** Keeps the sidebar filters (categorías, precio, marca) on every active catalog page. */
    private function refreshFilters(): void
    {
        $faceted = Module::getInstanceByName('ps_facetedsearch');
        if (!$faceted || !$faceted->active) {
            return;
        }
        $db = Db::getInstance();
        $template = $db->getRow('SELECT id_layered_filter, filters FROM `' . _DB_PREFIX_ . 'layered_filter` ORDER BY id_layered_filter');
        if (!$template) {
            return;
        }
        $filters = Tools::unSerialize($template['filters']);
        if (!is_array($filters)) {
            return;
        }
        $filters['categories'] = array_map('intval', array_column($db->executeS(
            'SELECT id_category FROM `' . _DB_PREFIX_ . 'category` WHERE active = 1 AND id_parent > 0'
        ) ?: [], 'id_category'));
        $db->update('layered_filter', [
            'filters' => pSQL(serialize($filters)),
            'n_categories' => count($filters['categories']),
        ], 'id_layered_filter = ' . (int) $template['id_layered_filter']);
        $faceted->buildLayeredCategories();
    }
}
