<?php
/**
 * NatuFitness - "Mi Tienda" simplified back-office panel.
 *
 * Adds one top-level back-office page (AdminNfPanel) where the store owner can
 * manage products (with photos, offer price and stock) and move orders through
 * their fulfilment states. Also creates the "Dueño" employee profile, which only
 * has access to this page and lands on it after login.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/NfPanelProductService.php';
require_once __DIR__ . '/classes/NfPanelOrderService.php';
require_once __DIR__ . '/classes/NfPanelHomeService.php';
require_once __DIR__ . '/classes/NfPanelPromoCardService.php';
require_once __DIR__ . '/classes/NfPanelCategoryService.php';

class Nf_Panel extends Module
{
    const TAB_CLASS = 'AdminNfPanel';
    /** Core admin sections the "Dueño" profile may open, besides Mi Tienda. */
    const OWNER_CORE_TABS = [
        'SELL' => ['view'],
        'AdminParentOrders' => ['view'],
        'AdminOrders' => ['view'],
        'AdminParentCustomer' => ['view'],
        'AdminCustomers' => ['view'],
    ];
    /** Sections taken away from the owner later (revoked on setup-owner runs). */
    const OWNER_REVOKED_TABS = ['AdminParentCustomerThreads', 'AdminCustomerThreads'];
    const TAB_ICON = 'storefront';
    const TAB_NAME = 'Mi Tienda';
    const PROFILE_NAME = 'Dueño';
    const CONFIG_PROFILE_ID = 'NF_PANEL_OWNER_PROFILE';

    public function __construct()
    {
        $this->name = 'nf_panel';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'NatuFitness';
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Mi Tienda (panel simple)', [], 'Modules.Nfpanel.Admin');
        $this->description = $this->trans('Página única para que el dueño gestione productos y pedidos desde el celular.', [], 'Modules.Nfpanel.Admin');
    }

    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    public function install()
    {
        return parent::install()
            && self::ensureTab() > 0
            && self::ensureOwnerProfile() > 0
            && NfPanelPromoCardService::ensureTable()
            && $this->registerHook('actionFrontControllerSetVariables')
            && $this->registerHook('displayBackOfficeHeader');
    }

    public function uninstall()
    {
        // The tab is removed by the core module tab unregister (tabs are owned by module name).
        // The "Dueño" profile is kept on purpose: deleting it would leave its employees without a profile.
        return parent::uninstall();
    }

    /**
     * Opens the panel when "Configure" is clicked in the module manager.
     */
    public function getContent()
    {
        Tools::redirectAdmin($this->context->link->getAdminLink(self::TAB_CLASS));
    }

    /**
     * Creates (or repairs) the top-level "Mi Tienda" tab and places it first in the side menu.
     *
     * @return int tab id
     */
    public static function ensureTab(): int
    {
        $tabId = (int) Tab::getIdFromClassName(self::TAB_CLASS);
        $tab = new Tab($tabId ?: null);
        $tab->class_name = self::TAB_CLASS;
        $tab->module = 'nf_panel';
        // First link of the "Vender" section, so it shows in every profile's side menu.
        $sellId = (int) Tab::getIdFromClassName('SELL');
        $tab->id_parent = $sellId;
        $tab->icon = self::TAB_ICON;
        $tab->active = true;
        $tab->enabled = true;
        foreach (Language::getLanguages(false) as $language) {
            $tab->name[(int) $language['id_lang']] = self::TAB_NAME;
        }
        if (!$tab->save()) {
            return 0;
        }

        // Place it first among its siblings.
        $db = Db::getInstance();
        $position = (int) $db->getValue('SELECT position FROM `' . _DB_PREFIX_ . 'tab` WHERE id_tab = ' . (int) $tab->id);
        if ($position !== 0) {
            $db->execute('UPDATE `' . _DB_PREFIX_ . 'tab` SET position = position + 1 WHERE id_parent = ' . $sellId . ' AND id_tab <> ' . (int) $tab->id . ' AND position < ' . $position);
            $db->execute('UPDATE `' . _DB_PREFIX_ . 'tab` SET position = 0 WHERE id_tab = ' . (int) $tab->id);
        }

        return (int) $tab->id;
    }

    /**
     * Creates the "Dueño" profile if missing, grants it the Mi Tienda tab permissions
     * and sets Mi Tienda as the landing page of every employee with that profile.
     *
     * @return int profile id
     */
    public static function ensureOwnerProfile(): int
    {
        $tabId = (int) Tab::getIdFromClassName(self::TAB_CLASS);
        if (!$tabId) {
            return 0;
        }

        $profileId = self::findOwnerProfileId();
        if (!$profileId) {
            $profile = new Profile();
            foreach (Language::getLanguages(false) as $language) {
                $profile->name[(int) $language['id_lang']] = self::PROFILE_NAME;
            }
            if (!$profile->add()) {
                return 0;
            }
            $profileId = (int) $profile->id;
        }
        Configuration::updateGlobalValue(self::CONFIG_PROFILE_ID, $profileId);

        // Grant CRUD on the Mi Tienda tab (roles are created by Tab::add()).
        $access = new Access();
        foreach (['view', 'add', 'edit', 'delete'] as $action) {
            $access->updateLgcAccess($profileId, $tabId, $action, true, false);
        }
        // Plus the few core sections the owner uses (everything else stays hidden for him):
        // orders and customers, read-only.
        // Module "view" right: without it PrestaShop skips this module's back-office hooks for
        // the owner (the clean-header style in hookDisplayBackOfficeHeader). Adds no menu entry.
        $moduleId = (int) Module::getModuleIdByName('nf_panel');
        if ($moduleId) {
            $access->updateLgcModuleAccess($profileId, $moduleId, 'view', true);
        }
        foreach (self::OWNER_REVOKED_TABS as $className) {
            $revokedTabId = (int) Tab::getIdFromClassName($className);
            foreach (['view', 'add', 'edit', 'delete'] as $action) {
                if ($revokedTabId) {
                    $access->updateLgcAccess($profileId, $revokedTabId, $action, false, false);
                }
            }
        }
        foreach (self::OWNER_CORE_TABS as $className => $actions) {
            $coreTabId = (int) Tab::getIdFromClassName($className);
            foreach ($actions as $action) {
                if ($coreTabId) {
                    $access->updateLgcAccess($profileId, $coreTabId, $action, true, false);
                }
            }
        }

        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'employee` SET default_tab = ' . (int) $tabId . ' WHERE id_profile = ' . (int) $profileId
        );

        return $profileId;
    }

    public static function findOwnerProfileId(): int
    {
        $configured = (int) Configuration::getGlobalValue(self::CONFIG_PROFILE_ID);
        if ($configured && Validate::isLoadedObject(new Profile($configured))) {
            return $configured;
        }

        return (int) Db::getInstance()->getValue(
            'SELECT id_profile FROM `' . _DB_PREFIX_ . 'profile_lang` WHERE name = "' . pSQL(self::PROFILE_NAME) . '" ORDER BY id_profile'
        );
    }

    /**
     * Storefront variables ($modules.nf_panel.*): the active promo cards for the home page.
     */
    public function hookActionFrontControllerSetVariables(array $params)
    {
        try {
            $cards = (new NfPanelPromoCardService($this->context, false))->listCards(true);
        } catch (Throwable $e) {
            $cards = []; // table not created yet
        }

        return ['promo_cards' => $cards];
    }

    /**
     * Owner profile only: hide the PrestaShop logo and version, "Acceso rápido" and the
     * "Ayuda" button, so the owner sees a clean Mi Tienda. Admins keep the full header.
     */
    public function hookDisplayBackOfficeHeader(array $params)
    {
        $employee = $this->context->employee;
        if (!$employee || (int) $employee->id_profile !== self::findOwnerProfileId()) {
            return '';
        }

        return '<style>#header_logo, #header_quick, .btn-help { display: none !important; }</style>';
    }
}
