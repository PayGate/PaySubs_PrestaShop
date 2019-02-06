<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

class PaySubsValidationModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        if ( !strcmp( $_SERVER["REQUEST_METHOD"], "GET" ) ) {
            if ( isset( $_REQUEST['p3'] ) ) {
                $this->handleResponse();
            }
        } else {
            $cart = $this->context->cart;
            if ( $cart->id_customer == 0
                || $cart->id_address_delivery == 0
                || $cart->id_address_invoice == 0
                || !$this->module->active ) {
                Tools::redirect( 'index.php?controller=order&step=1' );
            }

            $this->context->smarty->assign(
                $_POST
            );
            $this->setTemplate( 'module:paysubs/views/templates/hook/paysubs.tpl' );
        }
    }

    public function handleResponse()
    {
        if ( !strcmp( $_SERVER["REQUEST_METHOD"], "GET" ) ) {
            if ( isset( $_REQUEST['p3'] ) ) {
                $cart     = new Cart( (int) $_REQUEST['m_5'] );
                $customer = new Customer( (int) $cart->id_customer );
                if ( stripos( $_REQUEST['p3'], 'APPROVED' ) !== false ) {
                    // Payment Authorised
                    if ( !$cart->id ) {
                        $errors = $this->module->getResponseMessage( 'cart' ) . '<br />';
                    } else {
                        // Validate the order as paid
                        $this->module->validateOrder( (int) $cart->id, Configuration::get( 'PS_OS_PAYMENT' ), (float) $cart->getOrderTotal(), $this->module->displayName, $this->module->getResponseMessage( 'transaction' ) . $cart->id, array(), null, false, $customer->secure_key ); 
                    }

                    $url = __PS_BASE_URI__ . 'index.php?controller=order-confirmation&';
                    $url .= 'id_module=' . $this->module->id;
                    $url .= '&id_cart=' . $cart->id . '&key=' . $customer->secure_key;
                    Tools::redirect( $url );
                } else {
                    $errors = $this->module->getResponseMessage( 'failed' ) . ' - ' . $_REQUEST['p3'] . '<br />';
                    if ( !empty( $errors ) && isset( $_REQUEST['m_5'] ) ) {
                        $redirect_url = 'index.php?controller=order';
                        Tools::redirect( $redirect_url );
                    }
                }
            }
        }
    }
}