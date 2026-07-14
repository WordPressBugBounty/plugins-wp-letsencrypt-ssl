<?php

/**
 * @package WP Encryption
 *
 * @author     WP Encryption
 * @copyright  Copyright (C) 2019-2025, WP Encryption
 * @link       https://wpencryption.com
 * @since      Class available since Release 2.0.0
 *
 */

include_once dirname(__DIR__) . '/classes/le-core.php';

/**
 * WPLEPRO_Core
 * 
 * @since 2.0
 */
class WPLEPRO_Core extends WPLE_Core
{

    public function __construct($crontab = false)
    {
        $opts = get_option('wple_opts');

        $wc = false;
        if (isset($opts['type']) && $opts['type'] == 'wildcard') {
            $wc = true;
        }

        parent::__construct(array(), false, $wc, true);

        if (!$crontab) {
            add_action('cert_expiry_updated', array($this, 'wple_schedule_renewal_cron'));

            add_action('wple_ssl_renewal', array($this, 'wple_start_ssl_renewal'));
            ///add_action('wple_ssl_renewal_recheck', array($this, 'wple_confirm_ssl_renewal'));
            //add_action('wple_ssl_renewal_failed', array($this, 'wple_ssl_renewal_failed'));
        }
    }

    public function wple_schedule_renewal_cron()
    {
        $opts = get_option('wple_opts');

        if ($opts['expiry'] != '') {

            WPLE_Trait::clear_all_renewal_crons(true);

            parent::wple_log('Re-scheduling crons');

            //since 5.7.16
            $app_cron_path = __DIR__ . "/le-cron.php";
            $cron_success = false;
            if (function_exists('shell_exec')) {

                $output = shell_exec('crontab -l');
                ///parent::wple_log($output);

                $wp_root = trailingslashit(ABSPATH);
                $cron_file = $wp_root . 'crontab.txt';

                $add_new_cron_base = "0 5 * * * /usr/local/bin/php -q $app_cron_path"; //set to 5AM
                $add_new_cron = $add_new_cron_base . ' >/dev/null 2>&1';
                //ANother 6am cron for /usr/bin/php //v6.5
                $add_new_cron_base2 = "0 6 * * * /usr/bin/php -q $app_cron_path"; //set to 6AM
                $add_new_cron2 = $add_new_cron_base2 . ' >/dev/null 2>&1';

                //remove existing cron since 6.2
                $output = str_ireplace('0 0 * * * php -q ' . $app_cron_path . ' >/dev/null 2>&1', '', $output);
                $output = str_ireplace('0 0 * * * /usr/local/bin/php -q ' . $app_cron_path . ' >/dev/null 2>&1', '', $output);

                //v6.5 remove existing cron completely
                ///$output = str_ireplace('0 5 * * * /usr/local/bin/php -q ' . $app_cron_path . ' >/dev/null 2>&1', '', $output);

                //check if the cron job was already added
                if ((false === strpos($output, "/usr/bin/php -q $app_cron_path")) && (false === strpos($output, "/usr/local/bin/php -q $app_cron_path"))) {

                    $add_cron = trim($output . $add_new_cron) . PHP_EOL;
                    //v6.5
                    $add_cron = trim($add_cron . $add_new_cron2);

                    //TODO: Now test if crontab -l is updated properly with 2 cron jobs 5am & 6am

                    if (file_put_contents($cron_file, $add_cron . PHP_EOL)) {

                        $output = [];

                        //$return_var = 1 means error. $return_var = null means success.
                        $return_var = shell_exec("crontab $cron_file");

                        if (1 === $return_var) {
                            parent::wple_log("\n\n--------------CRONTAB UPDATE FAILED----------------\n", 'success', 'a', false);
                        } elseif (null === $return_var) {
                            unlink($cron_file);
                            $cron_success = true;

                            //v6.5 re-check if crontab was updated properly
                            $output = shell_exec('crontab -l');
                            if (false === strpos($output, $app_cron_path)) { //crontab failed to update
                                $cron_success = false;
                            } else {
                                parent::wple_log("\n\n--------------CRONTAB UPDATED----------------\n", 'success', 'a', false);
                            }
                        }
                    } else {
                        parent::wple_log("\n\n--------------CRONTAB FILE WRITE FAILED----------------\n", 'success', 'a', false);
                    }
                } else {
                    $cron_success = true;
                    parent::wple_log("\n\n--------------CRONTAB V2 ALREADY ACTIVE----------------\n", 'success', 'a', false);
                }
            }

            if ($cron_success) {
                update_option('wple_renewal_type', 'cp');
            } else {
                update_option('wple_renewal_type', 'wp');
            }

            if (!$cron_success && !wp_next_scheduled('wple_ssl_renewal')) { //crontab failed so we will use wp cron
                update_option('wple_renewal_type', 'wp');
                wp_schedule_event(strtotime('05:00:00'), 'daily', 'wple_ssl_renewal');
            }
        }
    }

    public function wple_start_ssl_renewal($waited_propagation = '', $started_via_crontab = false)
    {

        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }

        $det = date('d-m-Y');

        if (!wple_fs()->can_use_premium_code__premium_only()) {
            parent::wple_log("\n\n-------SSL RENEWAL ABORTED - PRO LICENSE EXPIRED OR NOT ACTIVE [$det]----PLEASE RENEW VIA HTTPS://WPENCRYPTION.COM/MY-ACCOUNT/----\n", 'success', 'w', false);
            return true;
        }

        $resuming = false;

        if ($waited_propagation == 'propagating') {
            $resuming = true;
            parent::wple_log("\n\n--------------WAITED FOR DNS PROPAGATION AND CONTINUING [$det]----------------\n", 'success', 'a', false);
        } else {

            if (get_transient('wple_ssl_installing')) { //cron already started          
                return true;
            }

            if (get_transient('server_restart_pending')) {
                return true;
            }

            if (get_option('wple_sectigo')) { //no renewal when using sectigo solution or cdn plan
                return true;
            }

            ///$opts = get_option('wple_opts');

            // if ($opts['expiry'] == '') {
            //     return true;
            // }

            //since 7.7.7 renew only based on SSLLabs or LIVE installed SSL expiry
            // $ssllabs_expiry = get_option('wple_ssllabs_expiry');

            // $its_renewal_time = false;
            // $triggeredBy = '';

            // if ($ssllabs_expiry) {

            //     $expiringIn30days = strtotime('-30 day', $ssllabs_expiry);

            //     if (strtotime('now') > $expiringIn30days) { //less than 30 days left to expiry
            //         $its_renewal_time = true;
            //         $triggeredBy = 'Labs';
            //     }
            // }

            //v6.5 If installed SSL is expiring within 30 days 
            //updated @7.8.5.13 - removed ssllabs dependency
            $urltocheck = WPLE_Trait::get_root_domain(true);
            $host = $urltocheck;
            $ctx = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true
                ]
            ]);

            $its_renewal_time = false;
            $triggeredBy = '';

            $timeout = 5;
            $errNo = $errStr = null;
            $fp = stream_socket_client("ssl://{$host}:443", $errNo, $errStr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
            if ($fp === false) {
                // no response                
            } else {

                $params = stream_context_get_params($fp);
                $cert = $params['options']['ssl']['peer_certificate'] ?? null;
                if ($cert) {
                    $ret = openssl_x509_parse($cert, true);
                }
                fclose($fp);

                $activecertexpirytime = strtotime('-30 day', $ret['validTo_time_t']);

                if (strtotime('now') > $activecertexpirytime) { //during last 30 days of active ssl expiry
                    $its_renewal_time = true;
                    $triggeredBy = 'ActiveSSL';
                }
            }

            if (!$its_renewal_time) {
                return true;
            }

            if (get_option('wple_hold_cron')) { //manual DNS verification required
                return true;
            }

            if ($started_via_crontab) {
                parent::wple_log("\n\n--------------CERT RENEWAL INITIATED BY CRONTAB-----\n");
            }

            //daily SSLlabs scan cron in case if not already exists
            if (!wp_next_scheduled('wple_ssl_expiry_update')) {
                wp_schedule_event(strtotime('05:30:00'), 'daily', 'wple_ssl_expiry_update');
            }

            //lock the status
            set_transient('wple_ssl_installing', 1, 600); //10 mins

            parent::wple_log("\n\n--------------STARTED CERT RENEWAL [$det]-------$triggeredBy---------\n", 'success', 'a', false);
            delete_option('wple_dns_new');

            if (defined('WPLE_PLUGIN_VER')) parent::wple_log("\nPRO VERSION: " . WPLE_PLUGIN_VER . "\n", "success", "a", false);
            parent::wple_log("Domains covered: " . json_encode($this->domains) . "\n");
        }

        ///delete_option('wple_sourceip_enable');

        parent::wple_create_client();
        parent::wple_generate_order();

        parent::wple_verify_pro_order__premium_only($resuming);

        try { //since 5.9.2

            parent::wple_generate_certs();
            parent::wple_install_certs__premium_only(true);
        } catch (Exception $e) {

            parent::wple_log($e->getMessage());
            parent::wple_send_usage_data();
        }

        parent::wple_send_usage_data();
        //parent::wple_save_expiry_date();

    }

    // public function wple_confirm_ssl_renewal()
    // {
    //   if (get_option('wple_renewal_failed') || get_option('wple_renewal_inprogress')) {

    //     delete_option('wple_renewal_failed');

    //     $det = date('d-m-Y');
    //     parent::wple_log("\n\n--------------RE-TRYING FAILED CERT RENEWAL [$det]----------------\n", 'success', 'a', false);

    //     $this->wple_start_ssl_renewal(); //lets try one more time
    //   }
    // }

    // public function wple_ssl_renewal_failed()
    // {
    //   if (FALSE !== get_option('wple_renewal_inprogress')) { //renewal failed probably due to domain verification or other issue before installation step
    //     delete_option('wple_renewal_inprogress');
    //     update_option('wple_renewal_failed_notice', 1);
    //   }
    // }

    public function log($msg = '')
    {
        parent::wple_log($msg, 'success', 'a', false);
    }
}
