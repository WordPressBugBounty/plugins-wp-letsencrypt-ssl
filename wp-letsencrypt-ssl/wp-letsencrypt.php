<?php

/**
 *
 * One Click SSL & Force HTTPS
 *
 * Plugin Name:       WP Encryption - One Click SSL & Force HTTPS
 * Plugin URI:        https://wpencryption.com
 * Description:       Secure your WordPress site with free SSL certificate and force HTTPS. Enable HTTPS padlock. Just activating this plugin won't help! - Please run the SSL install form of WP Encryption found on left panel. Enjoy the NEW Advanced security features including malware scan, vulnerability scan, file integrity monitoring, security hardening & more.
 * Version:           7.8.6.6
 * Author:            WP Encryption SSL HTTPS
 * Author URI:        https://wpencryption.com
 * License:           GNU General Public License v3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       wp-letsencrypt-ssl
 * Domain Path:       /languages
 *
 * @author      WP Encryption SSL
 * @category    Plugin
 * @package     WP Encryption
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 * 
 * @copyright   Copyright (C) 2019-2025, WP Encryption (support@wpencryption.com)
 *
 * @fs_premium_only /classes/le-autorenew.php, /classes/le-pleskapi.php, /classes/le-cron.php, /classes/le-gdaddy-dns.php, /classes/le-spmode.php, /classes/le-cpapi.php, /classes/cPanel, /classes/directadmin, /languages/, /admin/scss, /admin/app, /admin/wizard, /admin/interests, README_License.txt
 * 
 */

/**
 * Die on direct access
 */
if (!defined('ABSPATH')) {
    die('Access Denied');
}

/**
 * Definitions
 */
if (!defined('WPLE_PLUGIN_VER')) define('WPLE_PLUGIN_VER', '7.8.6.6');
if (!defined('WPLE_BASE')) define('WPLE_BASE', plugin_basename(__FILE__));
if (!defined('WPLE_DIR')) define('WPLE_DIR', plugin_dir_path(__FILE__));
if (!defined('WPLE_URL')) define('WPLE_URL', plugin_dir_url(__FILE__));
if (!defined('WPLE_NAME')) define('WPLE_NAME', 'WP Encryption');
if (!defined('WPLE_SLUG')) define('WPLE_SLUG', 'wp_encryption');

$wple_updir = wp_upload_dir();
$uploadpath = $wple_updir['basedir'] . '/';
if (!file_exists($uploadpath)) {
    $uploadpath = ABSPATH . 'wp-content/uploads/wp_encryption/';
}
if (!defined('WPLE_UPLOADS')) define('WPLE_UPLOADS', $uploadpath);

if (!defined('WPLE_DEBUGGER')) define('WPLE_DEBUGGER', WPLE_UPLOADS . 'wp_encryption/');

/**
 * Freemius
 */
if (function_exists('wple_fs')) {
    wple_fs()->set_basename(true, __FILE__);
} else {

    if (!function_exists('wple_fs')) {
        // Activate multisite network integration.
        if (!defined('WP_FS__PRODUCT_5090_MULTISITE')) {
            define('WP_FS__PRODUCT_5090_MULTISITE', true);
        }

        // Create a helper function for easy SDK access.
        function wple_fs()
        {
            global $wple_fs;
            ///$showpricing = (FALSE !== get_option('wple_no_pricing')) ? false : true;
            ///$showpricing = true;

            if (!isset($wple_fs)) {
                // Include Freemius SDK.
                require_once dirname(__FILE__) . '/freemius/start.php';

                $wple_fs = fs_dynamic_init(array(
                    'id'                  => '5090',
                    'slug'                => 'wp-letsencrypt-ssl',
                    'premium_slug'        => 'wp-letsencrypt-ssl-pro',
                    'type'                => 'plugin',
                    'public_key'          => 'pk_f6a07c106bf4ef064d9ac4b989e02',
                    'is_premium'          => true,
                    'has_premium_version' => true,
                    'has_addons'          => true,
                    'has_paid_plans'      => true,
                    'is_org_compliant'    => true,
                    //'has_affiliation'     => 'all',
                    'menu'                => array(
                        'slug'           => 'wp_encryption',
                        'support'        => false,
                        'contact'        => false,
                        ///'pricing'        => $showpricing,
                    ),
                    // Set the SDK to work in a sandbox mode (for development & testing).
                    // IMPORTANT: MAKE SURE TO REMOVE SECRET KEY BEFORE DEPLOYMENT.
                    'secret_key'          => 'sk_bWU$yy@Jv;h_}BKHcXi^k&{Pqs&Is',
                ));
            }

            return $wple_fs;
        }

        // Init Freemius.
        wple_fs();
        // Signal that SDK was initiated.
        do_action('wple_fs_loaded');
    }
}

// require composer autoloader if present
$composer_autoload = __DIR__ . '/vendor/autoload.php';
if (!class_exists('WPLEClient\LEClient')) {
    require_once $composer_autoload;
}


// wple_fs()->add_filter('pricing/disable_single_package', 'wple_show_single_package');
// if (!function_exists('wple_show_single_package')) {
//     function wple_show_single_package()
//     {
//         return true;
//     }
// }
wple_fs()->add_filter('pricing/show_annual_in_monthly', 'wple_annual_amount');
if (!function_exists('wple_annual_amount')) {
    function wple_annual_amount()
    {
        return false;
    }
}

wple_fs()->add_filter('templates/pricing.php', 'wple_pricing_reactstyle');
if (!function_exists('wple_pricing_reactstyle')) {
    function wple_pricing_reactstyle($template)
    {
        $style = "<style>
            header.fs-app-header .fs-page-title {
            display: none !important;
            }

            section.fs-plugin-title-and-logo {
            margin: 0 !important;
            }

            section.fs-plugin-title-and-logo h1 {
            font-size: 2em !important;
            }

            img.fs-limited-offer {
            max-width: 600px;
            }

            li.fs-selected-billing-cycle {
            background: -webkit-gradient(linear, left bottom, left top, from(#333), to(#444)) !important;
            background: linear-gradient(0deg, #333, #444) !important;
            color: #fff !important;
            }

            .fs-billing-cycles li {
            padding: 7px 50px !important;
            }

            button.fs-button.fs-button--size-large {
            background: -webkit-gradient(linear, left top, left bottom, from(#6cc703), to(#139104)) !important;
            background: linear-gradient(180deg, #6cc703, #139104) !important;
            border: none !important;
            color: #fff !important;
            padding-top: 12px !important;
            padding-bottom: 12px !important;
            font-weight: 400 !important;
            }

            h2.fs-plan-title {
            padding-top: 15px !important;
            padding-bottom: 15px !important;
            }

            span.fs-feature-title strong {
            padding-right: 3px;
            }

            ul.fs-plan-features-with-value li {
            padding: 5px 0;
            background: #f6f6f6;
            }

            ul.fs-plan-features-with-value li:nth-of-type(even) {
            background: none;
            }

            .fs-plan-support strong {
            font-weight: 500 !important;
            color: #666;
            }

            section.fs-section.fs-section--plans-and-pricing:before {
            content: '';
            display: block;
            background: url(https://gowebsmarty.com/limited-offer.png) no-repeat top center;
            height: 120px;
            background-size: 600px auto;
            }

            #fs_pricing_app .fs-package .fs-plan-features {
            margin: 20px 25px 0 !important;
            }

            button.fs-button.fs-button--size-large:hover {
            background: -webkit-gradient(linear, left top, left bottom, from(#6cc703), to(#148706)) !important;
            background: linear-gradient(180deg, #6cc703, #148706) !important;
            }

            /** 10-10-2025 **/
            .fs-featured-plan h2.fs-plan-title {
            background-image: -webkit-gradient(linear, left top, left bottom, from(#6bc405), to(#18ac07)) !important;
            background-image: linear-gradient(180deg, #6bc405, #18ac07) !important;
            background-color: #6bc405 !important;
            border-color: #6bc405 !important;
            }

            #fs_pricing_app .fs-section--packages .fs-packages-nav {
            overflow: visible;
            margin-top: 40px;
            }

            .fs-most-popular {
                position: absolute;
                width: 100%;
                top: 40px;
                overflow: hidden;
                height: 80px;
                background: none !important;
            }
            #fs_pricing_app .fs-package.fs-featured-plan .fs-most-popular h4{
            position: absolute;
            border-radius: 0;
            line-height: 1.4em;
            margin-top: 0;
            right: -25px;
            -webkit-transform: rotate(40deg);
                    transform: rotate(40deg);
            top: 18px;
            background: #dd3c26;
            letter-spacing: 0.5px;
            }

            #fs_pricing_app .fs-package.fs-featured-plan .fs-most-popular h4 {
            color: #fff;
            -webkit-box-shadow: 0px 0px 1px rgba(0, 0, 0, 0.5);
                    box-shadow: 0px 0px 1px rgba(0, 0, 0, 0.5);
            }

            #fs_pricing_app .fs-package.fs-featured-plan {
            position: relative;
            }

            #fs_pricing_app .fs-package.fs-featured-plan .fs-most-popular h4:before {
            z-index: 1;
            background: url(//wimg.freemius.com/website/pages/pricing/sprite.png);
            width: 39px;
            height: 52px;
            display: block;
            position: absolute;
            top: 0;
            }

            #fs_pricing_app .fs-package.fs-featured-plan .fs-most-popular h4 strong {
            font-weight: 400;
            font-size: 9px;
            padding: 0 20px;
            }

            #fs_pricing_app .fs-package .fs-plan-title {
            padding: 25px 0 !important;
            }

            select.fs-currencies {
            border-color: #aaa !important;
            width: 100px;
            }

            .fs-package-content strong.fs-currency-symbol {
            font-size: 28px !important;
            color: #888;
            font-weight: 400 !important;
            }

            #fs_pricing_app .fs-package .fs-selected-pricing-amount .fs-selected-pricing-amount-integer {
            color: #666666;
            }

            #fs_pricing_app .fs-package .fs-selected-pricing-amount .fs-selected-pricing-amount-integer strong {
            font-weight: 500;
            }

            #fs_pricing_app .fs-featured-plan .fs-selected-pricing-amount .fs-selected-pricing-amount-integer {
            font-size: 68px;
            color: #6bc406;
            }

            .fs-featured-plan strong.fs-selected-pricing-amount-fraction {
            color: #63b507;
            }

            #fs_pricing_app .fs-package .fs-selected-pricing-cycle {
            color: #666666;
            }

            #fs_pricing_app .fs-package.fs-featured-plan .fs-selected-pricing-license-quantity {
            text-transform: uppercase;
            margin-top: 10px;
            }

            #fs_pricing_app .fs-package.fs-featured-plan .fs-license-quantity-discount span {
            background: #6bc406;
            border: none;
            font-weight: 400;
            }

            #fs_pricing_app .fs-package.fs-featured-plan .fs-license-quantities .fs-license-quantity-selected {
            background: #333333;
            border-color: #333 !important;
            }

            #fs_pricing_app .fs-package .fs-upgrade-button-container .fs-upgrade-button {
            /* margin-top: 0; */
            /* border-radius: 0; */
            padding: 20px 0 !important;
            }

            .fs-free-plan .fs-selected-pricing-amount {
            margin-top: 20px !important;
            }

            .fs-free-plan button.fs-button.fs-button--size-large.fs-upgrade-button.fs-button--outline {
            background: #aaaaaa !important;
            }

            ul.fs-plan-features li .fs-feature-title {
            color: #555 !important;
            }

            section.fs-section.fs-section--custom-implementation {
            display: none !important;
            }

            div#fs_pricing_app {
            background: #f0f0f1 !important;
            }

            section.fs-section.fs-section--money-back-guarantee:before {
            background-image: linear-gradient(-135deg, #f1f1f1 7.5px, transparent 0), linear-gradient(135deg, #f1f1f1 7.5px, transparent 0);
            content: '';
            display: block;
            position: absolute;
            left: 0px;
            width: 100%;
            height: 15px;
            background-repeat: repeat-x;
            background-size: 15px 15px;
            background-position: left top;
            }

            section.fs-section.fs-section--money-back-guarantee {
            background: #fff;
            padding-bottom: 30px;
            }

            section.fs-section.fs-section--money-back-guarantee h2 {
            padding-top: 30px !important;
            }

            section.fs-section.fs-section--testimonials h2 {
            font-weight: 400;
            color: #0073aa !important;
            }
        </style>";


        return $style . $template;
    }
}


if (!class_exists('WPLE_Trait')) {
    require_once __DIR__ . '/classes/le-trait.php';
}

/**
 * Plugin Activator hook
 */
register_activation_hook(__FILE__, 'wple_activate');

if (!function_exists('wple_activate')) {
    function wple_activate($networkwide)
    {

        require_once __DIR__ . '/classes/le-activator.php';
        WPLE_Activator::activate($networkwide);
    }
}

/**
 * Plugin Deactivator hook
 */
register_deactivation_hook(__FILE__, 'wple_deactivate');

if (!function_exists('wple_deactivate')) {
    function wple_deactivate()
    {
        require_once __DIR__ . '/classes/le-deactivator.php';
        WPLE_Deactivator::deactivate();
    }
}


/**
 * Class to handle all aspects of plugin page
 */
if (!class_exists('WPLE_Admin')) {
    require_once __DIR__ . '/admin/le_admin.php';
    new WPLE_Admin();
}

/**
 * Admin Pages
 * @since 5.0.0
 */
if (!class_exists('WPLE_SubAdmin')) {
    require_once __DIR__ . '/admin/le_admin_pages.php';
    new WPLE_SubAdmin();
}

/**
 * Force SSL on frontend
 */
if (!class_exists('WPLE_ForceSSL')) {
    require_once __DIR__ . '/classes/le-forcessl.php';
    new WPLE_ForceSSL();
}

/**
 * Scanner
 *
 * @since 5.1.8
 */
if (!class_exists('WPLE_Scanner')) {
    require_once __DIR__ . '/classes/le-scanner.php';
    new WPLE_Scanner();
}

if (wple_fs()->can_use_premium_code__premium_only()) {

    /**
     * Auto renew SSL
     * 
     * @since 2.0
     */
    if (!class_exists('WPLEPRO_Core')) {
        require_once __DIR__ . '/classes/le-autorenew.php';
        new WPLEPRO_Core();
    }

    /**
     * Godaddy DNS automation
     * 
     * @since 3.4.0
     */
    if (!class_exists('WPLE_Gdaddy')) {
        require_once __DIR__ . '/classes/le-gdaddy-dns.php';
        new WPLE_Gdaddy();
    }

    /**
     * API Support
     * 
     * @since 5.0.4
     */
    ///if (!class_exists('WPLE_UAPI')) {
    require_once __DIR__ . '/classes/le-cpapi.php';
    new WPLE_UAPI();
    ///}

    /**
     * Plesk login API
     * 
     * @since 7.2.0
     */
    if (!class_exists('WPLE_PleskAPI')) {
        require_once __DIR__ . '/classes/le-pleskapi.php';
        new WPLE_PleskAPI();
    }

    /** DirectAdmin API */
    if (!class_exists('DirectAdmin')) {
        require_once __DIR__ . '/classes/directadmin/directadmin.php';
    }
}

if (function_exists('wple_fs') && !function_exists('wple_fs_custom_connect_message')) {
    function wple_fs_custom_connect_message($message)
    {
        $current_user = wp_get_current_user();

        return 'Howdy ' . ucfirst($current_user->user_nicename) . ', <br>' .
            __('Due to security nature of this plugin, We <b>HIGHLY</b> recommend you opt-in to our security & feature updates notifications, and <a href="https://freemius.com/wordpress/usage-tracking/5090/wp-letsencrypt-ssl/" target="_blank">non-sensitive diagnostic tracking</a> to get BEST support. If you skip this, that\'s okay! <b>WP Encryption</b> will still work just fine.', 'wp-letsencrypt-ssl');
    }

    wple_fs()->add_filter('connect_message', 'wple_fs_custom_connect_message');
}

/**
 * Support forum URL for Premium
 * 
 * @since 5.3.2
 */
if (wple_fs()->is_premium() && !function_exists('wple_premium_forum')) {
    function wple_premium_forum($wp_org_support_forum_url)
    {
        return 'https://support.wpencryption.com/';
    }
    wple_fs()->add_filter('support_forum_url', 'wple_premium_forum');
}

/**
 * Dont show cancel subscription popup
 * 
 * @since 5.3.2
 */
wple_fs()->add_filter('show_deactivation_subscription_cancellation', '__return_false');

/**
 * Security Init
 * 
 * @since 7.0.0
 */
if (!class_exists('WPLE_Security')) {
    require_once __DIR__ . '/classes/le-security.php';
    new WPLE_Security();
}

/**
 * Passkeys
 * 
 * @since 7.8.6
 */
if (!class_exists('WPLE_Passkeys')) {
    require_once __DIR__ . '/classes/le-passkeys.php';
    new WPLE_Passkeys();
}
