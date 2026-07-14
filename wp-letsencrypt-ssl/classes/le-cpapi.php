<?php

/**
 * @package WP Encryption
 *
 * @author     WP Encryption
 * @copyright  Copyright (C) 2019-2024, WP Encryption
 * @link       https://wpencryption.com
 * @since      Class available since Release 5.0.4
 *
 */

require_once dirname(__DIR__) . '/classes/cPanel/cPanel.php';

class WPLE_UAPI
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'wple_cpapi_menu__premium_only'), 15);
    }

    public function wple_cpapi_menu__premium_only()
    {
        add_submenu_page(WPLE_SLUG, 'cPanel Login', '-- cPanel Login', 'manage_options', 'wp_encryption_cpanel', array($this, 'wple_cpapi_page__premium_only'));
        add_submenu_page(WPLE_SLUG, 'DirectAdmin Login', '-- DirectAdmin Login', 'manage_options', 'wp_encryption_directadmin', array($this, 'wple_directadmin_page__premium_only'));
    }

    public function wple_cpapi_page__premium_only()
    {
        $this->wple_cpapi_handler();

        $opts = (FALSE !== get_option('wple_cpapi')) ? get_option('wple_cpapi') : array();

        $uh = isset($opts['uhost']) ? esc_attr($opts['uhost']) : '';

        $un = isset($opts['uname']) ? esc_attr($opts['uname']) : '';
        //$tk = isset($opts['token']) ? esc_attr($opts['token']) : '';

        $up = isset($opts['upass']) ? esc_attr($opts['upass']) : '';

        $gform = '<form method="post" class="le-genform">
        <label>cPanel Host</label>
        <input type="text" name="cpapi_host" placeholder="cPanel Host Url" value="' . $uh . '"><br />
        <small style="display: block; margin-bottom: 20px;">Please leave the above Host field empty unless you have cPanel in different url than your domain</small><br />
        <label>cPanel Username</label>
        <input type="text" name="cpapi_name" placeholder="Enter your cPanel Username" value="' . $un . '"><br />
        <label style="min-width: 140px;display: inline-block;">cPanel Password</label>
        <input type="password" name="cpapi_pass" placeholder="Enter your cPanel Password" value="' . $up . '"><br />    
        ' . wp_nonce_field('wple_cpapi', 'wplecpanelapi', false, false) . '
        <button type="submit" name="cpapi_save">Save Settings</button>
        </form>';

        $html = '<div class="wple-header">
        <img src="' . WPLE_URL . 'admin/assets/logo.png" class="wple-logo"/> <span class="wple-version">v' . WPLE_PLUGIN_VER . '</span>
        </div>';

        $html .= '<div id="wple-sslgen" class="wple-gdaddy">
        <h2>cPanel Login</h2>';

        $html .= do_action('wple_notice');

        $html .= '<p>Please use this backup feature Only if you receive an error saying <b>shell_exec</b> function not enabled on your server.<br><strong>NOTE:</strong> Please leave these fields <b>empty</b> if you have shell_exec function enabled and SSL installation is working fine.</p>
        ' . $gform . '
        <!--<video width="500" height="370" controls>
        <source src="https://gowebsmarty.com/cPanelAPI.webm" type="video/webm">
        Your browser does not support the video tag.
        </video>-->
        </div>';

        echo $html;
    }

    public function wple_cpapi_handler()
    {

        if (isset($_POST['wplecpanelapi'])) {

            if (!wp_verify_nonce($_POST['wplecpanelapi'], 'wple_cpapi') || !current_user_can('manage_options')) {
                exit('No cheating allowed');
            }

            $opts = array();
            $opts['uname'] = sanitize_text_field($_POST['cpapi_name']);
            $opts['uhost'] = sanitize_text_field($_POST['cpapi_host']);
            $token = sanitize_text_field($_POST['cpapi_pass']);

            if ($opts['uname'] == '' && $token == '') {
                delete_option('wple_cpapi');
            } else {

                if (base64_encode(base64_decode($token, true)) === $token) { //already encoded input
                    $opts['upass'] = $token;
                } else {
                    $opts['upass'] = base64_encode($token);
                }

                $decodedtoken = base64_decode($opts['upass']);

                $rootdomain = WPLE_Trait::get_root_domain();
                $host = is_ssl() ? 'https://' . $rootdomain : 'http://' . $rootdomain;

                if ($opts['uhost'] != '') {
                    $host = $opts['uhost'];
                }
                $CP_host = WPLE_LogMeIn::getCpanelHost($host);
                $CP = new WPLE_cPanel($CP_host, $opts['uname'], $decodedtoken);

                if (!$CP->checkConnection()) {
                    update_option('wple_cpapi', array());
                    add_action('wple_notice', array($this, 'wple_cpapi_error_notice'));
                    return;
                }

                update_option('wple_cpapi', $opts);
            }

            add_action('wple_notice', array($this, 'wple_cpapi_saved_notice'));
        }
    }

    public function wple_cpapi_saved_notice()
    {

        $html = '<div class="notice notice-success">
    <p><strong>Login saved! Please run the SSL install form now.</strong></p>
    </div>';

        echo $html;
    }

    public function wple_cpapi_error_notice()
    {

        $html = '<div class="notice notice-error">
    <p><strong>cPanel connection failed! Please double check your username and password.</strong></p>
    </div>';

        echo $html;
    }


    public static function wple_install_ssl__premium_only($cpcred, $domain, $cert, $key, $ca)
    {

        if ($cpcred['uname'] == '' || $cpcred['upass'] == '') {
            wp_die('cPanel Username or Password missing! Please go back and check cPanel login page.');
        }

        $cptoken = base64_decode($cpcred['upass']);

        $rootdomain = WPLE_Trait::get_root_domain();
        $host = is_ssl() ? 'https://' . $rootdomain : 'http://' . $rootdomain;

        if ($cpcred['uhost'] != '') {
            $host = $cpcred['uhost'];
        }
        $CP_host = WPLE_LogMeIn::getCpanelHost($host);
        $CP = new WPLE_cPanel($CP_host, $cpcred['uname'], $cptoken);

        return $CP->installSSL($rootdomain, ABSPATH . 'keys', true);
    }

    public function wple_directadmin_handler()
    {

        if (isset($_POST['wpledadminapi'])) {

            if (!wp_verify_nonce($_POST['wpledadminapi'], 'wple_dadmin') || !current_user_can('manage_options')) {
                exit('No cheating allowed');
            }

            $opts = array();
            $opts['uname'] = sanitize_text_field($_POST['dadmin_name']);
            $token = sanitize_text_field($_POST['dadmin_pass']);

            if ($opts['uname'] == '' && $token == '') {
                delete_option('wple_directadmin');
            } else {

                if (base64_encode(base64_decode($token, true)) === $token) { //already encoded input
                    $opts['upass'] = $token;
                } else {
                    $opts['upass'] = base64_encode($token);
                }

                update_option('wple_directadmin', $opts);
            }

            add_action('wple_notice', array($this, 'wple_cpapi_saved_notice'));
        }
    }

    public function wple_directadmin_page__premium_only()
    {
        $this->wple_directadmin_handler();

        $opts = (FALSE !== get_option('wple_directadmin')) ? get_option('wple_directadmin') : array();

        $un = isset($opts['uname']) ? esc_attr($opts['uname']) : '';
        $up = isset($opts['upass']) ? esc_attr($opts['upass']) : '';

        $gform = '<form method="post" class="le-genform">        
        <label>DirectAdmin Username</label>
        <input type="text" name="dadmin_name" placeholder="Enter your DirectAdmin Username" value="' . $un . '"><br />
        <label style="min-width: 140px;display: inline-block;">DirectAdmin Password</label>
        <input type="password" name="dadmin_pass" placeholder="Enter your DirectAdmin Password" value="' . $up . '"><br />    
        ' . wp_nonce_field('wple_dadmin', 'wpledadminapi', false, false) . '
        <button type="submit" name="dadmin_save">Save Settings</button>
        </form>';

        $html = '<div class="wple-header">
        <img src="' . WPLE_URL . 'admin/assets/logo.png" class="wple-logo"/> <span class="wple-version">v' . WPLE_PLUGIN_VER . '</span>
        </div>';

        $html .= '<div id="wple-sslgen" class="wple-gdaddy">
        <h2>DirectAdmin Login</h2>';

        $html .= do_action('wple_notice');

        $html .= '<p>Please fill in below required credentials only if you are using DirectAdmin hosting. Otherwise, please make sure to leave the fields empty.</p>
        ' . $gform . '
        </div>';

        echo $html;
    }
}
