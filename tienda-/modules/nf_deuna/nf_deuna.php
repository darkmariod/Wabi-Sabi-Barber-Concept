<?php
/**
 * NatuFitness - DeUna static QR payment module.
 *
 * Shows a static DeUna (Banco Pichincha) QR at checkout, creates the order in the
 * "Esperando pago DeUna" state and asks the customer to send the payment receipt
 * via WhatsApp. Payment is confirmed manually from the back office.
 */

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Nf_Deuna extends PaymentModule
{
    const CONFIG_OS_WAITING = 'NF_DEUNA_OS_WAITING';
    const CONFIG_QR_IMAGE = 'NF_DEUNA_QR_IMAGE';
    const CONFIG_HOLDER = 'NF_DEUNA_HOLDER';
    const CONFIG_INSTRUCTIONS = 'NF_DEUNA_INSTRUCTIONS';
    const CONFIG_WHATSAPP = 'NF_WHATSAPP_NUMBER';

    const DEFAULT_WHATSAPP = '593000000000';
    const ORDER_STATE_COLOR = '#17507d';
    const ALLOWED_COUNTRY_ISO = 'EC';
    const ALLOWED_CURRENCY_ISO = 'USD';
    const UPLOAD_SUBDIR = 'nf_deuna';
    const ALLOWED_IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

    public function __construct()
    {
        $this->name = 'nf_deuna';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'NatuFitness';
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
        $this->controllers = ['validation'];
        $this->is_eu_compatible = 1;
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('DeUna (QR estático)', [], 'Modules.Nfdeuna.Admin');
        $this->description = $this->trans('Cobra con el QR estático de DeUna (Banco Pichincha) y recibe el comprobante por WhatsApp.', [], 'Modules.Nfdeuna.Admin');
        $this->confirmUninstall = $this->trans('¿Seguro que deseas desinstalar DeUna? Se borrará su configuración.', [], 'Modules.Nfdeuna.Admin');
    }

    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    // --- Install / uninstall -------------------------------------------------

    public function install()
    {
        return parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('displayPaymentReturn')
            && $this->registerHook('actionFrontControllerSetVariables')
            && $this->installOrderState()
            && $this->installDefaults()
            && $this->restrictToEcuadorUsd();
    }

    public function uninstall()
    {
        $this->uninstallOrderState();
        $this->deleteUploadedQr();

        foreach ([self::CONFIG_OS_WAITING, self::CONFIG_QR_IMAGE, self::CONFIG_HOLDER, self::CONFIG_INSTRUCTIONS, self::CONFIG_WHATSAPP] as $key) {
            Configuration::deleteByName($key);
        }

        return parent::uninstall();
    }

    private function installDefaults(): bool
    {
        if (!Configuration::get(self::CONFIG_WHATSAPP)) {
            Configuration::updateValue(self::CONFIG_WHATSAPP, self::DEFAULT_WHATSAPP);
        }
        if (!Configuration::get(self::CONFIG_HOLDER)) {
            Configuration::updateValue(self::CONFIG_HOLDER, 'NatuFitness');
        }
        if (!Configuration::get(self::CONFIG_INSTRUCTIONS)) {
            Configuration::updateValue(
                self::CONFIG_INSTRUCTIONS,
                'Abre la app DeUna de Banco Pichincha, escanea el código QR y paga el monto exacto. Luego envíanos el comprobante por WhatsApp indicando tu número de pedido.'
            );
        }

        return true;
    }

    /**
     * Creates the "Esperando pago DeUna" order state, or revives the one left
     * behind (flagged deleted) by a previous uninstall.
     */
    private function installOrderState(): bool
    {
        $stateId = (int) Db::getInstance()->getValue(
            'SELECT id_order_state FROM `' . _DB_PREFIX_ . 'order_state` WHERE module_name = "' . pSQL($this->name) . '" ORDER BY id_order_state ASC'
        );

        $state = new OrderState($stateId ?: null);
        foreach (Language::getLanguages(false) as $language) {
            $state->name[(int) $language['id_lang']] = 'Esperando pago DeUna';
        }
        $state->module_name = $this->name;
        $state->color = self::ORDER_STATE_COLOR;
        $state->send_email = false;
        $state->logable = false;
        $state->paid = false;
        $state->invoice = false;
        $state->shipped = false;
        $state->delivery = false;
        $state->hidden = false;
        $state->unremovable = true;
        $state->deleted = false;

        if (!$state->save()) {
            return false;
        }

        // Reuse the bank wire icon so the state looks consistent in the back office.
        $icon = _PS_ORDER_STATE_IMG_DIR_ . (int) $state->id . '.gif';
        $bankWireIcon = _PS_ORDER_STATE_IMG_DIR_ . (int) Configuration::get('PS_OS_BANKWIRE') . '.gif';
        if (!file_exists($icon) && file_exists($bankWireIcon)) {
            @copy($bankWireIcon, $icon);
        }

        return Configuration::updateValue(self::CONFIG_OS_WAITING, (int) $state->id);
    }

    /**
     * Deletes the order state only when no order ever used it; otherwise it is
     * kept (flagged deleted) so existing order histories stay readable.
     */
    private function uninstallOrderState(): void
    {
        $stateId = (int) Configuration::get(self::CONFIG_OS_WAITING);
        if (!$stateId) {
            return;
        }

        $db = Db::getInstance();
        $used = (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'orders` WHERE current_state = ' . $stateId)
            + (int) $db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_history` WHERE id_order_state = ' . $stateId);

        $state = new OrderState($stateId);
        if (!Validate::isLoadedObject($state)) {
            return;
        }

        if ($used > 0) {
            $state->deleted = true;
            $state->save();

            return;
        }

        $state->unremovable = false;
        $state->delete();
        @unlink(_PS_ORDER_STATE_IMG_DIR_ . $stateId . '.gif');
    }

    /**
     * Limits the module to Ecuador and USD for every shop.
     */
    public function restrictToEcuadorUsd(): bool
    {
        $db = Db::getInstance();
        $countryId = (int) Country::getByIso(self::ALLOWED_COUNTRY_ISO);
        $currencyId = (int) Currency::getIdByIsoCode(self::ALLOWED_CURRENCY_ISO);
        if (!$countryId || !$currencyId) {
            return false;
        }

        $db->delete('module_country', 'id_module = ' . (int) $this->id);
        $db->delete('module_currency', 'id_module = ' . (int) $this->id);

        foreach (Shop::getShops(false, null, true) as $shopId) {
            $db->insert('module_country', ['id_module' => (int) $this->id, 'id_shop' => (int) $shopId, 'id_country' => $countryId]);
            $db->insert('module_currency', ['id_module' => (int) $this->id, 'id_shop' => (int) $shopId, 'id_currency' => $currencyId]);
        }

        return true;
    }

    // --- Back office configuration --------------------------------------------

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitNfDeuna')) {
            $errors = $this->processConfiguration();
            $output .= $errors
                ? $this->displayError(implode('<br>', $errors))
                : $this->displayConfirmation($this->trans('Configuración guardada.', [], 'Modules.Nfdeuna.Admin'));
        }

        return $output . $this->renderForm();
    }

    /**
     * @return string[] validation errors
     */
    private function processConfiguration(): array
    {
        $errors = [];

        $whatsapp = preg_replace('/\D+/', '', (string) Tools::getValue(self::CONFIG_WHATSAPP));
        if (!preg_match('/^593\d{8,9}$/', $whatsapp)) {
            $errors[] = $this->trans('El número de WhatsApp debe tener el formato 593 + número sin el 0 inicial (ej. 593991234567).', [], 'Modules.Nfdeuna.Admin');
        }

        $holder = trim((string) Tools::getValue(self::CONFIG_HOLDER));
        if ($holder === '' || !Validate::isGenericName($holder)) {
            $errors[] = $this->trans('El nombre del titular es obligatorio.', [], 'Modules.Nfdeuna.Admin');
        }

        $instructions = trim((string) Tools::getValue(self::CONFIG_INSTRUCTIONS));
        if (!Validate::isCleanHtml($instructions)) {
            $errors[] = $this->trans('Las instrucciones contienen HTML no permitido.', [], 'Modules.Nfdeuna.Admin');
        }

        if (!empty($_FILES['nf_deuna_qr']['name'])) {
            $uploadError = $this->storeUploadedQr($_FILES['nf_deuna_qr']);
            if ($uploadError) {
                $errors[] = $uploadError;
            }
        }

        if (!$errors) {
            Configuration::updateValue(self::CONFIG_WHATSAPP, $whatsapp);
            Configuration::updateValue(self::CONFIG_HOLDER, $holder);
            Configuration::updateValue(self::CONFIG_INSTRUCTIONS, $instructions);
        }

        return $errors;
    }

    /**
     * Saves the uploaded QR under img/nf_deuna/ (outside the module folder so it
     * survives module updates) and removes the previous one.
     *
     * @return string|null error message
     */
    private function storeUploadedQr(array $file): ?string
    {
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_IMAGE_EXTENSIONS, true)) {
            return $this->trans('El QR debe ser una imagen PNG, JPG o WEBP.', [], 'Modules.Nfdeuna.Admin');
        }

        $error = ImageManager::validateUpload($file, Tools::getMaxUploadSize());
        if ($error) {
            return $error;
        }

        $dir = _PS_IMG_DIR_ . self::UPLOAD_SUBDIR . '/';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return $this->trans('No se pudo crear la carpeta de imágenes.', [], 'Modules.Nfdeuna.Admin');
        }

        $filename = 'qr-' . date('YmdHis') . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
            return $this->trans('No se pudo guardar la imagen del QR.', [], 'Modules.Nfdeuna.Admin');
        }

        $this->deleteUploadedQr();
        Configuration::updateValue(self::CONFIG_QR_IMAGE, $filename);

        return null;
    }

    private function deleteUploadedQr(): void
    {
        $current = basename((string) Configuration::get(self::CONFIG_QR_IMAGE));
        if ($current !== '' && is_file(_PS_IMG_DIR_ . self::UPLOAD_SUBDIR . '/' . $current)) {
            @unlink(_PS_IMG_DIR_ . self::UPLOAD_SUBDIR . '/' . $current);
        }
        Configuration::updateValue(self::CONFIG_QR_IMAGE, '');
    }

    private function renderForm(): string
    {
        $qrUrl = $this->getQrUrl();
        $usingPlaceholder = !Configuration::get(self::CONFIG_QR_IMAGE);

        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Configuración de DeUna', [], 'Modules.Nfdeuna.Admin'),
                    'icon' => 'icon-qrcode',
                ],
                'input' => [
                    [
                        'type' => 'file',
                        'label' => $this->trans('Imagen del QR de DeUna', [], 'Modules.Nfdeuna.Admin'),
                        'name' => 'nf_deuna_qr',
                        'display_image' => true,
                        'image' => '<img src="' . htmlspecialchars($qrUrl, ENT_QUOTES) . '" alt="QR" style="max-width:200px">',
                        'desc' => $usingPlaceholder
                            ? $this->trans('Se está mostrando un QR de EJEMPLO. Sube el QR real descargado de la app DeUna (PNG, JPG o WEBP).', [], 'Modules.Nfdeuna.Admin')
                            : $this->trans('Sube una nueva imagen para reemplazar el QR actual (PNG, JPG o WEBP).', [], 'Modules.Nfdeuna.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Titular de la cuenta', [], 'Modules.Nfdeuna.Admin'),
                        'name' => self::CONFIG_HOLDER,
                        'required' => true,
                    ],
                    [
                        'type' => 'textarea',
                        'label' => $this->trans('Instrucciones de pago', [], 'Modules.Nfdeuna.Admin'),
                        'name' => self::CONFIG_INSTRUCTIONS,
                        'rows' => 4,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Número de WhatsApp', [], 'Modules.Nfdeuna.Admin'),
                        'name' => self::CONFIG_WHATSAPP,
                        'required' => true,
                        'desc' => $this->trans('Formato internacional sin + ni espacios: 593 + número sin el 0 inicial. También lo usa el botón flotante de la tienda.', [], 'Modules.Nfdeuna.Admin'),
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Guardar', [], 'Admin.Actions'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->identifier = $this->identifier;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->submit_action = 'submitNfDeuna';
        $helper->tpl_vars = [
            'fields_value' => [
                self::CONFIG_HOLDER => Tools::getValue(self::CONFIG_HOLDER, Configuration::get(self::CONFIG_HOLDER)),
                self::CONFIG_INSTRUCTIONS => Tools::getValue(self::CONFIG_INSTRUCTIONS, Configuration::get(self::CONFIG_INSTRUCTIONS)),
                self::CONFIG_WHATSAPP => Tools::getValue(self::CONFIG_WHATSAPP, Configuration::get(self::CONFIG_WHATSAPP)),
            ],
        ];

        return $helper->generateForm([$form]);
    }

    // --- Shared helpers -----------------------------------------------------------

    public function getWhatsappNumber(): string
    {
        $number = preg_replace('/\D+/', '', (string) Configuration::get(self::CONFIG_WHATSAPP));

        return $number ?: self::DEFAULT_WHATSAPP;
    }

    public function getQrUrl(): string
    {
        $file = basename((string) Configuration::get(self::CONFIG_QR_IMAGE));
        if ($file !== '' && is_file(_PS_IMG_DIR_ . self::UPLOAD_SUBDIR . '/' . $file)) {
            return $this->context->link->getBaseLink() . 'img/' . self::UPLOAD_SUBDIR . '/' . $file;
        }

        return $this->context->link->getBaseLink() . 'modules/' . $this->name . '/views/img/qr-placeholder.svg';
    }

    public function getWaitingStateId(): int
    {
        return (int) Configuration::get(self::CONFIG_OS_WAITING);
    }

    public function checkCurrency(Cart $cart): bool
    {
        $allowed = $this->getCurrency((int) $cart->id_currency);
        if (!is_array($allowed)) {
            return false;
        }
        foreach ($allowed as $currency) {
            if ((int) $currency['id_currency'] === (int) $cart->id_currency) {
                return true;
            }
        }

        return false;
    }

    // --- Hooks ----------------------------------------------------------------------

    /**
     * Exposes the WhatsApp number to every front template as
     * {$modules.nf_deuna.whatsapp_number}.
     */
    /**
     * Storefront variables ($modules.nf_deuna.*): WhatsApp number, DeUna QR, and the bank
     * transfer data of ps_wirepayment, so the footer bank logos can show how to pay.
     */
    public function hookActionFrontControllerSetVariables(array $params)
    {
        $details = preg_split('/\R/', (string) Configuration::get('BANK_WIRE_DETAILS')) ?: [];

        return [
            'whatsapp_number' => $this->getWhatsappNumber(),
            'qr_url' => $this->getQrUrl(),
            'holder' => (string) Configuration::get(self::CONFIG_HOLDER),
            'transfer_owner' => (string) Configuration::get('BANK_WIRE_OWNER'),
            'transfer_lines' => array_values(array_filter(array_map('trim', $details), 'strlen')),
        ];
    }

    public function hookPaymentOptions(array $params)
    {
        if (!$this->active || !$this->checkCurrency($params['cart'])) {
            return [];
        }

        $this->smarty->assign([
            'nf_deuna_qr_url' => $this->getQrUrl(),
            'nf_deuna_instructions' => (string) Configuration::get(self::CONFIG_INSTRUCTIONS),
        ]);

        $option = new PaymentOption();
        $option->setModuleName($this->name)
            ->setCallToActionText($this->trans('DeUna (QR)', [], 'Modules.Nfdeuna.Shop'))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/deuna.svg'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', [], true))
            ->setAdditionalInformation($this->fetch('module:nf_deuna/views/templates/hook/payment_option.tpl'));

        return [$option];
    }

    public function hookDisplayPaymentReturn(array $params)
    {
        if (!$this->active || empty($params['order'])) {
            return '';
        }

        /** @var Order $order */
        $order = $params['order'];
        $amount = (float) $order->getOrdersTotalPaid() - (float) $order->getTotalPaid();
        $currency = new Currency((int) $order->id_currency);

        $message = sprintf(
            'Hola, adjunto comprobante DeUna del pedido %s por $%s',
            $order->reference,
            number_format($amount, 2, '.', '')
        );

        $this->smarty->assign([
            'nf_deuna_qr_url' => $this->getQrUrl(),
            'nf_deuna_holder' => (string) Configuration::get(self::CONFIG_HOLDER),
            'nf_deuna_instructions' => (string) Configuration::get(self::CONFIG_INSTRUCTIONS),
            'nf_deuna_total' => Tools::getContextLocale($this->context)->formatPrice($amount, $currency->iso_code),
            'nf_deuna_reference' => $order->reference,
            'nf_deuna_whatsapp_url' => 'https://wa.me/' . $this->getWhatsappNumber() . '?text=' . rawurlencode($message),
        ]);

        return $this->fetch('module:nf_deuna/views/templates/hook/payment_return.tpl');
    }
}
