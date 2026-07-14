<?php

/**
 * @package WP Encryption
 *
 * @author     WP Encryption
 * @copyright  Copyright (C) 2019-2025, WP Encryption
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 * @link       https://wpencryption.com
 * @since      Class available since Release 1.0.0
 *
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

if (!wple_fs()->can_use_premium_code__premium_only()) {
    if (! defined('ABSPATH')) exit; // Exit if accessed directly
}

/**
 * require all the lib files for generating certs
 */

use WPLEClient\LEFunctions;
// use WPLEClient\LEConnector;
// use WPLEClient\LEAccount;
// use WPLEClient\LEAuthorization;

use WPLEClient\LEClient;
use WPLEClient\LEOrder;


if (wple_fs()->can_use_premium_code__premium_only()) {
    require_once(dirname(__DIR__) . '/classes/le-cpapi.php');
    require_once(dirname(__DIR__) . '/classes/le-spmode.php');
}

require_once dirname(__DIR__) . '/classes/le-trait.php';


if (class_exists('WPLE_Core')) {
    return;
}
/**
 * WPLE_Core class
 * Responsible for handling account registration, certificate generation & install certs on cPanel
 * 
 * @since 1.0.0  
 */
class WPLE_Core
{

    protected $email;
    protected $date;
    protected $basedomain;
    public $domains;
    protected $mdomain = false;
    protected $rootdomain;

    protected $client;
    protected $order;
    protected $pendings;

    protected $wcard = false;
    protected $dnss = false;
    protected $iscron = false;
    protected $noscriptresponse = false;

    protected $disablespmode = false;
    private $wizard = false;

    /**
     * construct all params & proceed with cert generation
     *
     * @since 1.0.0
     * @param array $opts
     * @param boolean $gen
     */
    public function __construct($opts = array(), $gen = true, $wc = false, $cron = false)
    {
        if ($cron) {
            $this->iscron = true;
        }


        if (!empty($opts)) {
            $this->email = sanitize_email($opts['email']);
            $this->date = $opts['date'];
            $optss = $opts;

            if (isset($opts['wizard'])) {
                $this->wizard = true;
            }
        } else {
            $optss = get_option('wple_opts');
            $this->email = isset($optss['email']) ? sanitize_email($optss['email']) : '';
            $this->date = isset($optss['date']) ? $optss['date'] : '';
        }

        $siteurl = site_url();

        if (wple_fs()->can_use_premium_code__premium_only()) {
            if (isset($optss['domain']) && !isset($optss['subdir'])) {
                $this->mdomain = $siteurl = sanitize_text_field($optss['domain']);
            }
        }

        if (isset($optss['subdir'])) {
            $siteurl = sanitize_text_field($optss['domain']);
        }

        $this->rootdomain = str_ireplace(array('http://', 'https://', 'www.'), array('', '', ''), $siteurl);

        $this->basedomain = str_ireplace(array('http://', 'https://'), array('', ''), $siteurl);

        $this->domains = array(
            $this->basedomain,
        );

        //include both www & non-www
        if (isset($optss['include_www']) && $optss['include_www'] == 1) {
            $this->basedomain = $this->rootdomain;
            $this->domains = array(
                $this->rootdomain,
                'www.' . $this->rootdomain
            );
        }

        /** v5.4.8 */
        if (isset($optss['include_mail']) && $optss['include_mail'] == 1) {
            $this->domains[] = 'mail.' . $this->rootdomain;
        }
        if (isset($optss['include_webmail']) && $optss['include_webmail'] == 1) {
            $this->domains[] = 'webmail.' . $this->rootdomain;
        }

        if (wple_fs()->can_use_premium_code__premium_only()) {
            if ($wc || (isset($optss['type']) && $optss['type'] == 'wildcard')) {
                $this->wcard = true;
                $this->basedomain = $this->rootdomain;

                $this->domains = array(
                    $this->basedomain,
                    '*.' . $this->basedomain
                );
            }
        }

        if (get_option('wple_disable_spmode') == true) {
            $this->disablespmode = true;
        }

        if ($gen) {
            $this->wple_generate_verify_ssl();
        }
    }

    /**
     * group all different steps into one function & clear debug.log intially.
     *
     * @since 1.0.0
     * @return void
     */
    public function wple_generate_verify_ssl()
    {

        $cpanel = (int) get_option('wple_have_cpanel');

        //since 4.7
        if (!isset($_GET['wpleauto'])) {
            update_option('wple_http_valid', 0);

            if (!wple_fs()->is__premium_only()) {
                if (isset($_POST['wple_send_usage'])) {
                    update_option('wple_send_usage', 1);
                } else {
                    update_option('wple_send_usage', 0);
                }
            }

            $storage = 'WEB';

            if (!wple_fs()->is__premium_only()) {

                /**
                 * Set certificate storage path
                 * Re-check permission each time
                 * @since 7.1.0
                 */

                $keys_above_root = dirname(ABSPATH, 1) . '/ssl/' . sanitize_file_name(WPLE_Trait::get_root_domain());

                if (file_exists($keys_above_root) && is_writable($keys_above_root)) { //already created
                    $storage = 'ROOT';
                    update_option('wple_parent_reachable', true);
                } else {

                    if (@mkdir($keys_above_root, 0755, true)) { //directory creation success
                        $testfile = $keys_above_root . '/testfile';
                        @file_put_contents($testfile, 'test123');

                        if (file_exists($testfile) && file_get_contents($testfile) == 'test123') { //file creation possible
                            unlink($testfile);
                            update_option('wple_parent_reachable', true);
                            $storage = 'ROOT';
                        } else { //file creation not possible
                            update_option('wple_parent_reachable', false);
                        }
                    } else {
                        update_option('wple_parent_reachable', false);
                    }
                }
            }


            $PRO = (wple_fs()->can_use_premium_code__premium_only()) ?  'PRO' : '';

            $PRO .= ($this->wcard) ? ' WILDCARD SSL ' : ' SINGLE DOMAIN SSL ';

            $PRO .= ($this->wizard) ? ' WIZARD ' : '';

            if (isset($_SERVER['GD_PHP_HANDLER'])) {
                $PRO .= 'GD ';
            }

            if (false !== stripos(ABSPATH, 'home/customer')) {
                $PRO .= 'SG ';
            }

            $PRO .= $cpanel;

            $interests_survey = get_option('wple_survey_interests');

            $this->wple_log('<b>' . WPLE_PLUGIN_VER . ' ' . $PRO . ' - ' . esc_html(site_url()) . ' - ' . esc_html($storage) . ' (' . esc_html($interests_survey) . ')</b>', 'success', 'w');
            $this->wple_log("Domain covered:\n" . json_encode($this->domains) . "\n");

            if (wple_fs()->can_use_premium_code__premium_only()) {
                //No cPanel so lets start SP mode
                if ((!$cpanel || get_option('wple_force_spmode') || wple_fs()->is_plan('firewall', true)) && !$this->iscron) {
                    $this->wple_spmode_starter__premium_only();
                }
            }
        }

        //since v6.6
        if (!function_exists('curl_init')) {
            $this->wple_log("PHP Curl is required & not enabled on your server. Please enable PHP Curl before proceeding.", 'error', 'a', true);
        }

        if (!wple_fs()->can_use_premium_code__premium_only()) update_option('wple_stage', 'starting_client');

        $this->wple_create_client();

        if (!wple_fs()->can_use_premium_code__premium_only()) update_option('wple_stage', 'generating_order');

        $this->wple_generate_order();

        if (!wple_fs()->can_use_premium_code__premium_only()) update_option('wple_stage', 'starting_verification');

        //divide paths from here i.e., verify, generate & install

        if (wple_fs()->is__premium_only()) {
            if (!wple_fs()->can_use_premium_code__premium_only()) {
                $this->wple_log('Alert: You are using PRO plugin with FREE license key. Please activate your PRO license key via Activate License option under Premium plugin on PLUGINS page.', 'error', 'a', true);
            }
        }

        if (!wple_fs()->can_use_premium_code__premium_only()) {
            $starthttpverify = $startdnsverify = false;

            if (isset($_GET['wpleauto'])) {
                if ($_GET['wpleauto'] == 'http') {
                    $starthttpverify  = true;
                    $this->wple_log('Starting HTTP verification');
                } else {
                    $startdnsverify = true;
                    $this->wple_log('Starting DNS verification');
                }
            }

            $this->wple_verify_free_order($starthttpverify, $startdnsverify);
        }

        if (wple_fs()->can_use_premium_code__premium_only()) {
            $this->wple_verify_pro_order__premium_only();
        }

        $this->wple_generate_certs();

        if (wple_fs()->can_use_premium_code__premium_only()) {
            $this->wple_install_certs__premium_only();
        }

        if (!wple_fs()->is__premium_only()) {
            if (FALSE != ($dlog = get_option('wple_send_usage')) && $dlog) {
                $this->wple_send_usage_data();
            }
        }

        if (wple_fs()->is__premium_only()) {
            $this->wple_send_usage_data();
        }
    }

    /**
     * create ACMEv2 client
     *
     * @since 1.0.0
     * @return void
     */
    protected function wple_create_client()
    {
        try {

            $keydir = WPLE_Trait::wple_cert_directory();

            if (wple_fs()->can_use_premium_code__premium_only()) {
                if (is_multisite() && $this->mdomain != FALSE) {
                    $keydir = ABSPATH . "keys/{$this->mdomain}/";
                }
            }

            if (wple_fs()->can_use_premium_code__premium_only()) {
                // if ($this->iscron) { //if renewal cron
                //     $certfile = $keydir . 'certificate.crt';
                //     try {
                //         $ret = $this->wple_parseCertificate($certfile);
                //         $live_expiry = $ret['validTo_time_t'];
                //         if (strtotime('now') < strtotime('-60 days', $live_expiry) && !get_option('wple_skip_logged')) {
                //             $this->wple_log('Skipping SSL generation as new SSL certificate is already generated within last 30 days. Please restart server once / install it on your hosting panel. Otherwise, You can use RESET once and then generate new SSL.');
                //             update_option('wple_skip_logged', true); //avoid daily logging for 30 days                            
                //             exit();
                //         } else {
                //             delete_option('wple_skip_logged'); //proceeed
                //         }
                //     } catch (Exception $e) {
                //     }
                // }
            }

            $sourceIP = get_option('wple_sourceip');

            if (!wple_fs()->can_use_premium_code__premium_only()) {
                //since 7.1 restore account key from option
                $acckey_path = $keydir . '__account/private.pem';
                if (!file_exists($acckey_path)) {
                    $acckey = get_option('wple_acc_key') ? get_option('wple_acc_key') : '';

                    file_put_contents($acckey_path, preg_replace('#<br\s*/?>#i', "", $acckey));
                }
            }

            $this->client = new LEClient($this->email, LEClient::LE_PRODUCTION, LEClient::LOG_STATUS, $keydir, '__account/', $sourceIP);
        } catch (Exception $e) {
            $pro_advantage = '';
            if (wple_fs()->can_use_premium_code__premium_only()) {
                if (!$this->iscron) $this->wple_spmode_starter__premium_only();

                //update cron time in case of new /directory rate limit hit
                do_action('cert_expiry_updated');
            }

            if (!wple_fs()->can_use_premium_code__premium_only()) {
                $pro_advantage = '<strong><i>You can still generate premium SSL certificate in Annual <b>PRO</b> Plan without these requirements.</i></strong>';
            }

            update_option('wple_error', 1);
            $mode = $this->iscron ? 'a' : 'w';
            $this->wple_log("VERSION " . WPLE_PLUGIN_VER . "\n\nCREATE_CLIENT:" . $e . "\n\n$pro_advantage", 'error', $mode, true);
        }

        ///echo '<pre>'; print_r( $client->getAccount() ); echo '</pre>';
    }

    /**
     * Generate order with ACMEv2 client for given domain
     *
     * @since 1.0.0
     * @return void
     */
    protected function wple_generate_order()
    {
        try {
            $this->order = $this->client->getOrCreateOrder($this->basedomain, $this->domains);
        } catch (Exception $e) {
            update_option('wple_error', 1);
            $mode = $this->iscron ? 'a' : 'w';
            $this->wple_log("VERSION " . WPLE_PLUGIN_VER . "\n\nCREATE_ORDER:" . $e, 'error', $mode, true);
        }
    }

    /**
     * Get all pendings orders which need domain verification
     *
     * @since 1.0.0
     * @return void
     */
    protected function wple_get_pendings($dns = false)
    {
        $chtype = LEOrder::CHALLENGE_TYPE_HTTP;
        $http = 1;

        if (wple_fs()->can_use_premium_code__premium_only()) {
            if ($this->wcard) {
                $chtype = LEOrder::CHALLENGE_TYPE_DNS;
                $http = 0;
            }
        }

        if ($dns) {
            $chtype = LEOrder::CHALLENGE_TYPE_DNS;
            $http = 0;
        }

        try {
            $this->pendings = $this->order->getPendingAuthorizations($chtype);

            if (!empty($this->pendings) && $http == 1) {
                $opts = get_option('wple_opts');

                $opts['challenge_files'] = array();

                foreach ($this->pendings as $chlng) {
                    $opts['challenge_files'][] = array(
                        'file' => sanitize_text_field(trim($chlng['filename'])),
                        'value' => sanitize_text_field(trim($chlng['content']))
                    );
                }

                update_option('wple_opts', $opts);
            }
        } catch (Exception $e) {
            $this->wple_log('GET_PENDING_AUTHS:' . $e, 'error', 'w', true);
        }
    }

    /**
     * Finalize and get certificates
     *
     * @since 1.0.0
     * @return void
     */
    public function wple_generate_certs()
    {
        if ($this->order->allAuthorizationsValid()) {
            if (!wple_fs()->can_use_premium_code__premium_only()) update_option('wple_stage', 'generated_certificate');

            // Finalize the order
            if (!$this->order->isFinalized()) {
                $this->wple_log(esc_html__('Finalizing the order', 'wp-letsencrypt-ssl'), 'success', 'a');
                $this->order->finalizeOrder();
            }

            // get the certificate.
            if ($this->order->isFinalized()) {
                $this->wple_log(esc_html__('Getting SSL certificates', 'wp-letsencrypt-ssl'), 'success', 'a');
                $this->order->getCertificate(); //'ISRG Root X1'
            }

            delete_option('wple_hold_cron');

            $cert = WPLE_Trait::wple_cert_directory() . 'certificate.crt';
            if (file_exists($cert)) {
                $this->wple_save_expiry_date();
                do_action('cert_expiry_updated'); //important
            }

            //since 5.3.5
            //$this->wple_email_cert_files();
            $this->wple_send_success_mail(); // 2 in 1 email since 5.7.2

            if (!wple_fs()->can_use_premium_code__premium_only()) {

                update_option('wple_ssl_screen', 'complete');

                $sslgenerated = "<h2>" . esc_html__('SSL Certificate generated successfully', 'wp-letsencrypt-ssl') . "!</h2>";
                $this->wple_log($sslgenerated, 'success', 'a');

                /**
                 * Case: Couldn't store above web root dir
                 * Delete private key and store in option
                 * Delete account key and store in option
                 * @since 7.0.0
                 */
                if (!get_option('wple_parent_reachable')) {
                    $priv_key = WPLE_Trait::wple_cert_directory() . 'private.pem';
                    $acc_key = WPLE_Trait::wple_cert_directory() . '__account/private.pem';

                    if (file_exists($priv_key)) {
                        $priv_key_content = sanitize_textarea_field(file_get_contents($priv_key));
                        $priv_key_content = nl2br($priv_key_content);
                        update_option('wple_priv_key', $priv_key_content);
                        unlink($priv_key);

                        $acc_key_content = sanitize_textarea_field(file_get_contents($acc_key));
                        $acc_key_content = nl2br($acc_key_content);
                        update_option('wple_acc_key', $acc_key_content);
                        unlink($acc_key);


                        $this->wple_log("Stored private key as option");
                    }
                }

                if ($this->wizard || (FALSE != ($dlog = get_option('wple_send_usage')) && $dlog)) {
                    $this->wple_send_usage_data();
                }

                if ($this->wizard) {
                    echo json_encode(['success' => true, 'message' => admin_url('/admin.php?page=wp_encryption')]);
                    exit();
                }

                wp_redirect(admin_url('/admin.php?page=wp_encryption'), 302);
                exit();
            }
        } else {

            update_option('wple_error', 2);

            if (wple_fs()->can_use_premium_code__premium_only()) {
                if ($this->iscron) {
                    update_option('wple_renewal_failed_notice', true);
                    update_option('wple_hold_cron', true); //let user manually complete verification instead of daily failures
                }
            }

            // if (get_option('wple_http_valid')) { //rare case

            //   $this->wple_log('Looks like HTTP file verification is not possible on your server. Please complete DNS based verification.');
            //   wp_redirect(admin_url('/admin.php?page=wp_encryption&subdir=1&error=1'), 302);
            //   exit();
            // } else {

            if (method_exists($this->order, 'updateOrderData') && !wple_fs()->is_premium()) {
                $this->order->updateOrderData();

                if ($this->order->status == 'invalid') {
                    update_option('wple_order_refreshed', true);
                    $this->wple_log("Order failed due to failed verification or other reasons. Getting new challenges from new order. PLEASE TRY DNS VERIFICATION.\n");
                    $this->wple_create_client();
                    $this->wple_generate_order();
                    $this->wple_verify_free_order();
                }
            }

            $this->wple_log('<h2>' . esc_html__('There are some pending verifications. Please try again with DNS challenges.', 'wp-letsencrypt-ssl') . '</h2>', 'success', 'a', false);

            $this->wple_save_all_challenges(); //re-update pending challenges

            if (!empty($this->pendings)) {
                $this->wple_log(json_encode($this->pendings));
            }

            $this->wple_log('', 'success', 'a', true);
            //}
        }
    }

    /**
     * Install generated certs on cPanel via shell command
     *
     * @since 1.0.0
     * @return void
     */
    public function wple_install_certs__premium_only($renewal = false)
    {
        $this->wple_log('Starting SSL Installation');

        $cert = WPLE_Trait::wple_cert_directory() . 'certificate.crt';
        $keyfile = WPLE_Trait::wple_cert_directory() . 'private.pem';
        $csrfile = WPLE_Trait::wple_cert_directory() . 'csr.crt';
        $ca_cert = WPLE_Trait::wple_cert_directory() . 'fullchain.crt';

        if (wple_fs()->can_use_premium_code__premium_only()) {
            if (is_multisite() && $this->mdomain != FALSE) {
                $cert = ABSPATH . 'keys/' . $this->mdomain . '/certificate.crt';
                $keyfile = ABSPATH . 'keys/' . $this->mdomain . '/private.pem';
                $csrfile = ABSPATH . 'keys/' . $this->mdomain . '/csr.crt';

                $ca_cert = ABSPATH . 'keys/' . $this->mdomain . '/fullchain.crt';
            }
        }

        if (!file_exists($cert)) {
            update_option('wple_error', 4);
            $this->wple_log(esc_html__("Certificate file is missing!. Please use RESET and generate new SSL certificate.", 'wp-letsencrypt-ssl'), 'error', 'a', true);
        }

        //since 7.2.0
        $pleskapi = get_option('wple_pleskapi');
        if ($pleskapi) {
            $plesk = new WPLE_PleskAPI();
            $plesk->wple_plesk_install_ssl($csrfile, $keyfile, $cert, $pleskapi, $ca_cert); //terminates here if success or error
        }

        //since 7.8.5.7
        $directadmin = get_option('wple_directadmin');
        if ($directadmin) {
            $da = new WPLE_DirectAdmin($directadmin);
            $da->wple_directadmin_install_ssl($cert, $keyfile, $ca_cert); //terminates here if success or error
        }

        //since 5.0.4
        $cpapi = get_option('wple_cpapi');
        $haveCpanel = get_option('wple_have_cpanel');

        // if (!$haveCpanel) {
        //   //delete_option('wple_renewal_inprogress');
        // }

        if (!isset($cpapi['upass']) && !function_exists('shell_exec') && !function_exists('exec') && !function_exists('system') && !function_exists('passthru')) {
            update_option('wple_error', 4);

            ///update_option('wple_hold_cron', true);

            $this->wple_log("None of <b>shell_exec</b>, <b>system</b>, <b>exec</b>, <b>passthru</b> functions are enabled on this server. Please enter your cPanel username and password on <b>cPanel Login</b> page & re-run SSL install form or please ask your hosting provider to enable <b>shell_exec</b> php function.", 'error', 'a', false);

            if ($haveCpanel) {
                $infomsg = 'shell_exec function not enabled so cPanel credentials are required. Please use RESET option once. Then go to CPANEL LOGIN page via left panel, enter your correct cPanel username & password, save the form and re-run this SSL install form once again.';
                update_option('wple_last_error', $infomsg);

                wp_redirect(admin_url('/admin.php?page=wp_encryption&lasterror=1'), 302);
                exit();
            }

            $this->wple_spmode_starter__premium_only();
            // if ($renewal) {
            //   $this->wple_restart_reminder__premium_only();
            // } else {
            $this->wple_log('<div class="wple-promo">' . esc_html__('Kindly open a support ticket(Register new account) at support.wpencryption.com with your hosting name(godaddy, digital ocean, etc.,) and site url. We will help you with this one time server setup.', 'wp-letsencrypt-ssl') . '</div>', 'success', 'a', true);
            //}
        }

        if (!isset($cpapi['upass'])) {
            $this->wple_check_uapi_exists__premium_only($renewal); //non-cpanel server restart reminder & exit
        }

        $cert = file_get_contents($cert);
        $key = file_get_contents($keyfile);
        $ca_cert = file_get_contents($ca_cert);

        if (!$ca_cert) {
            $this->wple_log(esc_html__('Could not find the cabundle file. Please re-install latest version of plugin.', 'wp-letsencrypt-ssl'), 'error', 'a', true);
        } else if (!$cert) {
            $this->wple_log(esc_html__('Could not find the generated certificate. Please try re-generating the certificates.', 'wp-letsencrypt-ssl'), 'error', 'a', true);
        } else if (!$key) {
            $this->wple_log(esc_html__('Could not find the generated key file. Please try re-generating the certificates.', 'wp-letsencrypt-ssl'), 'error', 'a', true);
        }

        if (function_exists('escapeshellarg')) {
            $enc_cert = escapeshellarg(urlencode(str_replace("\r\n", "\n", $cert)));
            $enc_key = escapeshellarg(urlencode(str_replace("\r\n", "\n", $key)));
            $enc_cacert = escapeshellarg(urlencode(str_replace("\r\n", "\n", $ca_cert)));
        } else {
            $enc_cert = urlencode(str_replace("\r\n", "\n", $cert));
            $enc_key = urlencode(str_replace("\r\n", "\n", $key));
            $enc_cacert = urlencode(str_replace("\r\n", "\n", $ca_cert));
        }


        $this->wple_log("\n<b>" . esc_html__('Installing your saved certificates', 'wp-letsencrypt-ssl') . ":</b>\n", 'success', 'a');

        $bdomain = str_ireplace('www.', '', $this->basedomain);

        $bdomain = str_ireplace('*.', '', $bdomain);

        $finalshell = $var = '';

        //since 5.0.4
        if (isset($cpapi['upass']) && isset($cpapi['uname'])) {
            $res = WPLE_UAPI::wple_install_ssl__premium_only($cpapi, $this->basedomain, $cert, $key, $ca_cert);

            if ($res) {
                delete_option('wple_error'); //complete
                //if ($renewal) {
                update_option('wple_ssl_screen', 'success');
                //}
                $finalshell = "<h2>" . esc_html__('SSL Installation Success', 'wp-letsencrypt-ssl') . "!. #LOGIN</h2>";
                $this->wple_log($finalshell, 'success', 'a');

                //delete_option('wple_renewal_inprogress');

                $this->wple_send_usage_data();
                wp_redirect(admin_url('/admin.php?page=wp_encryption&success=1'), 302);
                exit();
            } else {
                // if ($res->errors) {
                //   foreach ($res->errors as $key => $errormsg) {
                //     $this->wple_log(esc_html($errormsg), 'error', 'a');
                //   }
                // }

                $this->wple_log('SSL Installation via cPanel login failed. Please check if your provided cPanel username & password is correct on cPanel login page OR try emptying out the login info on CPANEL LOGIN page.', 'error', 'a', true);
            }
        }

        if (function_exists('shell_exec')) {

            $shell = shell_exec("uapi SSL install_ssl domain=$bdomain cert=$enc_cert key=$enc_key cabundle=$enc_cacert");

            $shellmsg = '';
            if ($shell == '') {
                $shellmsg = 'Please try cPanel login method or ask your hosting support to enable cPanel UAPI install_ssl feature';
            }

            $this->wple_log($bdomain . ' - ' . $shellmsg);

            $shell = str_ireplace(array('<br>', '<br />', '<b>', '</b>', '\n'), array('', '', '', '', ''), $shell);

            $fbr = stripos(htmlentities($shell), 'domain:');

            $finalshell = substr(htmlentities($shell), $fbr);

            $line_explode = explode("\n", $finalshell);

            $res_arr = array();
            foreach ($line_explode as $item) {
                $res_param = explode(":", $item);
                $res_arr[trim($res_param[0])] = isset($res_param[1]) ? $res_param[1] : '';
            }

            if ($res_arr['status'] == 1) {
                delete_option('wple_error'); //complete
                //if ($renewal) {
                update_option('wple_ssl_screen', 'success');
                //}
                $finalshell = "<b>" . $res_arr['statusmsg'] . "</b>\n<h2>" . esc_html__('SSL Installation Success', 'wp-letsencrypt-ssl') . "!. #se</h2>";
                $this->wple_log($finalshell, 'success', 'a');

                //delete_option('wple_renewal_inprogress');

                $this->wple_send_usage_data();
                if ($renewal) {
                    wp_redirect(admin_url('/admin.php?page=wp_encryption&success=1'), 302);
                } else {
                    wp_redirect(admin_url('/admin.php?page=wp_encryption&complete=1'), 302);
                }
                exit();
            }
        } else if (function_exists('system')) {

            ob_start();
            system("uapi SSL install_ssl domain=$bdomain cert=$enc_cert key=$enc_key cabundle=$enc_cacert", $var);
            $shell = ob_get_contents();
            ob_end_clean();

            $shell = str_ireplace(array('<br>', '<br />', '<b>', '</b>', '\n'), array('', '', '', '', ''), $shell);

            $fbr = stripos(htmlentities($shell), 'domain:');

            $finalshell = substr(htmlentities($shell), $fbr);

            $line_explode = explode("\n", $finalshell);

            $res_arr = array();
            foreach ($line_explode as $item) {
                $res_param = explode(":", $item);
                $res_arr[trim($res_param[0])] = isset($res_param[1]) ? $res_param[1] : '';
            }

            if ($res_arr['status'] == 1) {
                delete_option('wple_error'); //complete
                //if ($renewal) {
                update_option('wple_ssl_screen', 'success');
                //}
                $finalshell = "<b>" . $res_arr['statusmsg'] . "</b>\n<h2>" . esc_html__('SSL Installation Success', 'wp-letsencrypt-ssl') . "!. #sys</h2>";

                $this->wple_log($finalshell, 'success', 'a');

                //delete_option('wple_renewal_inprogress');

                $this->wple_send_usage_data();
                if ($renewal) {
                    wp_redirect(admin_url('/admin.php?page=wp_encryption&success=1'), 302);
                } else {
                    wp_redirect(admin_url('/admin.php?page=wp_encryption&complete=1'), 302);
                }
                exit();
            }
        } else if (function_exists('passthru')) {

            ob_start();
            passthru("uapi SSL install_ssl domain=$bdomain cert=$enc_cert key=$enc_key cabundle=$enc_cacert", $var);
            $shell = ob_get_contents();
            ob_end_clean();

            $shell = str_ireplace(array('<br>', '<br />', '<b>', '</b>', '\n'), array('', '', '', '', ''), $shell);

            $fbr = stripos(htmlentities($shell), 'domain:');

            $finalshell = substr(htmlentities($shell), $fbr);

            $line_explode = explode("\n", $finalshell);

            $res_arr = array();
            foreach ($line_explode as $item) {
                $res_param = explode(":", $item);
                $res_arr[trim($res_param[0])] = isset($res_param[1]) ? $res_param[1] : '';
            }

            if ($res_arr['status'] == 1) {
                delete_option('wple_error'); //complete
                //if ($renewal) {
                update_option('wple_ssl_screen', 'success');
                //}
                $finalshell = "<b>" . $res_arr['statusmsg'] . "</b>\n<h2>" . esc_html__('SSL Installation Success', 'wp-letsencrypt-ssl') . "!. #ps</h2>";

                $this->wple_log($finalshell, 'success', 'a');

                //delete_option('wple_renewal_inprogress');

                $this->wple_send_usage_data();
                if ($renewal) {
                    wp_redirect(admin_url('/admin.php?page=wp_encryption&success=1'), 302);
                } else {
                    wp_redirect(admin_url('/admin.php?page=wp_encryption&complete=1'), 302);
                }
                exit();
            }
        } else if (function_exists('exec')) {

            exec("uapi SSL install_ssl domain=$bdomain cert=$enc_cert key=$enc_key cabundle=$enc_cacert", $output, $var);
            $shell = implode(',', $output);

            $shell = str_ireplace(array('<br>', '<br />', '<b>', '</b>', '\n'), array('', '', '', '', ''), $shell);

            $fbr = stripos(htmlentities($shell), 'domain:');

            $finalshell = substr(htmlentities($shell), $fbr);

            $line_explode = explode(",", $finalshell);

            $res_arr = array();
            foreach ($line_explode as $item) {
                $res_param = explode(":", $item);
                $res_arr[trim($res_param[0])] = isset($res_param[1]) ? $res_param[1] : '';
            }

            if ($res_arr['status'] == 1) {
                delete_option('wple_error'); //complete
                //if ($renewal) {
                update_option('wple_ssl_screen', 'success');
                //}
                $finalshell = "<b>" . $res_arr['statusmsg'] . "</b>\n<h2>" . esc_html__('SSL Installation Success', 'wp-letsencrypt-ssl') . "!. #ex</h2>";

                $this->wple_log($finalshell, 'success', 'a');

                //delete_option('wple_renewal_inprogress');

                $this->wple_send_usage_data();
                if ($renewal) {
                    wp_redirect(admin_url('/admin.php?page=wp_encryption&success=1'), 302);
                } else {
                    wp_redirect(admin_url('/admin.php?page=wp_encryption&complete=1'), 302);
                }
                exit();
            }
        }

        //status was not 1
        $this->wple_log($finalshell, 'error', 'a', true);
    }

    /**
     * Save expiry date of cert dynamically by parsing the cert
     *
     * @since 1.0.0
     * @return void
     */
    public function wple_save_expiry_date()
    {
        $certfile = WPLE_Trait::wple_cert_directory() . 'certificate.crt';

        if (wple_fs()->can_use_premium_code__premium_only()) {
            if (is_multisite() && $this->mdomain != FALSE) {
                $certfile = ABSPATH . 'keys/' . $this->mdomain . '/certificate.crt';
            }
        }

        //TODO: expiry saved separately on each mapped site?
        if (file_exists($certfile)) {
            $opts = get_option('wple_opts');
            $opts['expiry'] = '';

            try {
                $this->wple_getRemainingDays($certfile, $opts);
            } catch (Exception $e) {
                update_option('wple_opts', $opts);
            }
        }
    }

    /**
     * Utility functions
     * 
     * @since 1.0.0 
     */
    public function wple_parseCertificate($cert_pem)
    {
        // if (false === ($ret = openssl_x509_read(file_get_contents($cert_pem)))) {
        //   throw new Exception('Could not load certificate: ' . $cert_pem . ' (' . $this->get_openssl_error() . ')');
        // }
        if (!is_array($ret = openssl_x509_parse(file_get_contents($cert_pem), true))) {
            throw new Exception('Could not parse certificate');
        }

        return $ret;
    }

    public function wple_getRemainingDays($cert_pem, $opts)
    {

        $ret = $this->wple_parseCertificate($cert_pem);
        $expiry = date('d-m-Y', $ret['validTo_time_t']);
        $opts['expiry'] = $expiry;

        update_option('wple_opts', $opts);
        update_option('wple_show_review', 1);
    }

    public function wple_log($msg = '', $type = 'success', $mode = 'a', $redirect = false)
    {

        $handle = fopen(WPLE_DEBUGGER . 'debug.log', $mode);

        if ($type == 'error') {
            $msg = '<span class="error"><b>' . esc_html__('ERROR', 'wp-letsencrypt-ssl') . ':</b> ' . wp_kses_post($msg) . '</span>';
        }

        fwrite($handle, wp_kses_post($msg) . "\n");
        fclose($handle);

        if ($redirect) {
            if (!wple_fs()->is__premium_only()) {
                if (FALSE != ($dlog = get_option('wple_send_usage')) && $dlog) {
                    $this->wple_send_usage_data();
                }
            }

            if (wple_fs()->is__premium_only()) {
                $this->wple_send_usage_data();
            }

            if ($this->wizard) {
                $debug_log = file_get_contents(WPLE_DEBUGGER . 'debug.log');
                echo json_encode(['success' => false, 'message' => $debug_log]);
                exit();
            }

            wp_redirect(admin_url('/admin.php?page=wp_encryption&error=1'), 302);
            die();
        }
    }

    /**
     * Collect usage data to improve plugin
     *
     * @since 2.1.0
     * @return void
     */
    public function wple_send_usage_data()
    {

        WPLE_Trait::wple_logger('Syncing debug log');

        $readlog = file_get_contents(WPLE_DEBUGGER . 'debug.log');
        $handle = curl_init();

        $srvr = array(
            'challenge_folder_exists' => '',
            'certificate_exists' => file_exists(WPLE_Trait::wple_cert_directory() . 'certificate.crt'),
            'server_software' => sanitize_text_field($_SERVER['SERVER_SOFTWARE']),
            'http_host' => site_url(),
            'pro' => (wple_fs()->is__premium_only()) ? 'PRO' : 'FREE',
        );

        $curlopts = array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POST => 1,
            CURLOPT_URL => 'https://support.wpencryption.com/?catchwple=1',
            CURLOPT_HEADER => false,
            CURLOPT_POSTFIELDS => array('response' => $readlog, 'server' => json_encode($srvr)),
            CURLOPT_TIMEOUT => 30
        );

        curl_setopt_array($handle, $curlopts);

        try {
            curl_exec($handle);
        } catch (Exception $e) {
            curl_close($handle);
            return;
        }

        curl_close($handle);
    }

    /**
     * Check if UAPI exists
     *
     * @since 2.4.0
     * @return void
     */
    private function wple_check_uapi_exists__premium_only($renewal = false)
    {
        update_option('wple_error', 4);


        // $nc = esc_html__("Unfortunately your server don't seem to have cPanel installed. You will need to manually specify the certs path in your server config file.\n", 'wp-letsencrypt-ssl');

        // $ticket = '<div class="wple-promo">' . esc_html__('Kindly open a support ticket(Register new account) at support.wpencryption.com with your site url & license key. We will help you with this one time server setup.', 'wp-letsencrypt-ssl') . '</div>';

        //check port & return
        //since 4.6.1 Pro
        if (get_option('wple_have_cpanel')) {
            return true;
        }

        $var = '';

        if (function_exists('shell_exec')) {
            $whichuapi = shell_exec('which uapi');
            if (empty($whichuapi)) {
                update_option('wple_ssl_screen', 'complete');

                if (wple_fs()->can_use_premium_code__premium_only()) {
                    if ($renewal) {

                        //v6.5
                        if (file_exists(ABSPATH . 'keys/certificate.crt') && file_exists(ABSPATH . 'keys/private.pem')) {
                            shell_exec("sudo /usr/sbin/service apache2 reload");
                        }

                        $this->wple_restart_reminder__premium_only();
                    } else {
                        $this->wple_spmode_starter__premium_only();
                        $this->wple_nocpanel_notice();
                        //$this->wple_log($nc, 'error', 'a', false);
                        //$this->wple_log($ticket, 'success', 'a', true);
                    }
                }
            }
        } else if (function_exists('system')) {
            ob_start();
            system("which uapi", $var);
            $shll = ob_get_contents();
            ob_end_clean();

            if (empty($shll)) {
                update_option('wple_ssl_screen', 'complete');

                if (wple_fs()->can_use_premium_code__premium_only()) {
                    if ($renewal) {
                        $this->wple_restart_reminder__premium_only();
                    } else {
                        $this->wple_spmode_starter__premium_only();
                        $this->wple_nocpanel_notice();
                        // $this->wple_log($nc, 'error', 'a', false);
                        // $this->wple_log($ticket, 'success', 'a', true);
                    }
                }
            }
        } else if (function_exists('passthru')) {
            ob_start();
            passthru("which uapi", $var);
            $shll = ob_get_contents();
            ob_end_clean();

            if (empty($shll)) {
                update_option('wple_ssl_screen', 'complete');

                if (wple_fs()->can_use_premium_code__premium_only()) {
                    if ($renewal) {
                        $this->wple_restart_reminder__premium_only();
                    } else {
                        $this->wple_spmode_starter__premium_only();
                        $this->wple_nocpanel_notice();
                        // $this->wple_log($nc, 'error', 'a', false);
                        // $this->wple_log($ticket, 'success', 'a', true);
                    }
                }
            }
        } else if (function_exists('exec')) {
            exec("which uapi", $output, $var);
            if (empty($output)) {
                update_option('wple_ssl_screen', 'complete');

                if (wple_fs()->can_use_premium_code__premium_only()) {
                    if ($renewal) {
                        $this->wple_restart_reminder__premium_only();
                    } else {
                        $this->wple_spmode_starter__premium_only();
                        $this->wple_nocpanel_notice();
                        // $this->wple_log($nc, 'error', 'a', false);
                        // $this->wple_log($ticket, 'success', 'a', true);
                    }
                }
            }
        }
    }

    /**
     * cPanel DNS automation - Premium
     *
     * @since 2.4.0
     * @return void
     */
    private function wple_process_dns_challenge__premium_only()
    {
        $opts = get_option('wple_opts');

        $this->wple_log(WPLE_Trait::wple_kses(__("<b>WP Encryption PRO</b> trying to automate DNS verification process via cPanel\n", 'wp-letsencrypt-ssl')), 'success', 'a', false);

        $siteurl = site_url();
        if (is_multisite() && $this->mdomain != FALSE) {
            $siteurl = $this->mdomain;
        }

        $nonwww = str_ireplace(array('http://', 'https://', 'www.'), array('', '', ''), $siteurl);
        $syt = escapeshellarg($nonwww);

        $records = $opts['dns_challenges'];

        $this->wple_log(esc_html(json_encode($records)));

        $sflag = 0; //success flag
        $dnserrors = array();
        $subdomain = 0;

        $res_arr = array(); //results
        for ($r = 0; $r < count($records); $r++) {
            $dnsitem = $records[$r];
            $record = explode('||', $dnsitem);

            $txtval = escapeshellarg($record[1]);
            $acmename = WPLE_Trait::wple_get_acmename($nonwww, $record[0]);
            $acmename = escapeshellarg($acmename);

            $shl = $origdns = shell_exec("cpapi2 ZoneEdit add_zone_record domain=$syt name=$acmename type=TXT txtdata=$txtval ttl=60");
            $shl = trim($shl);
            $shl = substr($shl, stripos($shl, 'cpanelresult') + 13);
            $line_explode = explode("\n", $shl);

            foreach ($line_explode as $item) {
                $res_param = explode(":", $item);
                $res_arr[$r][trim($res_param[0])] = isset($res_param[1]) ? $res_param[1] : '';
            }

            if ($res_arr[$r]['status'] == 1) { //success
                $sflag = 1;
            } else {
                $sflag = 0;

                if (stripos($res_arr[$r]['statusmsg'], 'SOA') !== false) { //its a sub-domain         
                    $subdomain = 1;
                    break;
                }

                if (isset($res_arr[$r]['statusmsg'])) {
                    $dnserrors[] = $res_arr[$r]['statusmsg'];
                } else {
                    $dnserrors[] = $res_arr[$r]['error'];
                }
            }
        }

        if ($subdomain == 1) { //re-run with primary domain
            $firstdot = stripos($nonwww, '.');
            $syt = substr($nonwww, $firstdot + 1); //root site
            $dnserrors = array();

            $res_arr = array();
            for ($rr = 0; $rr < count($records); $rr++) {
                $dnsitem = $records[$rr];
                $record = explode('||', $dnsitem);

                $txtval = escapeshellarg($record[1]);

                $acmename = WPLE_Trait::wple_get_acmename($nonwww, $record[0]);
                $acmename = escapeshellarg($acmename);

                $syt = escapeshellarg($syt);

                $shl = $origdns = shell_exec(escapeshellcmd("cpapi2 ZoneEdit add_zone_record domain=$syt name=$acmename type=TXT txtdata=$txtval ttl=60"));
                $shl = trim($shl);
                $shl = substr($shl, stripos($shl, 'cpanelresult') + 13);
                $line_explode = explode("\n", $shl);

                foreach ($line_explode as $item) {
                    $res_param = explode(":", $item);
                    $res_arr[$rr][trim($res_param[0])] = isset($res_param[1]) ? $res_param[1] : '';
                }

                if ($res_arr[$r]['status'] == 1) { //success
                    $sflag = 1;
                } else {
                    $sflag = 0;
                    if (isset($res_arr[$r]['statusmsg'])) {
                        $dnserrors[] = $res_arr[$r]['statusmsg'];
                    } else {
                        $dnserrors[] = $res_arr[$r]['error'];
                    }
                }
            }
        }

        if ($sflag == 1) {
            return true;
        }

        if (count($dnserrors)) {
            foreach ($dnserrors as $err) {
                if (!empty($err)) {
                    $this->wple_log("<b>CPANEL ERROR - </b>" . $err, 'error', 'a', false);
                }
            }
        }

        update_option('wple_error', 2);

        $this->wple_log("DNS response - " . function_exists('shell_exec') . " -- " . $origdns);

        $this->wple_log(esc_html__("Could not automate the DNS verification process. Please check if your domain DNS is managed by your cPanel or Godaddy.\n\n You can manually add DNS records (refer FAQ for video tutorial) and complete SSL installation.\n", 'wp-letsencrypt-ssl') . "\n", 'error', 'a', false);

        return false;
    }

    /**
     * UAPI based DNS automation
     * 
     * @since 7.7.5
     * @return boolean
     */
    private function wple_process_UAPI_dns_challenge__premium_only()
    {
        $opts = get_option('wple_opts');

        $this->wple_log(WPLE_Trait::wple_kses(__("<b>WP Encryption PRO</b> trying to automate DNS verification process via cPanel UAPI\n", 'wp-letsencrypt-ssl')), 'success', 'a', false);

        $siteurl = site_url();
        if (is_multisite() && $this->mdomain != FALSE) {
            $siteurl = $this->mdomain;
        }

        $nonwww = str_ireplace(array('http://', 'https://', 'www.'), array('', '', ''), $siteurl);
        $syt = escapeshellarg($nonwww);

        $records = $opts['dns_challenges'];

        $this->wple_log(esc_html(json_encode($records)));

        for ($r = 0; $r < count($records); $r++) {
            $dnsitem = $records[$r];
            $record = explode('||', $dnsitem);

            $txtval = escapeshellarg($record[1]);
            $acmename = WPLE_Trait::wple_get_acmename($nonwww, $record[0]);
            $acmename = escapeshellarg($acmename);

            $dnsdata = shell_exec("uapi --output=jsonpretty DNS  parse_zone zone='$syt'");
            $dnsdataArr = json_decode($dnsdata);
            $dns_data = $dnsdataArr->result->data;

            $soa = false;
            if (is_array($dns_data)) {
                foreach ($dns_data as $key => $val) {
                    if (isset($val->record_type) && $val->record_type == 'SOA') {
                        $soa = $val->data_b64[2];
                    }
                }
            }

            if ($soa) {
                $soaSerial = escapeshellarg(base64_decode($soa));
                $txtResult = shell_exec('uapi --output=jsonpretty DNS mass_edit_zone zone=\'' . $syt . '\' serial=' . $soaSerial . ' add=\'{"dname":"' . $acmename . '","ttl":14400,"record_type":"TXT","data":["' . $txtval . '"]}\'');

                $txtResultArr = json_decode($txtResult);
                if (isset($txtResultArr->status) && $txtResultArr->status == 1) {
                    continue; //successful
                }
            } else { //failed to get SOA serial
                update_option('wple_error', 2);

                $this->wple_log(esc_html__("Failed to get SOA serial & Could not automate the DNS verification process. Please check if your domain DNS is managed by your cPanel or Godaddy.\n\n You can manually add DNS records (refer FAQ for video tutorial) and complete SSL installation.\n", 'wp-letsencrypt-ssl') . "\n", 'error', 'a', false);

                return false;
            }
        }

        return true;
    }

    /**
     * Try to add DNS record //cPanel only
     *
     * @since 2.4.0
     * @return void
     */
    public function wple_attempt_dns_verification__premium_only($uapi = false)
    {
        $success = false;

        if ($uapi) {
            $success = $this->wple_process_UAPI_dns_challenge__premium_only();
        } else {

            $success = $this->wple_process_dns_challenge__premium_only(); //cpanel error shown within this
        }

        if ($success) { //DNS added successfully

            $localDNSVerify = WPLE_Trait::wple_verify_dns_records();

            if ($this->iscron || !$localDNSVerify) {

                update_option('wple_dns_new', 1);

                wp_schedule_single_event(time() + 1800, 'wple_ssl_renewal', array('propagating', false)); //lets wait 30mins & continue later

                $this->wple_log("<h2>" . esc_html__("DNS records have been added successfully. Verification will automatically continue in 30Mins. Please check back later", 'wp-letsencrypt-ssl') . "</h2>\n", 'success', 'a', true);
                exit();
            }

            $this->wple_log("<h2>" . esc_html__('DNS records have been added successfully. Starting DNS verification now.', 'wp-letsencrypt-ssl') . "</h2>\n", 'success', 'a');
        } else {
            $nocpanelwc = esc_html__("Unfortunately, CPAPI2 is not enabled so WP Encryption PRO cannot automate DNS verification process. You will have to manually add below DNS records for domain verification to succeed.\n", 'wp-letsencrypt-ssl');

            $nocpanel = WPLE_Trait::wple_kses(__("Unfortunately, CPAPI2 is not enabled so WP Encryption PRO cannot automate DNS verification process. You will have to manually add below DNS records or contact your hosting support to allow access to <b>.well-known</b> folder for domain verification to succeed.\n", 'wp-letsencrypt-ssl'));

            if ($this->wcard) {
                $this->wple_log($nocpanelwc, 'error', 'a');
            } else {
                $this->wple_log($nocpanel, 'error', 'a');
            }
            //offer manual challenge
            update_option('wple_hold_cron', true);
            $this->wple_log('Renewal cron is put on hold as manual verification is required.');

            wp_redirect(admin_url('/admin.php?page=wp_encryption&subdir=1'), 302);
            exit();
        }
    }

    /**
     * Deploy challenge files
     *
     * @since 3.2.0
     * @param array $challenge
     * @return void
     */
    private function wple_deploy_challenge_files__premium_only($acmefile, $challenge)
    {
        $this->wple_save_all_challenges();
        $this->wple_deploy_primary_from_subdir__premium_only();

        //since 7.8.6.0
        if (file_exists(ABSPATH . 'wp-config.php')) {
            $fpath = ABSPATH . '.well-known/acme-challenge/';
        } else {
            $fpath = dirname(ABSPATH) . '/.well-known/acme-challenge/';
        }

        if (!file_exists($fpath)) {
            mkdir($fpath, 0775, true);
        }

        $this->wple_log(esc_html__('Creating HTTP challenge file', 'wp-letsencrypt-ssl') . ' ' . $acmefile, 'success', 'a');

        file_put_contents($fpath . $challenge['filename'], trim($challenge['content']));
    }

    /**
     * Retrieve file content
     *
     * @since 3.2.0
     * @param string $acmefile
     * @return void
     */
    private function wple_get_file_response($acmefile)
    {

        $args = array(
            'sslverify'   => false,
        );

        $remoteget = wp_remote_get($acmefile, $args);
        if (is_wp_error($remoteget)) {
            $rsponse = 'error';
        } else {
            $rsponse = trim(wp_remote_retrieve_body($remoteget));
        }

        return $rsponse;
    }

    /**
     * Send server restart reminder for non-cPanel users
     *
     * @since 3.3.0
     * @return void
     */
    private function wple_restart_reminder__premium_only()
    {
        WPLE_Trait::wple_logger('Email - SSL renewed - Restart server');

        set_transient('server_restart_pending', true, 5 * DAY_IN_SECONDS); //Hold renewal for 5 days

        $msg = 'Looks like We had helped you with the manual setup of SSL certificate since you dont have cPanel. SSL certificates have been successfully renewed! If you dont see the new certificate active on your frontend site, Please restart your server once to reload new SSL certificates or ask your hosting support to restart your server. If you have some basic knowledge of SSH - You can connect via SSH console and easily restart your server using one of below commands:';

        $msg .= '
      <br />
      Apache2 Server: <b>sudo service apache2 restart</b><br>
      Apache Server: <b>sudo service httpd restart</b><br>
      Nginx Server: <b>sudo service nginx restart</b><br>
      AWS Lightsail/Bitnami Server: <b>sudo /opt/bitnami/ctlscript.sh restart apache</b><br><br>';

        $msg .= 'Tip: You can permit WordPress to restart apache gracefully using our tutorial(https://wpencryption.com/permit-wordpress-to-reload-apache/) to overcome the requirement of restarting server every 3 months once after renewal<br><br>';

        $site = str_ireplace(array('http://', 'https://'), array('', ''), site_url());

        $opts = get_option('wple_opts');

        $to = sanitize_email($opts['email']);

        if (isset($opts['domain'])) {
            $site = esc_html($opts['domain']);
        }

        $subject = sprintf(
            __('SSL certificates for %s successfully renewed! Please restart your server once', 'wp-letsencrypt-ssl'),
            esc_html($site)
        );
        $headers = array('Content-Type: text/html; charset=UTF-8');

        if (function_exists('wp_mail')) wp_mail($to, $subject, $msg, $headers);

        update_option('wple_ssl_screen', 'success');

        $this->wple_log($msg, 'success', 'a', true);
    }

    /**
     * Save HTTP + DNS challenges for later use
     *
     * @since 4.6.0
     * @return void
     */
    private function wple_save_all_challenges($dnsonly = false)
    {
        $opts = (FALSE === get_option('wple_opts')) ? array() : get_option('wple_opts');

        //DNS
        $chtype = LEOrder::CHALLENGE_TYPE_DNS;

        try {
            $dns_challenges = $this->order->getPendingAuthorizations($chtype);

            if (!empty($dns_challenges)) {

                $opts['dns_challenges'] = array();

                foreach ($dns_challenges as $challenge) {
                    if ($challenge['type'] == 'dns-01' && stripos($challenge['identifier'], $this->rootdomain) !== false) {
                        $identifier = $challenge['identifier'];
                        $opts['dns_challenges'][] = sanitize_text_field($identifier) . '||' . sanitize_text_field($challenge['DNSDigest']);
                    }
                }
            }
        } catch (Exception $e) {
            $this->wple_log('Unable to store DNS challenges:' . $e, 'error', 'w', true);
        }

        if ($opts['type'] != 'wildcard') {
            //HTTP
            $chtype = LEOrder::CHALLENGE_TYPE_HTTP;

            try {
                $httppendings = $this->order->getPendingAuthorizations($chtype);

                if (!empty($httppendings)) {

                    $opts['challenge_files'] = array();

                    foreach ($httppendings as $chlng) {
                        $opts['challenge_files'][] = array(
                            'file' => sanitize_text_field(trim($chlng['filename'])),
                            'value' => sanitize_text_field(trim($chlng['content']))
                        );
                    }
                }
            } catch (Exception $e) {
                $this->wple_log('Unable to store HTTP challenges:' . $e, 'error', 'w', true);
            }
        }

        update_option('wple_opts', $opts);
    }

    /**
     * Detect sub-dir site & act accordingly
     * Manual verification for subdir site
     *
     * @since 4.7.0
     * @return void
     */
    private function wple_deploy_primary_from_subdir__premium_only()
    {
        $opts = get_option('wple_opts');
        if (isset($opts['subdir']) && !isset($_GET['wpleauto'])) {

            $this->wple_log('Deploying to primary from subdir site', 'success', 'a');

            if (isset($opts['challenge_files'])) {
                unset($opts['challenge_files']);
            }
            if (isset($opts['dns_challenges'])) {
                unset($opts['dns_challenges']);
            }

            $this->wple_save_all_challenges();

            if (wple_fs()->can_use_premium_code__premium_only()) {
                $this->wple_subdir_http_deployment__premium_only(); //goes to wpleauto=http or returns to below flow
            } else {

                wp_redirect(admin_url('/admin.php?page=wp_encryption&subdir=1'), 302);
                exit();
            }
        }
    }

    /**
     * Automated HTTP verification for sub-dir site
     * Deploy to primary from subdir site
     *
     * @since 4.7.0
     * @return void
     */
    private function wple_subdir_http_deployment__premium_only()
    {
        $opts = get_option('wple_opts');
        if (isset($opts['challenge_files']) && !empty($opts['challenge_files'])) {
            $cwd = getcwd();
            $cwd = str_ireplace("/wp-admin", "", $cwd);
            $parent_path = substr($cwd, 0, strrpos($cwd, '/', 1));

            $fpath = $parent_path . '/.well-known/acme-challenge/';
            if (!file_exists($fpath)) {
                mkdir($fpath, 0775, true);
            }

            $verifications = $opts['challenge_files'];

            $putt = true;
            foreach ($verifications as $index => $ver) {

                $httpchfile = $fpath . $ver['file'];
                $httpchurl = 'http://' . $opts['domain'] . '/.well-known/acme-challenge/' . $ver['file'];
                $this->wple_log(esc_html__('Creating HTTP challenge file via subdir', 'wp-letsencrypt-ssl') . ' ' . $httpchurl, 'success', 'a');

                if (file_exists($httpchfile)) {
                    unlink($httpchfile); //remove existing
                }

                $putt = file_put_contents($httpchfile, trim($ver['value']));

                $rs = $this->wple_get_file_response($httpchurl);
                if ($rs !== trim($ver['value'])) {
                    $this->wple_log("Access to .well-known/acme-challenge folder strictly blocked on your hosting server. Could not complete HTTP based domain verification.\n", 'success', 'a');

                    update_option('wple_error', 2);

                    // $hdrs = wp_remote_head($httpchurl);

                    // if (is_wp_error($hdrs)) {
                    //   $statuscode = 0;
                    // } else {
                    //   $statuscode = $hdrs['response']['code'];
                    // }

                    // if ($statuscode != 200) { // not accessible
                    //   ////$this->try_n_prompt_dns();
                    //   //TODO: DNS automation for subdir site

                    //   $this->wple_spmode_starter__premium_only();

                    //   $this->wple_log("DNS automation under progress for sub-directory sites. Please await for next release. \n", 'error', 'a', true);
                    // }
                }
            }

            if ($putt) {
                if ($this->iscron) {
                    return true;
                } else {
                    wp_redirect(admin_url('/admin.php?page=wp_encryption&wpleauto=http'), 302);
                    exit();
                }
            } else {
                wp_die('Failed creating HTTP verification file at ' . $httpchfile . '. Please contact our premium support.');
            }
        }
    }

    protected function wple_goto_manual_challenges()
    {
        $this->wple_save_all_challenges();

        if (wple_fs()->can_use_premium_code__premium_only()) {
            $this->wple_spmode_starter__premium_only();
        }

        wp_redirect(admin_url('/admin.php?page=wp_encryption&subdir=1'), 302);
        exit();
    }

    /**
     * Only for single domain ssl
     * 
     * @return void
     */
    private function wple_spmode_starter__premium_only()
    {
        //spmode is cert panel since 5.10.0
        if (!$this->disablespmode && !$this->iscron) {
            $opts = get_option('wple_opts');

            if (isset($opts['type']) && $opts['type'] != 'wildcard') {
                $licid = WPLE_SPMode::checkExpiration();

                if ($licid) {
                    WPLE_SPMode::initiateValidation();
                }
            }
        }
    }

    /**
     * simple debug log message
     *
     * @since 5.2.6
     * @return void
     */
    private function wple_nocpanel_notice()
    {
        update_option('wple_ssl_screen', 'nocpanel');

        WPLE_Trait::wple_logger("Awaiting SSL installation for Non-cPanel site and SSL validation\n", "success");

        WPLE_Trait::wple_send_log_data();

        do_action('cert_expiry_updated');

        wp_redirect(admin_url('/admin.php?page=wp_encryption&nocpanel=1'), 302);
        exit();
    }

    /**
     * Send email to user on success
     * 
     * @since 3.0.0
     * @moved from le-admin.php on 5.7.2
     */
    private function wple_send_success_mail()
    {
        WPLE_Trait::wple_logger('Email - SSL generated');

        $opts = get_option('wple_opts');

        $to = sanitize_email($opts['email']);
        $subject = sprintf(esc_html__('Congratulations! Your SSL certificates for %s generated using WP Encryption Plugin', 'wp-letsencrypt-ssl'), WPLE_Trait::get_root_domain());
        $headers = array('Content-Type: text/html; charset=UTF-8');

        $body = '<h3>' . esc_html__('You are just ONE step away from enabling HTTPS for your WordPress site', 'wp-letsencrypt-ssl') . '</h3>';
        $body .= '<p>' . esc_html__('Download the generated SSL certificates from below given links and install it on your cPanel following the video tutorial', 'wp-letsencrypt-ssl') . ' (https://youtu.be/KQ2HYtplPEk). ' . esc_html__('These certificates expires on', 'wp-letsencrypt-ssl') . ' <b>' . esc_html($opts['expiry']) . '</b> so you will have to generate new certificate & install it manually before it expires.</p>
        <br/>
        <a href="' . admin_url('/admin.php?page=wp_encryption&le=1', 'http') . '" style="background: #0073aa; text-decoration: none; color: #fff; padding: 12px 20px; display: inline-block; margin: 10px 10px 10px 0; font-weight: bold;">' . esc_html__('Download Cert File', 'wp-letsencrypt-ssl') . '</a>
      <a href="' . admin_url('/admin.php?page=wp_encryption&le=2', 'http') . '" style="background: #0073aa; text-decoration: none; color: #fff; padding: 12px 20px; display: inline-block; margin: 10px; font-weight: bold;">' . esc_html__('Download Key File', 'wp-letsencrypt-ssl') . '</a>
      <a href="' . admin_url('/admin.php?page=wp_encryption&le=3', 'http') . '" style="background: #0073aa; text-decoration: none; color: #fff; padding: 12px 20px; display: inline-block; margin: 10px; font-weight: bold;">' . esc_html__('Download CA File', 'wp-letsencrypt-ssl') . '</a>
      <br/>';

        ///if (FALSE == get_option('wple_no_pricing')) {
        $body .= '<br /><br />';

        $body .= '<b>' . esc_html__('WP Encryption PRO can automate this entire process in one click including SSL installation on cPanel hosting and auto renewal of certificates every 90 days', 'wp-letsencrypt-ssl') . '!. <br><a href="' . admin_url('/admin.php?page=wp_encryption-pricing&checkout=true&billing_cycle_selector=responsive_list&plan_id=8210&plan_name=pro&billing_cycle=lifetime&pricing_id=7965&currency=usd') . '" style="background: #0073aa; text-decoration: none; color: #fff; padding: 12px 20px; display: inline-block; margin: 10px 0; font-weight: bold;">' . esc_html__('UPGRADE TO PREMIUM', 'wp-letsencrypt-ssl') . '</a></b><br /><br />';

        $body .= "<h3>" . esc_html__("Don't have cPanel hosting?", 'wp-letsencrypt-ssl') . "</h3>";

        $body .= '<p>No cPanel? No problem! Secure your site effortlessly with our <a href="' . admin_url('/admin.php?page=wp_encryption-pricing&checkout=true&billing_cycle_selector=responsive_list&plan_id=8210&plan_name=pro&billing_cycle=annual&pricing_id=7965&currency=usd') . '"><strong>Annual Pro plan</strong><a> designed to work across ANY hosting platform.' . WPLE_Trait::wple_kses(__('With free version, You can download and send these SSL certificates to your hosting support asking them to install these SSL certificates.', 'wp-letsencrypt-ssl')) . '</p><br /><br />';
        ///}    

        if (get_option('wple_email_certs') == true) {

            $certificate = WPLE_Trait::wple_cert_directory() . 'certificate.crt';

            if (class_exists('ZipArchive')) {

                if (file_exists($certificate)) {
                    $this->wple_log('Emailing certs as attachment');

                    $zip = new ZipArchive();
                    $zip->open(WPLE_Trait::wple_cert_directory() . 'certificates.zip', ZipArchive::CREATE);


                    $zip->addFile($certificate, 'certificate.crt');

                    $ret = $this->wple_parseCertificate($certificate);
                    $certexpirydate = date('d-m-Y', $ret['validTo_time_t']);

                    $pemfile = WPLE_Trait::wple_cert_directory() . 'private.pem';
                    $zip->addFile($pemfile, 'private.pem');

                    ///$cabundle = WPLE_DIR . 'cabundle/ca.crt';
                    // if (file_exists(ABSPATH . 'keys/cabundle.crt')) {
                    $cabundle = WPLE_Trait::wple_cert_directory() . 'fullchain.crt';
                    // }

                    $zip->addFile($cabundle, 'cabundle.crt');
                    $zip->close();

                    $body .= '<p>' . esc_html__('Confidential: New SSL cert files have been attached to this email as per your preferences.', 'wp-letsencrypt-ssl') . ' ' . esc_html__('These certificates expires on', 'wp-letsencrypt-ssl') . ' <b>' . esc_html($certexpirydate) . '</b></p>';

                    if (function_exists('wp_mail')) wp_mail($to, $subject, $body, $headers, array(WPLE_Trait::wple_cert_directory() . 'certificates.zip'));

                    unlink(WPLE_Trait::wple_cert_directory() . 'certificates.zip');
                } else {

                    $this->wple_log('Emailing certs skipped as certificate.crt not found.');
                    if (function_exists('wp_mail')) wp_mail($to, $subject, $body, $headers);
                }
            }
        } else {

            if (!wple_fs()->can_use_premium_code__premium_only()) { //success email to free users only
                if (function_exists('wp_mail')) wp_mail($to, $subject, $body, $headers);
            }
        }
    }

    /**
     * Test if http verification is possible on this server
     *
     * @since 5.9.0
     * @return void
     */
    // public function wple_verify_if_http_possible($domain)
    // {
    //   if (!wple_fs()->is__premium_only()) {

    //     $fpath = ABSPATH . '.well-known/acme-challenge/';
    //     if (!file_exists($fpath)) {
    //       mkdir($fpath, 0775, true);
    //     }

    //     $testfile = $fpath . 'testfile';

    //     if (!file_exists($testfile)) {
    //       file_put_contents($testfile, 'testcontent');
    //     }

    //     $testURL = 'http://' . $domain . '/.well-known/acme-challenge/testfile';
    //     $result = $this->wple_get_file_response($testURL);

    //     if ($result != 'testcontent') {
    //       $this->wple_log($result . ' acme-challenge directory is blocked on this server.');
    //       update_option('wple_http_valid', 1);
    //     }
    //   }
    // }

    public function wple_verify_free_order($starthttpverification = false, $startdnsverification = false)
    {
        if (!$this->order->allAuthorizationsValid()) {
            if (!$starthttpverification && !$startdnsverification) {
                $this->wple_save_all_challenges();

                $this->wple_wizard_redirections();

                ///$this->wple_log("HTTP Challenges --> " . json_encode($updated['challenge_files']), 'success', 'a');
                ///$this->wple_log("DNS Challenges --> " . json_encode($updated['dns_challenges']), 'success', 'a');

                $this->wple_log(esc_html__("Offering manual verification procedure.", 'wp-letsencrypt-ssl') . " \n", 'success', 'a');

                if (!wple_fs()->is__premium_only()) {
                    if (FALSE != ($dlog = get_option('wple_send_usage')) && $dlog) {
                        $this->wple_send_usage_data();
                    }
                }

                update_option('wple_ssl_screen', 'verification');

                wp_redirect(admin_url('/admin.php?page=wp_encryption&subdir=1'), 302);
                exit();
            } else { //?wpleauto        

                if ($starthttpverification) {
                    WPLE_Trait::static_wellknown_htaccess();
                    $this->wple_get_pendings(); //get http challenges
                }

                if ($startdnsverification) {
                    $this->wple_get_pendings(true); //get dns challenges
                }

                if (!empty($this->pendings)) {

                    foreach ($this->pendings as $challenge) {

                        if ($challenge['type'] == 'dns-01' && stripos($challenge['identifier'], $this->rootdomain) !== false) {

                            $this->order->verifyPendingOrderAuthorization($challenge['identifier'], LEOrder::CHALLENGE_TYPE_DNS, false);
                        } else if ($challenge['type'] == 'http-01' && stripos($challenge['identifier'], $this->rootdomain) !== false) {

                            $acmefile = "http://" . $challenge['identifier'] . "/.well-known/acme-challenge/" . $challenge['filename'];
                            $rsponse = $this->wple_get_file_response($acmefile);

                            if ($rsponse !== trim($challenge['content'])) {

                                WPLE_Trait::remove_wellknown_htaccess();
                                ///WPLE_Trait::static_wellknown_htaccess();

                                //re-try again
                                $rsponse = $this->wple_get_file_response($acmefile);

                                //ultimate failure
                                if ($rsponse !== trim($challenge['content'])) {
                                    update_option('wple_error', 2);
                                }
                            }

                            $this->order->verifyPendingOrderAuthorization($challenge['identifier'], LEOrder::CHALLENGE_TYPE_HTTP, false);
                        }
                    }
                } else {
                    $this->wple_log(esc_html__("No pending challenges. Proceeding..", 'wp-letsencrypt-ssl') . " \n", 'success', 'a');
                }
            }
        }
    }

    public function wple_verify_pro_order__premium_only($resuming_dns_verification = false)
    {
        if (isset($_GET['wpleauto']) && $_GET['wpleauto'] == 'dns') {
            $resuming_dns_verification = true;
        }

        if (!$this->order->allAuthorizationsValid()) {
            if ($this->wcard) {
                $this->wple_pro_wildcard__premium_only($resuming_dns_verification);
            } else {
                $this->wple_pro_single_domain__premium_only($resuming_dns_verification);
            }
        }
    }

    public function wple_pro_single_domain__premium_only($resumingdns = false, $DNSfallback = false)
    {
        if ($DNSfallback || $resumingdns) {
            $this->wple_get_pendings(true); //get dns challenges

            if (!empty($this->pendings)) {

                if (!$resumingdns) { //help with dns challenge completion
                    $this->wple_save_all_challenges();

                    if (FALSE !== get_option('wple_gdaddy')) {
                        $this->wple_godaddy_dns_automate__premium_only();
                    }

                    $this->wple_cpanel_dns_automate__premium_only();
                }

                //we have the records ready
                //godaddy or cpanel succeeded
                $localCheckDNS = true;
                if (isset($_GET['wpleauto'])) { //we have already done the check
                    $localCheckDNS = false;
                }
                foreach ($this->pendings as $challenge) {
                    if ($challenge['type'] == 'dns-01' && stripos($challenge['identifier'], $this->rootdomain) !== false) {
                        $this->order->verifyPendingOrderAuthorization($challenge['identifier'], LEOrder::CHALLENGE_TYPE_DNS, $localCheckDNS); //local check necessary
                    }
                }
            }
        } else {
            $this->wple_get_pendings(); //get http challenges

            if (!empty($this->pendings)) {
                foreach ($this->pendings as $challenge) {
                    if ($challenge['type'] == 'http-01' && stripos($challenge['identifier'], $this->rootdomain) !== false) {

                        $acmefile = "http://" . $challenge['identifier'] . "/.well-known/acme-challenge/" . $challenge['filename'];
                        $this->wple_deploy_challenge_files__premium_only($acmefile, $challenge);

                        $rsponse = $this->wple_get_file_response($acmefile);

                        if ($rsponse !== trim($challenge['content'])) {
                            WPLE_Trait::remove_wellknown_htaccess();
                            WPLE_Trait::static_wellknown_htaccess();

                            //re-try again
                            $rsponse = $this->wple_get_file_response($acmefile);

                            //ultimate failure
                            if ($rsponse !== trim($challenge['content'])) {
                                if (file_exists(ABSPATH . '.well-known/acme-challenge/' . $challenge['filename'])) {
                                    LEFunctions::log("Local file exists so skipping local check in Core.\n");
                                } else {

                                    update_option('wple_error', 2);

                                    $this->wple_log(esc_html__("HTTP verification failed as access to .well-known/acme-challenge folder strictly blocked on your hosting server. Trying DNS based verification now.\n", 'wp-letsencrypt-ssl') . " \n", 'success', 'a');

                                    //re-route to DNS method
                                    $this->wple_pro_single_domain__premium_only(false, true);
                                }
                            }
                        }

                        sleep(5);
                        $this->order->verifyPendingOrderAuthorization($challenge['identifier'], LEOrder::CHALLENGE_TYPE_HTTP, false); //we already do local checks
                    }
                }
            }
        }
    }

    public function wple_pro_wildcard__premium_only($resumingdns = false)
    {
        $this->wple_get_pendings(true); //get dns challenges

        if (!empty($this->pendings)) {

            if (!$resumingdns) {
                $this->wple_save_all_challenges();

                if (FALSE !== get_option('wple_gdaddy')) {
                    $this->wple_godaddy_dns_automate__premium_only();
                }

                $this->wple_cpanel_dns_automate__premium_only();
            }

            //we have the records ready
            //godaddy or cpanel succeeded
            foreach ($this->pendings as $challenge) {
                if ($challenge['type'] == 'dns-01' && stripos($challenge['identifier'], $this->rootdomain) !== false) {
                    $this->order->verifyPendingOrderAuthorization($challenge['identifier'], LEOrder::CHALLENGE_TYPE_DNS, false);
                }
            }
        }
    }


    public function wple_godaddy_dns_automate__premium_only()
    {
        require_once dirname(__DIR__) . '/classes/le-gdaddy-dns.php';

        $this->wple_log("Starting Godaddy DNS automation\n", 'success', 'a');

        $gdopts = (FALSE != get_option('wple_gdaddy')) ? get_option('wple_gdaddy') : array();
        $records = get_option('wple_opts');
        $records = $records['dns_challenges'];

        try {
            WPLE_Gdaddy::wple_add_dns_gdaddy__premium_only($records, $gdopts, $this->mdomain);

            $localDNSVerify = WPLE_Trait::wple_verify_dns_records();
            if ($this->iscron || !$localDNSVerify) {

                update_option('wple_dns_new', 1); //newly added dns records

                wp_schedule_single_event(time() + 1800, 'wple_ssl_renewal', array('propagating')); //lets wait 30mins & continue later

                $this->wple_log("<h2>" . esc_html__("DNS records have been added successfully. Verification will automatically continue in 30Mins. Please check back later", 'wp-letsencrypt-ssl') . "</h2>\n", 'success', 'a', true);
                exit();
            }
        } catch (Exception $e) {

            $this->wple_log($e->getMessage());
            $this->wple_log("Godaddy DNS automation failed\n");

            wp_redirect(admin_url('/admin.php?page=wp_encryption&subdir=1'), 302);
            exit();
        }
    }

    public function wple_cpanel_dns_automate__premium_only()
    {
        if (function_exists('shell_exec')) {
            $whichcpapi = shell_exec('which cpapi2');
            if (!empty($whichcpapi)) {

                $this->wple_attempt_dns_verification__premium_only();
            } else { //cpapi2 not available
                $this->wple_attempt_dns_verification__premium_only(true);
            }
        } else {
            $this->wple_log(esc_html__('Automatic DNS verification is not possible on this server as shell_exec PHP function is not enabled on this server.', 'wp-letsencrypt-ssl'), 'error', 'a', false);

            //offer manual challenge
            wp_redirect(admin_url('/admin.php?page=wp_encryption&subdir=1'), 302);
            exit();
        }
    }

    public function wple_wizard_redirections()
    {
        if ($this->wizard) {
            $opts = get_option('wple_opts');

            if (isset($opts['challenge_files'])) {
                $fpath = ABSPATH . '.well-known/acme-challenge/';
                if (!file_exists($fpath)) {
                    mkdir($fpath, 0775, true);
                }

                foreach ($opts['challenge_files'] as $index => $item) {
                    $this->wple_log(esc_html__('Deploying challenge file', 'wp-letsencrypt-ssl') . ' ' . $item['file'], 'success', 'a');

                    file_put_contents($fpath . $item['file'], trim($item['value']));
                }

                update_option('wple_send_usage', 1);

                //straight to verification
                echo json_encode(['success' => true, 'message' => admin_url('/admin.php?page=wp_encryption&wpleauto=http')]);
            } else { //manual verification page
                echo json_encode(['success' => true, 'message' => admin_url('/admin.php?page=wp_encryption&subdir=1'),]);
            }
            exit();
        }
    }
}
