<?php

/**
 * @package WP Encryption
 *
 * @author     WP Encryption
 * @copyright  Copyright (C) 2020, WP Encryption
 * @link       https://wpencryption.com
 * @since      Class available since Release 5.2.0
 *
 */

if (class_exists('WPLE_SPMode')) {
    return;
}
class WPLE_SPMode
{

    public static function checkExpiration()
    {
        $since = strtotime('2020-09-15');
        $lic = wple_fs()->_get_license();

        if (FALSE != $lic) {

            $created = strtotime($lic->created);
            $updated = strtotime($lic->updated);
            if (($created > $since || $updated > $since) && $lic->expiration != '') {
                return $lic->id;
            }
        }

        return false;
    }

    public static function initiateValidation()
    {

        WPLE_Trait::wple_logger("\nOffering Cert Panel Solution\n", 'success', 'a');

        delete_option('wple_error');
        delete_option('wple_ssl_screen');

        wp_redirect(admin_url('/admin.php?page=wp_encryption&certpanel=1'));
        exit();
    }
}
