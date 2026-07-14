<?php

require_once dirname(__DIR__) . '/cPanel/xmlapi.php';

class WPLE_cPanel
{
    private $cpanel_host;
    private $username;
    private $password;
    private $logger;

    /**
     * Initiates the cPanel class.
     *
     * @param string $cpanel_host
     * @param string $username
     * @param string $password
     */
    public function __construct($cpanel_host, $username, $password)
    {
        $this->cpanel_host = $cpanel_host;
        $this->username = $username;

        //        $adminFactory = new AdminFactory();

        //        $this->password = $adminFactory->decryptText($password);
        $this->password = $password;
        //        $this->logger = new Logger();
    }

    /**
     * Fetch all domains hosted in the cPanel - using cPanel UAPI.
     *
     * @return array|bool
     */
    public function allDomains()
    {
        //List primary domain, add-on domains and sub domain's info including document root, home directory etc
        $request_uri = 'https://' . $this->cpanel_host . ':2083/execute/DomainInfo/domains_data?format=hash';

        $domains_data = $this->connectUapi($request_uri);
        //Validate output
        if (empty($domains_data)) {
            //            echo "Oops! We can't connect to your cPanel. Please recheck the cPanel settings and provide correct credentials.";
            //            echo '<pre>';
            //            print_r($domains_data);
            //            echo '</pre>';
            //            die($domains_data);
            return false;
        }
        if (!$domains_data->status) {
            //            echo 'The domains_data cURL call returned valid JSON, but reported errors:';
            //            echo $domains_data->errors[0].'<br />';

            return false;
        }
        if ($domains_data->status) {
            //Success
            $all_domains = [];

            $all_domains[] = [
                'domain' => $domains_data->data->main_domain->domain,
                'serveralias' => $domains_data->data->main_domain->serveralias,
                'documentroot' => $domains_data->data->main_domain->documentroot,
            ];

            foreach ($domains_data->data->addon_domains as $addon_domain) {
                $all_domains[] = [
                    'domain' => $addon_domain->domain,
                    'serveralias' => $addon_domain->serveralias,
                    'documentroot' => $addon_domain->documentroot,
                ];
            }

            foreach ($domains_data->data->sub_domains as $sub_domain) {
                $all_domains[] = [
                    'domain' => $sub_domain->domain,
                    'serveralias' => $sub_domain->serveralias,
                    'documentroot' => $sub_domain->documentroot,
                ];
            }

            return $all_domains;
        }
    }

    /**
     * Connect to the cPanel using UAPI.
     *
     * @param string $request_uri
     * @param null|array $payload
     *
     * @return mixed
     */
    public function connectUapi($request_uri, $payload = null)
    {
        // Set up the cURL request object.

        $ch = curl_init($request_uri);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if (null !== $payload) {
            // Set up a POST request with the payload.
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Make the call, and then terminate the cURL caller object.
        $curl_response = curl_exec($ch);
        curl_close($ch);

        // Decode and return output.
        return json_decode($curl_response);
    }

    public function checkConnection()
    {

        //List primary domain, add-on domains and sub domain's info including document root, home directory etc
        $request_uri = 'https://' . $this->cpanel_host . ':2083/execute/DomainInfo/domains_data?format=hash';

        $domains_data = $this->connectUapi($request_uri);
        if (empty($domains_data)) {
            return false;
        }
        return true;
    }

    /**
     * Fetch installed SSL data from the cPanel - using cPanel UAPI.
     *
     * @return array|bool
     */
    public function installedHosts()
    {
        // Get already installed SSL's data (expiry date etc)

        $request_uri = 'https://' . $this->cpanel_host . ':2083/execute/SSL/installed_hosts';

        $installed_hosts = $this->connectUapi($request_uri);

        //Validate output
        if (empty($installed_hosts) || !$installed_hosts->status) {
            //            $this->logger->log('The installed_hosts cURL call did not returned valid JSON, but reported errors:');
            //            echo $installed_hosts->errors[0].'<br />';

            return false;
        }

        return $installed_hosts;
    }

    /**
     * Install an SSL certificate on the domain provided - using cPanel UAPI.
     *
     * @param string $domain
     * @param string $domainPath
     *
     * @return bool
     */
    public function installSSL($domain, $domainPath, $is_ajax = true)
    {
        // Define the SSL certificate and key files.
        $cert_file = $domainPath . '/certificate.crt';
        $key_file = $domainPath . '/private.pem';
        ///$cabundle_file = WPLE_DIR . 'cabundle/ca.crt';

        // if (file_exists(ABSPATH . 'keys/cabundle.crt')) {
        $cabundle_file = WPLE_Trait::wple_cert_directory() . 'fullchain.crt';
        // }

        // Define the API call.
        $request_uri = 'https://' . $this->cpanel_host . ':2083/execute/SSL/install_ssl';

        // Set up the payload to send to the server.
        $payload = [
            'domain' => $domain,
            'cert' => file_get_contents($cert_file),
            'key' => file_get_contents($key_file),
            'cabundle' => file_get_contents($cabundle_file),
        ];

        $response = $this->connectUapi($request_uri, $payload);

        if ($response->status) {
            return true;
        }

        WPLE_Trait::wple_logger(json_encode($response));

        return false;
    }

    /**
     * Set DNS TXT record using Json API through cPanel XMLAPI.
     *
     * @param string $domain
     * @param string $txt_name
     * @param string $txt_value
     *
     * @return array
     */
    public function setDnsTxt($domain, $txt_name, $txt_value)
    {
        $xmlapi = new WPLE_xmlapi($this->cpanel_host, $this->username, $this->password);

        $xmlapi->set_output('json');

        $xmlapi->set_port('2083');

        $xmlapi->set_debug(1);

        $responce = $xmlapi->api2_query(
            $this->username,
            'ZoneEdit',
            'add_zone_record',
            [
                'domain' => $domain,
                'name' => $txt_name,
                'type' => 'TXT',
                'txtdata' => $txt_value,
                'ttl' => '600',
                'class' => 'IN',
            ]
        );

        $responce_array = json_decode($responce, true);

        $result = [];

        //Check status

        $event_result = (bool) $responce_array['cpanelresult']['event']['result'];

        $preevent_result = isset($responce_array['cpanelresult']['preevent']) ? (bool) $responce_array['cpanelresult']['preevent']['result'] : true; //Some cPanel doesn't provide this key. In that case, ignore it by setting 'true'.

        $postevent_result = isset($responce_array['cpanelresult']['postevent']) ? (bool) $responce_array['cpanelresult']['postevent']['result'] : true; //Some cPanel doesn't provide this key. In that case, ignore it by setting 'true'.

        if ($event_result && $preevent_result && $postevent_result) {
            //$result['header'] = $response;
            $result['http_code'] = 200;
            $result['body'] = $responce_array;

            $this->logger->log('Congrats! TXT record added successfully.');
        } else {
            //$result['header'] = $response;
            $result['http_code'] = 404;
            $result['body'] = $responce_array;

            $this->logger->log('Sorry, the record was not added due to an error');
        }

        return $result;
    }
}


class WPLE_LogMeIn
{
    // The available services with their HTTPS ports
    private static $servicePorts = array('cpanel' => 2083, 'whm' => 2087, 'webmail' => 2096);

    public static function getLoggedInUrl($user, $pass, $hostname, $service, $goto = '/', $is_reset_host = false)
    {
        // If no valid service has been given, default to cPanel
        $port = isset(self::$servicePorts[$service]) ? self::$servicePorts[$service] : 2083;
        $ch = curl_init();
        $fields = array('user' => $user, 'pass' => $pass, 'goto_uri' => $goto);
        // Sets the POST URL to something like: https://example.com:2083/login/
        //		curl_setopt($ch, CURLOPT_URL, 'http://' . $hostname . ':' . $port);
        //		curl_setopt($ch, CURLOPT_URL, 'https://' . $hostname . ':' . $port . '/login/');
        curl_setopt($ch, CURLOPT_URL, $hostname . ':' . $port . '/login/');
        curl_setopt($ch, CURLOPT_POST, true);
        // Turn our array of fields into a url encoded query string i.e.: ?user=foo&pass=bar
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        // RFC 2616 14.10 compliance
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection' => 'close'));
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Execute POST query returning both the response headers and content into $page
        $page = curl_exec($ch);
        curl_close($ch);

        //		if($is_reset_host === true){
        //			var_dump($hostname);
        //			exit;
        //		}
        //		string(364) "HTTP/1.1 301 Moved Content-length: 136 Location: https://n3plcpnl0074.prod.ams3.secureserver.net:2083 Content-type: text/html; charset="utf-8" Cache-Control: no-cache, no-store, must-revalidate, private Pragma: no-cache "
        if ($is_reset_host === false && strpos($page, '301 Moved')) {
            //			var_dump($hostname);
            //			var_dump($page);
            $start = strpos($page, 'https://');
            $end = strpos($page, ':2083');
            //			$link = substr($page,$start,$end - $start + 5);
            $link = substr($page, $start, $end - $start);
            //			var_dump($link);exit;
            return self::getLoggedInUrl($user, $pass, $link, $service, $goto, true);
        }
        $session = $token = array();
        // Find the session cookie in the page headers
        if (!preg_match('/session=([^\;]+)/', $page, $session)) {
            //			echo '!preg_match(session';
            // This will also fail if the login authentication failed. No need to explicitly check for it.
            return false;
        }
        // Find the cPanel session token in the page content
        if (!preg_match('|<META HTTP-EQUIV="refresh"[^>]+URL=/(cpsess\d+)/|i', $page, $token)) {
            //			echo '!preg_match(META';
            return false;
        }
        // Append the goto_uri to the query string if it's been manually set
        $extra = $goto == '/' ? '' : '&goto_uri=' . urlencode($goto);
        return 'https://' . $hostname . ':' . $port . '/' . $token[1] . '/login/?session=' . $session[1] . $extra;
    }

    public static function getCpanelHost($hostname, $is_reset_host = false)
    {
        // If no valid service has been given, default to cPanel
        $port = 2083;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $hostname . ':' . $port);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection' => 'close'));
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $page = curl_exec($ch);
        curl_close($ch);
        if ($is_reset_host === false && strpos($page, '301 Moved')) {
            $start = strpos($page, 'https://') + 8;
            $end = strpos($page, ':2083');
            $link = substr($page, $start, $end - $start);
            return self::getCpanelHost($link, true);
        }
        $hostname = str_replace('http://', '', $hostname);
        $hostname = str_replace('https://', '', $hostname);

        return $hostname;
        //return 'localhost';
    }
}
