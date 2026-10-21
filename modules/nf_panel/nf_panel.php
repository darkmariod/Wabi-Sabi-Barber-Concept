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

class Nf_Panel extends Module
{
    const TAB_CLASS = 'AdminNfPanel';
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
            && self::ensureOwnerProfile() > 0;
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
        $tab->id_parent = 0;
        $tab->icon = self::TAB_ICON;
        $tab->active = true;
        $tab->enabled = true;
        foreach (Language::getLanguages(false) as $language) {
            $tab->name[(int) $language['id_lang']] = self::TAB_NAME;
        }
        if (!$tab->save()) {
            return 0;
        }

        // Place the tab first among the top-level entries (before the dashboard).
        $db = Db::getInstance();
        $position = (int) $db->getValue('SELECT position FROM `' . _DB_PREFIX_ . 'tab` WHERE id_tab = ' . (int) $tab->id);
        if ($position !== 0) {
            $db->execute('UPDATE `' . _DB_PREFIX_ . 'tab` SET position = position + 1 WHERE id_parent = 0 AND id_tab <> ' . (int) $tab->id . ' AND position < ' . $position);
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

        // Grant CRUD on the Mi Tienda tab only (roles are created by Tab::add()).
        $access = new Access();
        foreach (['view', 'add', 'edit', 'delete'] as $action) {
            $access->updateLgcAccess($profileId, $tabId, $action, true, false);
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
}
