<?php
/*
 * Copyright (c) 2019 PayGate (Pty) Ltd
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if ( !defined( '_PS_VERSION_' ) ) {
    exit;
}

class PaySubs extends PaymentModule
{
    private $postErrors = array();

    public function __construct()
    {
        $this->name    = 'paysubs';
        $this->tab     = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author  = 'PayGate';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName      = $this->l( 'PaySubs' );
        $this->description      = $this->l( 'Accept credit/debit card payments via PayGate PaySubs South Africa' );
        $this->confirmUninstall = $this->l( 'Are you sure you want to delete these details?' );
    }

    public function install()
    {
        if (
            !parent::install()
            || !Configuration::updateValue( 'PAYSUBS_MERCHANT_ID', '1234' )
            || !Configuration::updateValue( 'PAYSUBS_BUDGET', '0' )
            || !Configuration::updateValue( 'PAYSUBS_CURRENCYCODE', 'ZAR' )
            || !Configuration::updateValue( 'PAYSUBS_RECURRING', '0' )
            || !Configuration::updateValue( 'PAYSUBS_RECUR_FREQUENCY', 'M' )
            || !$this->registerHook( 'payment' )
            || !$this->registerHook( 'paymentReturn' )
            || !$this->registerHook( 'orderConfirmation' )
            || !$this->registerHook( 'paymentOptions' )
            || !$this->installCurrency()
        ) {
            return false;
        }

        return true;
    }

    public function installCurrency()
    {
        // Check if ZAR is installed, install and refresh if not
        $currency         = new Currency();
        $currency_rand_id = $currency->getIdByIsoCode( 'ZAR' );

        if ( is_null( $currency_rand_id ) ) {
            $currency->name            = "South African Rand";
            $currency->iso_code        = "ZAR";
            $currency->sign            = "R";
            $currency->format          = 1;
            $currency->blank           = 1;
            $currency->decimals        = 1;
            $currency->deleted         = 0;
            $currency->conversion_rate = 1;
            $currency->add();
            $currency->refreshCurrencies();
        }

        return true;
    }

    public function uninstall()
    {
        if (
            !Configuration::deleteByName( 'PAYSUBS_MERCHANT_ID' )
            || !Configuration::deleteByName( 'PAYSUBS_BUDGET' )
            || !Configuration::deleteByName( 'PAYSUBS_CURRENCYCODE' )
            || !Configuration::deleteByName( 'PAYSUBS_RECURRING' )
            || !Configuration::deleteByName( 'PAYSUBS_RECUR_FREQUENCY' )
            || !parent::uninstall()
        ) {
            return false;
        }

        return true;
    }

    public function hookPaymentOptions( $params )
    {
        // Safety check that our currency is there
        $currency         = new Currency();
        $currency_rand_id = $currency->getIdByIsoCode( 'ZAR' );

        if ( is_null( $currency_rand_id ) ) {
            $currency->name            = "South African Rand";
            $currency->iso_code        = "ZAR";
            $currency->sign            = "R";
            $currency->format          = 1;
            $currency->blank           = 1;
            $currency->decimals        = 1;
            $currency->deleted         = 0;
            $currency->conversion_rate = 1;
            $currency->add();
            $currency->refreshCurrencies();
        }

        $step = 3;

        if ( Tools::getIsset( 'step' ) ) {
            $step = Tools::getValue( 'step' );
        }

        $address  = new Address( (int) $params['cart']->id_address_invoice );
        $customer = new Customer( (int) $params['cart']->id_customer );
        $currency = new Currency( $params['cart']->id_currency );

        $merchant_id = Configuration::get( 'PAYSUBS_MERCHANT_ID' );
        $budget      = Configuration::get( 'PAYSUBS_BUDGET' );
        if ( $budget == 1 ) {
            $budget = 'Y';
        } else {
            $budget = 'N';
        }

        $currency_code = Configuration::get( 'PAYSUBS_CURRENCYCODE' );

        $currency_rand_id = $currency->getIdByIsoCode( $currency_code );
        $currency_rand    = new Currency( $currency_rand_id );
        if (
            !Validate::isLoadedObject( $address )
            || !Validate::isLoadedObject( $customer )
            || !Validate::isLoadedObject( $currency ) ) {
            return $this->l( 'PaySubs payment module error: (invalid address or customer)' );
        }

        $ordertotal                = $params['cart']->getOrderTotal( true, 3 );
        $paysubsTotal                  = $ordertotal;
        $paysubsTotal                  = number_format( $paysubsTotal, 2, '.', '' );
        $order_id                  = $params['cart']->id;
        $return_url                = $this->context->link->getPageLink( 'order' );
        $answer_url                = Context::getContext()->link->getModuleLink( 'paysubs', 'validation' );
        $unique_transaction_number = $params['cart']->id_customer . '-' . date( 'Ymdhis' );
        $url                       = "https://www.vcs.co.za/vvonline/vcspay.aspx";
        $inputs                    = array(
            'p1'              => array(
                'name'  => 'p1',
                'type'  => 'hidden',
                'value' => $merchant_id,
            ),
            'p2'              => array(
                'name'  => 'p2',
                'type'  => 'hidden',
                'value' => $unique_transaction_number,
            ),
            'p3'              => array(
                'name'  => 'p3',
                'type'  => 'hidden',
                'value' => Configuration::get( 'PS_SHOP_NAME' ) . ' - Order Number: ' . $order_id,
            ),
            'p4'              => array(
                'name'  => 'p4',
                'type'  => 'hidden',
                'value' => number_format( $paysubsTotal / $currency->conversion_rate, 2, '.', '' ),
            ),
            'p5'              => array(
                'name'  => 'p5',
                'type'  => 'hidden',
                'value' => Tools::strtolower( $currency_rand->iso_code ),
            ),
            'Budget'          => array(
                'name'  => 'Budget',
                'type'  => 'hidden',
                'value' => $budget,
            ),
            'm_5'             => array(
                'name'  => 'm_5',
                'type'  => 'hidden',
                'value' => $order_id,
            ),
            'p10'             => array(
                'name'  => 'p10',
                'type'  => 'hidden',
                'value' => $return_url,
            ),
            'osCommerce'      => array(
                'name'  => 'osCommerce',
                'type'  => 'hidden',
                'value' => "Y",
            ),
            'osApprovedUrl'   => array(
                'name'  => 'osApprovedUrl',
                'type'  => 'hidden',
                'value' => $answer_url,
            ),
            'osDeclinedUrl'   => array(
                'name'  => 'osDeclinedUrl',
                'type'  => 'hidden',
                'value' => $answer_url,
            ),
            'cardholderemail' => array(
                'name'  => 'cardholderemail',
                'type'  => 'hidden',
                'value' => utf8_decode( $customer->email ),
            ),
            'paysubsGatewayUrl'   => array(
                'name'  => 'paysubsGatewayUrl',
                'type'  => 'hidden',
                'value' => $url,
            ),
        );

        if ( Configuration::get( 'PAYSUBS_RECURRING' ) ) {
            $inputs['p6'] = ['name' => 'p6', 'type' => 'hidden', 'value' => 'U'];
            $inputs['p7'] = ['name' => 'p7', 'type' => 'hidden', 'value' => Configuration::get( 'PAYSUBS_RECUR_FREQUENCY' )];
        }

        if ( Configuration::get( 'PAYSUBS_ACTIVATE_HASHING' ) ) {
            if ( Configuration::get( 'PAYSUBS_RECURRING' ) ) {
                $hash = $inputs['p1']['value'] . $inputs['p2']['value'] . $inputs['p3']['value'] . $inputs['p4']['value'] . $inputs['p5']['value'] . $inputs['p10']['value'] . $inputs['Budget']['value'] . $inputs['cardholderemail']['value'] . $inputs['m_5']['value'] . Configuration::get( 'PAYSUBS_MD5_KEY' );
            } else {
                $hash = $inputs['p1']['value'] . $inputs['p2']['value'] . $inputs['p3']['value'] . $inputs['p4']['value'] . $inputs['p5']['value'] . $inputs['p6']['value'] . $inputs['p7']['value'];
                $hash .= $inputs['p10']['value'] . $inputs['Budget']['value'] . $inputs['cardholderemail']['value'] . $inputs['m_5']['value'] . Configuration::get( 'PAYSUBS_MD5_KEY' );
            }

            $inputs['hash'] = array(
                'name'  => 'Hash',
                'type'  => 'hidden',
                'value' => md5( $hash ),
            );
        }

        $newOption = new PaymentOption();
        $newOption->setCallToActionText( $this->l( 'Pay Via PAYSUBS' ) )
            ->setLogo( Media::getMediaPath( _PS_MODULE_DIR_ . 'paysubs/views/img/paygate.png' ) )
            ->setAdditionalInformation( 'You will be redirected to PaySubs website when you place an order.' )
            ->setAction( $this->context->link->getModuleLink( $this->name, 'validation', array(), true ) )
            ->setInputs( $inputs );

        return array( $newOption );
    }

    public function getContent()
    {
        $this->_html = '';

        if ( Tools::isSubmit( 'btnSubmit' ) ) {
            $this->postValidation();
            if ( !count( $this->postErrors ) ) {
                $this->postProcess();
            } else {
                foreach ( $this->postErrors as $err ) {
                    $this->_html .= $this->displayError( $err );
                }
            }
        }

        $this->_html .= $this->renderForm();
        $this->_html .= $this->context->smarty->fetch( _PS_MODULE_DIR_ . 'paysubs/views/templates/admin/info.tpl' );
        return $this->_html;
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans( 'Settings', array(), 'Admin.Global' ),
                    'icon'  => 'icon-gear',
                ),
                'input'  => array(
                    array(
                        'type'     => 'text',
                        'label'    => $this->l( 'PaySubs Merchant ID' ),
                        'name'     => 'PAYSUBS_MERCHANT_ID',
                        'required' => true,
                        'col'      => 3,
                    ),
                    array(
                        'type'   => 'switch',
                        'label'  => $this->l( 'Enable recurring payments' ),
                        'name'   => 'PAYSUBS_RECURRING',
                        'values' => array(
                            array(
                                'id'    => 'recurring_on',
                                'value' => '1',
                                'label' => $this->trans( 'Yes', array(), 'Admin.Actions' ),
                            ),
                            array(
                                'id'    => 'recurring_off',
                                'value' => '0',
                                'label' => $this->trans( 'No', array(), 'Admin.Actions' ),
                            ),
                        ),
                    ),
                    array(
                        'type'    => 'select',
                        'label'   => $this->l( 'Recurring payment frequency' ),
                        'name'    => 'PAYSUBS_RECUR_FREQUENCY',
                        'options' => array(
//                            'query' => $idevents = array(
                            'query' => array(
                                array(
                                    'id'    => 'D',
                                    'value' => 'Daily',
                                ),
                                array(
                                    'id'    => 'W',
                                    'value' => 'Weekly',
                                ),
                                array(
                                    'id'    => 'M',
                                    'value' => 'Monthly',
                                ),
                                array(
                                    'id'    => 'Q',
                                    'value' => 'Quarterly',
                                ),
                                array(
                                    'id'    => '6',
                                    'value' => 'Bi-annually',
                                ),
                                array(
                                    'id'    => 'Y',
                                    'value' => 'Yearly',
                                ),
                            ),
                            'id'    => 'id',
                            'name'  => 'value',
                        ),
                    ),
                    array(
                        'type'   => 'switch',
                        'label'  => $this->l( 'Accept PaySubs Budget Payments' ),
                        'name'   => 'PAYSUBS_BUDGET',
                        'values' => array(
                            array(
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->trans( 'Yes', array(), 'Admin.Actions' ),
                            ),
                            array(
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->trans( 'No', array(), 'Admin.Actions' ),
                            ),
                        ),
                    ),
                    array(
                        'type'   => 'switch',
                        'label'  => $this->l( 'Activate Hashing' ),
                        'name'   => 'PAYSUBS_ACTIVATE_HASHING',
                        'values' => array(
                            array(
                                'id'    => 'active_on',
                                'value' => 1,
                                'label' => $this->trans( 'Yes', array(), 'Admin.Actions' ),
                            ),
                            array(
                                'id'    => 'active_off',
                                'value' => 0,
                                'label' => $this->trans( 'No', array(), 'Admin.Actions' ),
                            ),
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans( 'Save', array(), 'Admin.Actions' ),
                ),
            ),
        );

        if ( Tools::getValue( 'PAYSUBS_ACTIVATE_HASHING', Configuration::get( 'PAYSUBS_ACTIVATE_HASHING' ) ) ) {
            $fields_form['form']['input'][] = array(
                'type'     => 'text',
                'label'    => $this->l( 'MD5 Key' ),
                'name'     => 'PAYSUBS_MD5_KEY',
                'required' => true,
                'col'      => 3,
            );
        }

        $helper                = new HelperForm();
        $helper->show_toolbar  = false;
        $helper->identifier    = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex  = $this->context->link->getAdminLink( 'AdminModules', false );
        $helper->currentIndex .= '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token    = Tools::getAdminTokenLite( 'AdminModules' );
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
        );
        $this->fields_form = array();
        return $helper->generateForm( array( $fields_form ) );
    }

    public function getConfigFieldsValues()
    {
        return array(
            'PAYSUBS_MERCHANT_ID'      => Tools::getValue( 'PAYSUBS_MERCHANT_ID', Configuration::get( 'PAYSUBS_MERCHANT_ID' ) ),
            'PAYSUBS_ACTIVATE_HASHING' => Tools::getValue( 'PAYSUBS_ACTIVATE_HASHING', Configuration::get( 'PAYSUBS_ACTIVATE_HASHING' ) ),
            'PAYSUBS_BUDGET'           => Tools::getValue( 'PAYSUBS_BUDGET', Configuration::get( 'PAYSUBS_RECUR_FREQUENCY_BUDGET' ) ),
            'PAYSUBS_MD5_KEY'          => Tools::getValue( 'PAYSUBS_MD5_KEY', Configuration::get( 'PAYSUBS_MD5_KEY' ) ),
            'PAYSUBS_RECURRING'        => Tools::getValue( 'PAYSUBS_RECURRING', Configuration::get( 'PAYSUBS_RECURRING' ) ),
            'PAYSUBS_RECUR_FREQUENCY'  => Tools::getValue( 'PAYSUBS_RECUR_FREQUENCY', Configuration::get( 'PAYSUBS_RECUR_FREQUENCY' ) ),
        );
    }

    private function postProcess()
    {
        if ( Tools::isSubmit( 'btnSubmit' ) ) {
            Configuration::updateValue( 'PAYSUBS_MERCHANT_ID', Tools::getValue( 'PAYSUBS_MERCHANT_ID' ) );
            Configuration::updateValue( 'PAYSUBS_ACTIVATE_HASHING', Tools::getValue( 'PAYSUBS_ACTIVATE_HASHING' ) );
            Configuration::updateValue( 'PAYSUBS_BUDGET', Tools::getValue( 'PAYSUBS_BUDGET' ) );
            Configuration::updateValue( 'PAYSUBS_MD5_KEY', Tools::getValue( 'PAYSUBS_MD5_KEY' ) );
        }
        $this->_html .= $this->displayConfirmation( $this->l( 'Settings updated' ) );
    }

    private function postValidation()
    {
        if ( Tools::isSubmit( 'btnSubmit' ) ) {
            if ( !Tools::getValue( 'PAYSUBS_MERCHANT_ID' ) ) {
                $this->postErrors[] = $this->l( 'The "PaySubs Merchant ID" field is required.' );
            } elseif ( Tools::getValue( 'PAYSUBS_ACTIVATE_HASHING' ) && !Tools::getValue( 'PAYSUBS_MD5_KEY' ) ) {
                $this->postErrors[] = $this->l( 'The "MD5 Key" field is required.' );
            }
        }
    }

    public function getResponseMessage( $key )
    {
        $translations = array(
            'payment'     => $this->l( 'Payment: ' ),
            'cart'        => $this->l( 'Cart not found' ),
            'order'       => $this->l( 'Order has already been placed' ),
            'transaction' => $this->l( 'Transaction ID: ' ),
            'failed'      => $this->l( 'The payment transaction could NOT be verified' ),
        );
        return $translations[$key];
    }

    public function hookPaymentReturn( $params )
    {
    }

    public function hookOrderConfirmation( $params )
    {
        if ( !$this->active ) {
            return false;
        }

        $state = $params['order']->getCurrentState();
        if ( $state == _PS_OS_PAYMENT_ || $state == _PS_OS_OUTOFSTOCK_ ) {
            $this->context->smarty->assign( array(
                'total_to_pay' => Tools::displayPrice(
                    $params['order']->getOrdersTotalPaid(),
                    new Currency( $params['order']->id_currency ),
                    false
                ),
                'status'       => 'ok',
                'id_order'     => $params['order']->id,
                'base_dir'     => _PS_BASE_URL_ . __PS_BASE_URI__,
            ) );

            $this->context->smarty->assign( 'message_display', $this->l( 'Your payment was successful.' ) );
            return $this->context->smarty->fetch( 'module:paysubs/views/templates/front/order_confirmation.tpl' );
        } else {
            $this->context->smarty->assign(
                array(
                    'total_to_pay' => Tools::displayPrice(
                        $params['order']->getOrdersTotalPaid(),
                        new Currency( $params['order']->id_currency ),
                        false
                    ),
                    'status'       => 'failed',
                    'id_order'     => $params['order']->id,
                    'base_dir'     => _PS_BASE_URL_ . __PS_BASE_URI__,
                )
            );
            $this->context->smarty->assign( 'message_display', $this->l( 'Your payment was NOT successful.' ) );
            return $this->context->smarty->fetch( 'module:paysubs/views/templates/front/order_confirmation.tpl' );
        }
    }
}
