<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
require_once dirname( __DIR__ ) . '/classes/credential-repository.php';
if ( class_exists( 'WPLE_Passkeys' ) ) {
    return;
}
class WPLE_Passkeys {
    public function __construct() {
        add_action( 'wp_ajax_wple_enable_passkey', [__CLASS__, 'wple_ajax_enable_passkey'] );
        add_action( 'wp_ajax_wple_remove_passkey', [__CLASS__, 'wple_ajax_remove_passkey'] );
        add_action( 'show_user_profile', [__CLASS__, 'render_profile_section'] );
        add_action( 'edit_user_profile', [__CLASS__, 'render_profile_section'] );
        add_action( 'wp_ajax_wple_get_register_options', [__CLASS__, 'ajax_get_register_options'] );
        add_action( 'wp_ajax_wple_register_passkey', [__CLASS__, 'ajax_register_passkey'] );
        add_action( 'wp_ajax_wple_passkey_login_options', [__CLASS__, 'wple_ajax_login_options'] );
        add_action( 'wp_ajax_nopriv_wple_passkey_login_options', [__CLASS__, 'wple_ajax_login_options'] );
        add_action( 'wp_ajax_wple_passkey_login_verify', [__CLASS__, 'wple_ajax_login_verify'] );
        add_action( 'wp_ajax_nopriv_wple_passkey_login_verify', [__CLASS__, 'wple_ajax_login_verify'] );
    }

    public static function wple_ajax_remove_passkey() {
        // Remove a passkey
        // Use check_ajax_referer to validate nonce and avoid undefined index notices.
        check_ajax_referer( 'wple-rempasskey', '_wpnonce' );
        $cred_id = ( isset( $_GET['cred_id'] ) ? intval( $_GET['cred_id'] ) : 0 );
        if ( !$cred_id ) {
            wp_die( 'ID is not provided!' );
            exit;
        }
        $uid = get_current_user_id();
        if ( !$uid ) {
            wp_die( 'Not authenticated!' );
            exit;
        }
        $credRepo = new Credential_Repository();
        $result = $credRepo->findOneByID( $cred_id );
        // Check if credential exists and belongs to the user (optional, can be enforced in delete method)
        if ( !$result ) {
            wp_die( 'Credential not found!' );
            exit;
        }
        if ( intval( $result['user_id'] ) !== intval( $uid ) ) {
            wp_die( 'You do not have permission to delete this credential!' );
            exit;
        }
        $go = $credRepo->deleteCredential( $cred_id );
        if ( $go ) {
            wp_redirect( admin_url( 'profile.php?removed=1#wple-passkeys' ) );
        } else {
            wp_die( 'Failed to delete credential!' );
        }
        exit;
    }

    public static function wple_ajax_enable_passkey() {
        // Enable / disable passkey feature
        // Use check_ajax_referer to validate nonce and avoid undefined index notices.
        check_ajax_referer( 'wple-adminjs', 'nc' );
        // Only allow users with manage_options capability (admins).
        if ( !current_user_can( 'manage_options' ) ) {
            exit( 'failed' );
        }
        // Sanitize / validate the enabled parameter; accept boolean-like values.
        $enabled = false;
        if ( isset( $_POST['enabled'] ) ) {
            $enabled = filter_var( $_POST['enabled'], FILTER_VALIDATE_BOOLEAN );
        }
        update_option( 'wple_passkeys', ( $enabled ? true : false ) );
        exit( 'success' );
    }

    public static function render_passkeys_table() {
        $uid = get_current_user_id();
        if ( !$uid ) {
            return true;
        }
        $credRepo = new Credential_Repository();
        $credentials = $credRepo->findAllByUserHandle( base64_encode( intval( $uid ) ) );
        ?>

        <table class="wp-list-table widefat striped table-view-list wple-passkeys" width="100%">
            <thead>
                <tr>
                    <th><?php 
        esc_html_e( 'Name', 'wp-letsencrypt-ssl' );
        ?></th>
                    <th><?php 
        esc_html_e( 'Created At', 'wp-letsencrypt-ssl' );
        ?></th>
                    <th><?php 
        esc_html_e( 'Last Used', 'wp-letsencrypt-ssl' );
        ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php 
        if ( empty( $credentials ) ) {
            echo '<tr><td colspan="4">' . esc_html__( 'No passkeys created yet.', 'wp-letsencrypt-ssl' ) . '</td></tr>';
        } else {
            foreach ( $credentials as $cred ) {
                ?>
                        <tr>
                            <td><?php 
                echo esc_html( $cred['device_name'] );
                ?></td>
                            <td><?php 
                echo esc_html( $cred['created_at'] );
                ?></td>
                            <td><?php 
                echo esc_html( $cred['last_used'] );
                ?></td>
                            <td>
                                <button class="button wple-remove-passkey"><a href="<?php 
                echo wp_nonce_url( admin_url( 'admin-ajax.php?action=wple_remove_passkey&cred_id=' . esc_attr( $cred['id'] ) ), 'wple-rempasskey' );
                ?>"><?php 
                esc_html_e( 'Remove', 'wp-letsencrypt-ssl' );
                ?></a></button>
                            </td>
                        </tr>
                <?php 
            }
        }
        ?>
            </tbody>
        </table>

    <?php 
    }

    public static function render_profile_section( $user ) {
        if ( !get_option( 'wple_passkeys' ) ) {
            return;
        }
        ?>
        <h2 id="wple-passkeys"><?php 
        esc_html_e( 'WP Encryption - Passkeys', 'wp-letsencrypt-ssl' );
        ?></h2>
        <?php 
        SELF::render_passkeys_table();
        ?>
        <table class="form-table">
            <tr>
                <td>
                    <div id="wple-passkey-area">
                        <button id="wple-register-passkey" class="button"><?php 
        esc_html_e( 'Register New Passkey', 'wp-letsencrypt-ssl' );
        ?></button>
                        <span id="wple-passkey-msg" style="margin-left:12px;line-height:30px;">
                            <?php 
        if ( isset( $_GET['removed'] ) ) {
            ?>
                                <i style="color:red;"><?php 
            esc_html_e( 'Passkey removed successfully!', 'wp-letsencrypt-ssl' );
            ?></i>
                            <?php 
        }
        ?>
                        </span>
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    <small>Passkeys are not a two-factor authentication (2FA) method. They are a passwordless multi-factor authentication (MFA) solution that allows users to securely log in without using a password - No passwords. No brute force. Just secure, frictionless login with passkey-based WebAuthn - powered by your browser and protected by fingerprint, Face ID, or device PIN.</small>
                </td>
            </tr>
        </table>
<?php 
    }

    public static function ajax_get_register_options() {
        if ( !wp_verify_nonce( $_REQUEST['nonce'] ?? '', 'wple-adminjs' ) ) {
            wp_send_json_error( 'Invalid nonce', 403 );
        }
        $uid = get_current_user_id();
        if ( !$uid ) {
            wp_send_json_error( 'Not authenticated', 403 );
        }
        $ttl = 300;
        // seconds
        $challenge = base64_encode( random_bytes( 32 ) );
        // Save challenge with expiry in user meta
        $meta = array(
            'challenge' => $challenge,
            'expires'   => time() + $ttl,
        );
        update_user_meta( $uid, 'wple_registration_challenge', $meta );
        $user = get_userdata( $uid );
        // Build options object. Client will convert base64 -> Uint8Array
        $options = array(
            'challenge'        => $challenge,
            'rp'               => array(
                'name' => get_bloginfo( 'name' ),
                'id'   => wp_parse_url( home_url(), PHP_URL_HOST ),
            ),
            'user'             => array(
                'id'          => base64_encode( intval( $uid ) ),
                'name'        => $user->user_login,
                'displayName' => ( $user->display_name ?: $user->user_login ),
            ),
            'pubKeyCredParams' => array(
                array(
                    'type' => 'public-key',
                    'alg'  => -7,
                ),
                // ES256
                array(
                    'type' => 'public-key',
                    'alg'  => -257,
                ),
            ),
            'timeout'          => 60000,
            'attestation'      => 'none',
        );
        // Exclude existing credentials
        $credRepo = new Credential_Repository();
        $existing = $credRepo->findAllByUserHandle( base64_encode( (string) $uid ) );
        if ( !empty( $existing ) ) {
            //free limit
            wp_send_json_error( 'You have reached the free limit for passkeys. Please upgrade for unlimited passkey registrations or delete existing ones.', 403 );
            $exclude = array();
            foreach ( $existing as $cred ) {
                $exclude[] = array(
                    'type' => 'public-key',
                    'id'   => $cred['credential_id'],
                );
            }
            $options['excludeCredentials'] = $exclude;
        }
        wp_send_json_success( $options );
    }

    public static function ajax_register_passkey() {
        if ( !wp_verify_nonce( $_POST['nonce'] ?? '', 'wple-adminjs' ) ) {
            wp_send_json_error( 'Invalid nonce', 403 );
        }
        $uid = get_current_user_id();
        if ( !$uid ) {
            wp_send_json_error( 'Not authenticated', 403 );
        }
        $challenge_sent = sanitize_text_field( $_POST['challenge'] ?? '' );
        // $id = sanitize_text_field($_POST['id'] ?? '');
        // $rawId = sanitize_text_field($_POST['rawId'] ?? '');
        // $type = sanitize_text_field($_POST['type'] ?? '');
        $device_name = sanitize_text_field( $_POST['deviceName'] ?? '' );
        $clientDataJSON_b64 = sanitize_text_field( $_POST['clientDataJSON'] ?? '' );
        $attestationObject_b64 = sanitize_text_field( $_POST['attestationObject'] ?? '' );
        if ( empty( $clientDataJSON_b64 ) || empty( $attestationObject_b64 ) ) {
            wp_send_json_error( 'Missing fields', 400 );
        }
        $meta = get_user_meta( $uid, 'wple_registration_challenge', true );
        if ( !is_array( $meta ) || empty( $meta['challenge'] ) || empty( $meta['expires'] ) || time() > intval( $meta['expires'] ) ) {
            wp_send_json_error( 'Challenge expired or missing', 400 );
        }
        if ( hash_equals( $meta['challenge'], $challenge_sent ) === false ) {
            wp_send_json_error( 'Challenge mismatch', 400 );
        }
        // Basic clientDataJSON checks (type + origin + challenge)
        $clientDataJson = base64_decode( $clientDataJSON_b64 );
        $clientData = json_decode( $clientDataJson, true );
        if ( !is_array( $clientData ) ) {
            wp_send_json_error( 'Invalid clientDataJSON', 400 );
        }
        if ( ($clientData['type'] ?? '') !== 'webauthn.create' ) {
            wp_send_json_error( 'Invalid clientData type', 400 );
        }
        $expected_origin = rtrim( home_url(), '/' );
        $origin = rtrim( $clientData['origin'] ?? '', '/' );
        if ( $origin !== $expected_origin && $origin !== rtrim( admin_url(), '/' ) ) {
            wp_send_json_error( 'Invalid origin', 400 );
        }
        //attestation checks
        $att_raw = SELF::wple_b64url_decode( $attestationObject_b64 );
        if ( !$att_raw ) {
            wp_send_json_error( array(
                'message' => 'Invalid attestation object encoding.',
            ) );
        }
        $offset = 0;
        $att_map = SELF::wple_cbor_decode( $att_raw, $offset );
        if ( !is_array( $att_map ) || !isset( $att_map['authData'] ) ) {
            wp_send_json_error( array(
                'message' => 'Invalid attestation object structure.',
            ) );
        }
        $auth = SELF::wple_parse_auth_data( $att_map['authData'] );
        if ( !$auth ) {
            wp_send_json_error( array(
                'message' => 'Could not parse authenticator data.',
            ) );
        }
        if ( !SELF::wple_verify_rp_id_hash( $auth['rpIdHash'] ) ) {
            wp_send_json_error( array(
                'message' => 'RP ID mismatch.',
            ) );
        }
        if ( !$auth['userPresent'] ) {
            wp_send_json_error( array(
                'message' => 'User presence flag not set.',
            ) );
        }
        if ( !$auth['credentialId'] || !$auth['publicKeyCbor'] ) {
            wp_send_json_error( array(
                'message' => 'No credential data in authenticator response.',
            ) );
        }
        $pem = SELF::wple_cose_to_pem( $auth['publicKeyCbor'] );
        if ( !$pem ) {
            wp_send_json_error( array(
                'message' => 'Unsupported public key algorithm.',
            ) );
        }
        $cred_id = SELF::wple_b64url_encode( $auth['credentialId'] );
        $credRepo = new Credential_Repository();
        $saved = $credRepo->save_credential(
            $uid,
            $cred_id,
            $auth['publicKeyCbor'],
            (int) $auth['signCount'],
            $device_name
        );
        if ( !$saved ) {
            wp_send_json_error( array(
                'message' => 'Could not save credential (may already be registered).',
            ) );
        }
        wp_send_json_success( array(
            'message'      => 'Passkey registered!',
            'credentialId' => $cred_id,
        ) );
        exit;
    }

    // For anonymous users (login): key by a random token passed back by the client.
    static function wple_challenge_create_token( $type, $token ) {
        $raw = random_bytes( 32 );
        set_transient( 'wple_' . $type . '_tok_' . $token, SELF::wple_b64url_encode( $raw ), 9000 );
        return SELF::wple_b64url_encode( $raw );
    }

    static function wple_challenge_get_token( $type, $token ) {
        $key = 'wple_' . $type . '_tok_' . sanitize_key( $token );
        $raw = get_transient( $key );
        //b64 url encoded
        delete_transient( $key );
        return ( $raw ? $raw : null );
    }

    static function wple_get_rp_id() {
        return wp_parse_url( home_url(), PHP_URL_HOST );
    }

    static function wple_ajax_login_options() {
        check_ajax_referer( 'wple-loginjs', 'nonce' );
        $user_login = ( isset( $_POST['user_login'] ) ? sanitize_text_field( $_POST['user_login'] ) : '' );
        $allow_creds = array();
        $lookup_user_id = 0;
        if ( $user_login ) {
            $u = get_user_by( 'login', $user_login );
            if ( !$u ) {
                $u = get_user_by( 'email', $user_login );
            }
            if ( $u ) {
                $lookup_user_id = (int) $u->ID;
                $credDB = new Credential_Repository();
                foreach ( $credDB->get_user_credentials( $lookup_user_id ) as $c ) {
                    $allow_creds[] = array(
                        'type' => 'public-key',
                        'id'   => $c['credential_id'],
                    );
                }
            }
        }
        // Use a transient keyed to a random token stored client-side as well,
        // so anonymous users can also get a per-request challenge.
        $token = wp_generate_password( 32, false );
        $challenge = SELF::wple_challenge_create_token( 'login', $token );
        $options = array(
            'rpId'             => SELF::wple_get_rp_id(),
            'challenge'        => $challenge,
            'challengeToken'   => $token,
            'timeout'          => 60000,
            'userVerification' => 'preferred',
            'allowCredentials' => $allow_creds,
            'ch'               => 'wple_login_tok_' . $token,
        );
        wp_send_json_success( $options );
    }

    static function wple_get_json_body() {
        $raw = file_get_contents( 'php://input' );
        if ( $raw ) {
            $decoded = json_decode( $raw, true );
            if ( is_array( $decoded ) ) {
                return $decoded;
            }
        }
        return ( is_array( $_POST ) ? $_POST : array() );
    }

    static function wple_ajax_login_verify() {
        check_ajax_referer( 'wple-loginjs', 'nonce' );
        $body = SELF::wple_get_json_body();
        $cred_id = ( isset( $body['credentialId'] ) ? sanitize_text_field( trim( $body['credentialId'] ) ) : '' );
        $cdj = ( isset( $body['clientDataJSON'] ) ? sanitize_text_field( trim( $body['clientDataJSON'] ) ) : '' );
        $auth_data = ( isset( $body['authenticatorData'] ) ? sanitize_text_field( trim( $body['authenticatorData'] ) ) : '' );
        $sig = ( isset( $body['signature'] ) ? sanitize_text_field( trim( $body['signature'] ) ) : '' );
        $token = ( isset( $body['challengeToken'] ) ? sanitize_text_field( trim( $body['challengeToken'] ) ) : '' );
        if ( !$cred_id || !$cdj || !$auth_data || !$sig || !$token ) {
            wp_send_json_error( array(
                'message' => 'Missing assertion fields.',
            ) );
        }
        $credDB = new Credential_Repository();
        $stored = $credDB->findOneByCredentialId( $cred_id );
        if ( !$stored ) {
            wp_send_json_error( array(
                'message' => 'Unknown credential.',
            ) );
        }
        $raw_challenge = SELF::wple_challenge_get_token( 'login', $token );
        if ( !$raw_challenge ) {
            wp_send_json_error( array(
                'message' => 'Challenge expired. Please try again.',
            ) );
        }
        if ( !SELF::wple_verify_client_data( $cdj, 'webauthn.get', $raw_challenge ) ) {
            wp_send_json_error( array(
                'message' => 'Client data verification failed.',
            ) );
        }
        $raw_auth = SELF::wple_b64url_decode( $auth_data );
        $parsed = SELF::wple_parse_auth_data( $raw_auth );
        if ( !$parsed ) {
            wp_send_json_error( array(
                'message' => 'Could not parse authenticator data.',
            ) );
        }
        if ( !SELF::wple_verify_rp_id_hash( $parsed['rpIdHash'] ) ) {
            wp_send_json_error( array(
                'message' => 'RP ID mismatch.',
            ) );
        }
        if ( !$parsed['userPresent'] ) {
            wp_send_json_error( array(
                'message' => 'User presence flag not set.',
            ) );
        }
        // signed_data = authData || SHA-256(clientDataJSON_bytes)
        $cdj_hash = hash( 'sha256', SELF::wple_b64url_decode( $cdj ), true );
        $signed_data = $raw_auth . $cdj_hash;
        $pub_cbor = base64_decode( $stored['public_key'] );
        $pem = SELF::wple_cose_to_pem( $pub_cbor );
        if ( !$pem ) {
            wp_send_json_error( array(
                'message' => 'Stored public key is invalid.',
            ) );
        }
        $pub_key_res = openssl_get_publickey( $pem );
        if ( !$pub_key_res ) {
            wp_send_json_error( array(
                'message' => 'Could not load public key.',
            ) );
        }
        $signature = SELF::wple_b64url_decode( $sig );
        $verified = openssl_verify(
            $signed_data,
            $signature,
            $pub_key_res,
            OPENSSL_ALGO_SHA256
        );
        if ( $verified !== 1 ) {
            wp_send_json_error( array(
                'message' => 'Signature verification failed.',
            ) );
        }
        $new_count = (int) $parsed['signCount'];
        if ( $new_count !== 0 && $new_count <= (int) $stored['sign_count'] ) {
            wp_send_json_error( array(
                'message' => 'Possible replay attack.',
            ) );
        }
        $credDB->wple_db_update_sign_count( $cred_id, $new_count );
        $user_id = (int) $stored['user_id'];
        $user = get_user_by( 'id', $user_id );
        if ( !$user ) {
            wp_send_json_error( array(
                'message' => 'Associated user not found.',
            ) );
        }
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id, true );
        do_action( 'wp_login', $user->user_login, $user );
        $redirect = apply_filters( 'wple_login_redirect', admin_url(), $user );
        wp_send_json_success( array(
            'message'  => 'Logged in!',
            'redirect' => $redirect,
        ) );
    }

    /**
     * Convert base64url (no padding) to standard base64 with padding.
     */
    // function wple_base64url_to_base64($input)
    // {
    //     $b64 = strtr($input, '-_', '+/');
    //     $pad = strlen($b64) % 4;
    //     if ($pad) {
    //         $b64 .= str_repeat('=', 4 - $pad);
    //     }
    //     return $b64;
    // }
    // ============================================================
    // Base64URL helpers
    // ============================================================
    static function wple_b64url_encode( $data ) {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    static function wple_b64url_decode( $data ) {
        if ( !$data ) {
            return false;
        }
        $rem = strlen( $data ) % 4;
        $pad = ( $rem ? str_repeat( '=', 4 - $rem ) : '' );
        return base64_decode( strtr( $data, '-_', '+/' ) . $pad );
    }

    // ============================================================
    // WebAuthn verification helpers
    // ============================================================
    static function wple_verify_client_data( $cdj_b64url, $expected_type, $raw_challenge ) {
        $decoded = SELF::wple_b64url_decode( $cdj_b64url );
        if ( !$decoded ) {
            return false;
        }
        $json = json_decode( $decoded, true );
        if ( !is_array( $json ) ) {
            return false;
        }
        if ( (( isset( $json['type'] ) ? $json['type'] : '' )) !== $expected_type ) {
            return false;
        }
        $received = ( isset( $json['challenge'] ) ? $json['challenge'] : '' );
        if ( !$received || !hash_equals( $raw_challenge, $received ) ) {
            return false;
        }
        $expected_origin = rtrim( home_url(), '/' );
        $received_origin = rtrim( ( isset( $json['origin'] ) ? $json['origin'] : '' ), '/' );
        if ( $received_origin !== $expected_origin ) {
            return false;
        }
        return true;
    }

    static function wple_parse_auth_data( $auth_data ) {
        if ( strlen( $auth_data ) < 37 ) {
            return null;
        }
        $offset = 0;
        $rp_id_hash = substr( $auth_data, 0, 32 );
        $offset = 32;
        $flags_byte = ord( $auth_data[32] );
        $offset = 33;
        $sign_count_raw = substr( $auth_data, 33, 4 );
        if ( strlen( $sign_count_raw ) < 4 ) {
            return null;
        }
        $sign_count = unpack( 'N', $sign_count_raw );
        $sign_count = $sign_count[1];
        $offset = 37;
        $result = array(
            'rpIdHash'         => $rp_id_hash,
            'userPresent'      => (bool) ($flags_byte & 0x1),
            'userVerified'     => (bool) ($flags_byte & 0x4),
            'attestedCredData' => (bool) ($flags_byte & 0x40),
            'signCount'        => $sign_count,
            'credentialId'     => null,
            'publicKeyCbor'    => null,
        );
        if ( $result['attestedCredData'] ) {
            if ( strlen( $auth_data ) < $offset + 18 ) {
                return $result;
            }
            $offset += 16;
            // AAGUID
            $len_raw = substr( $auth_data, $offset, 2 );
            if ( strlen( $len_raw ) < 2 ) {
                return $result;
            }
            $cred_id_len = unpack( 'n', $len_raw );
            $cred_id_len = $cred_id_len[1];
            $offset += 2;
            if ( strlen( $auth_data ) < $offset + $cred_id_len ) {
                return $result;
            }
            $result['credentialId'] = substr( $auth_data, $offset, $cred_id_len );
            $offset += $cred_id_len;
            $result['publicKeyCbor'] = substr( $auth_data, $offset );
        }
        return $result;
    }

    static function wple_verify_rp_id_hash( $rp_id_hash ) {
        if ( !is_string( $rp_id_hash ) || strlen( $rp_id_hash ) !== 32 ) {
            return false;
        }
        $expected = hash( 'sha256', wp_parse_url( home_url(), PHP_URL_HOST ), true );
        return hash_equals( $expected, $rp_id_hash );
    }

    // ============================================================
    // CBOR decoder  (recursive; handles all types needed by WebAuthn)
    // ============================================================
    static function wple_cbor_decode( $data, &$offset ) {
        $len = strlen( $data );
        if ( $offset >= $len ) {
            return null;
        }
        $byte = ord( $data[$offset] );
        $major = $byte >> 5 & 0x7;
        $info = $byte & 0x1f;
        $offset++;
        // Decode argument (length or value)
        if ( $info < 24 ) {
            $arg = $info;
        } elseif ( $info === 24 ) {
            if ( $offset >= $len ) {
                return null;
            }
            $arg = ord( $data[$offset] );
            $offset++;
        } elseif ( $info === 25 ) {
            if ( $offset + 2 > $len ) {
                return null;
            }
            $tmp = unpack( 'n', substr( $data, $offset, 2 ) );
            $arg = $tmp[1];
            $offset += 2;
        } elseif ( $info === 26 ) {
            if ( $offset + 4 > $len ) {
                return null;
            }
            $tmp = unpack( 'N', substr( $data, $offset, 4 ) );
            $arg = $tmp[1];
            $offset += 4;
        } elseif ( $info === 27 ) {
            if ( $offset + 8 > $len ) {
                return null;
            }
            $hi_arr = unpack( 'N', substr( $data, $offset, 4 ) );
            $lo_arr = unpack( 'N', substr( $data, $offset + 4, 4 ) );
            $arg = $hi_arr[1] * 4294967296.0 + $lo_arr[1];
            $offset += 8;
        } else {
            return null;
            // indefinite/reserved
        }
        switch ( $major ) {
            case 0:
                // unsigned int
                return (int) $arg;
            case 1:
                // negative int
                return -1 - (int) $arg;
            case 2:
                // byte string
                if ( $offset + $arg > $len ) {
                    return null;
                }
                $bytes = substr( $data, $offset, $arg );
                $offset += $arg;
                return $bytes;
            case 3:
                // text string (UTF-8)
                if ( $offset + $arg > $len ) {
                    return null;
                }
                $str = substr( $data, $offset, $arg );
                $offset += $arg;
                return $str;
            case 4:
                // array
                $arr = array();
                for ($i = 0; $i < $arg; $i++) {
                    $arr[] = SELF::wple_cbor_decode( $data, $offset );
                }
                return $arr;
            case 5:
                // map
                $map = array();
                for ($i = 0; $i < $arg; $i++) {
                    $key = SELF::wple_cbor_decode( $data, $offset );
                    $val = SELF::wple_cbor_decode( $data, $offset );
                    if ( $key !== null ) {
                        $map[$key] = $val;
                    }
                }
                return $map;
            case 6:
                // semantic tag – decode wrapped value
                return SELF::wple_cbor_decode( $data, $offset );
            default:
                return null;
        }
    }

    // ============================================================
    // COSE public key → PEM
    // ============================================================
    static function wple_cose_to_pem( $cbor ) {
        if ( !$cbor ) {
            return null;
        }
        $offset = 0;
        $map = SELF::wple_cbor_decode( $cbor, $offset );
        if ( !is_array( $map ) ) {
            return null;
        }
        $kty = ( isset( $map[1] ) ? $map[1] : null );
        if ( $kty === 2 ) {
            // EC P-256  (alg -7 = ES256)
            $crv = ( isset( $map[-1] ) ? $map[-1] : null );
            $x = ( isset( $map[-2] ) ? $map[-2] : null );
            $y = ( isset( $map[-3] ) ? $map[-3] : null );
            if ( $crv !== 1 || !is_string( $x ) || !is_string( $y ) ) {
                return null;
            }
            return SELF::wple_ec_pem( $x, $y );
        }
        if ( $kty === 3 ) {
            // RSA  (alg -257 = RS256)
            $n = ( isset( $map[-1] ) ? $map[-1] : null );
            $e = ( isset( $map[-2] ) ? $map[-2] : null );
            if ( !is_string( $n ) || !is_string( $e ) ) {
                return null;
            }
            return SELF::wple_rsa_pem( $n, $e );
        }
        return null;
    }

    static function wple_der_len( $len ) {
        if ( $len < 128 ) {
            return chr( $len );
        }
        $bytes = '';
        $tmp = $len;
        while ( $tmp > 0 ) {
            $bytes = chr( $tmp & 0xff ) . $bytes;
            $tmp >>= 8;
        }
        return chr( 0x80 | strlen( $bytes ) ) . $bytes;
    }

    static function wple_ec_pem( $x, $y ) {
        $oid_ec = "*\x86H\xce=\x02\x01";
        $oid_p256 = "*\x86H\xce=\x03\x01\x07";
        $alg = "0" . SELF::wple_der_len( 2 + strlen( $oid_ec ) + 2 + strlen( $oid_p256 ) ) . "\x06" . SELF::wple_der_len( strlen( $oid_ec ) ) . $oid_ec . "\x06" . SELF::wple_der_len( strlen( $oid_p256 ) ) . $oid_p256;
        $pt = "\x04" . $x . $y;
        $bit = "\x03" . SELF::wple_der_len( strlen( $pt ) + 1 ) . "\x00" . $pt;
        $spki = "0" . SELF::wple_der_len( strlen( $alg ) + strlen( $bit ) ) . $alg . $bit;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $spki ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
    }

    static function wple_rsa_pem( $n, $e ) {
        if ( ord( $n[0] ) & 0x80 ) {
            $n = "\x00" . $n;
        }
        if ( ord( $e[0] ) & 0x80 ) {
            $e = "\x00" . $e;
        }
        $rsa = "0" . SELF::wple_der_len( 2 + strlen( $n ) + 2 + strlen( $e ) ) . "\x02" . SELF::wple_der_len( strlen( $n ) ) . $n . "\x02" . SELF::wple_der_len( strlen( $e ) ) . $e;
        $oid = "*\x86H\x86\xf7\r\x01\x01\x01";
        $alg = "0" . SELF::wple_der_len( 2 + strlen( $oid ) + 2 ) . "\x06" . SELF::wple_der_len( strlen( $oid ) ) . $oid . "\x05\x00";
        $bit = "\x03" . SELF::wple_der_len( strlen( $rsa ) + 1 ) . "\x00" . $rsa;
        $spki = "0" . SELF::wple_der_len( strlen( $alg ) + strlen( $bit ) ) . $alg . $bit;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $spki ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
    }

}
