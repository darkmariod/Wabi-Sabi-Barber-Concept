<?php
/**
 * NatuFitness - WhatsApp sales alerts for the owner.
 *
 * - New order: products, customer, phone (wa.me link), delivery and payment method.
 * - Payment confirmed (order moved to a "paid" state, or paid at checkout by card):
 *   "listo para despachar".
 * Each alert is sent once per order (table nf_order_alert). Sending never blocks checkout.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/NfOrderAlertMessage.php';
require_once __DIR__ . '/classes/NfWhatsAppSender.php';

class Nf_Orderalerts extends Module
{
    const CONFIG_ENABLED = 'NF_ALERTS_ENABLED';
    const CONFIG_PHONE = 'NF_ALERTS_PHONE';
    const CONFIG_API_KEY = 'NF_ALERTS_CALLMEBOT_KEY';
    const TABLE = 'nf_order_alert';

    public function __construct()
    {
        $this->name = 'nf_orderalerts';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'NatuFitness';
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Alertas de venta por WhatsApp', [], 'Modules.Nforderalerts.Admin');
        $this->description = $this->trans('Avisa al dueño por WhatsApp de cada pedido nuevo y de cada pago confirmado, listo para despachar.', [], 'Modules.Nforderalerts.Admin');
    }

    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    // --- Install / uninstall -------------------------------------------------

    public function install()
    {
        return parent::install()
            && $this->registerHook('actionValidateOrder')
            && $this->registerHook('actionOrderStatusPostUpdate')
            && Db::getInstance()->execute(
                'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE . '` (
                    `id_order` INT UNSIGNED NOT NULL,
                    `kind` VARCHAR(16) NOT NULL,
                    `date_add` DATETIME NOT NULL,
                    PRIMARY KEY (`id_order`, `kind`)
                ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4'
            )
            && Configuration::updateValue(self::CONFIG_ENABLED, 1)
            && Configuration::updateValue(self::CONFIG_PHONE, (string) Configuration::get('NF_WHATSAPP_NUMBER'));
    }

    public function uninstall()
    {
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABLE . '`');
        foreach ([self::CONFIG_ENABLED, self::CONFIG_PHONE, self::CONFIG_API_KEY] as $key) {
            Configuration::deleteByName($key);
        }

        return parent::uninstall();
    }

    // --- Hooks -----------------------------------------------------------------

    public function hookActionValidateOrder(array $params)
    {
        if (empty($params['order']) || !Validate::isLoadedObject($params['order'])) {
            return;
        }
        $paid = !empty($params['orderStatus']) && (bool) $params['orderStatus']->paid;
        if ($paid) {
            // Paid at checkout (card): one "ready to dispatch" alert is enough.
            $this->markSent((int) $params['order']->id, NfOrderAlertMessage::KIND_NEW);
            $this->alert($params['order'], NfOrderAlertMessage::KIND_PAID);
        } else {
            $this->alert($params['order'], NfOrderAlertMessage::KIND_NEW);
        }
    }

    public function hookActionOrderStatusPostUpdate(array $params)
    {
        if (empty($params['newOrderStatus']) || !$params['newOrderStatus']->paid) {
            return;
        }
        $order = new Order((int) $params['id_order']);
        if (Validate::isLoadedObject($order)) {
            $this->alert($order, NfOrderAlertMessage::KIND_PAID);
        }
    }

    // --- Sending -------------------------------------------------------------------

    /**
     * Sends one alert kind per order at most. Never throws.
     */
    public function alert(Order $order, string $kind): void
    {
        if (!Configuration::get(self::CONFIG_ENABLED) || $this->wasSent((int) $order->id, $kind)) {
            return;
        }
        try {
            $text = NfOrderAlertMessage::compose($kind, NfOrderAlertMessage::fromOrder($order));
            $error = $this->sender()->send($text);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
        if ($error !== null) {
            PrestaShopLogger::addLog('nf_orderalerts: ' . $error, 2, null, 'Order', (int) $order->id, true);

            return;
        }
        $this->markSent((int) $order->id, $kind);
    }

    private function sender(): NfWhatsAppSender
    {
        return new NfWhatsAppSender((string) Configuration::get(self::CONFIG_PHONE), (string) Configuration::get(self::CONFIG_API_KEY));
    }

    private function wasSent(int $orderId, string $kind): bool
    {
        return (bool) Db::getInstance()->getValue(
            'SELECT 1 FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE id_order = ' . $orderId . ' AND kind = \'' . pSQL($kind) . '\''
        );
    }

    private function markSent(int $orderId, string $kind): void
    {
        Db::getInstance()->insert(self::TABLE, ['id_order' => $orderId, 'kind' => pSQL($kind), 'date_add' => date('Y-m-d H:i:s')], false, true, Db::INSERT_IGNORE);
    }

    // --- Configuration page ----------------------------------------------------

    public function getContent()
    {
        $output = '';
        if (Tools::isSubmit('submitNfOrderAlerts')) {
            $phone = NfOrderAlertMessage::waNumber((string) Tools::getValue(self::CONFIG_PHONE));
            if ($phone === '') {
                $output .= $this->displayError($this->trans('El número debe ser un celular de Ecuador (ej. 0983168337 o 593983168337).', [], 'Modules.Nforderalerts.Admin'));
            } else {
                Configuration::updateValue(self::CONFIG_ENABLED, (int) Tools::getValue(self::CONFIG_ENABLED));
                Configuration::updateValue(self::CONFIG_PHONE, $phone);
                $key = trim((string) Tools::getValue(self::CONFIG_API_KEY));
                if ($key !== '') { // empty keeps the saved key (the field never shows it)
                    Configuration::updateValue(self::CONFIG_API_KEY, $key);
                }
                $output .= $this->displayConfirmation($this->trans('Configuración guardada.', [], 'Modules.Nforderalerts.Admin'));
            }
        }
        if (Tools::isSubmit('submitNfOrderAlertsTest')) {
            $error = $this->sender()->send('✅ NatuFitness: las alertas de venta por WhatsApp están funcionando.');
            $output .= $error === null
                ? $this->displayConfirmation($this->trans('Mensaje de prueba enviado. Revisa tu WhatsApp.', [], 'Modules.Nforderalerts.Admin'))
                : $this->displayError($error);
        }

        return $output . $this->renderForm();
    }

    private function renderForm(): string
    {
        $hasKey = (string) Configuration::get(self::CONFIG_API_KEY) !== '';
        $form = [
            'form' => [
                'legend' => ['title' => $this->trans('Alertas de venta por WhatsApp', [], 'Modules.Nforderalerts.Admin'), 'icon' => 'icon-bell'],
                'description' => $this->trans('Activación (una sola vez, desde el celular del dueño): 1) Abre callmebot.com/blog/free-api-whatsapp-messages y guarda el número de CallMeBot que aparece ahí. 2) Envíale por WhatsApp: I allow callmebot to send me messages. 3) Te responde con tu API key: pégala aquí, guarda y pulsa "Enviar prueba".', [], 'Modules.Nforderalerts.Admin'),
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Alertas activas', [], 'Modules.Nforderalerts.Admin'),
                        'name' => self::CONFIG_ENABLED,
                        'values' => [['id' => 'on', 'value' => 1, 'label' => 'Sí'], ['id' => 'off', 'value' => 0, 'label' => 'No']],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('WhatsApp del dueño', [], 'Modules.Nforderalerts.Admin'),
                        'name' => self::CONFIG_PHONE,
                        'required' => true,
                        'desc' => $this->trans('El número que recibe los avisos (el mismo que activó CallMeBot).', [], 'Modules.Nforderalerts.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('API key de CallMeBot', [], 'Modules.Nforderalerts.Admin'),
                        'name' => self::CONFIG_API_KEY,
                        'desc' => $hasKey
                            ? $this->trans('Ya hay una API key guardada. Déjalo vacío para conservarla.', [], 'Modules.Nforderalerts.Admin')
                            : $this->trans('Todavía no hay API key: las alertas no se enviarán hasta pegarla.', [], 'Modules.Nforderalerts.Admin'),
                    ],
                ],
                'submit' => ['title' => $this->trans('Guardar', [], 'Admin.Actions')],
                'buttons' => [[
                    'type' => 'submit',
                    'name' => 'submitNfOrderAlertsTest',
                    'title' => $this->trans('Enviar prueba', [], 'Modules.Nforderalerts.Admin'),
                    'icon' => 'process-icon-envelope',
                    'class' => 'pull-right',
                ]],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->identifier = $this->identifier;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->submit_action = 'submitNfOrderAlerts';
        $helper->tpl_vars = ['fields_value' => [
            self::CONFIG_ENABLED => (int) Configuration::get(self::CONFIG_ENABLED),
            self::CONFIG_PHONE => (string) Configuration::get(self::CONFIG_PHONE),
            self::CONFIG_API_KEY => '',
        ]];

        return $helper->generateForm([$form]);
    }
}
