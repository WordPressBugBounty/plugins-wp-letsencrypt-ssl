<?php

/**
 * @package WP Encryption
 *
 * @author     WP Encryption
 * @copyright  Copyright (C) 2019-2024, WP Encryption
 * @link       https://wpencryption.com
 * @since      Class available since Release 7.2.0
 *
 */
require_once dirname(__DIR__) . '/classes/le-trait.php';

if (class_exists('WPLE_PleskAPI')) {
    return;
}
class WPLE_PleskAPI
{
    private $webspace;

    public function __construct()
    {
        add_action('admin_menu', array($this, 'wple_plesk_menu__premium_only'), 15);
    }

    public function wple_plesk_menu__premium_only()
    {
        add_submenu_page(WPLE_SLUG, 'Plesk Login', '-- Plesk Login', 'manage_options', 'wp_encryption_plesk', array($this, 'wple_plesk_page__premium_only'));
    }

    public function wple_plesk_page__premium_only()
    {
        $this->wple_plesk_login_handler();

        $opts = (FALSE !== get_option('wple_pleskapi')) ? get_option('wple_pleskapi') : array();

        $uh = isset($opts['uhost']) ? esc_attr($opts['uhost']) : '';
        $ud = isset($opts['udomain']) ? esc_attr($opts['udomain']) : '';

        $un = isset($opts['uname']) ? esc_attr($opts['uname']) : '';
        //$tk = isset($opts['token']) ? esc_attr($opts['token']) : '';

        $up = isset($opts['upass']) ? esc_attr($opts['upass']) : '';

        $gform = '<form method="post" class="le-genform">
    <label>Plesk Host</label>
    <input type="text" name="pleskapi_host" placeholder="https://yourdomain.com:8443" value="' . $uh . '"><br />
    <small style="display: block; margin-bottom: 20px;">Please leave the above Host field empty unless your plesk admin panel is in different location than yoursite.com:8443</small><br />
    <label>Plesk Domain</label>
    <input type="text" name="pleskapi_domain" placeholder="yourdomain.com" value="' . $ud . '"><br />
    <small style="display: block; margin-bottom: 20px;">This could be www or non-www domain as defined in websites & domains page of your plesk panel.</small><br />

    <label>Plesk Username</label>
    <input type="text" name="pleskapi_name" placeholder="Enter your Plesk administrator Username" value="' . $un . '"><br />

    <label style="min-width: 140px;display: inline-block;">Plesk Password</label>
    <input type="password" name="pleskapi_pass" placeholder="Enter your Plesk Password" value="' . $up . '"><br />    

    ' . wp_nonce_field('wple_pleskapi', 'wplepleskapi', false, false) . '
    <button type="submit" name="pleskapi_save">Save Settings</button>
    </form>';

        $html = '<div class="wple-header">
      <img src="' . WPLE_URL . 'admin/assets/logo.png" class="wple-logo"/> <span class="wple-version">v' . WPLE_PLUGIN_VER . '</span>
    </div>';

        $html .= '<div id="wple-sslgen" class="wple-gdaddy">
    <h2>Plesk Login</h2>';

        $html .= do_action('wple_notice');

        $html .= '<p>Please fill in below required credentials only if you are using plesk hosting. Otherwise, please make sure to leave the fields empty.</p>
    ' . $gform . '
    </div>';

        echo $html;
    }

    public function wple_plesk_login_handler()
    {

        if (isset($_POST['wplepleskapi'])) {

            if (!wp_verify_nonce($_POST['wplepleskapi'], 'wple_pleskapi') || !current_user_can('manage_options')) {
                exit('No cheating allowed');
            }

            $opts = array();
            $opts['uname'] = sanitize_text_field($_POST['pleskapi_name']);
            $opts['uhost'] = sanitize_text_field($_POST['pleskapi_host']);
            $opts['udomain'] = sanitize_text_field($_POST['pleskapi_domain']);
            $token = $_POST['pleskapi_pass'];

            if ($opts['uname'] == '' && $token == '') {
                delete_option('wple_pleskapi');
            } else {

                if (base64_encode(base64_decode($token, true)) === $token) { //already encoded input
                    $opts['upass'] = $token;
                } else {
                    $opts['upass'] = base64_encode($token);
                }

                //$rootdomain = WPLE_Trait::get_root_domain();
                // $host = is_ssl() ? 'https://' . $rootdomain : 'http://' . $rootdomain;

                // if ($opts['uhost'] != '') {
                //   $host = $opts['uhost'];
                // }

                // if (!$CP->checkConnection()) {
                //   update_option('wple_cpapi', array());
                //   add_action('wple_notice', array($this, 'wple_cpapi_error_notice'));
                //   return;
                // }

                update_option('wple_pleskapi', $opts);
            }

            add_action('wple_notice', array($this, 'wple_pleskapi_saved_notice'));
        }
    }

    public function wple_pleskapi_saved_notice()
    {

        $html = '<div class="notice notice-success">
    <p><strong>Login saved! Please run the SSL install form now.</strong></p>
    </div>';

        echo $html;
    }

    // public function wple_pleskapi_error_notice()
    // {

    //   $html = '<div class="notice notice-error">
    //   <p><strong>Plesk connection failed! Please double check your username and password.</strong></p>
    //   </div>';

    //   echo $html;
    // }

    /**
     * main function to install ssl
     *
     * @param [type] $csr
     * @param [type] $key
     * @param [type] $cert
     * @param [type] $plesklogin
     * @return void
     */
    public function wple_plesk_install_ssl($csr, $key, $cert, $plesklogin, $cabundlefile)
    {
        $csr = file_get_contents($csr);
        $key = file_get_contents($key);
        $cert = file_get_contents($cert);
        $cabundle = file_get_contents($cabundlefile);

        $this->webspace = $plesklogin['udomain'] !== '' ? $plesklogin['udomain'] : WPLE_Trait::get_root_domain(true);

        $date = date('d-m-Y-H-i');

        $request = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<packet>
<certificate>
<install>
   <name>wpencryption-{$date}</name>
   <webspace>{$this->webspace}</webspace>
   <content>
      <csr>
      {$csr}
      </csr>      
      <pvt>
      {$key}
      </pvt>
      <cert> 
      {$cert}
      </cert>
      <ca>
    {$cabundle}
      </ca>
   </content>
</install>
</certificate>
</packet>
EOF;


        $response = $this->wple_plesk_request($plesklogin, $request); //returns simplexmlelement

        try {
            $this->wple_check_xml_error($response);

            $pleskresult = $response->xpath("//result");

            if (is_array($pleskresult)) {
                $status = $pleskresult[0];
                if ($status->status == 'ok') {
                    WPLE_Trait::wple_logger("Plesk SSL wpencryption-{$date} installed. Assigning the SSL to webspace domain now...");
                    $this->wple_assign_plesk_ssl($this->webspace, $plesklogin, "wpencryption-$date");
                }
            }
        } catch (Exception $e) {
            WPLE_Trait::wple_logger("Plesk SSL installation error | " . $e->getCode() . ": " . $e->getMessage(), 'error', 'a', true);
        }
    }

    private function wple_assign_plesk_ssl($webspace, $plesklogin, $cert_name)
    {
        $req = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<packet>
    <webspace>
    <set>
        <filter>
            <name>{$webspace}</name>
        </filter>
        <values>
            <hosting>
                <vrt_hst>
                    <property>
                        <name>certificate_name</name>
                        <value>{$cert_name}</value>
                    </property>
                </vrt_hst>
            </hosting>
        </values>
    </set>  
</webspace>
</packet>
EOF;

        $res = $this->wple_plesk_request($plesklogin, $req);
        try {
            $this->wple_check_xml_error($res);
            $pleskresult = $res->xpath("//result");

            if (is_array($pleskresult)) {
                $status = $pleskresult[0];
                if ($status->status == 'ok') {

                    delete_option('wple_error'); //complete
                    update_option('wple_ssl_screen', 'success');

                    $finalshell = "<h2>" . esc_html__('Plesk SSL assigned successfully!', 'wp-letsencrypt-ssl') . "!. #PLESK</h2>";
                    WPLE_Trait::wple_logger($finalshell, 'success', 'a');
                    WPLE_Trait::wple_send_log_data();

                    wp_redirect(admin_url('/admin.php?page=wp_encryption&success=1'), 302);
                    exit();
                }
            }
        } catch (Exception $e) {
            WPLE_Trait::wple_logger("Plesk SSL assignment error | " . $e->getCode() . ": " . $e->getMessage(), 'error', 'a', true);
        }
    }

    private function wple_plesk_request($plesklogin, $request)
    {

        $uname = $plesklogin['uname'];
        $upass = base64_decode($plesklogin['upass']);
        $uhost = $plesklogin['uhost'];

        $headers = array(
            "Content-Type: text/xml",
            "HTTP_PRETTY_PRINT: TRUE",
        );
        $headers[] = "HTTP_AUTH_LOGIN: $uname";
        $headers[] = "HTTP_AUTH_PASSWD: $upass";
        ///$headers[] = "KEY: $this->secretKey";

        $curl = curl_init();

        $url = $uhost != '' ? $uhost : (is_ssl() ? 'https://' : 'http://') . WPLE_Trait::get_root_domain() . (is_ssl() ? ':8443' : ':8880');

        curl_setopt($curl, CURLOPT_URL, trailingslashit($url) . "enterprise/control/agent.php");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $request);

        $result = curl_exec($curl);

        $xml = new SimpleXMLElement($result);
        ///$xml = $res->asXML();

        curl_close($curl);

        return $xml;
    }

    public function wple_check_xml_error($xml)
    {
        if ($xml->system && $xml->system->status && 'error' == (string) $xml->system->status) {
            throw new Exception((string) $xml->system->errtext, (int) $xml->system->errcode);
        }

        if ($xml->xpath('//status[text()="error"]') && $xml->xpath('//errcode') && $xml->xpath('//errtext')) {
            try {
                $errorCode = (int) ($xml->xpath('//errcode') ? $xml->xpath('//errcode')[0] : 0);
                $errorMessage = (string) ($xml->xpath('//errtext') ? $xml->xpath('//errtext')[0] : '');
            } catch (Exception $e) {
                $errorCode = 0;
                $errorMessage = '';
            }

            throw new Exception($errorMessage, $errorCode);
        }
    }
}
