<?php

/**
 * @package WP Encryption
 *
 * @author     WP Encryption
 * @copyright  Copyright (C) 2019-2024, WP Encryption
 * @link       https://wpencryption.com
 * @since      Class available since Release 3.4.0
 *
 */

/**
 * DNS verification automation via Godaddy DNS API
 * 
 * @since 3.4.0
 */

if (class_exists('WPLE_Gdaddy')) {
    return;
}

class WPLE_Gdaddy
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'wple_gdaddy_menu__premium_only'), 13);
    }

    public function wple_gdaddy_menu__premium_only()
    {
        add_submenu_page(WPLE_SLUG, 'WPLE Godaddy DNS Automation', '-- Godaddy DNS', 'manage_options', 'wp_encryption_godaddy', array($this, 'wple_gdaddy_page__premium_only'));
    }

    public function wple_gdaddy_page__premium_only()
    {
        $this->wple_gdaddy_handler();

        $opts = (FALSE !== get_option('wple_gdaddy')) ? get_option('wple_gdaddy') : array();

        $ky = isset($opts['key']) ? esc_attr($opts['key']) : '';
        $sc = isset($opts['secret']) ? esc_attr($opts['secret']) : '';
        $dmn = isset($opts['domain']) ? esc_attr($opts['domain']) : '';

        $gform = '<form method="post" class="le-genform">
    <label>API Key</label><input type="text" name="gdaddy_key" placeholder="Enter your Godaddy API Key" value="' . $ky . '"><br />
    <label>API Secret</label><input type="text" name="gdaddy_secret" placeholder="Enter your Godaddy API Secret" value="' . $sc . '"><br />
    <label style="display:block;margin:15px 0;">(Optional) Enter primary domain below IF your WordPress site is on sub-domain.</label>    
    <input type="text" name="gdaddy_domain" value="' . $dmn . '" placeholder="wpencryption.com"><br />
    ' . wp_nonce_field('wple_godaddy', 'wplegd', false, false) . '
    <button type="submit" name="gdaddy_save">Save Settings</button>
    </form>';

        $html = '<div class="wple-header">
      <img src="' . WPLE_URL . 'admin/assets/logo.png" class="wple-logo"/> <span class="wple-version">v' . WPLE_PLUGIN_VER . '</span>
    </div>';

        $html .= '<div id="wple-sslgen" class="wple-gdaddy">
    <h2>Godaddy DNS Automation</h2>';

        $html .= do_action('wple_notice');

        $html .= '<p>If your primary domain DNS is managed by <strong>Godaddy DNS</strong> and NOT by <strong>cPanel</strong>, Please create your Godaddy API <strong>Production</strong> keys at <strong>https://developer.godaddy.com/keys</strong> and enter the key & secret in below form to automate <strong>DNS based domain verification</strong> while generating Wildcard SSL Certificates.</p>  
    <p><strong>NOTE:</strong> Please leave these fields empty for cPanel based DNS automation.</p> 
    ' . $gform . '
    <iframe width="560" height="315" src="https://www.youtube.com/embed/7Dztj-02Ebg" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
    </div>';

        echo $html;
    }

    public function wple_gdaddy_handler()
    {

        if (isset($_POST['wplegd'])) {

            if (!wp_verify_nonce($_POST['wplegd'], 'wple_godaddy') || !current_user_can('manage_options')) {
                exit('No cheating allowed');
            }

            $opts = array();
            $opts['key'] = sanitize_text_field($_POST['gdaddy_key']);
            $opts['secret'] = sanitize_text_field($_POST['gdaddy_secret']);

            if ($_POST['gdaddy_domain'] != '') {
                $opts['domain'] = sanitize_text_field($_POST['gdaddy_domain']);
            }

            if ($opts['key'] != '' && $opts['secret'] != '') {

                if (isset($opts['domain'])) {
                    $siteURL = esc_html($opts['domain']);
                } else {
                    $siteURL = str_ireplace(array('http://', 'https://', 'www.'), array('', '', ''), site_url());
                }


                $apiURL = 'https://api.godaddy.com/v1/domains/' . $siteURL;
                $handle = curl_init();

                $curlopts = array(
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_URL => $apiURL,
                    CURLOPT_HEADER => false,
                    CURLOPT_HTTPHEADER => array(
                        'accept: application/json',
                        'Authorization: sso-key ' . esc_attr($opts['key']) . ':' . esc_attr($opts['secret']) . ''
                    )
                );

                curl_setopt_array($handle, $curlopts);

                $response = curl_exec($handle);

                curl_close($handle);

                $res = json_decode($response);

                if (property_exists($res, 'message')) {
                    delete_option('wple_gdaddy');
                    wp_die("<span style=\"color:#fff\">Provided API credentials cannot modify DNS records for your domain!. Please double check provided API credentials [Error: " . esc_html($res->message) . "]</span>");
                }

                update_option('wple_gdaddy', $opts);
            } else if ($opts['key'] == '' && $opts['secret'] == '') {
                delete_option('wple_gdaddy');
            }

            add_action('wple_notice', array($this, 'wple_gdaddy_saved_notice'));
        }
    }

    public function wple_gdaddy_saved_notice()
    {

        $html = '<div class="notice notice-success">
    <p><strong>Settings saved!</strong></p>
    </div>';

        echo $html;
    }

    public static function wple_add_dns_gdaddy__premium_only($records, $gdopts, $mdomain = false)
    {
        $syt = str_ireplace(array('http://', 'https://', 'www.'), array('', '', ''), site_url());

        if (is_multisite() && $mdomain != FALSE) {
            $syt = $mdomain;
        }

        $primary = $syt;
        if (isset($gdopts['domain']) && $gdopts['domain'] != '') {
            $primary = str_ireplace(array('http://', 'https://', 'www.'), array('', '', ''), $gdopts['domain']);
        }

        for ($r = 0; $r < count($records); $r++) {
            $dnsitem = $records[$r];

            $record = explode('||', $dnsitem);

            $apiURL = 'https://api.godaddy.com/v1/domains/' . $primary . '/records';
            $handle = curl_init();

            $acmename = WPLE_Trait::wple_get_acmename($syt, $record[0]);
            $payload = '[{"data": "' . $record[1] . '","name": "' . $acmename . '","ttl": 600,"type": "TXT"}]';

            $curlopts = array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_CUSTOMREQUEST => 'PATCH',
                CURLOPT_URL => $apiURL,
                //CURLOPT_HEADER => false,
                CURLOPT_HTTPHEADER => array(
                    'accept: application/json',
                    'Content-Type: application/json',
                    'Authorization: sso-key ' . esc_attr($gdopts['key']) . ':' . esc_attr($gdopts['secret']) . ''
                ),
                CURLOPT_POSTFIELDS => $payload
            );

            curl_setopt_array($handle, $curlopts);

            $response = curl_exec($handle);

            $rcode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

            curl_close($handle);

            WPLE_Trait::wple_logger($payload . '-----' . $rcode);

            if ($rcode !== 200) {
                $rarr = json_decode($response);
                if (is_array($rarr)) {
                    $error = array_key_exists('message', $rarr) ? $rarr['message'] : 'Something went wrong! try again';
                } else if (is_object($rarr)) {
                    $error = $rarr->message;
                } else {
                    $error = 'Something went wrong! try again';
                }

                if (false === stripos($error, 'exist')) {
                    throw new Exception($error);
                }
            }
        }

        return true;
    }
}
