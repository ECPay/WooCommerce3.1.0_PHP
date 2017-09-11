<?php
    /**
     * @copyright Copyright (c) 2016 Green World FinTech Service Co., Ltd. (https://www.ecpay.com.tw)
     * @version 1.1.0901
     *
     * Plugin Name: ECPay Payment
     * Plugin URI: https://www.ecpay.com.tw
     * Description: ECPay Integration Payment Gateway for WooCommerce
     * Version: 1.1.0901
     * Author: ECPay Green World FinTech Service Co., Ltd. 
     * Author URI: https://www.ecpay.com.tw
     */

    add_action('plugins_loaded', 'ecpay_integration_plugin_init', 0);
    add_action('wp_footer', 'ecpay_integration_plugin_init_payment_method');

    function ecpay_integration_plugin_init() 
    {
        # Make sure WooCommerce is setted.
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }

        class WC_Gateway_ECPay extends WC_Payment_Gateway
        {
            public $ecpay_test_mode;
            public $ecpay_merchant_id;
            public $ecpay_hash_key;
            public $ecpay_hash_iv;
            public $ecpay_choose_payment;
            public $ecpay_payment_methods;
            public $ecpay_domain;
            
            public function __construct()
            {
                # Load the translation
                $this->ecpay_domain = 'ecpay';
                load_plugin_textdomain($this->ecpay_domain, false, '/ecpay/translation');
                
                # Initialize construct properties
                $this->id = 'ecpay';
                
                # Title of the payment method shown on the admin page
                $this->method_title = $this->tran('ECPay');
            
                # If you want to show an image next to the gateway’s name on the frontend, enter a URL to an image
                $this->icon = apply_filters('woocommerce_ecpay_icon', plugins_url('images/icon.png', __FILE__));
                
                # Bool. Can be set to true if you want payment fields to show on the checkout (if doing a direct integration)
                $this->has_fields = true;
                
                # Load the form fields
                $this->init_form_fields();
                
                # Load the administrator settings
                $this->init_settings();
                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description');
                $this->ecpay_test_mode = $this->get_option('ecpay_test_mode');
                $this->ecpay_merchant_id = $this->get_option('ecpay_merchant_id');
                $this->ecpay_hash_key = $this->get_option('ecpay_hash_key');
                $this->ecpay_hash_iv = $this->get_option('ecpay_hash_iv');
                $this->ecpay_payment_methods = $this->get_option('ecpay_payment_methods');
                
                # Register a action to save administrator settings
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                
                # Register a action to redirect to ECPay payment center
                add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
                
                # Register a action to process the callback
                add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'receive_response'));
            }
            
            /**
             * Initialise Gateway Settings Form Fields
             */
            public function init_form_fields ()
            {
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => $this->tran('Enable/Disable'),
                        'type' => 'checkbox',
                        'label' => $this->tran('Enable'),
                        'default' => 'no'
                    ),
                    'title' => array(
                        'title' => $this->tran('Title'),
                        'type' => 'text',
                        'description' => $this->tran('This controls the title which the user sees during checkout.'),
                        'default' => $this->tran('ECPay')
                    ),
                    'description' => array(
                        'title' => $this->tran('Description'),
                        'type' => 'textarea',
                        'description' => $this->tran('This controls the description which the user sees during checkout.')
                    ),
                    'ecpay_test_mode' => array(
                        'title' => $this->tran('Test Mode'),
                        'label' => $this->tran('Enable'),
                        'type' => 'checkbox',
                        'description' => $this->tran('Test order will add date as prefix.'),
                        'default' => 'no'
                    ),
                    'ecpay_merchant_id' => array(
                        'title' => $this->tran('Merchant ID'),
                        'type' => 'text',
                        'default' => '2000132'
                    ),
                    'ecpay_hash_key' => array(
                        'title' => $this->tran('Hash Key'),
                        'type' => 'text',
                        'default' => '5294y06JbISpM5x9'
                    ),
                    'ecpay_hash_iv' => array(
                        'title' => $this->tran('Hash IV'),
                        'type' => 'text',
                        'default' => 'v77hoKGq4kWxNNIS'
                    ),
                    'ecpay_payment_methods' => array(
                        'title' => $this->tran('Payment Method'),
                        'type' => 'multiselect',
                        'description' => $this->tran('Press CTRL and the right button on the mouse to select multi payments.'),
                        'options' => array(
                            'Credit' => $this->get_payment_desc('Credit'),
                            'Credit_3' => $this->get_payment_desc('Credit_3'),
                            'Credit_6' => $this->get_payment_desc('Credit_6'),
                            'Credit_12' => $this->get_payment_desc('Credit_12'),
                            'Credit_18' => $this->get_payment_desc('Credit_18'),
                            'Credit_24' => $this->get_payment_desc('Credit_24'),
                            'WebATM' => $this->get_payment_desc('WebATM'),
                            'ATM' => $this->get_payment_desc('ATM'),
                            'CVS' => $this->get_payment_desc('CVS'),
                            'BARCODE' => $this->get_payment_desc('BARCODE')
                        )
                    )
                );
            }
            
            /**
             * Set the admin title and description
             */
            public function admin_options()
            {
                echo $this->add_next_line('  <h3>' . $this->tran('ECPay Integration Payments') . '</h3>');
                echo $this->add_next_line('  <p>' . $this->tran('ECPay is the most popular payment gateway for online shopping in Taiwan') . '</p>');
                echo $this->add_next_line('  <table class="form-table">');
                
                # Generate the HTML For the settings form.
                $this->generate_settings_html();
                echo $this->add_next_line('  </table>');
            }
            
            /**
             * Display the form when chooses ECPay payment
             */
            public function payment_fields()
            {
                if (!empty($this->description)) {
                    echo $this->add_next_line($this->description . '<br /><br />');
                }
                echo $this->tran('Payment Method') . ' : ';
                echo $this->add_next_line('<select name="ecpay_choose_payment">');
                foreach ($this->ecpay_payment_methods as $payment_method) {
                    echo $this->add_next_line('  <option value="' . $payment_method . '">');
                    echo $this->add_next_line('    ' . $this->get_payment_desc($payment_method));
                    echo $this->add_next_line('  </option>');
                }
                echo $this->add_next_line('</select>');
            }
            
            /**
             * Check the payment method and the chosen payment
             */
            public function validate_fields()
            {
                $choose_payment = $_POST['ecpay_choose_payment'];
                $payment_desc = $this->get_payment_desc($choose_payment);
                if ($_POST['payment_method'] == $this->id && !empty($payment_desc)) {
                    $this->ecpay_choose_payment = $choose_payment;
                    return true;
                } else {
                    $this->ECPay_add_error($this->tran('Invalid payment method.'));
                    return false;
                }
            }
            
            /**
             * Process the payment
             */
            public function process_payment($order_id)
            {
                # Update order status
                $order = new WC_Order($order_id);
                $order->update_status('pending', $this->tran('Awaiting ECPay payment'));
                
                # Set the ECPay payment type to the order note
                $order->add_order_note($this->ecpay_choose_payment, true);
                
                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true)
                );
            }
            
            /**
             * Redirect to ECPay
             */
            public function receipt_page($order_id)
            {
                # Clean the cart
                global $woocommerce;
                $woocommerce->cart->empty_cart();
                $order = new WC_Order($order_id);
                
                try {
                    $this->invoke_ecpay_module();
                    $aio = new ECPay_AllInOne();
                    $aio->Send['MerchantTradeNo'] = '';
                    $service_url = '';
                    if ($this->ecpay_test_mode == 'yes') {
                        $service_url = 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut';
                        $aio->Send['MerchantTradeNo'] = date('YmdHis');
                    } else {
                        $service_url = 'https://payment.ecpay.com.tw/Cashier/AioCheckOut';
                    }
                    $aio->MerchantID = $this->ecpay_merchant_id;
                    $aio->HashKey = $this->ecpay_hash_key;
                    $aio->HashIV = $this->ecpay_hash_iv;
                    $aio->ServiceURL = $service_url;
                    $aio->Send['ReturnURL'] = add_query_arg('wc-api', 'WC_Gateway_ECPay', home_url('/'));
                    $aio->Send['ClientBackURL'] = home_url('?page_id=' . get_option('woocommerce_myaccount_page_id') . '&view-order=' . $order->id);;
                    $aio->Send['MerchantTradeNo'] .= $order->id;
                    $aio->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');
                    
                    # Set the product info
                    $aio->Send['TotalAmount'] = $order->get_total();
                    array_push(
                        $aio->Send['Items'],
                        array(
                            'Name' => '網路商品一批',
                            'Price' => $aio->Send['TotalAmount'],
                            'Currency' => $order->get_order_currency(),
                            'Quantity' => 1
                        )
                    );
                    
                    $aio->Send['TradeDesc'] = 'ecpay_module_woocommerce_1_1_0901';
                    
                    # Get the chosen payment and installment
                    $notes = $order->get_customer_order_notes();
                    $choose_payment = '';
                    $choose_installment = '';
                    if (isset($notes[0])) {
                        list($choose_payment, $choose_installment) = explode('_', $notes[0]->comment_content);
                    }
                    $aio->Send['ChoosePayment'] = $choose_payment;
                    
                    # Set the extend information
                    switch ($aio->Send['ChoosePayment']) {
                        case 'Credit':
                            # Do not support UnionPay
                            $aio->SendExtend['UnionPay'] = false;
                            
                            # Credit installment parameters
                            if (!empty($choose_installment)) {
                                $aio->SendExtend['CreditInstallment'] = $choose_installment;
                                $aio->SendExtend['InstallmentAmount'] = $aio->Send['TotalAmount'];
                                $aio->SendExtend['Redeem'] = false;
                            }
                            break;
                        case 'WebATM':
                            break;
                        case 'ATM':
                            $aio->SendExtend['ExpireDate'] = 3;
                            $aio->SendExtend['PaymentInfoURL'] = $aio->Send['ReturnURL'];
                            break;
                        case 'CVS':
                        case 'BARCODE':
                            $aio->SendExtend['Desc_1'] = '';
                            $aio->SendExtend['Desc_2'] = '';
                            $aio->SendExtend['Desc_3'] = '';
                            $aio->SendExtend['Desc_4'] = '';
                            $aio->SendExtend['PaymentInfoURL'] = $aio->Send['ReturnURL'];
                            break;
                        default:
                            throw new Exception($this->tran('Invalid payment method.'));
                            break;
                    }
                    $aio->CheckOut();
                    exit;
                } catch(Exception $e) {
                    $this->ECPay_add_error($e->getMessage());
                }
            }
            
            /**
             * Process the callback
             */
            public function receive_response()
            {
                $result_msg = '1|OK';
                $order = null;
                try {
                    # Retrieve the check out result
                    $this->invoke_ecpay_module();
                    $aio = new ECPay_AllInOne();
                    $aio->HashKey = $this->ecpay_hash_key;
                    $aio->HashIV = $this->ecpay_hash_iv;
                    $aio->MerchantID = $this->ecpay_merchant_id;
                    $ecpay_feedback = $aio->CheckOutFeedback();
                    unset($aio);
                    if(count($ecpay_feedback) < 1) {
                        throw new Exception('Get ECPay feedback failed.');
                    } else {
                        # Get the cart order id
                        $cart_order_id = $ecpay_feedback['MerchantTradeNo'];
                        if ($this->ecpay_test_mode == 'yes') {
                            $cart_order_id = substr($ecpay_feedback['MerchantTradeNo'], 14);
                        }

                        # Get the cart order amount
                        $order = new WC_Order($cart_order_id);
                        $cart_amount = $order->get_total();

                        # Check the amounts
                        $ecpay_amount = $ecpay_feedback['TradeAmt'];
                        if (round($cart_amount) != $ecpay_amount) {
                            throw new Exception('Order ' . $cart_order_id . ' amount are not identical.');
                        }
                        else
                        {
                            # Set the common comments
                            $comments = sprintf(
                                $this->tran('Payment Method : %s<br />Trade Time : %s<br />'),
                                $ecpay_feedback['PaymentType'],
                                $ecpay_feedback['TradeDate']
                            );
                            
                            # Set the getting code comments
                            $return_code = $ecpay_feedback['RtnCode'];
                            $return_message = $ecpay_feedback['RtnMsg'];
                            $get_code_result_comments = sprintf(
                                $this->tran('Getting Code Result : (%s)%s'),
                                $return_code,
                                $return_message
                            );
                            
                            # Set the payment result comments
                            $payment_result_comments = sprintf(
                                $this->tran('Payment Result : (%s)%s'),
                                $return_code,
                                $return_message
                            );
                            
                            # Set the fail message
                            $fail_message = sprintf('Order %s Exception.(%s: %s)', $cart_order_id, $return_code, $return_message);
                            
                            # Get ECPay payment method
                            $ecpay_payment_method = $this->get_payment_method($ecpay_feedback['PaymentType']);

                            # Set the order comments
                            switch($ecpay_payment_method) {
                                case ECPay_PaymentMethod::Credit:
                                case ECPay_PaymentMethod::WebATM:
                                    if ($return_code != 1 and $return_code != 800) {
                                        throw new Exception($fail_msg);
                                    } else {
                                        if (!$this->is_order_complete($order)) {
                                            $this->confirm_order($order, $payment_result_comments);
                                        } else {
                                            # The order already paid or not in the standard procedure, do nothing
                                        }
                                    }
                                    break;
                                case ECPay_PaymentMethod::ATM:
                                    if ($return_code != 1 and $return_code != 2 and $return_code != 800) {
                                        throw new Exception($fail_msg);
                                    } else {
                                        if ($return_code == 2) {
                                            # Set the getting code result
                                            $comments .= $this->get_order_comments($ecpay_feedback);
                                            $comments .= $get_code_result_comments;
                                            $order->add_order_note($comments);
                                        } else {
                                            if (!$this->is_order_complete($order)) {
                                                $this->confirm_order($order, $payment_result_comments);
                                            } else {
                                                # The order already paid or not in the standard procedure, do nothing
                                            }
                                        }
                                    }
                                    break;
                                case ECPay_PaymentMethod::CVS:
                                case ECPay_PaymentMethod::BARCODE:
                                    if ($return_code != 1 and $return_code != 800 and $return_code != 10100073) {
                                        throw new Exception($fail_msg);
                                    } else {
                                        if ($return_code == 10100073) {
                                            # Set the getting code result
                                            $comments .= $this->get_order_comments($ecpay_feedback);
                                            $comments .= $get_code_result_comments;
                                            $order->add_order_note($comments);
                                        } else {
                                            if (!$this->is_order_complete($order)) {
                                                $this->confirm_order($order, $payment_result_comments);
                                            } else {
                                                # The order already paid or not in the standard procedure, do nothing
                                            }
                                        }
                                    }
                                    break;
                                default:
                                    throw new Exception('Invalid payment method of the order ' . $cart_order_id . '.');
                                    break;
                            }
                        }
                    }
                } catch (Exception $e) {
                    $error = $e->getMessage();
                    if (!empty($order)) {
                        $comments .= sprintf($this->tran('Faild To Pay<br />Error : %s<br />'), $error);
                        $order->update_status('failed', $comments);
                    }
                    
                    # Set the failure result
                    $result_msg = '0|' . $error;
                }
                echo $result_msg;
                exit;
            }
            
            # Custom functin
            
            /**
             * Translate the content
             * @param  string   translate target
             * @return string   translate result
             */
            private function tran($content)
            {
                return __($content, $this->ecpay_domain);
            }
            
            /**
             * Get the payment method description
             * @param  string   payment name
             * @return string   payment method description
             */
            private function get_payment_desc($payment_name)
            {
                $payment_desc = array(
                    'Credit' => $this->tran('Credit'),
                    'Credit_3' => $this->tran('Credit(3 Installments)'),
                    'Credit_6' => $this->tran('Credit(6 Installments)'),
                    'Credit_12' => $this->tran('Credit(12 Installments)'),
                    'Credit_18' => $this->tran('Credit(18 Installments)'),
                    'Credit_24' => $this->tran('Credit(24 Installments)'),
                    'WebATM' => $this->tran('WEB-ATM'),
                    'ATM' => $this->tran('ATM'),
                    'CVS' => $this->tran('CVS'),
                    'BARCODE' => $this->tran('BARCODE')
                );
                
                return $payment_desc[$payment_name];
            }
            
            /**
             * Add a next line character
             * @param  string   content
             * @return string   content with next line character
             */
            private function add_next_line($content)
            {
                return $content . "\n";
            }
            
            /**
             * Invoke ECPay module
             */
            private function invoke_ecpay_module()
            {
                if (!class_exists('ECPay_AllInOne')) {
                    if (!require(plugin_dir_path(__FILE__) . '/lib/ECPay.Payment.Integration.php')) {
                        throw new Exception($this->tran('ECPay module missed.'));
                    }
                }
            }
            
            /**
             * Format the version description
             * @param  string   version string
             * @return string   version description
             */
            private function format_version_desc($version)
            {
                return str_replace('.', '_', $version);
            }
            
            /**
             * Add a WooCommerce error message
             * @param  string   error message
             */
            private function ECPay_add_error($error_message)
            {
                wc_add_notice($error_message, 'error');
            }
            
            /**
             * Check if the order status is complete
             * @param  object   order
             * @return boolean  is the order complete
             */
            private function is_order_complete($order)
            {
                $status = '';
                $status = (method_exists($Order,'get_status') == true )? $order->get_status(): $order->status;

                if ($status == 'pending') {
                    return false;
                } else {
                    return true;
                }
            }
            
            /**
             * Get the payment method from the payment_type
             * @param  string   payment type
             * @return string   payment method
             */
            private function get_payment_method($payment_type)
            {
                $info_pieces = explode('_', $payment_type);
                
                return $info_pieces[0];
            }
            
            /**
             * Get the order comments
             * @param  array    ECPay feedback
             * @return string   order comments
             */
            function get_order_comments($ecpay_feedback)
            {
                $comments = array(
                    'ATM' => 
                        sprintf(
                          $this->tran('Bank Code : %s<br />Virtual Account : %s<br />Payment Deadline : %s<br />'),
                            $ecpay_feedback['BankCode'],
                            $ecpay_feedback['vAccount'],
                            $ecpay_feedback['ExpireDate']
                        ),
                    'CVS' => 
                        sprintf(
                            $this->tran('Trade Code : %s<br />Payment Deadline : %s<br />'),
                            $ecpay_feedback['PaymentNo'],
                            $ecpay_feedback['ExpireDate']
                        ),
                    'BARCODE' => 
                        sprintf(
                            $this->tran('Payment Deadline : %s<br />BARCODE 1 : %s<br />BARCODE 2 : %s<br />BARCODE 3 : %s<br />'),
                            $ecpay_feedback['ExpireDate'],
                            $ecpay_feedback['Barcode1'],
                            $ecpay_feedback['Barcode2'],
                            $ecpay_feedback['Barcode3']
                        )
                );
                $payment_method = $this->get_payment_method($ecpay_feedback['PaymentType']);
                
                return $comments[$payment_method];
            }
            
            /**
             * Complete the order and add the comments
             * @param  object   order
             */
            function confirm_order($order, $comments)
            {
                $order->add_order_note($comments, true);
                $order->payment_complete();

                // call invoice model
                $invoice_active_ecpay = 0 ;
                $invoice_active_allpay = 0 ;

                $active_plugins = (array) get_option( 'active_plugins', array() );

                $active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );

                foreach ($active_plugins as $key => $value) {
                    if ((strpos($value,'/woocommerce-ecpayinvoice.php') !== false)) {
                        $invoice_active_ecpay = 1;
                    }

                    if ((strpos($value,'/woocommerce-allpayinvoice.php') !== false)) {
                        $invoice_active_allpay = 1;
                    }
                }

                if ($invoice_active_ecpay == 0 && $invoice_active_allpay == 1) { // allpay
                
                    if (is_file( get_home_path().'/wp-content/plugins/allpay_invoice/woocommerce-allpayinvoice.php') ) {
                        $aConfig_Invoice = get_option('wc_allpayinvoice_active_model') ;

                        if (isset($aConfig_Invoice) && $aConfig_Invoice['wc_allpay_invoice_enabled'] == 'enable' && $aConfig_Invoice['wc_allpay_invoice_auto'] == 'auto' ) {
                            do_action('allpay_auto_invoice', $order->id, $ecpay_feedback['SimulatePaid']);
                        }
                    }
                } elseif ($invoice_active_ecpay == 1 && $invoice_active_allpay == 0) { //ecpay
                
                    if (is_file( get_home_path().'/wp-content/plugins/ecpay_invoice/woocommerce-ecpayinvoice.php') ) {
                        $aConfig_Invoice = get_option('wc_ecpayinvoice_active_model') ;

                        if (isset($aConfig_Invoice) && $aConfig_Invoice['wc_ecpay_invoice_enabled'] == 'enable' && $aConfig_Invoice['wc_ecpay_invoice_auto'] == 'auto' ) {
                            do_action('ecpay_auto_invoice', $order->id, $ecpay_feedback['SimulatePaid']);
                        }
                    }
                }
            }
        }
        
        class WC_Gateway_Ecpay_DCA extends WC_Payment_Gateway
        {
            public $ecpay_test_mode;
            public $ecpay_merchant_id;
            public $ecpay_hash_key;
            public $ecpay_hash_iv;
            public $ecpay_choose_payment;
            public $ecpay_domain;
            public $ecpay_dca_payment;

            public function __construct()
            {
                # Load the translation
                $this->ecpay_domain = 'ecpay_dca';

                # Initialize construct properties
                $this->id = 'ecpay_dca';

                # If you want to show an image next to the gateway’s name on the frontend, enter a URL to an image
                $this->icon = '';

                # Bool. Can be set to true if you want payment fields to show on the checkout (if doing a direct integration)
                $this->has_fields = true;

                # Title of the payment method shown on the admin page
                $this->method_title = __('ECPay Paid Automatically', 'ecpay');
                $this->method_description = __('Enable to use ECPay Paid Automatically', 'ecpay');

                # Load the form fields
                $this->init_form_fields();

                # Load the administrator settings
                $this->init_settings();
                $this->title = $this->get_option( 'title' );

                $admin_options = get_option('woocommerce_ecpay_settings');
                $this->ecpay_test_mode = $admin_options['ecpay_test_mode'];
                $this->ecpay_merchant_id = $admin_options['ecpay_merchant_id'];
                $this->ecpay_hash_key = $admin_options['ecpay_hash_key'];
                $this->ecpay_hash_iv = $admin_options['ecpay_hash_iv'];
                $this->ecpay_dca_payment = $this->getEcpayDcaPayment();

                $this->ecpay_dca = get_option( 'woocommerce_ecpay_dca',
                    array(
                        array(
                            'periodType' => $this->get_option( 'periodType' ),
                            'frequency' => $this->get_option( 'frequency' ),
                            'execTimes' => $this->get_option( 'execTimes' ),
                        ),
                    )
                );

                # Register a action to save administrator settings
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_dca_details' ) );
                
                # Register a action to redirect to ECPay payment center
                add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
                
                # Register a action to process the callback
                add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'receive_response'));
            }
            
            /**
             * Initialise Gateway Settings Form Fields
             */
            public function init_form_fields()
            {
                $this->form_fields = array(
                    'enabled' => array(
                        'title'   => __( 'Enable/Disable', 'woocommerce' ),
                        'type'    => 'checkbox',
                        'label'   => __('Enable ECPay Paid Automatically', 'ecpay'),
                        'default' => 'no'
                    ),
                    'title' => array(
                        'title'       => __( 'Title', 'woocommerce' ),
                        'type'        => 'text',
                        'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                        'default'     => __('ECPay Paid Automatically', 'ecpay'),
                        'desc_tip'    => true,
                    ),
                    'ecpay_dca' => array(
                        'type'        => 'ecpay_dca'
                    ),
                );
            }

            public function generate_ecpay_dca_html()
            {
                ob_start();

                ?>
                <tr valign="top">
                    <th scope="row" class="titledesc"><?php echo __('ECPay Paid Automatically Details', 'ecpay'); ?></th>
                    <td class="forminp" id="ecpay_dca">
                        <table class="widefat wc_input_table sortable" cellspacing="0" style="width: 600px;">
                            <thead>
                                <tr>
                                    <th class="sort">&nbsp;</th>
                                    <th><?php echo __('Peroid Type', 'ecpay'); ?></th>
                                    <th><?php echo __('Frequency', 'ecpay'); ?></th>
                                    <th><?php echo __('Execute Times', 'ecpay'); ?></th>
                                </tr>
                            </thead>
                            <tbody class="accounts">
                                <?php
                                    if (
                                        sizeof($this->ecpay_dca) === 1
                                        && $this->ecpay_dca[0]["periodType"] === ''
                                        && $this->ecpay_dca[0]["frequency"] === ''
                                        && $this->ecpay_dca[0]["execTimes"] === ''
                                    ) {
                                        // 初始預設定期定額方式
                                        $this->ecpay_dca = [
                                            [
                                                'periodType' => "Y",
                                                'frequency' => "1",
                                                'execTimes' => "6",
                                            ],
                                            [
                                                'periodType' => "M",
                                                'frequency' => "1",
                                                'execTimes' => "12",
                                            ],
                                        ];
                                    }

                                    $i = -1;
                                    if ( is_array($this->ecpay_dca) ) {
                                        foreach ( $this->ecpay_dca as $dca ) {
                                            $i++;
                                            echo '<tr class="account">
                                                <td class="sort"></td>
                                                <td><input type="text" class="fieldPeriodType" value="' . esc_attr( $dca['periodType'] ) . '" name="periodType[' . $i . ']" required /></td>
                                                <td><input type="number" class="fieldFrequency" value="' . esc_attr( $dca['frequency'] ) . '" name="frequency[' . $i . ']" required /></td>
                                                <td><input type="number" class="fieldExecTimes" value="' . esc_attr( $dca['execTimes'] ) . '" name="execTimes[' . $i . ']" required /></td>
                                            </tr>';
                                        }
                                    }
                                ?>
                            </tbody>
                            <?php /* 動態新增/刪除 定期定額方式 ?>
                            <tfoot>
                                <tr>
                                    <th colspan="4">
                                        <a href="#" class="add button"><?php echo __('add', 'ecpay'); ?></a>
                                        <a href="#" class="remove_rows button"><?php echo __('remove', 'ecpay'); ?></a>
                                    </th>
                                </tr>
                            </tfoot>
                            <?php */ ?>
                        </table>
                        <p class="description"><?php echo __('Don\'t forget to save modify.', 'ecpay'); ?></p>
                        <script type="text/javascript">
                            jQuery(function() {
                                jQuery('#ecpay_dca').on( 'click', 'a.add', function() {
                                    var size = jQuery('#ecpay_dca').find('tbody .account').length;

                                    jQuery('<tr class="account">\
                                            <td class="sort"></td>\
                                            <td><input type="text" class="fieldPeriodType" name="periodType[' + size + ']" required /></td>\
                                            <td><input type="number" class="fieldFrequency" name="frequency[' + size + ']" required /></td>\
                                            <td><input type="number" class="fieldExecTimes" name="execTimes[' + size + ']" required /></td>\
                                        </tr>').appendTo('#ecpay_dca table tbody');

                                    return false;
                                });

                                jQuery('#ecpay_dca').on( 'blur', 'input', function() {
                                    var size = jQuery('#ecpay_dca').find('tbody .account').length;

                                    var fieldPeriodType = document.getElementsByClassName('fieldPeriodType');
                                    var fieldFrequency = document.getElementsByClassName('fieldFrequency');
                                    var fieldExecTimes = document.getElementsByClassName('fieldExecTimes');

                                    for (var i = 0; i < size; i++) {
                                        if (
                                            fieldPeriodType[i].value.length !== 0
                                            && fieldFrequency[i].value.length !== 0
                                            && fieldExecTimes[i].value.length !== 0
                                        ) {
                                            if (validateFields.periodType(fieldPeriodType[i].value) === false) {
                                                alert('<?php echo __('Invalid Peroid Type.', 'ecpay'); ?>');
                                            }
                                            if (validateFields.frequency(fieldPeriodType[i].value, fieldFrequency[i].value) === false) {
                                                alert('<?php echo __('Invalid Frequency.', 'ecpay'); ?>');
                                            }
                                            if (validateFields.execTimes(fieldPeriodType[i].value, fieldExecTimes[i].value) === false) {
                                                alert('<?php echo __('Invalid Execute Times.', 'ecpay'); ?>');
                                            }
                                        }
                                    }
                                });
                            });

                            var data = {
                                'periodType': ['D', 'M', 'Y'],
                                'frequency': ['365', '12', '1'],
                                'execTimes': ['999', '99', '9']
                            };

                            var validateFields = {
                                periodType: function(field) {
                                    return (data.periodType.indexOf(field) != -1);
                                },
                                frequency: function(periodType, field) {
                                    let maxFrequency = parseInt(data.frequency[data.periodType.indexOf(periodType)], 10);
                                    return ((field > 0) && ((maxFrequency + 1) > field));
                                },
                                execTimes: function(periodType, field) {
                                    let maxExecTimes = parseInt(data.execTimes[data.periodType.indexOf(periodType)], 10);
                                    return ((field > 1) && ((maxExecTimes + 1) > field));
                                }
                            };
                        </script>
                    </td>
                </tr>
                <?php
                return ob_get_clean();
            }

            /**
             * Save account details table.
             */
            public function save_dca_details()
            {
                $ecpayDca = array();

                if ( isset( $_POST['periodType'] ) ) {

                    $periodTypes = array_map( 'wc_clean', $_POST['periodType'] );
                    $frequencys = array_map( 'wc_clean', $_POST['frequency'] );
                    $execTimes = array_map( 'wc_clean', $_POST['execTimes'] );

                    foreach ( $periodTypes as $i => $name ) {
                        if ( ! isset( $periodTypes[ $i ] ) ) {
                            continue;
                        }

                        $ecpayDca[] = array(
                            'periodType' => $periodTypes[ $i ],
                            'frequency' => $frequencys[ $i ],
                            'execTimes' => $execTimes[ $i ],
                        );
                    }
                }

                update_option( 'woocommerce_ecpay_dca', $ecpayDca );
            }

            /**
             * Display the form when chooses ECPay payment
             */
            public function payment_fields()
            {
                global $woocommerce;
                $ecpayDCA = get_option('woocommerce_ecpay_dca');
                $periodTypeMethod = [
                    'Y' => ' ' . __('year', 'ecpay'),
                    'M' => ' ' . __('month', 'ecpay'),
                    'D' => ' ' . __('day', 'ecpay')
                ];
                $ecpay = '';
                foreach ($ecpayDCA as $dca) {
                    $option = sprintf(
                            __('NT$ %d / %s %s, up to a maximun of %s', 'ecpay'),
                            (int)$woocommerce->cart->total,
                            $dca['frequency'],
                            $periodTypeMethod[$dca['periodType']],
                            $dca['execTimes']
                        );
                    $ecpay .= '
                        <option value="' . $dca['periodType'] . '_' . $dca['frequency'] . '_' . $dca['execTimes'] . '">
                            ' . $option . '
                        </option>';
                }
                echo '
                    <select id="ecpay_dca_payment" name="ecpay_dca_payment">
                        <option>------</option>
                        ' . $ecpay . '
                    </select>
                    <div id="ecpay_dca_show"></div>
                    <hr style="margin: 12px 0px;background-color: #eeeeee;">
                    <p style="font-size: 0.8em;color: #c9302c;">
                        你將使用<strong>綠界科技定期定額信用卡付款</strong>，請留意你所購買的商品為<strong>非單次扣款</strong>商品。
                    </p>
                ';
            }

            public function getEcpayDcaPayment()
            {
                global $woocommerce;
                $ecpayDCA = get_option('woocommerce_ecpay_dca');
                $ecpay = [];
                if (is_array($ecpayDCA)) {
                    foreach ($ecpayDCA as $dca) {
                        array_push($ecpay, $dca['periodType'] . '_' . $dca['frequency'] . '_' . $dca['execTimes']);
                    }
                }

                return $ecpay;
            }

            /**
             * Translate the content
             * @param  string   translate target
             * @return string   translate result
             */
            private function tran($content)
            {
                return __($content, $this->ecpay_domain);
            }

            /**
             * Invoke ECPay module
             */
            private function invoke_ecpay_module()
            {
                if (!class_exists('ECPay_AllInOne')) {
                    if (!require(plugin_dir_path(__FILE__) . '/lib/ECPay.Payment.Integration.php')) {
                        throw new Exception($this->tran('ECPay module missed.'));
                    }
                }
            }

            /**
             * Check the payment method and the chosen payment
             */
            public function validate_fields()
            {
                $choose_payment = $_POST['ecpay_dca_payment'];

                if ($_POST['payment_method'] == $this->id && in_array($choose_payment, $this->ecpay_dca_payment)) {
                    $this->ecpay_choose_payment = $choose_payment;
                    return true;
                } else {
                    $this->ECPay_add_error($this->tran('Invalid payment method.'));
                    return false;
                }
            }

            /**
             * Add a WooCommerce error message
             * @param  string   error message
             */
            private function ECPay_add_error($error_message)
            {
                wc_add_notice($error_message, 'error');
            }
            
            /**
             * Process the payment
             */
            public function process_payment($order_id)
            {
                # Update order status
                $order = new WC_Order($order_id);
                $order->update_status('pending', $this->tran('Awaiting ECPay payment'));
                
                # Set the ECPay payment type to the order note
                $order->add_order_note('Credit_' . $this->ecpay_choose_payment, true);
                
                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true)
                );
            }

            /**
             * Redirect to ECPay
             */
            public function receipt_page($order_id)
            {
                # Clean the cart
                global $woocommerce;
                $woocommerce->cart->empty_cart();
                $order = new WC_Order($order_id);

                try {
                    $this->invoke_ecpay_module();
                    $aio = new ECPay_AllInOne();
                    $aio->Send['MerchantTradeNo'] = '';
                    $service_url = '';
                    if ($this->ecpay_test_mode == 'yes') {
                        $service_url = 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut';
                        $aio->Send['MerchantTradeNo'] = date('YmdHis');
                    } else {
                        $service_url = 'https://payment.ecpay.com.tw/Cashier/AioCheckOut';
                    }
                    $aio->MerchantID = $this->ecpay_merchant_id;
                    $aio->HashKey = $this->ecpay_hash_key;
                    $aio->HashIV = $this->ecpay_hash_iv;
                    $aio->ServiceURL = $service_url;
                    $aio->Send['ReturnURL'] = add_query_arg('wc-api', 'WC_Gateway_ECPay', home_url('/'));
                    $aio->Send['ClientBackURL'] = home_url('?page_id=' . get_option('woocommerce_myaccount_page_id') . '&view-order=' . $order->get_id());;
                    $aio->Send['MerchantTradeNo'] .= $order->get_id();
                    $aio->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');
                    
                    # Set the product info
                    $aio->Send['TotalAmount'] = $order->get_total();
                    array_push(
                        $aio->Send['Items'],
                        array(
                            'Name' => '網路商品一批',
                            'Price' => $aio->Send['TotalAmount'],
                            'Currency' => $order->get_order_currency(),
                            'Quantity' => 1
                        )
                    );
                    
                    $aio->Send['TradeDesc'] = 'ecpay_module_woocommerce_1_1_0901';
                    $notes = $order->get_customer_order_notes();
                    $PeriodType = '';
                    $Frequency = '';
                    $ExecTimes = '';
                    if (isset($notes[0])) {
                        list($ChoosePayment, $PeriodType, $Frequency, $ExecTimes) = explode('_', $notes[0]->comment_content);
                    }
                    $aio->Send['ChoosePayment'] = 'Credit';
                    $aio->SendExtend['UnionPay'] = false;
                    $aio->SendExtend['PeriodAmount'] = $aio->Send['TotalAmount'];
                    $aio->SendExtend['PeriodType'] = $PeriodType;
                    $aio->SendExtend['Frequency'] = $Frequency;
                    $aio->SendExtend['ExecTimes'] = $ExecTimes;
                    $aio->SendExtend['PeriodReturnURL'] = add_query_arg('wc-api', 'WC_Gateway_ECPay', home_url('/'));
                    $aio->CheckOut();
                    exit;
                } catch(Exception $e) {
                    $this->ECPay_add_error($e->getMessage());
                }
            }
            
            /**
             * Process the callback
             */
            public function receive_response()
            {
                $response = $_REQUEST;

                //若為測試模式，拆除時間參數
                $MerchantTradeNo = (($response['MerchantID']=='2000132') || ($response['MerchantID']=='2000933')) ? strrev(substr(strrev($response['MerchantTradeNo']), 10)) : $response['MerchantTradeNo'];

                if (isset($response['AllPayLogisticsID'])) {
                    $this->storeLogisticMeta($response);
                }

                if (!empty($response['CVSStoreName']) && !empty($response['CVSAddress']))
                    $this->receive_changeStore_response($response);
                
                $order = wc_get_order( $MerchantTradeNo );
                $order->add_order_note(print_r($response, true));

                if ($response['RtnCode'] == '300' || $response['RtnCode'] == '2001') {
                    $order->update_status( 'ecpay', "商品已出貨" );
                }

                if (get_post_meta( $MerchantTradeNo, '_payment_method', true ) == 'ecpay_dca') {
                    if ($response['RtnCode'] == '2067' || $response['RtnCode'] == '3022') {
                        $order->update_status( 'processing', "處理中" );

                        // call invoice model
                        $invoice_active_ecpay   = 0 ;
                        $invoice_active_allpay  = 0 ;

                        $active_plugins = (array) get_option( 'active_plugins', array() );

                        $active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );

                        foreach ($active_plugins as $key => $value) {
                            if ((strpos($value,'/woocommerce-ecpayinvoice.php') !== false)) {
                                $invoice_active_ecpay = 1;
                            }

                            if ((strpos($value,'/woocommerce-allpayinvoice.php') !== false)) {
                                $invoice_active_allpay = 1;
                            }
                        }

                        if ($invoice_active_ecpay == 0 && $invoice_active_allpay == 1) { // allpay
                            if ( is_file( get_home_path().'/wp-content/plugins/allpay_invoice/woocommerce-allpayinvoice.php') ) {
                                $aConfig_Invoice = get_option('wc_allpayinvoice_active_model') ;

                                if (isset($aConfig_Invoice) && $aConfig_Invoice['wc_allpay_invoice_enabled'] == 'enable' && $aConfig_Invoice['wc_allpay_invoice_auto'] == 'auto' ) {
                                    do_action('allpay_auto_invoice', $order->id, $ecpay_feedback['SimulatePaid']);
                                }
                            }
                        } elseif ($invoice_active_ecpay == 1 && $invoice_active_allpay == 0) { // ecpay
                            if ( is_file( get_home_path().'/wp-content/plugins/ecpay_invoice/woocommerce-ecpayinvoice.php') ) {
                                $aConfig_Invoice = get_option('wc_ecpayinvoice_active_model') ;

                                if (isset($aConfig_Invoice) && $aConfig_Invoice['wc_ecpay_invoice_enabled'] == 'enable' && $aConfig_Invoice['wc_ecpay_invoice_auto'] == 'auto' ) {
                                    do_action('ecpay_auto_invoice', $order->id, $ecpay_feedback['SimulatePaid']);
                                }
                            }
                        }
                    }
                }
                echo '1|OK';
                exit;
            }
        }

        /**
         * Add the Gateway Plugin to WooCommerce
         * */
        function woocommerce_add_ecpay_plugin($methods)
        {
            $methods[] = 'WC_Gateway_ECPay';
            $methods[] = 'WC_Gateway_Ecpay_DCA';
            return $methods;
        }

        add_filter('woocommerce_payment_gateways', 'woocommerce_add_ecpay_plugin');
    }
    
    function ecpay_integration_plugin_init_payment_method()
    {
        ?>
        <script>
            (function() {
                if (
                    document.getElementById("shipping_option") !== null && 
                    typeof document.getElementById("shipping_option") !== "undefined"
                ) {
                    if (window.addEventListener) {
                        window.addEventListener('DOMContentLoaded', initPaymentMethod, false);
                    } else {
                        window.attachEvent('onload', initPaymentMethod);
                    }
                }
            })();
            function initPaymentMethod() {
                var e = document.getElementById("shipping_option");
                var shipping = e.options[e.selectedIndex].value;
                var payment = document.getElementsByName('payment_method');
                
                if (
                    shipping == "HILIFE_Collection" ||
                    shipping == "FAMI_Collection" ||
                    shipping == "UNIMART_Collection"
                ) {
                    var i;
                   
                    for (i = 0; i< payment.length; i++) {
                        if (payment[i].id != 'payment_method_ecpay_shipping_pay') {
                            payment[i].style.display="none";

                            checkclass = document.getElementsByClassName("wc_payment_method " + payment[i].id).length;

                            if (checkclass == 0) {
                                var x = document.getElementsByClassName(payment[i].id);
                                x[0].style.display = "none";
                            } else {
                                var x = document.getElementsByClassName("wc_payment_method " + payment[i].id);
                                x[0].style.display = "none";
                            }
                        } else {
                            checkclass = document.getElementsByClassName("wc_payment_method " + payment[i].id).length;

                            if (checkclass == 0) {
                                var x = document.getElementsByClassName(payment[i].id);
                                x[0].style.display = "";
                            } else {
                                var x = document.getElementsByClassName("wc_payment_method " + payment[i].id);
                                x[0].style.display = "";
                            }
                        }
                    }
                    document.getElementById('payment_method_ecpay').checked = false;
                    document.getElementById('payment_method_ecpay_shipping_pay').checked = true;
                    document.getElementById('payment_method_ecpay_shipping_pay').style.display = '';
                } else {
                    var i;
                    for (i = 0; i< payment.length; i++) {
                        if (payment[i].id != 'payment_method_ecpay_shipping_pay') {
                            payment[i].style.display=""; 

                            checkclass = document.getElementsByClassName("wc_payment_method " + payment[i].id).length;

                            if (checkclass == 0) {
                                var x = document.getElementsByClassName(payment[i].id);
                                x[0].style.display = "";
                            } else {
                                var x = document.getElementsByClassName("wc_payment_method " + payment[i].id);
                                x[0].style.display = "";
                            }
                        } else {
                            checkclass = document.getElementsByClassName("wc_payment_method " + payment[i].id).length;

                            if (checkclass == 0) {
                                var x = document.getElementsByClassName(payment[i].id);
                                x[0].style.display = "none";
                            } else {
                                var x = document.getElementsByClassName("wc_payment_method " + payment[i].id);
                                x[0].style.display = "none";
                            }

                            document.getElementById('payment_method_ecpay').checked = true;
                            document.getElementById('payment_method_ecpay_shipping_pay').checked = false;
                            document.getElementById('payment_method_ecpay_shipping_pay').style.display = "none";
                        }
                    }
                }
            }
        </script>
        <?php
    }
?>