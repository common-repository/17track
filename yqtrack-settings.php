<?php
/*
	Version: 1.2.10
	Author: 17TRACK
	Author URI: http://www.17track.net
	Copyright: © 17TRACK
*/
if ( ! defined( 'ABSPATH' ) )exit;
class YqTrack_Settings
{
    private $options;
    private $plugins;    
    public function __construct()
    {
        $this->plugins[] = array(
            'value' => '17TRACK',
            'label' => '17TRACK',
            'path' => 'yqtrack-woocommerce-tracking/17TRACK.php'
        );
        $this->plugins[] = array(
            'value' => 'wc-shipment-tracking',
            'label' => 'WooCommerce Shipment Tracking',
            'path' => array('woocommerce-shipment-tracking/shipment-tracking.php', 'woocommerce-shipment-tracking/woocommerce-shipment-tracking.php')
        );

        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
        add_action('admin_print_styles', array($this, 'admin_styles'));
        add_action('admin_print_scripts', array(&$this, 'library_scripts'));
    }


    public function admin_styles()
    {
        wp_enqueue_style('yqtrack_styles_chosen', plugins_url(basename(dirname(__FILE__))) . '/assets/plugin/chosen/chosen.min.css');
        wp_enqueue_style('yqtrack_styles', plugins_url(basename(dirname(__FILE__))) . '/assets/css/admin.css');
    }

    public function library_scripts()
    {
        wp_enqueue_script('yqtrack_styles_chosen_jquery', plugins_url(basename(dirname(__FILE__))) . '/assets/plugin/chosen/chosen.jquery.min.js');
        wp_enqueue_script('yqtrack_styles_chosen_proto', plugins_url(basename(dirname(__FILE__))) . '/assets/plugin/chosen/chosen.proto.min.js');
        wp_enqueue_script('yqtrack_script_util', plugins_url(basename(dirname(__FILE__))) . '/assets/js/util.js');
        wp_enqueue_script('yqtrack_script_couriers', '//res.17track.net/i18n/merge-i18n/enum/enum.en.js?src=woo');
        wp_enqueue_script('yqtrack_script_setting', plugins_url(basename(dirname(__FILE__))) . '/assets/js/setting.js');
    }
    
    public function add_plugin_page()
    {
        add_options_page(
            '17TRACK Settings Admin',
            '17TRACK',
            'manage_options',
            'yqtrack-setting-admin',
            array($this, 'create_admin_page')
        );
    }
    
    public function create_admin_page()
    {
        $this->options = get_option('yqtrack_option_name');
        ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2>17TRACK Settings</h2>

            <form method="post" action="options.php">
                <?php
                settings_fields('yqtrack_option_group');
                do_settings_sections('yqtrack-setting-admin');
                submit_button();
                ?>
            </form>
        </div>
    <?php
    }
    
    public function page_init()
    {
        register_setting(
            'yqtrack_option_group',
            'yqtrack_option_name', 
            array($this, 'sanitize')
        );

        add_settings_section(
            'yqtrack_setting_section_id', 
            '', 
            array($this, 'print_section_info'), 
            'yqtrack-setting-admin'
        );

        add_settings_field(
            'plugin',
            'Plugin',
            array($this, 'plugin_callback'),
            'yqtrack-setting-admin',
            'yqtrack_setting_section_id'
        );

        add_settings_field(
            'couriers',
            'Couriers',
            array($this, 'couriers_callback'),
            'yqtrack-setting-admin',
            'yqtrack_setting_section_id'
        );

        add_settings_field(
            'order',
            'User account display tracking information',
            array($this, 'order_callback'),
            'yqtrack-setting-admin',
            'yqtrack_setting_section_id'
        );

        add_settings_field(
            'email',
            'Email display tracking information',
            array($this, 'email_callback'),
            'yqtrack-setting-admin',
            'yqtrack_setting_section_id'
        );
    }
    
    public function sanitize($input)
    {
        $new_input = array();

        if (isset($input['couriers'])) {
            $new_input['couriers'] = sanitize_text_field($input['couriers']);
        }

        if (isset($input['plugin'])) {
            $new_input['plugin'] = sanitize_text_field($input['plugin']);
        }

        if (isset($input['order'])) {
            $new_input['order'] = sanitize_text_field($input['order']);
        }

        if (isset($input['email'])) {
            $new_input['email'] = sanitize_text_field($input['email']);
        }
        return $new_input;
    }
    
    public function print_section_info()
    {
       
    }
    public function couriers_callback()
    {
        $couriers = array();
        if (isset($this->options['couriers'])) {
            $couriers = explode(',', $this->options['couriers']);
        }
        echo '<select data-placeholder="Please select couriers" id="yqtrack_couriers_select" class="chosen-select " multiple style="width:100%">';
        echo '</select>';
        echo '<div><div><input type="hidden" id="couriers" name="yqtrack_option_name[couriers]" value="' . implode(",", $couriers) . '"/></div></div>';

    }

    public function order_callback()
    {
        $orderDisplay = 1;
        if (isset($this->options['order'])) {
            $orderDisplay = ($this->options['order']);
        }
        echo '<input type="checkbox" '. ($orderDisplay == 1 ? "checked" : " ") .' id="orderChk" name="orderChk" class="" />';
        echo '<div><input type="hidden" id="order" name="yqtrack_option_name[order]" value="' . $orderDisplay . '"/></div>';
    }

    public function email_callback()
    {
        $emailDisplay = 1;
        if (isset($this->options['email'])) {
            $emailDisplay = ($this->options['email']);
        }
        echo '<input type="checkbox" '. ($emailDisplay == 1 ? "checked" : " ") .' id="emailChk" name="emailChk" class="" />';
        echo '<div><input type="hidden" id="email" name="yqtrack_option_name[email]" value="' . $emailDisplay . '"/></div>';
    }

    public function plugin_callback()
    {
        $options = "";
        foreach ($this->plugins as $plugin) {
            if($plugin['value']=='17TRACK')
            {
                $option = '<option value="' . $plugin['value'] . '"';
                if (isset($this->options['plugin']) && esc_attr($this->options['plugin']) == $plugin['value']) {
                    $option .= ' selected="selected"';
                }

                $option .= '>' . $plugin['label'] . '</option>';
                $options .= $option;
            }
        }
        printf(
            '<select id="plugin" name="yqtrack_option_name[plugin]" class="yqtrack_dropdown">' . $options . '</select>'
        );
    }

}


if (is_admin())
    $yqtrack_settings = new YqTrack_Settings();
