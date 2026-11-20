<?php
/**
 * Creates the order in the "Esperando pago DeUna" state and redirects to the
 * order confirmation page, which renders the QR and the WhatsApp button.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @property Nf_Deuna $module
 */
class Nf_DeunaValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active || !$this->module->hasRealQr()) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // The option may have become unavailable if the address changed right before confirming.
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] === $this->module->name) {
                $authorized = true;
                break;
            }
        }
        if (!$authorized || !$this->module->checkCurrency($cart)) {
            exit($this->module->getTranslator()->trans('Este método de pago no está disponible.', [], 'Modules.Nfdeuna.Shop'));
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $currency = $this->context->currency;
        $total = (float) $cart->getOrderTotal(true, Cart::BOTH);

        $this->module->validateOrder(
            (int) $cart->id,
            $this->module->getWaitingStateId(),
            $total,
            $this->module->displayName,
            null,
            [],
            (int) $currency->id,
            false,
            $customer->secure_key
        );

        Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . (int) $this->module->currentOrder . '&key=' . $customer->secure_key);
    }
}
