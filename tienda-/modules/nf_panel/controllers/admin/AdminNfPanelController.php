<?php
/**
 * "Mi Tienda" back-office page: products (list + form), catalogs (categories), orders
 * (cards + state buttons) and home page (banners + home promotions).
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdminNfPanelController extends ModuleAdminController
{
    const SECTIONS = ['products', 'catalogs', 'orders', 'home'];
    const MESSAGES = [
        'saved' => 'Producto guardado.',
        'hidden' => 'El producto ya no se muestra en la tienda.',
        'shown' => 'El producto ya se muestra en la tienda.',
        'deleted' => 'Producto eliminado.',
        'order' => 'Pedido actualizado.',
        'order_nomail' => 'Pedido actualizado, pero no se pudo enviar el correo al cliente. Avísale por WhatsApp.',
        'banner_added' => 'Banner agregado a la portada.',
        'banner_updated' => 'Banner actualizado.',
        'banner_deleted' => 'Banner eliminado.',
        'promo_removed' => 'El producto ya no sale en Promociones de la portada.',
        'catalog_created' => 'Catálogo creado. Ya aparece en la tienda.',
        'catalog_updated' => 'Catálogo actualizado.',
        'catalog_deleted' => 'Catálogo eliminado.',
    ];

    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
        $this->meta_title = [$this->trans('Mi Tienda', [], 'Modules.Nfpanel.Admin')];
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->addCSS(_MODULE_DIR_ . $this->module->name . '/views/css/panel.css');
        $this->addJS(_MODULE_DIR_ . $this->module->name . '/views/js/panel.js');
    }

    public function initPageHeaderToolbar()
    {
        $this->page_header_toolbar_title = $this->trans('Mi Tienda', [], 'Modules.Nfpanel.Admin');
        parent::initPageHeaderToolbar();
    }

    public function initToolbar()
    {
        $this->toolbar_btn = [];
    }

    public function postProcess()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return parent::postProcess();
        }

        if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->errors[] = $this->trans('Las fotos pesan demasiado en total. Sube menos fotos a la vez.', [], 'Modules.Nfpanel.Admin');

            return false;
        }

        $action = (string) Tools::getValue('nf_action');
        if ($action === '') {
            return parent::postProcess();
        }
        if (!$this->checkToken()) {
            $this->errors[] = $this->trans('La sesión expiró. Recarga la página e inténtalo de nuevo.', [], 'Modules.Nfpanel.Admin');

            return false;
        }

        switch ($action) {
            case 'save_product':
                return $this->processSaveProduct();
            case 'toggle_product':
                return $this->processProductAction('toggle');
            case 'delete_product':
                return $this->processProductAction('delete');
            case 'order_state':
                return $this->processOrderState();
            case 'add_banner':
            case 'toggle_banner':
            case 'move_banner':
            case 'delete_banner':
            case 'remove_promo':
                return $this->processHome($action);
            case 'create_catalog':
            case 'rename_catalog':
            case 'toggle_catalog':
            case 'delete_catalog':
                return $this->processCatalog($action);
        }

        return false;
    }

    private function processSaveProduct()
    {
        $this->requirePermission('edit');
        if ($this->errors) {
            return false;
        }
        $service = new NfPanelProductService($this->context);
        $files = NfPanelProductService::normalizeUploads($_FILES['photos'] ?? null);
        $errors = [];
        $id = $service->save($_POST, $files, $errors);
        if (!$id) {
            $this->errors = array_merge($this->errors, $errors);

            return false;
        }
        $this->redirectToPanel(['section' => 'products', 'msg' => 'saved']);
    }

    private function processProductAction(string $kind)
    {
        $this->requirePermission($kind === 'delete' ? 'delete' : 'edit');
        if ($this->errors) {
            return false;
        }
        $service = new NfPanelProductService($this->context);
        $id = (int) Tools::getValue('id_product');
        $params = ['section' => 'products', 'q' => (string) Tools::getValue('q'), 'p' => (int) Tools::getValue('p')];

        if ($kind === 'delete') {
            if (!$service->delete($id)) {
                $this->errors[] = $this->trans('No se pudo eliminar el producto.', [], 'Modules.Nfpanel.Admin');

                return false;
            }
            $this->redirectToPanel($params + ['msg' => 'deleted']);
        }

        if (!$service->toggle($id)) {
            $this->errors[] = $this->trans('No se pudo cambiar la visibilidad del producto.', [], 'Modules.Nfpanel.Admin');

            return false;
        }
        $active = (bool) Db::getInstance()->getValue('SELECT active FROM `' . _DB_PREFIX_ . 'product_shop` WHERE id_product = ' . $id . ' AND id_shop = ' . (int) $this->context->shop->id);
        $this->redirectToPanel($params + ['msg' => $active ? 'shown' : 'hidden']);
    }

    private function processOrderState()
    {
        $this->requirePermission('edit');
        if ($this->errors) {
            return false;
        }
        $service = new NfPanelOrderService($this->context);
        $error = $service->changeState((int) Tools::getValue('id_order'), (string) Tools::getValue('state_action'));
        if ($error !== null) {
            $this->errors[] = $error;

            return false;
        }
        $this->redirectToPanel([
            'section' => 'orders',
            'filter' => (string) Tools::getValue('filter'),
            'p' => (int) Tools::getValue('p'),
            'msg' => $service->lastEmailFailed ? 'order_nomail' : 'order',
        ]);
    }

    private function processHome(string $action)
    {
        $this->requirePermission(in_array($action, ['delete_banner'], true) ? 'delete' : 'edit');
        if ($this->errors) {
            return false;
        }
        $service = new NfPanelHomeService($this->context);
        $id = (int) Tools::getValue('id');
        switch ($action) {
            case 'add_banner':
                $errors = [];
                if (!$service->addBanner($_FILES['banner'] ?? null, (string) Tools::getValue('title'), (string) Tools::getValue('url'), $errors)) {
                    $this->errors = array_merge($this->errors, $errors);

                    return false;
                }
                $this->redirectToPanel(['section' => 'home', 'msg' => 'banner_added']);
                // no break: redirect exits
            case 'toggle_banner':
                $ok = $service->toggleBanner($id);
                $msg = 'banner_updated';
                break;
            case 'move_banner':
                $ok = $service->moveBanner($id, (int) Tools::getValue('direction'));
                $msg = 'banner_updated';
                break;
            case 'delete_banner':
                $ok = $service->deleteBanner($id);
                $msg = 'banner_deleted';
                break;
            default: // remove_promo
                $ok = $service->removeFromHome($id);
                $msg = 'promo_removed';
        }
        if (!$ok) {
            $this->errors[] = $this->trans('No se pudo hacer el cambio. Recarga la página e inténtalo de nuevo.', [], 'Modules.Nfpanel.Admin');

            return false;
        }
        $this->redirectToPanel(['section' => 'home', 'msg' => $msg]);
    }

    private function processCatalog(string $action)
    {
        $this->requirePermission($action === 'delete_catalog' ? 'delete' : ($action === 'create_catalog' ? 'add' : 'edit'));
        if ($this->errors) {
            return false;
        }
        $service = new NfPanelCategoryService($this->context);
        $id = (int) Tools::getValue('id');
        $errors = [];
        switch ($action) {
            case 'create_catalog':
                $ok = $service->create((string) Tools::getValue('name'), (int) Tools::getValue('id_parent'), $errors) > 0;
                $msg = 'catalog_created';
                break;
            case 'rename_catalog':
                $ok = $service->rename($id, (string) Tools::getValue('name'), $errors);
                $msg = 'catalog_updated';
                break;
            case 'toggle_catalog':
                $ok = $service->toggle($id);
                $msg = 'catalog_updated';
                break;
            default: // delete_catalog
                $ok = $service->delete($id, $errors);
                $msg = 'catalog_deleted';
        }
        if (!$ok) {
            $this->errors = array_merge($this->errors, $errors ?: [$this->trans('No se pudo hacer el cambio. Recarga la página e inténtalo de nuevo.', [], 'Modules.Nfpanel.Admin')]);

            return false;
        }
        $this->redirectToPanel(['section' => 'catalogs', 'msg' => $msg]);
    }

    private function requirePermission(string $legacyAction): void
    {
        if (!$this->access($legacyAction)) {
            $this->errors[] = $this->trans('No tienes permiso para hacer esto.', [], 'Modules.Nfpanel.Admin');
        }
    }

    private function redirectToPanel(array $params): void
    {
        $params = array_filter($params, function ($value) {
            return $value !== '' && $value !== 0 && $value !== null;
        });
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminNfPanel') . '&' . http_build_query($params));
    }

    // --- Rendering -----------------------------------------------------------

    public function initContent()
    {
        parent::initContent();

        $section = (string) Tools::getValue('section');
        if (!in_array($section, self::SECTIONS, true)) {
            $section = 'products';
        }
        $msg = (string) Tools::getValue('msg');
        if (isset(self::MESSAGES[$msg]) && !$this->errors) {
            if ($msg === 'order_nomail') {
                $this->warnings[] = $this->trans(self::MESSAGES[$msg], [], 'Modules.Nfpanel.Admin');
            } else {
                $this->confirmations[] = $this->trans(self::MESSAGES[$msg], [], 'Modules.Nfpanel.Admin');
            }
        }

        $baseUrl = $this->context->link->getAdminLink('AdminNfPanel');
        $this->context->smarty->assign([
            'nf_base_url' => $baseUrl,
            'nf_token' => $this->token,
            'nf_section' => $section,
        ]);

        if ($section === 'orders') {
            $body = $this->renderOrders();
        } elseif ($section === 'home') {
            $body = $this->renderHome();
        } elseif ($section === 'catalogs') {
            $body = $this->renderCatalogs();
        } elseif (Tools::getIsset('edit') || ($this->errors && Tools::getValue('nf_action') === 'save_product')) {
            $body = $this->renderProductForm();
        } else {
            $body = $this->renderProductList();
        }

        $this->context->smarty->assign('nf_body', $body);
        $this->content .= $this->context->smarty->fetch($this->nfTemplate('panel.tpl'));
        $this->context->smarty->assign('content', $this->content);
    }

    /**
     * Module templates live in views/templates/admin.
     */
    private function nfTemplate(string $template): string
    {
        return _PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/' . $template;
    }

    private function renderProductList(): string
    {
        $service = new NfPanelProductService($this->context);
        $search = trim((string) Tools::getValue('q'));
        $search = Tools::substr($search, 0, 64);
        $result = $service->listProducts($search, (int) Tools::getValue('p', 1));
        $native = $this->canUseNative('AdminProducts');
        foreach ($result['items'] as &$item) {
            $item['native_url'] = $native ? $this->nativeProductUrl((int) $item['id']) : null;
        }
        unset($item);

        $this->context->smarty->assign([
            'nf_products' => $result['items'],
            'nf_total' => $result['total'],
            'nf_page' => $result['page'],
            'nf_pages' => $result['pages'],
            'nf_search' => $search,
        ]);

        return $this->context->smarty->fetch($this->nfTemplate('products_list.tpl'));
    }

    private function renderProductForm(): string
    {
        $service = new NfPanelProductService($this->context);
        $id = (int) Tools::getValue('id_product', Tools::getValue('edit'));
        $data = $service->getFormData($id);

        if ($this->errors && Tools::getValue('nf_action') === 'save_product') {
            // Re-display what the owner typed (photos must be selected again).
            foreach (['name', 'price', 'iva', 'offer', 'stock', 'description'] as $field) {
                $data[$field] = (string) Tools::getValue($field);
            }
            $data['id_category'] = (int) Tools::getValue('id_category');
            $data['id_manufacturer'] = (int) Tools::getValue('id_manufacturer');
            $data['show_home'] = (bool) Tools::getValue('show_home');
            $data['active'] = (bool) Tools::getValue('active');
        }

        $this->context->smarty->assign([
            'nf_product' => $data,
            'nf_categories' => $service->getCategoryOptions(),
            'nf_manufacturers' => $service->getManufacturerOptions(),
            'nf_iva_options' => ['15' => '15%', '0' => '0%'],
            'nf_max_photo_mb' => (int) (NfPanelProductService::MAX_PHOTO_BYTES / 1048576),
            'nf_native_product_url' => $id && $this->canUseNative('AdminProducts') ? $this->nativeProductUrl($id) : null,
        ]);

        return $this->context->smarty->fetch($this->nfTemplate('product_form.tpl'));
    }

    private function renderOrders(): string
    {
        $service = new NfPanelOrderService($this->context);
        $filter = (string) Tools::getValue('filter', 'pending');
        if (!in_array($filter, NfPanelOrderService::FILTERS, true)) {
            $filter = 'pending';
        }
        $result = $service->listOrders($filter, (int) Tools::getValue('p', 1));
        $native = $this->canUseNative('AdminOrders');
        foreach ($result['items'] as &$item) {
            $item['native_url'] = $native ? $this->context->link->getAdminLink('AdminOrders', true, ['id_order' => (int) $item['id'], 'vieworder' => 1]) : null;
        }
        unset($item);

        $this->context->smarty->assign([
            'nf_orders' => $result['items'],
            'nf_total' => $result['total'],
            'nf_page' => $result['page'],
            'nf_pages' => $result['pages'],
            'nf_filter' => $filter,
            'nf_filters' => [
                'pending' => $this->trans('Pendientes', [], 'Modules.Nfpanel.Admin'),
                'all' => $this->trans('Todos', [], 'Modules.Nfpanel.Admin'),
                'delivered' => $this->trans('Entregados', [], 'Modules.Nfpanel.Admin'),
                'cancelled' => $this->trans('Cancelados', [], 'Modules.Nfpanel.Admin'),
            ],
        ]);

        return $this->context->smarty->fetch($this->nfTemplate('orders.tpl'));
    }

    private function renderCatalogs(): string
    {
        $service = new NfPanelCategoryService($this->context);
        $this->context->smarty->assign([
            'nf_catalogs' => $service->listCatalogs(),
            'nf_parents' => $service->parentOptions(),
            'nf_form_name' => (string) Tools::getValue('name'),
        ]);

        return $this->context->smarty->fetch($this->nfTemplate('catalogs.tpl'));
    }

    private function renderHome(): string
    {
        $service = new NfPanelHomeService($this->context);
        $this->context->smarty->assign([
            'nf_banners' => $service->listBanners(),
            'nf_promos' => $service->listHomePromos(),
            'nf_max_banner_mb' => (int) (NfPanelHomeService::MAX_BANNER_BYTES / 1048576),
        ]);

        return $this->context->smarty->fetch($this->nfTemplate('home.tpl'));
    }

    private function nativeProductUrl(int $idProduct): string
    {
        return $this->context->link->getAdminLink('AdminProducts', true, ['id_product' => $idProduct, 'updateproduct' => 1]);
    }

    private function canUseNative(string $controller): bool
    {
        $employee = $this->context->employee;
        if (!$employee || !$employee->id_profile) {
            return false;
        }
        if ((int) $employee->id_profile === (int) _PS_ADMIN_PROFILE_) {
            return true;
        }

        return Access::isGranted('ROLE_MOD_TAB_' . strtoupper($controller) . '_READ', (int) $employee->id_profile);
    }
}
