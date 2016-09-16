<?php
/**
 * Piwik Integration
 *
 * Allows tracking code to be inserted into store pages.
 *
 * @class        WC_Piwik
 * @extends        WC_Integration
 */
class WC_Piwik extends WC_Integration
{
    const PIWIK_PRO_URL = 'panel.piwik.pro';

    public $id;

    public $form_text_fields = array();

    /**
     * Init and hook in the integration.
     */
    public function __construct()
    {
        $this->redirectToPiwikPro();
        $this->id = 'piwik';
        $this->method_title = __('WooCommerce Piwik', 'woocommerce');
        $this->method_description = __('This extension enables you to integrate seamlessly with Piwik, a web analytics platform that gives you valuable
        insights into your website`s visitors, e-commerce purchases, products statistics, your marketing campaigns and much
        more, so you can optimize your strategy and online experience of your visitors.',
            'woocommerce');

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        $this->setupPiwikPro();

        $this->piwik_idsite = $this->get_option('piwik_idsite');
        $this->piwik_domain_name = $this->get_option('piwik_domain_name');
        $this->piwik_standard_tracking_enabled = $this->get_option('piwik_standard_tracking_enabled');
        $this->piwik_ecommerce_tracking_enabled = $this->get_option('piwik_ecommerce_tracking_enabled');
        $this->piwik_cartupdate_tracking_enabled = $this->get_option('piwik_cartupdate_tracking_enabled');

        $this->disconnectPiwikCloud();

        // Define user set variables

        $this->addActions();
    }

    /**
     * Initialise Settings Form Fields
     *
     * @access public
     * @return void
     */
    function init_form_fields()
    {
        $this->selfHostedFields = array(
            'piwik_idsite' => array(
                'title' => __('Piwik site ID', 'woocommerce'),
                'description' => __('You can find site ID in Piwik administration panel', 'woocommerce'),
                'type' => 'text'
            ),
            'piwik_domain_name' => array(
                'title' => __('Piwik domain', 'woocommerce'),
                'description' => 'Location of your Piwik installation (without http(s)://, i.e. piwik.example.com)',
                'type' => 'text'
            )
        );

        $this->commonFields = array(
            'piwik_standard_tracking_enabled' => array(
                'title' => __('Tracking code', 'woocommerce'),
                'label' => __('Add tracking code to your site. You don\'t need to enable this if using a 3rd party
				analytics plugin (i.e. Piwiktracking plugin)',
                    'woocommerce'),
                'type' => 'checkbox',
                'checkboxgroup' => 'start',
                'default' => ($this->is_wp_piwik_installed()) ? 'no' : 'yes'
            ),
            'piwik_ecommerce_tracking_enabled' => array(
                'label' => __('Add eCommerce tracking code to the thankyou page', 'woocommerce'),
                'type' => 'checkbox',
                'checkboxgroup' => '',
                'default' => 'yes'
            ),
            'piwik_cartupdate_tracking_enabled' => array(
                'label' => __('Add cart update for add to cart actions (i.e. allows to track abandoned carts)',
                    'woocommerce'),
                'type' => 'checkbox',
                'checkboxgroup' => 'end',
                'default' => 'yes'
            )
        );

        $this->form_fields = array_merge($this->selfHostedFields, $this->commonFields);
    }


    /**
     * Piwik standard tracking
     *
     * @access public
     * @return void
     */
    function piwik_tracking_code()
    {
        include_once(__DIR__ . '/../templates/tracking-code.php');
    }

    /**
     * Piwik eCommerce order tracking
     *
     * @access public
     *
     * @param mixed $order_id
     *
     * @return void
     */
    function ecommerce_tracking_code($order_id)
    {
        if (get_post_meta($order_id, '_piwik_tracked', true) == 1) {
            return;
        }

        $order = new WC_Order($order_id);
        $code = '
            var _paq = _paq || [];
        ';

        if ($order->get_items()) {
            foreach ($order->get_items() as $item) {
                $_product = $order->get_product_from_item($item);
                $code .= '
                _paq.push(["addEcommerceItem",
                    "' . esc_js($_product->get_sku() ? $_product->get_sku() : $_product->id) . '",
                    "' . esc_js($item['name']) . '",';

                $out = array();
                $categories = get_the_terms($_product->id, 'product_cat');
                if ($categories) {
                    foreach ($categories as $category) {
                        $out[] = $category->name;
                    }
                }
                if (count($out) > 0) {
                    $code .= '["' . join("\", \"", $out) . '"],';
                } else {
                    $code .= '[],';
                }

                $code .= '"' . esc_js($order->get_item_total($item)) . '",';
                $code .= '"' . esc_js($item['qty']) . '"';
                $code .= "]);";
            }
        }

        $code .= '
            _paq.push(["trackEcommerceOrder",
                "' . esc_js($order->get_order_number()) . '",
                "' . esc_js($order->get_total()) . '",
                "' . esc_js($order->get_total() - $order->get_total_shipping()) . '",
                "' . esc_js($order->get_total_tax()) . '",
                "' . esc_js($order->get_total_shipping()) . '"
            ]);
        ';

        echo '<script type="text/javascript">' . $code . '</script>';

        update_post_meta($order_id, '_piwik_tracked', 1);
    }

    function get_cart_items_js_code()
    {
        global $woocommerce;

        $cart_content = $woocommerce->cart->get_cart();
        $code = '
            var cartItems = [];';
        foreach ($cart_content as $item) {

            $item_sku = esc_js(($sku = $item['data']->get_sku()) ? $sku : $item['product_id']);
            $item_price = $item['data']->get_price();
            $item_title = $item['data']->get_title();
            $cats = $this->getProductCategories($item['product_id']);

            $code .= "
            cartItems.push({
                    sku: \"$item_sku\",
                    title: \"$item_title\",
                    price: $item_price,
                    quantity: {$item['quantity']},
                    categories: $cats
                });
            ";
        }

        return $code;
    }

    /**
     * Sends cart update request
     */
    function update_cart()
    {

        $code = $this->get_cart_items_js_code();

        wc_enqueue_js("
            " . $code . "
            var arrayLength = cartItems.length, revenue = 0;

            for (var i = 0; i < arrayLength; i++) {
                _paq.push(['addEcommerceItem',
                    cartItems[i].sku,
                    cartItems[i].title,
                    cartItems[i].categories,
                    cartItems[i].price,
                    cartItems[i].quantity
                    ]);

                revenue += cartItems[i].price * cartItems[i].quantity;
            }


            _paq.push(['trackEcommerceCartUpdate', revenue]);
		");
    }

    /**
     * Ajax action to get cart
     */
    function get_cart()
    {
        global $woocommerce;

        $cart_content = $woocommerce->cart->get_cart();
        $products = array();

        foreach ($cart_content as $item) {
            $item_sku = esc_js(($sku = $item['data']->get_sku()) ? $sku : $item['product_id']);
            $cats = $this->getProductCategories($item['product_id']);

            $products[] = array(
                'sku' => $item_sku,
                'title' => $item['data']->get_title(),
                'price' => $item['data']->get_price(),
                'quantity' => $item['quantity'],
                'categories' => $cats
            );
        }

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($products);
        exit;
    }

    function send_update_cart_request()
    {
        if (!empty($_REQUEST['add-to-cart']) && is_numeric($_REQUEST['add-to-cart'])) {
            $code = $this->get_cart_items_js_code();
            wc_enqueue_js($code . "
			    $(document).ready(function(){
                    $('body').trigger('added_to_cart');
                });
            ");
        }
    }

    /**
     * @param $itemID
     *
     * @return string
     */
    protected function getProductCategories($itemID)
    {
        $out = array();
        $categories = get_the_terms($itemID, 'product_cat');

        if ($categories) {
            foreach ($categories as $category) {
                $out[] = $category->name;
            }
        }

        if (count($out) > 0) {
            $cats = '["' . join("\", \"", $out) . '"]';

            return $cats;
        } else {
            $cats = '[]';

            return $cats;
        }
    }

    /**
     * Add actions using WooCommerce hooks
     */
    protected function addActions()
    {
        add_action('woocommerce_update_options_integration_piwik', array($this, 'process_admin_options'));
        add_action('wp_ajax_nopriv_woocommerce_piwik_get_cart', array($this, 'get_cart'));
        add_action('wp_ajax_woocommerce_piwik_get_cart', array($this, 'get_cart'));
        add_action('woocommerce_after_single_product_summary', array($this, 'product_view'));
        add_action('woocommerce_after_shop_loop', array($this, 'category_view'));

        if (
            ((empty($this->piwik_idsite) || !is_numeric($this->piwik_idsite) || empty($this->piwik_domain_name))
                && !$this->is_wp_piwik_installed())
            || is_admin() || current_user_can('manage_options')
        ) {
            return;
        }

        if ($this->piwik_standard_tracking_enabled == 'yes') {
            add_action('wp_footer', array($this, 'piwik_tracking_code'));
        }

        if ($this->piwik_ecommerce_tracking_enabled == 'yes') {
            add_action('woocommerce_thankyou', array($this, 'ecommerce_tracking_code'));
        }

        if ($this->piwik_cartupdate_tracking_enabled == 'yes') {
            add_action('woocommerce_after_single_product', array($this, 'send_update_cart_request'));
            add_action('woocommerce_after_cart', array($this, 'update_cart'));

            $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
            $assets_path = str_replace(array('http:', 'https:'),
                    '',
                    untrailingslashit(plugins_url('/', __FILE__))) . '/';
            $frontend_script_path = $assets_path . '../assets/js/';
            wp_enqueue_script('get-cart',
                $frontend_script_path . 'get-cart' . $suffix . '.js',
                array('jquery'),
                WC_VERSION,
                true);
        }
    }

    function category_view()
    {
        global $wp_query;

        if (isset($wp_query->query_vars['product_cat']) && !empty($wp_query->query_vars['product_cat'])) {
            $jsCode = sprintf("
            _paq.push(['setEcommerceView',
                    false,
                    false,
                    '%s'
            ]);
            _paq.push(['trackPageView']);
            ", urlencode($wp_query->queried_object->name));
            wc_enqueue_js($jsCode);
        }
    }

    function product_view()
    {
        global $product;

        $jsCode = sprintf("
            _paq.push(['setEcommerceView',
                    '%s',
                    '%s',
                    %s,
                    %f
            ]);
            _paq.push(['trackPageView']);
        ",
            $product->get_sku(),
            urlencode($product->get_title()),
            $this->getEncodedCategoriesByProduct($product),
            $product->get_price()
        );
        wc_enqueue_js($jsCode);
    }

    protected function getEncodedCategoriesByProduct($product)
    {
        $categories = get_the_terms($product->post->ID, 'product_cat');

        if (!$categories) {
            $categories = array();
        }

        $categories = array_map(function ($element) {
            return sprintf("'%s'", urlencode($element->name));
        }, $categories);

        return sprintf("[%s]", implode(", ", $categories));
    }

    protected function redirectToPiwikPro()
    {
        if (isset($_GET['integrate-piwik-cloud']) && $_GET['integrate-piwik-cloud']) {
            $token = $this->generateToken();
            $siteUrl = $this->getSiteUrl();
            delete_option('woocommerce_piwik_integration');
            delete_option('woocommerce_piwik_token');
            delete_option('woocommerce_piwik_ts_valid');
            add_option('woocommerce_piwik_token', $token);
            add_option('woocommerce_piwik_ts_valid', time());

            header('Location: http://' . self::PIWIK_PRO_URL . '/integrate/woocommerce?shop=' . $siteUrl . '&code=' . $token);

            exit;
        }

    }

    protected function useOpenSsl()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD') && version_compare(PHP_VERSION, '5.3.4', '<')) {
            return false;
        } elseif (!function_exists('openssl_random_pseudo_bytes')) {
            return false;
        } else {
            return true;
        }
    }

    protected function generateToken()
    {
        return rtrim(strtr(base64_encode($this->getRandomNumber()), '+/', '-_'), '=');
    }

    protected function getRandomNumber()
    {
        $nbBytes = 32;

        // try OpenSSL
        if ($this->useOpenSsl()) {
            $bytes = openssl_random_pseudo_bytes($nbBytes, $strong);

            if (false !== $bytes && true === $strong) {
                return $bytes;
            }
        }

        return hash('sha256', uniqid(mt_rand(), true), true);
    }

    protected function setupPiwikPro()
    {
        if ($this->validateIntegrationValues()) {
            $this->setOption('piwik_idsite', $_GET['idsite']);
            $this->setOption('piwik_domain_name', $_GET['piwikurl']);

            $postData = [
                $this->get_field_key("piwik_domain_name") => $_GET['piwikurl'],
                $this->get_field_key("piwik_idsite") => $_GET['idsite']
            ];

            $this->set_post_data($postData);
            $this->process_admin_options();

            WC_Admin_Settings::add_message(__('Your site has been successfuly integrated with Piwik Cloud!',
                'woocommerce'));
            delete_option('woocommerce_piwik_ts_valid');
            add_option('woocommerce_piwik_integrated', true);

            $this->update_wp_piwik_settings();

        } else {
            if (!empty($_GET['code']) || !empty($_GET['piwikurl']) || !empty($_GET['idsite'])) {
                header('Location: ' . site_url() . '/wp-admin/admin.php?page=wc-settings&tab=integration');
                exit;
            }
        }
    }

    protected function disconnectPiwikCloud()
    {
        if (!empty($_GET['disconnect-piwik-cloud']) && $_GET['disconnect-piwik-cloud']) {

            if (!class_exists('WP_Http')) {
                include_once(ABSPATH . WPINC . '/class-http.php');
            }

            $request = new WP_Http;
            $result = $request->request(
                'http://' . $this->piwik_domain_name
                . '/index.php?module=API&method=Integration.woocommerceUninstall&format=JSON&shop='
                . $this->getSiteUrl() . '&code='
                . get_option('woocommerce_piwik_token')
            );

            if (array_key_exists('response', $result) && $result['response']['code'] == 200) {
                $this->setOption('piwik_idsite', '');
                $this->setOption('piwik_domain_name', '');
                $this->process_admin_options();
                delete_option('woocommerce_piwik_integrated');

                WC_Admin_Settings::add_message(__('Your site has been successfully disconnected from Piwik Cloud!',
                    'woocommerce'));

                $this->update_wp_piwik_settings();
            } else {
                WC_Admin_Settings::add_error(__('An error occurred when trying to disconnect, please try again later',
                    'woocommerce'));
            }

        }
    }

    protected function setOption($key, $value)
    {
        $this->settings[$key] = $value;
    }

    public function validate_checkbox_field($key)
    {
        if ($this->validateIntegrationValues()) {
            return 'yes';
        }

        $status = 'no';
        if (isset($_POST[$this->plugin_id . $this->id . '_' . $key]) && (1 == $_POST[$this->plugin_id . $this->id . '_' . $key])) {
            $status = 'yes';
        }

        return $status;
    }
    protected function validateIntegrationValues()
    {
        // ignore field validation if piwik pro integration values are provided
        if (!empty($_GET['code']) && !empty($_GET['piwikurl']) && !empty($_GET['idsite'])) {
            $token = get_option('woocommerce_piwik_token');
            $timestamp = get_option('woocommerce_piwik_ts_valid');

            if ($token == $_GET['code']) {
                if (((time() - $timestamp) < 3600)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function admin_options()
    {
        $uriParts = explode('?', $_SERVER['REQUEST_URI'], 2);
        $url = 'http://' . $_SERVER['HTTP_HOST'] . $uriParts[0] . '?page=wc-settings&tab=integration';

        if ($_GET['subtab'] == 'piwik-cloud') {
            $cloudClass = 'enabled ';
            $selfHostedClass = '';
            $cloudSubtab = '';
            $selfHostedSubtab = $url . '&subtab=piwik-self-hosted';
        } elseif ($_GET['subtab'] == 'piwik-self-hosted') {
            $cloudClass = '';
            $selfHostedClass = 'enabled ';
            $cloudSubtab = $url . '&subtab=piwik-cloud';
            $selfHostedSubtab = '';
        } else {
            $cloudClass = 'enabled ';
            $selfHostedClass = '';
            $cloudSubtab = $url . '&subtab=piwik-cloud';
            $selfHostedSubtab = $url . '&subtab=piwik-self-hosted';
        }

        include_once(__DIR__ . '/../templates/admin-options.php');

    }

    public function validate_settings_fields($form_fields = false)
    {
        parent::validate_settings_fields(array_merge($this->form_text_fields, $this->form_fields));
    }

    /**
     * @return mixed
     */
    protected function getSiteUrl()
    {
        $siteUrl = str_replace('http://', '', get_site_url());
        $siteUrl = str_replace('https://', '', $siteUrl);

        return $siteUrl;
    }

    /**
     * Check if wp piwik is installed
     *
     * @return bool
     */
    protected function is_wp_piwik_installed()
    {
        return (isset($GLOBALS['wp_piwik']));
    }

    protected function get_wp_piwik_settings()
    {
        return get_option('wp-piwik_settings');
    }

    protected function get_wp_piwik_global_settings()
    {
        return get_option('wp-piwik_global-settings');
    }

    protected function set_wp_piwik_settings($value)
    {
        update_option('wp-piwik_settings', $value);
    }

    protected function set_wp_piwik_global_settings($value)
    {
        update_option('wp-piwik_global-settings', $value);
    }

    protected function update_wp_piwik_settings()
    {
        if (!$this->is_wp_piwik_installed()) {
            return;
        }

        $settings = $this->get_wp_piwik_settings();
        $globalSettings = $this->get_wp_piwik_global_settings();

        if ($idSite = $this->get_option('piwik_idsite')) {
            $settings['site_id'] = $idSite;
            $globalSettings['add_tracking_code'] = 0;
            $globalSettings['piwik_url'] = 'http://' . $this->get_option('piwik_domain_name');
            $this->setOption('piwik_standard_tracking_enabled', 'yes');

            $this->set_wp_piwik_settings($settings);
            $this->set_wp_piwik_global_settings($globalSettings);

            WC_Admin_Settings::add_message(__('Introduced changes to WP Piwik settings. Please update them according to your needs!',
                'woocommerce'));
        } else {
            $settings['site_id'] = '';
            $globalSettings['add_tracking_code'] = 0;
            $globalSettings['piwik_url'] = '';
            $this->setOption('piwik_standard_tracking_enabled', 'no');

            $this->set_wp_piwik_settings($settings);
            $this->set_wp_piwik_global_settings($globalSettings);

            WC_Admin_Settings::add_message(__('Introduced changes to WP Piwik settings. Please update them according to your needs!',
                'woocommerce'));
        }
    }
}

