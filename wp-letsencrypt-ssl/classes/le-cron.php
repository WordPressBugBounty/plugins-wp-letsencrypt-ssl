<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "SSL certificate auto renewal started";

require_once(__DIR__ . '/../../../../wp-load.php');

require_once(ABSPATH . 'wp-admin/includes/file.php');

require_once __DIR__ . '/../wp-letsencrypt.php'; //req for crontab

require_once __DIR__ . '/le-autorenew.php';

///$certfile = ABSPATH . 'keys/certificate.crt';

if (!wple_fs()->can_use_premium_code__premium_only()) {
    parent::wple_log("\n\n-------SSL RENEWAL ABORTED - PRO LICENSE EXPIRED OR NOT ACTIVE ----PLEASE RENEW VIA HTTPS://WPENCRYPTION.COM/MY-ACCOUNT/----\n", 'success', 'w', false);
    exit();
}

$currentdomain = esc_html(str_ireplace(array('http://', 'https://'), array('', ''), site_url()));
$slashpos = stripos($currentdomain, '/');
if (false !== $slashpos) { //subdir installation
    $currentdomain = substr($currentdomain, 0, $slashpos);
}


if (get_option('wple_sectigo')) { //detected on ssllabs scan
    exit();
}

$ipaddr = gethostbyname($currentdomain);
if (stripos($ipaddr, '151.139') !== false) {
    exit();
}

// if (!file_exists($certfile) || !is_array($ret = openssl_x509_parse(file_get_contents($certfile), true))) {
//   exit();
// }

//$expiry = date('d-m-Y', $ret['validTo_time_t']);

// $cronstartat = strtotime('-30 day', strtotime($expiry)); //is expiring in less than 30 days

// $today = time();

// if ($today < $cronstartat) {
//   exit();
// }

//its time to renew

$AR = new WPLEPRO_Core(true);

//delete_option('wple_renewal_inprogress');
//delete_option('wple_renewal_failed');
//delete_option('wple_renewal_failed_notice');

$AR->wple_start_ssl_renewal('', true);

//TODO: waited_propagation and continue
