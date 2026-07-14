<?php

require_once dirname(__DIR__) . '/directadmin/httpsocket.php';

class WPLE_DirectAdmin
{

    private $username;
    private $password;
    private $host;

    public function __construct($creds)
    {
        if ($creds) {
            $this->username = $creds['uname'];
            $this->password = base64_decode($creds['upass']);
            $this->host = WPLE_Trait::get_root_domain();
        }
    }

    public function wple_directadmin_install_ssl($cert, $key, $cabundle)
    {
        $cert_file = file_get_contents($cert);
        $key_file = file_get_contents($key);
        $cabundle_file = file_get_contents($cabundle);

        try {
            $server_ssl = true;
            $server_port = 2222;
            $sock = new HTTPSocket;

            if ($server_ssl) {
                $sock->connect("ssl://" . $this->host, $server_port);
            } else {
                $sock->connect($this->host, $server_port);
            }
            $sock->set_login($this->username, $this->password);
            $sock->method = "POST";
            $sock->query(
                '/CMD_API_SSL',
                array(
                    'domain' => $this->host,
                    'action' => 'save',
                    'type' => 'paste',
                    'certificate' => $key_file . $cert_file
                )
            );
            $response = $sock->fetch_parsed_body();

            $first = isset($response[0]) ? (string)$response[0] : '';
            if (stripos($first, 'Error') === false && (!empty($response['details']))) {
                $sock->query(
                    '/CMD_SSL',
                    array(
                        'domain' => $this->host,
                        'action' => 'save',
                        'type' => 'cacert',
                        'active' => 'yes',
                        'cacert' => $cabundle_file
                    )
                );
                $response = $sock->fetch_parsed_body();
                $first = isset($response[0]) ? (string)$response[0] : '';
                if (stripos($first, 'Error') === false && (!empty($response['details']))) {
                    delete_option('wple_error'); //complete
                    update_option('wple_ssl_screen', 'success');

                    $finalshell = "<h2>" . esc_html__('DirectAdmin SSL installed successfully!', 'wp-letsencrypt-ssl') . "!. #DIRECTADMIN</h2>";
                    WPLE_Trait::wple_logger($finalshell, 'success', 'a');
                    WPLE_Trait::wple_send_log_data();

                    wp_redirect(admin_url('/admin.php?page=wp_encryption&success=1'), 302);
                    exit();
                }
            } else { //caught some error
                throw new Exception($first);
            }
        } catch (Exception $e) {
            WPLE_Trait::wple_logger("DirectAdmin SSL installation error | " . $e->getCode() . ": " . $e->getMessage(), 'error', 'a', true);
        }
    }
}
