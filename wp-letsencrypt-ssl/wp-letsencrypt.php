<?php

/**
 *
 * One Click SSL & Force HTTPS
 *
 * Plugin Name:       WP Encryption - One Click SSL & Force HTTPS
 * Plugin URI:        https://wpencryption.com
 * Description:       Secure your WordPress site with free SSL certificate and force HTTPS. Enable HTTPS padlock. Just activating this plugin won't help! - Please run the SSL install form of WP Encryption found on left panel. Enjoy the NEW Advanced security features including malware scan, vulnerability scan, file integrity monitoring, security hardening & more.
 * Version:           7.8.5.10
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
 * 
 */
/**
 * Die on direct access
 */
if ( !defined( 'ABSPATH' ) ) {
    die( 'Access Denied' );
}
/**
 * Definitions
 */
if ( !defined( 'WPLE_PLUGIN_VER' ) ) {
    define( 'WPLE_PLUGIN_VER', '7.8.5.10' );
}
if ( !defined( 'WPLE_BASE' ) ) {
    define( 'WPLE_BASE', plugin_basename( __FILE__ ) );
}
if ( !defined( 'WPLE_DIR' ) ) {
    define( 'WPLE_DIR', plugin_dir_path( __FILE__ ) );
}
if ( !defined( 'WPLE_URL' ) ) {
    define( 'WPLE_URL', plugin_dir_url( __FILE__ ) );
}
if ( !defined( 'WPLE_NAME' ) ) {
    define( 'WPLE_NAME', 'WP Encryption' );
}
if ( !defined( 'WPLE_SLUG' ) ) {
    define( 'WPLE_SLUG', 'wp_encryption' );
}
$wple_updir = wp_upload_dir();
$uploadpath = $wple_updir['basedir'] . '/';
if ( !file_exists( $uploadpath ) ) {
    $uploadpath = ABSPATH . 'wp-content/uploads/wp_encryption/';
}
if ( !defined( 'WPLE_UPLOADS' ) ) {
    define( 'WPLE_UPLOADS', $uploadpath );
}
if ( !defined( 'WPLE_DEBUGGER' ) ) {
    define( 'WPLE_DEBUGGER', WPLE_UPLOADS . 'wp_encryption/' );
}
/**
 * Freemius
 */
if ( function_exists( 'wple_fs' ) ) {
    wple_fs()->set_basename( false, __FILE__ );
} else {
    if ( !function_exists( 'wple_fs' ) ) {
        // Activate multisite network integration.
        if ( !defined( 'WP_FS__PRODUCT_5090_MULTISITE' ) ) {
            define( 'WP_FS__PRODUCT_5090_MULTISITE', true );
        }
        // Create a helper function for easy SDK access.
        function wple_fs() {
            global $wple_fs;
            ///$showpricing = (FALSE !== get_option('wple_no_pricing')) ? false : true;
            ///$showpricing = true;
            if ( !isset( $wple_fs ) ) {
                // Include Freemius SDK.
                require_once dirname( __FILE__ ) . '/freemius/start.php';
                $wple_fs = fs_dynamic_init( array(
                    'id'               => '5090',
                    'slug'             => 'wp-letsencrypt-ssl',
                    'premium_slug'     => 'wp-letsencrypt-ssl-pro',
                    'type'             => 'plugin',
                    'public_key'       => 'pk_f6a07c106bf4ef064d9ac4b989e02',
                    'is_premium'       => false,
                    'has_addons'       => true,
                    'has_paid_plans'   => true,
                    'is_org_compliant' => true,
                    'menu'             => array(
                        'slug'    => 'wp_encryption',
                        'support' => false,
                        'contact' => false,
                    ),
                    'is_live'          => true,
                ) );
            }
            return $wple_fs;
        }

        // Init Freemius.
        wple_fs();
        // Signal that SDK was initiated.
        do_action( 'wple_fs_loaded' );
    }
}
// wple_fs()->add_filter('pricing/disable_single_package', 'wple_show_single_package');
// if (!function_exists('wple_show_single_package')) {
//     function wple_show_single_package()
//     {
//         return true;
//     }
// }
wple_fs()->add_filter( 'pricing/show_annual_in_monthly', 'wple_annual_amount' );
if ( !function_exists( 'wple_annual_amount' ) ) {
    function wple_annual_amount() {
        return false;
    }

}
wple_fs()->add_filter( 'templates/pricing.php', 'wple_pricing_reactstyle' );
if ( !function_exists( 'wple_pricing_reactstyle' ) ) {
    function wple_pricing_reactstyle(  $template  ) {
        $style = "<style>\r\n            header.fs-app-header .fs-page-title {\r\n            display: none !important;\r\n            }\r\n\r\n            section.fs-plugin-title-and-logo {\r\n            margin: 0 !important;\r\n            }\r\n\r\n            section.fs-plugin-title-and-logo h1 {\r\n            font-size: 2em !important;\r\n            }\r\n\r\n            img.fs-limited-offer {\r\n            max-width: 600px;\r\n            }\r\n\r\n            li.fs-selected-billing-cycle {\r\n            background: -webkit-gradient(linear, left bottom, left top, from(#333), to(#444)) !important;\r\n            background: linear-gradient(0deg, #333, #444) !important;\r\n            color: #fff !important;\r\n            }\r\n\r\n            .fs-billing-cycles li {\r\n            padding: 7px 50px !important;\r\n            }\r\n\r\n            button.fs-button.fs-button--size-large {\r\n            background: -webkit-gradient(linear, left top, left bottom, from(#6cc703), to(#139104)) !important;\r\n            background: linear-gradient(180deg, #6cc703, #139104) !important;\r\n            border: none !important;\r\n            color: #fff !important;\r\n            padding-top: 12px !important;\r\n            padding-bottom: 12px !important;\r\n            font-weight: 400 !important;\r\n            }\r\n\r\n            h2.fs-plan-title {\r\n            padding-top: 15px !important;\r\n            padding-bottom: 15px !important;\r\n            }\r\n\r\n            span.fs-feature-title strong {\r\n            padding-right: 3px;\r\n            }\r\n\r\n            ul.fs-plan-features-with-value li {\r\n            padding: 5px 0;\r\n            background: #f6f6f6;\r\n            }\r\n\r\n            ul.fs-plan-features-with-value li:nth-of-type(even) {\r\n            background: none;\r\n            }\r\n\r\n            .fs-plan-support strong {\r\n            font-weight: 500 !important;\r\n            color: #666;\r\n            }\r\n\r\n            section.fs-section.fs-section--plans-and-pricing:before {\r\n            content: '';\r\n            display: block;\r\n            background: url(https://gowebsmarty.com/limited-offer.png) no-repeat top center;\r\n            height: 120px;\r\n            background-size: 600px auto;\r\n            }\r\n\r\n            #fs_pricing_app .fs-package .fs-plan-features {\r\n            margin: 20px 25px 0 !important;\r\n            }\r\n\r\n            button.fs-button.fs-button--size-large:hover {\r\n            background: -webkit-gradient(linear, left top, left bottom, from(#6cc703), to(#148706)) !important;\r\n            background: linear-gradient(180deg, #6cc703, #148706) !important;\r\n            }\r\n\r\n            /** 10-10-2025 **/\r\n            .fs-featured-plan h2.fs-plan-title {\r\n            background-image: -webkit-gradient(linear, left top, left bottom, from(#6bc405), to(#18ac07)) !important;\r\n            background-image: linear-gradient(180deg, #6bc405, #18ac07) !important;\r\n            background-color: #6bc405 !important;\r\n            border-color: #6bc405 !important;\r\n            }\r\n\r\n            #fs_pricing_app .fs-section--packages .fs-packages-nav {\r\n            overflow: visible;\r\n            margin-top: 40px;\r\n            }\r\n\r\n            .fs-most-popular {\r\n                position: absolute;\r\n                width: 100%;\r\n                top: 40px;\r\n                overflow: hidden;\r\n                height: 80px;\r\n                background: none !important;\r\n            }\r\n            #fs_pricing_app .fs-package.fs-featured-plan .fs-most-popular h4{\r\n            position: absolute;\r\n            border-radius: 0;\r\n            line-height: 1.4em;\r\n            margin-top: 0;\r\n            right: -25px;\r\n            -webkit-transform: rotate(40deg);\r\n                    transform: rotate(40deg);\r\n            top: 18px;\r\n            background: #dd3c26;\r\n            letter-spacing: 0.5px;\r\n            }\r\n\r\n            #fs_pricing_app .fs-package.fs-featured-plan .fs-most-popular h4 {\r\n            color: #fff;\r\n            -webkit-box-shadow: 0px 0px 1px rgba(0, 0, 0, 0.5);\r\n                    box-shadow: 0px 0px 1px rgba(0, 0, 0, 0.5);\r\n            }\r\n\r\n            #fs_pricing_app .fs-package.fs-featured-plan {\r\n            position: relative;\r\n            }\r\n\r\n            #fs_pricing_app .fs-package.fs-featured-plan .fs-most-popular h4:before {\r\n            z-index: 1;\r\n            background: url(//wimg.freemius.com/website/pages/pricing/sprite.png);\r\n            width: 39px;\r\n            height: 52px;\r\n            display: block;\r\n            position: absolute;\r\n            top: 0;\r\n            }\r\n\r\n            #fs_pricing_app .fs-package.fs-featured-plan .fs-most-popular h4 strong {\r\n            font-weight: 400;\r\n            font-size: 9px;\r\n            padding: 0 20px;\r\n            }\r\n\r\n            #fs_pricing_app .fs-package .fs-plan-title {\r\n            padding: 25px 0 !important;\r\n            }\r\n\r\n            select.fs-currencies {\r\n            border-color: #aaa !important;\r\n            width: 100px;\r\n            }\r\n\r\n            .fs-package-content strong.fs-currency-symbol {\r\n            font-size: 28px !important;\r\n            color: #888;\r\n            font-weight: 400 !important;\r\n            }\r\n\r\n            #fs_pricing_app .fs-package .fs-selected-pricing-amount .fs-selected-pricing-amount-integer {\r\n            color: #666666;\r\n            }\r\n\r\n            #fs_pricing_app .fs-package .fs-selected-pricing-amount .fs-selected-pricing-amount-integer strong {\r\n            font-weight: 500;\r\n            }\r\n\r\n            #fs_pricing_app .fs-featured-plan .fs-selected-pricing-amount .fs-selected-pricing-amount-integer {\r\n            font-size: 68px;\r\n            color: #6bc406;\r\n            }\r\n\r\n            .fs-featured-plan strong.fs-selected-pricing-amount-fraction {\r\n            color: #63b507;\r\n            }\r\n\r\n            #fs_pricing_app .fs-package .fs-selected-pricing-cycle {\r\n            color: #666666;\r\n            }\r\n\r\n            #fs_pricing_app .fs-package.fs-featured-plan .fs-selected-pricing-license-quantity {\r\n            text-transform: uppercase;\r\n            margin-top: 10px;\r\n            }\r\n\r\n            #fs_pricing_app .fs-package.fs-featured-plan .fs-license-quantity-discount span {\r\n            background: #6bc406;\r\n            border: none;\r\n            font-weight: 400;\r\n            }\r\n\r\n            #fs_pricing_app .fs-package.fs-featured-plan .fs-license-quantities .fs-license-quantity-selected {\r\n            background: #333333;\r\n            border-color: #333 !important;\r\n            }\r\n\r\n            #fs_pricing_app .fs-package .fs-upgrade-button-container .fs-upgrade-button {\r\n            /* margin-top: 0; */\r\n            /* border-radius: 0; */\r\n            padding: 20px 0 !important;\r\n            }\r\n\r\n            .fs-free-plan .fs-selected-pricing-amount {\r\n            margin-top: 20px !important;\r\n            }\r\n\r\n            .fs-free-plan button.fs-button.fs-button--size-large.fs-upgrade-button.fs-button--outline {\r\n            background: #aaaaaa !important;\r\n            }\r\n\r\n            ul.fs-plan-features li .fs-feature-title {\r\n            color: #555 !important;\r\n            }\r\n\r\n            section.fs-section.fs-section--custom-implementation {\r\n            display: none !important;\r\n            }\r\n\r\n            div#fs_pricing_app {\r\n            background: #f0f0f1 !important;\r\n            }\r\n\r\n            section.fs-section.fs-section--money-back-guarantee:before {\r\n            background-image: linear-gradient(-135deg, #f1f1f1 7.5px, transparent 0), linear-gradient(135deg, #f1f1f1 7.5px, transparent 0);\r\n            content: '';\r\n            display: block;\r\n            position: absolute;\r\n            left: 0px;\r\n            width: 100%;\r\n            height: 15px;\r\n            background-repeat: repeat-x;\r\n            background-size: 15px 15px;\r\n            background-position: left top;\r\n            }\r\n\r\n            section.fs-section.fs-section--money-back-guarantee {\r\n            background: #fff;\r\n            padding-bottom: 30px;\r\n            }\r\n\r\n            section.fs-section.fs-section--money-back-guarantee h2 {\r\n            padding-top: 30px !important;\r\n            }\r\n\r\n            section.fs-section.fs-section--testimonials h2 {\r\n            font-weight: 400;\r\n            color: #0073aa !important;\r\n            }\r\n        </style>";
        return $style . $template;
    }

}
require_once WPLE_DIR . 'classes/le-trait.php';
/**
 * Plugin Activator hook
 */
register_activation_hook( __FILE__, 'wple_activate' );
if ( !function_exists( 'wple_activate' ) ) {
    function wple_activate(  $networkwide  ) {
        require_once WPLE_DIR . 'classes/le-activator.php';
        WPLE_Activator::activate( $networkwide );
    }

}
/**
 * Plugin Deactivator hook
 */
register_deactivation_hook( __FILE__, 'wple_deactivate' );
if ( !function_exists( 'wple_deactivate' ) ) {
    function wple_deactivate() {
        require_once WPLE_DIR . 'classes/le-deactivator.php';
        WPLE_Deactivator::deactivate();
    }

}
/**
 * Class to handle all aspects of plugin page
 */
require_once WPLE_DIR . 'admin/le_admin.php';
new WPLE_Admin();
/**
 * Admin Pages
 * @since 5.0.0
 */
require_once WPLE_DIR . 'admin/le_admin_pages.php';
new WPLE_SubAdmin();
/**
 * Force SSL on frontend
 */
require_once WPLE_DIR . 'classes/le-forcessl.php';
new WPLE_ForceSSL();
/**
 * Scannr
 * 
 * @since 5.1.8
 */
require_once WPLE_DIR . 'classes/le-scanner.php';
new WPLE_Scanner();
if ( function_exists( 'wple_fs' ) && !function_exists( 'wple_fs_custom_connect_message' ) ) {
    function wple_fs_custom_connect_message(  $message  ) {
        $current_user = wp_get_current_user();
        return 'Howdy ' . ucfirst( $current_user->user_nicename ) . ', <br>' . __( 'Due to security nature of this plugin, We <b>HIGHLY</b> recommend you opt-in to our security & feature updates notifications, and <a href="https://freemius.com/wordpress/usage-tracking/5090/wp-letsencrypt-ssl/" target="_blank">non-sensitive diagnostic tracking</a> to get BEST support. If you skip this, that\'s okay! <b>WP Encryption</b> will still work just fine.', 'wp-letsencrypt-ssl' );
    }

    wple_fs()->add_filter( 'connect_message', 'wple_fs_custom_connect_message' );
}
/**
 * Support forum URL for Premium
 * 
 * @since 5.3.2
 */
if ( wple_fs()->is_premium() && !function_exists( 'wple_premium_forum' ) ) {
    function wple_premium_forum(  $wp_org_support_forum_url  ) {
        return 'https://support.wpencryption.com/';
    }

    wple_fs()->add_filter( 'support_forum_url', 'wple_premium_forum' );
}
/**
 * Dont show cancel subscription popup
 * 
 * @since 5.3.2
 */
wple_fs()->add_filter( 'show_deactivation_subscription_cancellation', '__return_false' );
/**
 * Security Init
 * 
 * @since 7.0.0
 */
require_once plugin_dir_path( __FILE__ ) . 'classes/le-security.php';
new WPLE_Security();