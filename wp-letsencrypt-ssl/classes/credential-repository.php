<?php
if (! defined('ABSPATH')) {
    exit;
}

if (class_exists('Credential_Repository')) {
    return;
}

class Credential_Repository
{
    public function __construct()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = $wpdb->prefix . 'wpen_credentials';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            credential_id varchar(255) NOT NULL,
            public_key mediumtext NOT NULL,
            sign_count bigint(20) unsigned NOT NULL DEFAULT 0,
            device_name varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT current_timestamp(),
            last_used datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY credential_id (credential_id),
            KEY user_id (user_id)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    public function findOneByCredentialId(string $credentialId)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wpen_credentials';

        $cred_b64 = $credentialId;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE credential_id = %s", $cred_b64),
            ARRAY_A
        );

        if (! $row) {
            return null;
        }

        return $row;
    }

    public function findOneByID(int $rowId)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wpen_credentials';

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $rowId),
            ARRAY_A
        );

        if (! $row) {
            return null;
        }

        return $row;
    }

    public function deleteCredential(int $rowId)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wpen_credentials';

        return $wpdb->delete($table, ['id' => $rowId]);
    }

    function wple_db_update_sign_count($credential_id, $new_count)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wpen_credentials';
        $wpdb->update(
            $table,
            array('sign_count' => (int) $new_count, 'last_used' => current_time('mysql', true)),
            array('credential_id' => $credential_id)
        );
    }

    public function findAllByUserHandle(string $userHandle): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wpen_credentials';

        $user_id = intval(base64_decode($userHandle));
        if (! $user_id) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $user_id),
            ARRAY_A
        );

        if (! $rows) {
            return [];
        }

        return $rows;
    }

    function save_credential($user_id, $credential_id, $public_key_cbor, $sign_count, $device_name)
    {
        global $wpdb;
        $table  = $wpdb->prefix . 'wpen_credentials';
        $result = $wpdb->insert($table, array(
            'user_id'       => (int) $user_id,
            'credential_id' => sanitize_text_field($credential_id),
            'public_key'    => base64_encode($public_key_cbor),
            'sign_count'    => (int) $sign_count,
            'device_name'   => sanitize_text_field($device_name),
            'created_at'    => current_time('mysql', true),
        ));
        return (false !== $result);
    }

    function get_user_credentials($user_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wpen_credentials';
        $rows  = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM `{$table}` WHERE user_id = %d ORDER BY created_at DESC", (int) $user_id),
            ARRAY_A
        );
        return is_array($rows) ? $rows : array();
    }

    // public function saveCredentialSource(PublicKeyCredentialSource $credentialSource): void
    // {
    //     global $wpdb;
    //     $table = $wpdb->prefix . 'wple_credentials';

    //     $user_id = intval(base64_decode($credentialSource->getUserHandle()));
    //     $cred_id_b64 = base64_encode($credentialSource->getPublicKeyCredentialId());
    //     $public_key = $credentialSource->getCredentialPublicKey();

    //     $wpdb->insert(
    //         $table,
    //         [
    //             'user_id' => $user_id,
    //             'credential_id' => $cred_id_b64,
    //             'public_key' => $public_key,
    //             'sign_count' => $credentialSource->getCounter(),
    //             'transports' => wp_json_encode($credentialSource->getTransports() ?? []),
    //             'attestation_type' => $credentialSource->getAttestationType(),
    //         ],
    //         ['%d', '%s', '%s', '%d', '%s', '%s']
    //     );
    // }

    // public function updateCredentialCounter(string $credentialId, int $newSignCount): void
    // {
    //     global $wpdb;
    //     $table = $wpdb->prefix . 'wple_credentials';

    //     $cred_b64 = base64_encode($credentialId);

    //     $wpdb->update(
    //         $table,
    //         ['sign_count' => $newSignCount],
    //         ['credential_id' => $cred_b64],
    //         ['%d'],
    //         ['%s']
    //     );
    // }

    // private function load_credential_source(array $row): PublicKeyCredentialSource
    // {
    //     $user_handle = base64_encode((string) $row['user_id']);
    //     $cred_id = base64_decode($row['credential_id']);
    //     $public_key = $row['public_key'];
    //     $transports = json_decode($row['transports'] ?? '[]', true);
    //     $attestation_type = $row['attestation_type'] ?? 'none';

    //     return new PublicKeyCredentialSource(
    //         $cred_id,
    //         'public-key',
    //         $transports,
    //         $attestation_type,
    //         new EmptyTrustPath(),
    //         random_bytes(16), // AAGUID placeholder
    //         $public_key,
    //         $user_handle,
    //         intval($row['sign_count'] ?? 0)
    //     );
    // }
}
