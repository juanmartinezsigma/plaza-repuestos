<?php
/**
 * Setup Wizard Class
 * 
 * @since 2.7.7
 * @package WC Marketplace
 * @author WC Marketplace
 */
if (!defined('ABSPATH')) {
    exit;
}

class WCMp_Admin_Setup_Wizard {

    /** @var string Currenct Step */
    private $step = '';

    /** @var array Steps for the setup wizard */
    private $steps = array();

    public function __construct() {
        add_action('admin_menu', array($this, 'admin_menus'));
        add_action('admin_init', array($this, 'setup_wizard'));
    }

    /**
     * Add admin menus/screens.
     */
    public function admin_menus() {
        add_dashboard_page('', '', 'manage_options', 'wcmp-setup', '');
    }

    /**
     * Show the setup wizard.
     */
    public function setup_wizard() {
        global $WCMp;
        if (filter_input(INPUT_GET, 'page') != 'wcmp-setup') {
            return;
        }

        if (!WC_Dependencies_Product_Vendor::is_woocommerce_active()) {
            if (isset($_POST['submit'])) {
                $this->install_woocommerce();
            }
            $this->install_woocommerce_view();
            exit();
        }
        $default_steps = array(
            'introduction' => array(
                'name' => __('Introduction', 'dc-woocommerce-multi-vendor'),
                'view' => array($this, 'wcmp_setup_introduction'),
                'handler' => '',
            ),
            'store' => array(
                'name' => __('Store Setup', 'dc-woocommerce-multi-vendor'),
                'view' => array($this, 'wcmp_setup_store'),
                'handler' => array($this, 'wcmp_setup_store_save')
            ),
            'commission' => array(
                'name' => __('Commission Setup', 'dc-woocommerce-multi-vendor'),
                'view' => array($this, 'wcmp_setup_commission'),
                'handler' => array($this, 'wcmp_setup_commission_save')
            ),
            'payments' => array(
                'name' => __('Payments', 'dc-woocommerce-multi-vendor'),
                'view' => array($this, 'wcmp_setup_payments'),
                'handler' => array($this, 'wcmp_setup_payments_save')
            ),
            'capability' => array(
                'name' => __('Capability', 'dc-woocommerce-multi-vendor'),
                'view' => array($this, 'wcmp_setup_capability'),
                'handler' => array($this, 'wcmp_setup_capability_save')
            ),
            'introduction_migration' => array(
                'name' => __('Migration', 'dc-woocommerce-multi-vendor' ),
                'view' => array($this, 'wcmp_migration_introduction'),
                'handler' => '',
            ),
            'store-process' => array(
                'name' => __('Processing', 'dc-woocommerce-multi-vendor'),
                'view' => array($this, 'wcmp_migration_store_process'),
                'handler' => ''
            ),
            'next_steps' => array(
                'name' => __('Ready!', 'dc-woocommerce-multi-vendor'),
                'view' => array($this, 'wcmp_setup_ready'),
                'handler' => '',
            ),
        );
        if (!$WCMp->multivendor_migration->wcmp_is_marketplace()) {
            unset( $default_steps['introduction_migration'], $default_steps['store-process'] );
        } 
        $this->steps = apply_filters('wcmp_setup_wizard_steps', $default_steps);
        $current_step = filter_input(INPUT_GET, 'step');
        $this->step = $current_step ? sanitize_key($current_step) : current(array_keys($this->steps));
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
        wp_register_script('jquery-blockui', WC()->plugin_url() . '/assets/js/jquery-blockui/jquery.blockUI' . $suffix . '.js', array('jquery'), '2.70', true);
        wp_register_script( 'jquery-tiptip', WC()->plugin_url() . '/assets/js/jquery-tiptip/jquery.tipTip' . $suffix . '.js', array( 'jquery' ), WC_VERSION, true );
        wp_register_script( 'selectWoo', WC()->plugin_url() . '/assets/js/selectWoo/selectWoo.full' . $suffix . '.js', array( 'jquery' ), '1.0.0' );
        wp_register_script('wc-enhanced-select', WC()->plugin_url() . '/assets/js/admin/wc-enhanced-select' . $suffix . '.js', array('jquery', 'selectWoo'), WC_VERSION);
        wp_localize_script('wc-enhanced-select', 'wc_enhanced_select_params', array(
            'i18n_no_matches' => _x('No matches found', 'enhanced select', 'dc-woocommerce-multi-vendor'),
            'i18n_ajax_error' => _x('Loading failed', 'enhanced select', 'dc-woocommerce-multi-vendor'),
            'i18n_input_too_short_1' => _x('Please enter 1 or more characters', 'enhanced select', 'dc-woocommerce-multi-vendor'),
            'i18n_input_too_short_n' => _x('Please enter %qty% or more characters', 'enhanced select', 'dc-woocommerce-multi-vendor'),
            'i18n_input_too_long_1' => _x('Please delete 1 character', 'enhanced select', 'dc-woocommerce-multi-vendor'),
            'i18n_input_too_long_n' => _x('Please delete %qty% characters', 'enhanced select', 'dc-woocommerce-multi-vendor'),
            'i18n_selection_too_long_1' => _x('You can only select 1 item', 'enhanced select', 'dc-woocommerce-multi-vendor'),
            'i18n_selection_too_long_n' => _x('You can only select %qty% items', 'enhanced select', 'dc-woocommerce-multi-vendor'),
            'i18n_load_more' => _x('Loading more results&hellip;', 'enhanced select', 'dc-woocommerce-multi-vendor'),
            'i18n_searching' => _x('Searching&hellip;', 'enhanced select', 'dc-woocommerce-multi-vendor'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'search_products_nonce' => wp_create_nonce('search-products'),
            'search_customers_nonce' => wp_create_nonce('search-customers'),
        ));

        wp_enqueue_style('woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', array(), WC_VERSION);
        wp_enqueue_style('wc-setup', WC()->plugin_url() . '/assets/css/wc-setup.css', array('dashicons', 'install'), WC_VERSION);
        wp_register_script('wc-setup', WC()->plugin_url() . '/assets/js/admin/wc-setup' . $suffix . '.js', array('jquery', 'wc-enhanced-select', 'jquery-blockui', 'jquery-tiptip'), WC_VERSION);
        wp_register_script('wcmp-setup', $WCMp->plugin_url . '/assets/admin/js/setup-wizard.js', array('wc-setup'), WC_VERSION);
        wp_localize_script('wc-setup', 'wc_setup_params', array(
            'locale_info' => json_encode(include( WC()->plugin_path() . '/i18n/locale-info.php' )),
        ));

        if (!empty($_POST['save_step']) && isset($this->steps[$this->step]['handler'])) {
            call_user_func($this->steps[$this->step]['handler'], $this);
        }

        ob_start();
        $this->setup_wizard_header();
        $this->setup_wizard_steps();
        $this->setup_wizard_content();
        $this->setup_wizard_footer();
        exit();
    }

    /**
     * Content for install woocommerce view
     */
    public function install_woocommerce_view() {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
            <head>
                <meta name="viewport" content="width=device-width" />
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                <title><?php esc_html_e('WC Marketplace &rsaquo; Setup Wizard', 'dc-woocommerce-multi-vendor'); ?></title>
                <?php do_action('admin_print_styles'); ?>
        <?php do_action('admin_head'); ?>
                <style type="text/css">
                    body {
                        margin: 100px auto 24px;
                        box-shadow: none;
                        background: #f1f1f1;
                        padding: 0;
                        max-width: 700px;
                    }
                    #wcmp-logo {
                        border: 0;
                        margin: 0 0 24px;
                        padding: 0;
                        text-align: center;
                    }
                    .wcmp-install-woocommerce {
                        box-shadow: 0 1px 3px rgba(0,0,0,.13);
                        padding: 24px 24px 0;
                        margin: 0 0 20px;
                        background: #fff;
                        overflow: hidden;
                        zoom: 1;
                    }
                    .wcmp-install-woocommerce .button-primary{
                        font-size: 1.25em;
                        padding: .5em 1em;
                        line-height: 1em;
                        margin-right: .5em;
                        margin-bottom: 2px;
                        height: auto;
                    }
                    .wcmp-install-woocommerce{
                        font-family: sans-serif;
                        text-align: center;    
                    }
                    .wcmp-install-woocommerce form .button-primary{
                        color: #fff;
                        background-color: #9c5e91;
                        font-size: 16px;
                        border: 1px solid #9a548d;
                        width: 230px;
                        padding: 10px;
                        margin: 25px 0 20px;
                        cursor: pointer;
                    }
                    .wcmp-install-woocommerce form .button-primary:hover{
                        background-color: #9a548d;
                    }
                    .wcmp-install-woocommerce p{
                        line-height: 1.6;
                    }

                </style>
            </head>
            <body class="wcmp-setup wp-core-ui">
                <h1 id="wcmp-logo"><a href="http://wc-marketplace.com/"><img src="<?php echo trailingslashit(plugins_url('dc-woocommerce-multi-vendor')); ?>assets/images/wc-marketplace.png" alt="WC Marketplace" /></a></h1>
                <div class="wcmp-install-woocommerce">
                    <p><?php esc_html_e('WC Marketplace requires WooCommerce plugin to be active!', 'dc-woocommerce-multi-vendor'); ?></p>
                    <form method="post" action="" name="wcmp_install_woocommerce">
                        <?php submit_button(__('Install WooCommerce', 'dc-woocommerce-multi-vendor'), 'primary', 'wcmp_install_woocommerce'); ?>
        <?php wp_nonce_field('wcmp-install-woocommerce'); ?>
                    </form>
                </div>
            </body>
        </html>
        <?php
    }

    /**
     * Install woocommerce if not exist
     * @throws Exception
     */
    public function install_woocommerce() {
        check_admin_referer('wcmp-install-woocommerce');
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );
        require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

        WP_Filesystem();
        $skin = new Automatic_Upgrader_Skin;
        $upgrader = new WP_Upgrader($skin);
        $installed_plugins = array_map(array(__CLASS__, 'format_plugin_slug'), array_keys(get_plugins()));
        $plugin_slug = 'woocommerce';
        $plugin = $plugin_slug . '/' . $plugin_slug . '.php';
        $installed = false;
        $activate = false;
        // See if the plugin is installed already
        if (in_array($plugin_slug, $installed_plugins)) {
            $installed = true;
            $activate = !is_plugin_active($plugin);
        }
        // Install this thing!
        if (!$installed) {
            // Suppress feedback
            ob_start();

            try {
                $plugin_information = plugins_api('plugin_information', array(
                    'slug' => $plugin_slug,
                    'fields' => array(
                        'short_description' => false,
                        'sections' => false,
                        'requires' => false,
                        'rating' => false,
                        'ratings' => false,
                        'downloaded' => false,
                        'last_updated' => false,
                        'added' => false,
                        'tags' => false,
                        'homepage' => false,
                        'donate_link' => false,
                        'author_profile' => false,
                        'author' => false,
                    ),
                ));

                if (is_wp_error($plugin_information)) {
                    throw new Exception($plugin_information->get_error_message());
                }

                $package = $plugin_information->download_link;
                $download = $upgrader->download_package($package);

                if (is_wp_error($download)) {
                    throw new Exception($download->get_error_message());
                }

                $working_dir = $upgrader->unpack_package($download, true);

                if (is_wp_error($working_dir)) {
                    throw new Exception($working_dir->get_error_message());
                }

                $result = $upgrader->install_package(array(
                    'source' => $working_dir,
                    'destination' => WP_PLUGIN_DIR,
                    'clear_destination' => false,
                    'abort_if_destination_exists' => false,
                    'clear_working' => true,
                    'hook_extra' => array(
                        'type' => 'plugin',
                        'action' => 'install',
                    ),
                ));

                if (is_wp_error($result)) {
                    throw new Exception($result->get_error_message());
                }

                $activate = true;
            } catch (Exception $e) {
                printf(
                        __('%1$s could not be installed (%2$s). <a href="%3$s">Please install it manually by clicking here.</a>', 'dc-woocommerce-multi-vendor'), 'WooCommerce', $e->getMessage(), esc_url(admin_url('plugin-install.php?tab=search&s=woocommerce'))
                );
                exit();
            }

            // Discard feedback
            ob_end_clean();
        }

        wp_clean_plugins_cache();
        // Activate this thing
        if ($activate) {
            try {
                $result = activate_plugin($plugin);

                if (is_wp_error($result)) {
                    throw new Exception($result->get_error_message());
                }
            } catch (Exception $e) {
                printf(
                        __('%1$s was installed but could not be activated. <a href="%2$s">Please activate it manually by clicking here.</a>', 'dc-woocommerce-multi-vendor'), 'WooCommerce', admin_url('plugins.php')
                );
                exit();
            }
        }
        wp_safe_redirect(admin_url('index.php?page=wcmp-setup'));
    }

    /**
     * Get slug from path
     * @param  string $key
     * @return string
     */
    private static function format_plugin_slug($key) {
        $slug = explode('/', $key);
        $slug = explode('.', end($slug));
        return $slug[0];
    }

    /**
     * Get the URL for the next step's screen.
     * @param string step   slug (default: current step)
     * @return string       URL for next step if a next step exists.
     *                      Admin URL if it's the last step.
     *                      Empty string on failure.
     * @since 2.7.7
     */
    public function get_next_step_link($step = '') {
        if (!$step) {
            $step = $this->step;
        }

        $keys = array_keys($this->steps);
        if (end($keys) === $step) {
            return admin_url();
        }

        $step_index = array_search($step, $keys);
        if (false === $step_index) {
            return '';
        }

        return add_query_arg('step', $keys[$step_index + 1]);
    }

    /**
     * Setup Wizard Header.
     */
    public function setup_wizard_header() {
        global $WCMp;
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
            <head>
                <meta name="viewport" content="width=device-width" />
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                <title><?php esc_html_e('WC Marketplace &rsaquo; Setup Wizard', 'dc-woocommerce-multi-vendor'); ?></title>
                <?php wp_print_scripts('wc-setup'); ?>
                <?php wp_print_scripts('wcmp-setup'); ?>
                <?php do_action('admin_print_styles'); ?>
                <style type="text/css">
                    .wc-setup-steps {
                        justify-content: center;
                    }
                </style>
            </head>
            <body class="wc-setup wp-core-ui">
                <h1 id="wc-logo"><a href="http://wc-marketplace.com/"><img src="<?php echo esc_url($WCMp->plugin_url); ?>assets/images/wc-marketplace.png" alt="WC Marketplace" /></a></h1>
                <?php
            }

    /**
     * Output the steps.
     */
    public function setup_wizard_steps() {
        $ouput_steps = $this->steps;
        array_shift($ouput_steps);
        ?>
        <ol class="wc-setup-steps">
            <?php foreach ($ouput_steps as $step_key => $step) : ?>
                <li class="<?php
                if ($step_key === $this->step) {
                    echo 'active';
                } elseif (array_search($this->step, array_keys($this->steps)) > array_search($step_key, array_keys($this->steps))) {
                    echo 'done';
                }
                ?>"><?php echo esc_html($step['name']); ?></li>
        <?php endforeach; ?>
        </ol>
        <?php
    }

    /**
     * Output the content for the current step.
     */
    public function setup_wizard_content() {
        echo '<div class="wc-setup-content">';
        call_user_func($this->steps[$this->step]['view'], $this);
        echo '</div>';
    }

    /**
     * Introduction step.
     */
    public function wcmp_setup_introduction() {
        ?>
        <h1><?php esc_html_e('Welcome to the WC Marketplace family!', 'dc-woocommerce-multi-vendor'); ?></h1>
        <p><?php esc_html_e('Thank you for choosing WC Marketplace! This quick setup wizard will help you configure the basic settings and you will have your marketplace ready in no time. <strong>It’s completely optional and shouldn’t take longer than five minutes.</strong>', 'dc-woocommerce-multi-vendor'); ?></p>
        <p><?php esc_html_e("If you don't want to go through the wizard right now, you can skip and return to the WordPress dashboard. Come back anytime if you change your mind!", 'dc-woocommerce-multi-vendor'); ?></p>
        <p class="wc-setup-actions step">
            <a href="<?php echo esc_url($this->get_next_step_link()); ?>" class="button-primary button button-large button-next"><?php esc_html_e("Let's go!", 'dc-woocommerce-multi-vendor'); ?></a>
            <a href="<?php echo esc_url(admin_url()); ?>" class="button button-large"><?php esc_html_e('Not right now', 'dc-woocommerce-multi-vendor'); ?></a>
        </p>
        <?php
    }

    /**
     * Store setup content
     */
    public function wcmp_setup_store() {
        ?>
        <h1><?php esc_html_e('Store setup', 'dc-woocommerce-multi-vendor'); ?></h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="vendor_store_url"><?php esc_html_e('Store URL', 'dc-woocommerce-multi-vendor'); ?></label></th>
                    <td>
                        <?php
                        $permalinks = get_option('dc_vendors_permalinks');
                        $vendor_slug = empty($permalinks['vendor_shop_base']) ? _x('', 'slug', 'dc-woocommerce-multi-vendor') : $permalinks['vendor_shop_base'];
                        ?>
                        <input type="text" id="vendor_store_url" name="vendor_store_url" placeholder="<?php esc_attr_e('vendor', 'dc-woocommerce-multi-vendor'); ?>" value="<?php echo esc_attr( $vendor_slug ); ?>" />
                        <p class="description"><?php esc_html_e('Define vendor store URL (' . site_url() . '/[this-text]/[seller-name])', 'dc-woocommerce-multi-vendor') ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="is_single_product_multiple_vendor"><?php esc_html_e('Single Product Multiple Vendors', 'dc-woocommerce-multi-vendor'); ?></label></th>
                    <td>
<?php $is_single_product_multiple_vendor = isset(get_option('wcmp_general_settings_name')['is_singleproductmultiseller']) ? get_option('wcmp_general_settings_name')['is_singleproductmultiseller'] : ''; ?>
                        <input type="checkbox" <?php checked($is_single_product_multiple_vendor, 'Enable'); ?> id="is_single_product_multiple_vendor" name="is_single_product_multiple_vendor" class="input-checkbox" value="Enable" />
                    </td>
                </tr>
            </table>
            <p class="wc-setup-actions step">
                <input type="submit" class="button-primary button button-large button-next" value="<?php esc_attr_e('Continue', 'dc-woocommerce-multi-vendor'); ?>" name="save_step" />
                <a href="<?php echo esc_url($this->get_next_step_link()); ?>" class="button button-large button-next"><?php esc_html_e('Skip this step', 'dc-woocommerce-multi-vendor'); ?></a>
        <?php wp_nonce_field('wcmp-setup'); ?>
            </p>
        </form>
        <?php
    }

    /**
     * commission setup content
     */
    public function wcmp_setup_commission() {
        $payment_settings = get_option('wcmp_payment_settings_name');
        ?>
        <h1><?php esc_html_e('Commission Setup', 'dc-woocommerce-multi-vendor'); ?></h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="revenue_sharing_mode"><?php esc_html_e('Revenue Sharing Mode', 'dc-woocommerce-multi-vendor'); ?></label></th>
                    <td>
                        <?php
                        $revenue_sharing_mode = isset($payment_settings['revenue_sharing_mode']) ? $payment_settings['revenue_sharing_mode'] : 'vendor';
                        ?>
                        <label><input type="radio" <?php checked($revenue_sharing_mode, 'admin'); ?> id="revenue_sharing_mode" name="revenue_sharing_mode" class="input-radio" value="admin" /> <?php esc_html_e('Admin fees', 'dc-woocommerce-multi-vendor'); ?></label><br/>
                        <label><input type="radio" <?php checked($revenue_sharing_mode, 'vendor'); ?> id="revenue_sharing_mode" name="revenue_sharing_mode" class="input-radio" value="vendor" /> <?php esc_html_e('Vendor Commissions', 'dc-woocommerce-multi-vendor'); ?></label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="commission_type"><?php esc_html_e('Commission Type', 'dc-woocommerce-multi-vendor'); ?></label></th>
                    <td>
                        <?php
                        $commission_type = isset($payment_settings['commission_type']) ? $payment_settings['commission_type'] : 'percent';
                        ?>
                        <select id="commission_type" name="commission_type" class="wc-enhanced-select">
                            <option value="fixed" data-fields="#tr_default_commission" <?php selected($commission_type, 'fixed'); ?>><?php esc_html_e('Fixed Amount', 'dc-woocommerce-multi-vendor'); ?></option>
                            <option value="percent" data-fields="#tr_default_commission" <?php selected($commission_type, 'percent'); ?>><?php esc_html_e('Percentage', 'dc-woocommerce-multi-vendor'); ?></option>
                            <option value="fixed_with_percentage" data-fields="#tr_default_percentage,#tr_fixed_with_percentage" <?php selected($commission_type, 'fixed_with_percentage'); ?>><?php esc_html_e('%age + Fixed (per transaction)', 'dc-woocommerce-multi-vendor'); ?></option>
                            <option value="fixed_with_percentage_qty" data-fields="#tr_default_percentage,#tr_fixed_with_percentage_qty" <?php selected($commission_type, 'fixed_with_percentage_qty'); ?>><?php esc_html_e('%age + Fixed (per unit)', 'dc-woocommerce-multi-vendor'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr id="tr_default_commission" class="wcmp_commission_type_fields">
                    <th scope="row"><label for="default_commission"><?php esc_html_e('Commission value', 'dc-woocommerce-multi-vendor'); ?></label></th>
                    <td>
                        <?php
                        $default_commission = isset($payment_settings['default_commission']) ? $payment_settings['default_commission'] : '';
                        ?>
                        <input type="text" id="default_commission" name="default_commission" placeholder="" value="<?php echo esc_attr($default_commission); ?>" />
                    </td>
                </tr>

                <tr id="tr_default_percentage" class="wcmp_commission_type_fields">
                    <th scope="row"><label for="default_percentage"><?php esc_html_e('Commission Percentage', 'dc-woocommerce-multi-vendor'); ?></label></th>
                    <td>
                        <?php
                        $default_percentage = isset($payment_settings['default_percentage']) ? $payment_settings['default_percentage'] : '';
                        ?>
                        <input type="text" id="default_percentage" name="default_percentage" placeholder="" value="<?php echo esc_attr($default_percentage); ?>" />
                    </td>
                </tr>

                <tr id="tr_fixed_with_percentage" class="wcmp_commission_type_fields">
                    <th scope="row"><label for="fixed_with_percentage"><?php esc_html_e('Fixed Amount', 'dc-woocommerce-multi-vendor'); ?></label></th>
                    <td>
                        <?php
                        $fixed_with_percentage = isset($payment_settings['fixed_with_percentage']) ? $payment_settings['fixed_with_percentage'] : '';
                        ?>
                        <input type="text" id="fixed_with_percentage" name="fixed_with_percentage" placeholder="" value="<?php echo esc_attr($fixed_with_percentage); ?>" />
                    </td>
                </tr>

                <tr id="tr_fixed_with_percentage_qty" class="wcmp_commission_type_fields">
                    <th scope="row"><label for="fixed_with_percentage_qty"><?php esc_html_e('Fixed Amount', 'dc-woocommerce-multi-vendor'); ?></label></th>
                    <td>
                        <?php
                        $fixed_with_percentage_qty = isset($payment_settings['fixed_with_percentage_qty']) ? $payment_settings['fixed_with_percentage_qty'] : '';
                        ?>
                        <input type="text" id="fixed_with_percentage_qty" name="fixed_with_percentage_qty" placeholder="" value="<?php echo esc_attr($fixed_with_percentage_qty); ?>" />
                    </td>
                </tr>

            </table>
            <p class="wc-setup-actions step">
                <input type="submit" class="button-primary button button-large button-next" value="<?php esc_attr_e('Continue', 'dc-woocommerce-multi-vendor'); ?>" name="save_step" />
                <a href="<?php echo esc_url($this->get_next_step_link()); ?>" class="button button-large button-next"><?php esc_html_e('Skip this step', 'dc-woocommerce-multi-vendor'); ?></a>
        <?php wp_nonce_field('wcmp-setup'); ?>
            </p>
        </form>
        <?php
    }

    /**
     * payment setup content
     */
    public function wcmp_setup_payments() {
        $payment_settings = get_option('wcmp_payment_settings_name');
        $gateways = $this->get_payment_methods();
        ?>
        <h1><?php esc_html_e('Payments', 'dc-woocommerce-multi-vendor'); ?></h1>
        <form method="post" class="wc-wizard-payment-gateway-form">
            <p><?php esc_html_e('Allowed Payment Methods', 'dc-woocommerce-multi-vendor'); ?></p>

            <ul class="wc-wizard-services wc-wizard-payment-gateways">
                        <?php foreach ($gateways as $gateway_id => $gateway): ?>
                    <li class="wc-wizard-service-item wc-wizard-gateway <?php echo esc_attr($gateway['class']); ?>">
                        <div class="wc-wizard-service-name">
                            <label>
    <?php echo esc_html($gateway['label']); ?>
                            </label>
                        </div>
                        <div class="wc-wizard-gateway-description">
                    <?php echo wp_kses_post(wpautop($gateway['description'])); ?>
                        </div>
                        <div class="wc-wizard-service-enable">
                            <span class="wc-wizard-service-toggle disabled">
                                <?php
                                $is_enable_gateway = isset($payment_settings['payment_method_' . $gateway_id]) ? $payment_settings['payment_method_' . $gateway_id] : '';
                                ?>
                                <input type="checkbox" <?php checked($is_enable_gateway, 'Enable') ?> name="payment_method_<?php echo esc_attr($gateway_id); ?>" class="input-checkbox" value="Enable" />
                            </span>
                        </div>
                    </li>
<?php endforeach; ?>
            </ul>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="wcmp_disbursal_mode_admin"><?php esc_html_e('Disbursal Schedule', 'dc-woocommerce-multi-vendor'); ?></label></th>
                    <td>
                        <?php
                        $wcmp_disbursal_mode_admin = isset($payment_settings['wcmp_disbursal_mode_admin']) ? $payment_settings['wcmp_disbursal_mode_admin'] : '';
                        ?>
                        <input type="checkbox" data-field="#tr_payment_schedule" <?php checked($wcmp_disbursal_mode_admin, 'Enable'); ?> id="wcmp_disbursal_mode_admin" name="wcmp_disbursal_mode_admin" class="input-checkbox" value="Enable" />
                        <p class="description"><?php esc_html_e('If checked, automatically vendors commission will disburse.', 'dc-woocommerce-multi-vendor') ?></p>
                    </td>
                </tr>
                <tr id="tr_payment_schedule">
                    <th scope="row"><label for="payment_schedule"><?php esc_html_e('Set Schedule', 'dc-woocommerce-multi-vendor'); ?></label></th>
                    <?php
                    $payment_schedule = isset($payment_settings['payment_schedule']) ? $payment_settings['payment_schedule'] : 'monthly';
                    ?>
                    <td>
                        <label><input type="radio" <?php checked($payment_schedule, 'weekly'); ?> id="payment_schedule" name="payment_schedule" class="input-radio" value="weekly" /> <?php esc_html_e('Weekly', 'dc-woocommerce-multi-vendor'); ?></label><br/>
                        <label><input type="radio" <?php checked($payment_schedule, 'daily'); ?> id="payment_schedule" name="payment_schedule" class="input-radio" value="daily" /> <?php esc_html_e('Daily', 'dc-woocommerce-multi-vendor'); ?></label><br/>
                        <label><input type="radio" <?php checked($payment_schedule, 'monthly'); ?> id="payment_schedule" name="payment_schedule" class="input-radio" value="monthly" /> <?php esc_html_e('Monthly', 'dc-woocommerce-multi-vendor'); ?></label><br/>
                        <label><input type="radio" <?php checked($payment_schedule, 'fortnightly'); ?> id="payment_schedule" name="payment_schedule" class="input-radio" value="fortnightly" /> <?php esc_html_e('Fortnightly', 'dc-woocommerce-multi-vendor'); ?></label><br/>
                        <label><input type="radio" <?php checked($payment_schedule, 'hourly'); ?> id="payment_schedule" name="payment_schedule" class="input-radio" value="hourly" /> <?php esc_html_e('Hourly', 'dc-woocommerce-multi-vendor'); ?></label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wcmp_disbursal_mode_vendor"><?php esc_html_e('Withdrawal Request', 'dc-woocommerce-multi-vendor'); ?></label></th>
                    <td>
                        <?php
                        $wcmp_disbursal_mode_vendor = isset($payment_settings['wcmp_disbursal_mode_vendor']) ? $payment_settings['wcmp_disbursal_mode_vendor'] : '';
                        ?>
                        <input type="checkbox" <?php checked($wcmp_disbursal_mode_vendor, 'Enable'); ?> id="wcmp_disbursal_mode_vendor" name="wcmp_disbursal_mode_vendor" class="input-checkbox" value="Enable" />
                        <p class="description"><?php esc_html_e('Vendors can request for commission withdrawal.', 'dc-woocommerce-multi-vendor') ?></p>
                    </td>
                </tr>
            </table>
            <p class="wc-setup-actions step">
                <input type="submit" class="button-primary button button-large button-next" value="<?php esc_attr_e('Continue', 'dc-woocommerce-multi-vendor'); ?>" name="save_step" />
                <a href="<?php echo esc_url($this->get_next_step_link()); ?>" class="button button-large button-next"><?php esc_html_e('Skip this step', 'dc-woocommerce-multi-vendor'); ?></a>
        <?php wp_nonce_field('wcmp-setup'); ?>
            </p>
        </form>
        <?php
    }

    /**
     * capability setup content
     */
    public function wcmp_setup_capability() {
        $capabilities_settings = get_option('wcmp_capabilities_product_settings_name');
        ?>
        <h1><?php esc_html_e('Capability', 'dc-woocommerce-multi-vendor'); ?></h1>
        <form method="post">
            <table class="form-table">
                <?php
                $is_submit_product = isset($capabilities_settings['is_submit_product']) ? $capabilities_settings['is_submit_product'] : '';
                ?>
                <tr>
                    <th scope="row"><label for="is_submit_product"><?php esc_html_e('Submit Products', 'dc-woocommerce-multi-vendor'); ?></label></th>
                    <td>
                        <input type="checkbox" <?php checked($is_submit_product, 'Enable'); ?> id="is_submit_product" name="is_submit_product" class="input-checkbox" value="Enable">
                        <p class="description"><?php esc_html_e('Allow vendors to submit products for approval/publishing.', 'dc-woocommerce-multi-vendor'); ?></p>
                    </td>
                </tr>
                <?php
                $is_published_product = isset($capabilities_settings['is_published_product']) ? $capabilities_settings['is_published_product'] : '';
                ?>
                <tr>
                    <th scope="row"><label for="is_published_product"><?php esc_html_e('Publish Products', 'dc-woocommerce-multi-vendor'); ?></label></th>
                    <td>
                        <input type="checkbox" <?php checked($is_published_product, 'Enable'); ?> id="is_published_product" name="is_published_product" class="input-checkbox" value="Enable">
                        <p class="description"><?php esc_html_e('If checked, products uploaded by vendors will be directly published without admin approval.', 'dc-woocommerce-multi-vendor'); ?></p>
                    </td>
                </tr>
                <?php
                $is_edit_delete_published_product = isset($capabilities_settings['is_edit_delete_published_product']) ? $capabilities_settings['is_edit_delete_published_product'] : '';
                ?>
                <tr>
                    <th scope="row"><label for="is_edit_delete_published_product"><?php esc_html_e('Edit Publish Products', 'dc-woocommerce-multi-vendor'); ?></label></th>
                    <td>
                        <input type="checkbox" <?php checked($is_edit_delete_published_product, 'Enable'); ?> id="is_edit_delete_published_product" name="is_edit_delete_published_product" class="input-checkbox" value="Enable">
                        <p class="description"><?php esc_html_e('Allow vendors to Edit published products.', 'dc-woocommerce-multi-vendor'); ?></p>
                    </td>
                </tr>
                <?php
                $is_submit_coupon = isset($capabilities_settings['is_submit_coupon']) ? $capabilities_settings['is_submit_coupon'] : '';
                ?>
                <tr>
                    <th scope="row"><label for="is_submit_coupon"><?php esc_html_e('Submit Coupons', 'dc-woocommerce-multi-vendor'); ?></label></th>
                    <td>
                        <input type="checkbox" <?php checked($is_submit_coupon, 'Enable'); ?> id="is_submit_coupon" name="is_submit_coupon" class="input-checkbox" value="Enable">
                        <p class="description"><?php esc_html_e('Allow vendors to create coupons.', 'dc-woocommerce-multi-vendor'); ?></p>
                    </td>
                </tr>
                <?php
                $is_published_coupon = isset($capabilities_settings['is_published_coupon']) ? $capabilities_settings['is_published_coupon'] : '';
                ?>
                <tr>
                    <th scope="row"><label for="is_published_coupon"><?php esc_html_e('Publish Coupons', 'dc-woocommerce-multi-vendor'); ?></label></th>
                    <td>
                        <input type="checkbox" <?php checked($is_published_coupon, 'Enable'); ?> id="is_published_coupon" name="is_published_coupon" class="input-checkbox" value="Enable">
                        <p class="description"><?php esc_html_e('If checked, coupons added by vendors will be directly published without admin approval.', 'dc-woocommerce-multi-vendor'); ?></p>
                    </td>
                </tr>
                <?php
                $is_edit_delete_published_coupon = isset($capabilities_settings['is_edit_delete_published_coupon']) ? $capabilities_settings['is_edit_delete_published_coupon'] : '';
                ?>
                <tr>
                    <th scope="row"><label for="is_edit_delete_published_coupon"><?php esc_html_e('Edit Publish Coupons', 'dc-woocommerce-multi-vendor'); ?></label></th>
                    <td>
                        <input type="checkbox" <?php checked($is_edit_delete_published_coupon, 'Enable'); ?> id="is_edit_delete_published_coupon" name="is_edit_delete_published_coupon" class="input-checkbox" value="Enable">
                        <p class="description"><?php esc_html_e('Allow Vendor To edit delete published shop coupons.', 'dc-woocommerce-multi-vendor'); ?></p>
                    </td>
                </tr>
                <?php
                $is_upload_files = isset($capabilities_settings['is_upload_files']) ? $capabilities_settings['is_upload_files'] : '';
                ?>
                <tr>
                    <th scope="row"><label for="is_upload_files"><?php esc_html_e('Upload Media Files', 'dc-woocommerce-multi-vendor'); ?></label></th>
                    <td>
                        <input type="checkbox" <?php checked($is_upload_files, 'Enable'); ?> id="is_upload_files" name="is_upload_files" class="input-checkbox" value="Enable">
                        <p class="description"><?php esc_html_e('Allow vendors to upload media files.', 'dc-woocommerce-multi-vendor'); ?></p>
                    </td>
                </tr>

            </table>
            <p class="wc-setup-actions step">
                <input type="submit" class="button-primary button button-large button-next" value="<?php esc_attr_e('Continue', 'dc-woocommerce-multi-vendor'); ?>" name="save_step" />
                <a href="<?php echo esc_url($this->get_next_step_link()); ?>" class="button button-large button-next"><?php esc_html_e('Skip this step', 'dc-woocommerce-multi-vendor'); ?></a>
                <?php wp_nonce_field('wcmp-setup'); ?>
            </p>
        </form>
        <?php
    }

    /**
     * Ready to go content
     */
    public function wcmp_setup_ready() {
        ?>
        <a href="https://twitter.com/share" class="twitter-share-button" data-url="<?php echo site_url(); ?>" data-text="Hey Guys! Our new marketplace is now live and ready to be ransacked! Check it out at" data-via="wc_marketplace" data-size="large">Tweet</a>
        <script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
        <h1><?php esc_html_e('Yay! All done!', 'dc-woocommerce-multi-vendor'); ?></h1>
        <div class="woocommerce-message woocommerce-tracker">
            <p><?php esc_html_e("Your marketplace is ready. It's time to bring some sellers on your platform and start your journey. We wish you all the success for your business, you will be great!", "dc-woocommerce-multi-vendor") ?></p>
        </div>
        <div class="wc-setup-next-steps">
            <div class="wc-setup-next-steps-first">
                <h2><?php esc_html_e( 'Next steps', 'dc-woocommerce-multi-vendor' ); ?></h2>
                <ul>
                    <li class="setup-product"><a class="button button-primary button-large" href="<?php echo esc_url( admin_url( 'admin.php?page=wcmp-setting-admin&tab=vendor&tab_section=registration' ) ); ?>"><?php esc_html_e( 'Create your vendor registration form', 'dc-woocommerce-multi-vendor' ); ?></a></li>
                </ul>
            </div>
            <div class="wc-setup-next-steps-last">
                <h2><?php _e( 'Learn more', 'dc-woocommerce-multi-vendor' ); ?></h2>
                <ul>
                    <li class="video-walkthrough"><a href="https://www.youtube.com/c/WCMarketplace"><?php esc_html_e( 'Watch the tutorial videos', 'dc-woocommerce-multi-vendor' ); ?></a></li>
                    <li class="newsletter"><a href="https://wc-marketplace.com/knowledgebase/wcmp-setup-guide/?utm_source=wcmp_plugin&utm_medium=setup_wizard&utm_campaign=new_installation&utm_content=documentation"><?php esc_html_e( 'Looking for help to get started', 'dc-woocommerce-multi-vendor' ); ?></a></li>
                    <li class="learn-more"><a href="https://wc-marketplace.com/best-revenue-model-marketplace-part-one/?utm_source=wcmp_plugin&utm_medium=setup_wizard&utm_campaign=new_installation&utm_content=blog"><?php esc_html_e( 'Learn more about revenue models', 'dc-woocommerce-multi-vendor' ); ?></a></li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * save store settings
     */
    public function wcmp_setup_store_save() {
        check_admin_referer('wcmp-setup');
        $general_settings = get_option('wcmp_general_settings_name');
        $vendor_permalink = filter_input(INPUT_POST, 'vendor_store_url');
        $is_single_product_multiple_vendor = filter_input(INPUT_POST, 'is_single_product_multiple_vendor');
        if ($is_single_product_multiple_vendor) {
            $general_settings['is_singleproductmultiseller'] = $is_single_product_multiple_vendor;
        } else if (isset($general_settings['is_singleproductmultiseller'])) {
            unset($general_settings['is_singleproductmultiseller']);
        }
        update_option('wcmp_general_settings_name', $general_settings);
        if ($vendor_permalink) {
            $permalinks = get_option('dc_vendors_permalinks', array());
            $permalinks['vendor_shop_base'] = untrailingslashit($vendor_permalink);
            update_option('dc_vendors_permalinks', $permalinks);
            flush_rewrite_rules();
        }
        wp_redirect(esc_url_raw($this->get_next_step_link()));
        exit;
    }

    /**
     * save commission settings
     */
    public function wcmp_setup_commission_save() {
        check_admin_referer('wcmp-setup');
        $payment_settings = get_option('wcmp_payment_settings_name');
        $revenue_sharing_mode = filter_input(INPUT_POST, 'revenue_sharing_mode');
        $commission_type = filter_input(INPUT_POST, 'commission_type');
        $default_commission = filter_input(INPUT_POST, 'default_commission');
        $default_percentage = filter_input(INPUT_POST, 'default_percentage');
        $fixed_with_percentage = filter_input(INPUT_POST, 'fixed_with_percentage');
        $fixed_with_percentage_qty = filter_input(INPUT_POST, 'fixed_with_percentage_qty');
        if ($revenue_sharing_mode) {
            $payment_settings['revenue_sharing_mode'] = $revenue_sharing_mode;
        }
        if ($commission_type) {
            $payment_settings['commission_type'] = $commission_type;
        }
        if ($default_commission) {
            $payment_settings['default_commission'] = $default_commission;
        }
        if ($default_percentage) {
            $payment_settings['default_percentage'] = $default_percentage;
        }
        if ($fixed_with_percentage) {
            $payment_settings['fixed_with_percentage'] = $fixed_with_percentage;
        }
        if ($fixed_with_percentage_qty) {
            $payment_settings['fixed_with_percentage_qty'] = $fixed_with_percentage_qty;
        }
        update_option('wcmp_payment_settings_name', $payment_settings);
        wp_redirect(esc_url_raw($this->get_next_step_link()));
        exit;
    }

    /**
     * save payment settings
     */
    public function wcmp_setup_payments_save() {
        check_admin_referer('wcmp-setup');
        $gateways = $this->get_payment_methods();
        $payment_settings = get_option('wcmp_payment_settings_name');
        $wcmp_disbursal_mode_admin = filter_input(INPUT_POST, 'wcmp_disbursal_mode_admin');
        $wcmp_disbursal_mode_vendor = filter_input(INPUT_POST, 'wcmp_disbursal_mode_vendor');
        if ($wcmp_disbursal_mode_admin) {
            $payment_settings['wcmp_disbursal_mode_admin'] = $wcmp_disbursal_mode_admin;
            $payment_schedule = filter_input(INPUT_POST, 'payment_schedule');
            if ($payment_schedule) {
                $payment_settings['payment_schedule'] = $payment_schedule;
                $schedule = wp_get_schedule('masspay_cron_start');
                if ($schedule != $payment_schedule) {
                    if (wp_next_scheduled('masspay_cron_start')) {
                        $timestamp = wp_next_scheduled('masspay_cron_start');
                        wp_unschedule_event($timestamp, 'masspay_cron_start');
                    }
                    wp_schedule_event(time(), $payment_schedule, 'masspay_cron_start');
                }
            }
        } else if (isset($payment_settings['wcmp_disbursal_mode_admin'])) {
            unset($payment_settings['wcmp_disbursal_mode_admin']);
            if (wp_next_scheduled('masspay_cron_start')) {
                $timestamp = wp_next_scheduled('masspay_cron_start');
                wp_unschedule_event($timestamp, 'masspay_cron_start');
            }
        }

        if ($wcmp_disbursal_mode_vendor) {
            $payment_settings['wcmp_disbursal_mode_vendor'] = $wcmp_disbursal_mode_vendor;
        } else if (isset($payment_settings['wcmp_disbursal_mode_vendor'])) {
            unset($payment_settings['wcmp_disbursal_mode_vendor']);
        }

        foreach ($gateways as $gateway_id => $gateway) {
            $is_enable_gateway = filter_input(INPUT_POST, 'payment_method_' . $gateway_id);
            if ($is_enable_gateway) {
                $payment_settings['payment_method_' . $gateway_id] = $is_enable_gateway;
                if (!empty($gateway['repo-slug'])) {
                    wp_schedule_single_event(time() + 10, 'woocommerce_plugin_background_installer', array($gateway_id, $gateway));
                }
            } else if (isset($payment_settings['payment_method_' . $gateway_id])) {
                unset($payment_settings['payment_method_' . $gateway_id]);
            }
        }
        update_option('wcmp_payment_settings_name', $payment_settings);
        wp_redirect(esc_url_raw($this->get_next_step_link()));
        exit;
    }

    /**
     * save capability settings
     * @global object $WCMp
     */
    public function wcmp_setup_capability_save() {
        global $WCMp;
        check_admin_referer('wcmp-setup');
        $capability_settings = get_option('wcmp_capabilities_product_settings_name');

        $is_submit_product = filter_input(INPUT_POST, 'is_submit_product');
        $is_published_product = filter_input(INPUT_POST, 'is_published_product');
        $is_edit_delete_published_product = filter_input(INPUT_POST, 'is_edit_delete_published_product');
        $is_submit_coupon = filter_input(INPUT_POST, 'is_submit_coupon');
        $is_published_coupon = filter_input(INPUT_POST, 'is_published_coupon');
        $is_edit_delete_published_coupon = filter_input(INPUT_POST, 'is_edit_delete_published_coupon');
        $is_upload_files = filter_input(INPUT_POST, 'is_upload_files');

        if ($is_submit_product) {
            $capability_settings['is_submit_product'] = $is_submit_product;
        } else if (isset($capability_settings['is_submit_product'])) {
            unset($capability_settings['is_submit_product']);
        }
        if ($is_published_product) {
            $capability_settings['is_published_product'] = $is_published_product;
        } else if (isset($capability_settings['is_published_product'])) {
            unset($capability_settings['is_published_product']);
        }
        if ($is_edit_delete_published_product) {
            $capability_settings['is_edit_delete_published_product'] = $is_edit_delete_published_product;
        } else if (isset($capability_settings['is_edit_delete_published_product'])) {
            unset($capability_settings['is_edit_delete_published_product']);
        }
        if ($is_submit_coupon) {
            $capability_settings['is_submit_coupon'] = $is_submit_coupon;
        } else if (isset($capability_settings['is_submit_coupon'])) {
            unset($capability_settings['is_submit_coupon']);
        }
        if ($is_published_coupon) {
            $capability_settings['is_published_coupon'] = $is_published_coupon;
        } else if (isset($capability_settings['is_published_coupon'])) {
            unset($capability_settings['is_published_coupon']);
        }
        if ($is_edit_delete_published_coupon) {
            $capability_settings['is_edit_delete_published_coupon'] = $is_edit_delete_published_coupon;
        } else if (isset($capability_settings['is_edit_delete_published_coupon'])) {
            unset($capability_settings['is_edit_delete_published_coupon']);
        }
        if ($is_upload_files) {
            $capability_settings['is_upload_files'] = $is_upload_files;
        } else if (isset($capability_settings['is_upload_files'])) {
            unset($capability_settings['is_upload_files']);
        }
        update_option('wcmp_capabilities_product_settings_name', $capability_settings);
        $WCMp->vendor_caps->update_wcmp_vendor_role_capability();
        wp_redirect(esc_url_raw($this->get_next_step_link()));
        exit;
    }

    /**
     * Migration Introduction step.
     */
    public function wcmp_migration_introduction() {
        global $WCMp;
        $WCMp->multivendor_migration->wcmp_migration_first_step( $this->get_next_step_link() );
    }
    
    public function wcmp_migration_store_process() {
        global $WCMp;
        $WCMp->multivendor_migration->wcmp_migration_third_step( $this->get_next_step_link() );
    }

    /**
     * Setup Wizard Footer.
     */
    public function setup_wizard_footer() {
        if ('next_steps' === $this->step) :
            ?>
            <a class="wc-return-to-dashboard" href="<?php echo esc_url(admin_url()); ?>"><?php esc_html_e('Return to the WordPress Dashboard', 'dc-woocommerce-multi-vendor'); ?></a>
<?php endif; ?>
    </body>
</html>
<?php
}

    public function get_payment_methods() {
        $methods = array(
            'paypal_masspay' => array(
                'label' => __('Paypal Masspay', 'dc-woocommerce-multi-vendor'),
                'description' => __('Pay via paypal masspay', 'dc-woocommerce-multi-vendor'),
                'class' => 'featured featured-row-last'
            ),
            'paypal_payout' => array(
                'label' => __('Paypal Payout', 'dc-woocommerce-multi-vendor'),
                'description' => __('Pay via paypal payout', 'dc-woocommerce-multi-vendor'),
                'class' => 'featured featured-row-first'
            ),
            'direct_bank' => array(
                'label' => __('Direct Bank Transfer', 'dc-woocommerce-multi-vendor'),
                'description' => __('', 'dc-woocommerce-multi-vendor'),
                'class' => ''
            ),
            'stripe_masspay' => array(
                'label' => __('Stripe Connect', 'dc-woocommerce-multi-vendor'),
                'description' => __('', 'dc-woocommerce-multi-vendor'),
                //'repo-slug' => 'marketplace-stripe-gateway',
                'class' => ''
            ),
            'paypal_adaptive' => array(
                'label' => __('PayPal Adaptive', 'dc-woocommerce-multi-vendor'),
                'description' => __('', 'dc-woocommerce-multi-vendor'),
                'repo-slug' => 'wcmp-paypal-adaptive-gateway',
                'class' => ''
            )
        );
        return $methods;
    }

}

new WCMp_Admin_Setup_Wizard();
