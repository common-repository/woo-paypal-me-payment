<?php

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))))
    return;
/*
Plugin Name: Woocommerce Paypal Me Payment
Description: Use Paypal Me as payment mehtod in your Woocommerce store.
Version: 1.0.0
Author: Dennis Wuepping
 */
    add_action('plugins_loaded', 'bit_paypal_me_payment', 20);
    add_action('wp_enqueue_scripts', "add_paypal_me_style"); // Add Frontend CSS
    add_action('admin_enqueue_scripts', 'add_paypal_me_style'); // Add Backend CSS
    add_filter('woocommerce_payment_gateways', 'add_paypal_me_payment');
function bit_paypal_me_payment()
{


    class BIT_Paypal_Me_Payment extends WC_Payment_Gateway
    {

        function __construct()
        {
            global $woocommerce;
            $this->id                 = "paypal-me";
            $this->has_fields         = false;
            $this->method_title       = __("Paypal Me", 'woocommerce-paypal-me');
            $this->method_description = "Use Paypal Me as payment method. Customers are redirected to Paypal Me and are able to pay there order.  <br/>
            <div class='col bit_admin_desc'><a class='bit_paypal_me_button_backend' id='bit_paypal_donate_admin_button' target='_blank' href='https://www.paypal.me/BoringIT'>Give me some Beer and Coffee</a></div>";
            //Initialize form methods
            $this->init_form_fields();
            $this->init_settings();

            // Config.
            $this->title                        = $this->settings['title'];
            $this->description                  = $this->settings['description'];
            $this->instructions                 = $this->settings['instructions'];
            $this->paypal_me_url                = $this->settings['paypal_me_url'];
            $this->paypal_email                 = $this->settings['paypal_email'];
            $this->paypal_email_notice          = $this->settings['paypal_email_notice'];
            $this->paypal_currency              = $this->settings['paypal_currency'];
            $this->paypal_redirect_status       = $this->settings['paypal_redirect_status'];
            $this->paypal_redirect_description  = $this->settings['paypal_redirect_description'];
            $this->paypal_time_delay            = $this->settings['paypal_time_delay'];
            $this->paypal_finish_button         = $this->settings['paypal_finish_button'];
            $this->paypal_button_title          = $this->settings['paypal_button_title'];
            $this->paypal_email_order_notice    = $this->settings['paypal_email_order_notice'];
            $this->paypal_email_order_link_text = $this->settings['paypal_email_order_link_text'];
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action('woocommerce_thankyou_' . $this->id, array($this, 'finish_page'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
                add_action('woocommerce_thankyou', array(&$this, 'finish_page'));
            }
            add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
        }

        public function validate_paypal_me_url_field($key, $url)
        {
            if (isset($url) && !empty($url)) {
                if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                    WC_Admin_Settings::add_error(esc_html__('You entered an invalid PayPal Me URL.', 'woocommerce-paypal-me'));
                }
            }
            return $url;
        }

        public function validate_paypal_email_field($key, $email)
        {
            if (isset($email) && !empty($email)) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    WC_Admin_Settings::add_error(esc_html__('You entered an invalid PayPal Email Address.', 'woocommerce-paypal-me'));
                }
            }
            return $email;
        }

        public function process_payment($orderId)
        {
            $order = wc_get_order($orderId);
            $order->update_status('on-hold', __('Awaiting Payple Me Payment', 'woocommerce-paypal-me'));
            $order->reduce_order_stock(); // correct stock
            WC()->cart->empty_cart(); // flush cart
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order),
            );
        }

        public function finish_page($order_id)
        {
            $order       = new WC_Order($order_id);
            $order_total = "/" . $order->get_total() . $this->paypal_currency;
            $button      = "<div class='paypal_me_container'>
                    <a href=" . $this->paypal_me_url . $order_total . " class='paypal_me_button' id='paypal_button' target='_blank'>"
            . $this->paypal_button_title .
                "</a></div>";
            if ($this->instructions) {
                echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
            }
            if ($this->paypal_me_url) {
                echo $this->paypal_redirect_description;
                if ($this->paypal_finish_button) {
                    echo $button;
                }
            }
            if ($this->paypal_email) {
                echo $this->paypal_email_notice . " <b>" . $this->paypal_email . "</b><br/><br/>";
            }
            if ($this->paypal_redirect_status) {
                //header('refresh:'.$this->paypal_time_delay.'; url="'.$this->paypal_me_url . $order_total.'"');
            }
        }

        public function email_instructions($order, $sent_to_admin, $plain_text = false)
        {
            if ($this->instructions && !$sent_to_admin && 'paypal-me' === $order->payment_method && $order->has_status('on-hold')) {
                if ($this->instructions) {
                    echo wpautop(wptexturize($this->instructions));
                }
                $link = '<a href="' . $this->paypal_me_url . '" target="_blank" >' . $this->paypal_email_order_link_text . '</a>';
                if ($this->paytm_me_url) {
                    echo $this->paypal_email_order_notice . $link;
                    echo "Our Paypal.Me link = <b>" . $this->paypal_me_url . "</b>";
                }
                if ($this->paypal_email) {
                    echo $this->paypal_email_notice . " <b>" . $this->paypal_email . "</b><br/><br/>";
                }
            }
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled'                      => array(
                    'title'   => __('Status', 'woocommerce-paypal-me'),
                    'type'    => 'checkbox',
                    'label'   => __('Enable Paypal Me Payment', 'woocommerce-paypal-me'),
                    'default' => 'no',
                ),
                'title'                        => array(
                    'title'       => __('Title', 'woocommerce-paypal-me'),
                    'type'        => 'text',
                    'description' => __('This title will shown in the checkout as payment title.', 'woocommerce-paypal-me'),
                    'default'     => __('Pay with PayPal', 'woocommerce-paypal-me'),
                    'desc_tip'    => true,
                ),
                'description'                  => array(
                    'title'       => __('Description', 'woocommerce-paypal-me'),
                    'type'        => 'textarea',
                    'description' => __('This description will shown in the checkout as payment description.', 'woocommerce-paypal-me'),
                    'default'     => __('Pay easy with your Paypal Account. Add as reference your order id.', 'woocommerce-paypal-me'),
                    'desc_tip'    => true,
                ),
                'instructions'                 => array(
                    'title'       => __('Instructions', 'woocommerce-paypal-me'),
                    'type'        => 'textarea',
                    'description' => __('This Tutorial Text will be shown at the thank you page and emails.', 'woocommerce-paypal-me'),
                    'default'     => "Pay easy with your Paypal Account. Add as reference your order id. Your order wont be processed until your payment are recieved.",
                    'desc_tip'    => true,
                ),
                'paypal_me_url'                => array(
                    'title'       => __('Paypal.me URL', 'woocommerce-paypal-me'),
                    'type'        => 'text',
                    'description' => __('Add your Paypel Me Link', 'woocommerce-paypal-me'),
                    'default'     => "This Payple ME URL will be added to the Thank you Page, Email and the customer will be redirected to. For example https://paypal.me/smitraval",
                    'desc_tip'    => true,
                ),
                'paypal_email'                 => array(
                    'title'       => __('Paypal Email', 'woocommerce-paypal-me'),
                    'type'        => 'text',
                    'description' => __('This Paypal Email will also shown on the Thank you Page. So Customer can send money onto it.', 'woocommerce-paypal-me'),
                    'default'     => "For Example: info@boring-it.de",
                    'desc_tip'    => true,
                ),
                'paypal_email_notice'          => array(
                    'title'       => __('Email Notice', 'woocommerce-paypal-me'),
                    'type'        => 'text',
                    'description' => __('This Paypal Email will also shown on the Thank you Page. The email will be displayed after this notice..', 'woocommerce-paypal-me'),
                    'default'     => "Do you have some problems ? So you can use our Paypal Email Adress to make payments. Our Paypal Email Adress is",
                    'desc_tip'    => true,
                ),
                'paypal_currency'              => array(
                    'title'       => __('Currency', 'woocommerce-paypal-me'),
                    'type'        => 'Select',
                    'description' => __('Configure the default Currency for you Customer.', 'woocommerce-paypal-me'),
                    'default'     => "EUR",
                    'desc_tip'    => true,
                    'options'     => array(
                        'EUR' => 'EUR',
                        'USD' => 'USD',
                    ),
                ),
                'paypal_redirect_status'       => array(
                    'title'   => __('Automated Redirect', 'woocommerce-paypal-me'),
                    'type'    => 'checkbox',
                    'label'   => __('Automated redirect to Paypal Me on finish page', 'woocommerce-paypal-me'),
                    'default' => 'yes',
                ),
                'paypal_time_delay'            => array(
                    'title'       => __('Time until redirect', 'woocommerce-paypal-me'),
                    'type'        => 'Select',
                    'description' => __('Choose the time delay of the redirect.', 'woocommerce-paypal-me'),
                    'default'     => "5",
                    'desc_tip'    => true,
                    'options'     => array(
                        '3' => '3',
                        '5' => '5',
                        '7' => '7',
                    ),
                ),
                'paypal_redirect_description'  => array(
                    'title'       => __('Notice for Redirect', 'woocommerce-paypal-me'),
                    'type'        => 'text',
                    'description' => __('This will be a notice for the automated redirect on the finish page.', 'woocommerce-paypal-me'),
                    'default'     => "You will be automated redirect in a few seconds",
                    'desc_tip'    => true,
                ),
                'paypal_finish_button'         => array(
                    'title'   => __('Finish Page Button Status', 'woocommerce-paypal-me'),
                    'type'    => 'checkbox',
                    'label'   => __('Enable Paypal Me button on finish page', 'woocommerce-paypal-me'),
                    'default' => 'yes',
                ),
                'paypal_button_title'          => array(
                    'title'       => __('Finish Page Button Title', 'woocommerce-paypal-me'),
                    'type'        => 'text',
                    'description' => __('This is the title for the paypal button on the finish page.', 'woocommerce-paypal-me'),
                    'default'     => "Pay now with Paypal",
                    'desc_tip'    => true,
                ),
                'paypal_email_order_link_text' => array(
                    'title'       => __('Text for Link in Order Email', 'woocommerce-paypal-me'),
                    'type'        => 'text',
                    'description' => __('This is the text for the link in the order mail.', 'woocommerce-paypal-me'),
                    'default'     => "Click Here",
                    'desc_tip'    => true,
                ),
                'paypal_email_order_notice'    => array(
                    'title'       => __('Text for Order Email', 'woocommerce-paypal-me'),
                    'type'        => 'text',
                    'description' => __('This is the Text for the Order Mail, after the link will be added.', 'woocommerce-paypal-me'),
                    'default'     => "Pay now with Paypal. Pleas add your Order ID as reference. ",
                    'desc_tip'    => true,
                ),
            );
        }

    }

    function add_paypal_me_payment($methods)
    {
        $methods[] = 'BIT_Paypal_Me_Payment';
        return $methods;
    }

    function add_paypal_me_style()
    {
        wp_enqueue_style('bit_paypal_me_style', plugins_url('/public/css/boring-it-paypal-me-payment.css', __FILE__), false, '1.0.0', 'all');
    }

}
