<?php

/**
 * Dashboard widget for WP Encryption status overview.
 *
 * @since 7.8.7.2
 * @package WP Encryption
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WPLE_Dashboard_Widget')) {
    class WPLE_Dashboard_Widget
    {
        public function __construct()
        {
            if (get_option('wple_dashboard_widget')) {
                add_action('wp_dashboard_setup', [$this, 'register_widget']);
            }
        }

        public function register_widget()
        {
            if (!current_user_can('manage_options')) {
                return;
            }

            wp_add_dashboard_widget(
                'wple_ssl_security_dashboard_widget',
                __('WP Encryption SSL & Security Summary', 'wp-letsencrypt-ssl'),
                [$this, 'render_widget']
            );
        }

        public function render_widget()
        {
            $ssl_data = $this->get_ssl_data();
            $malware_count = $this->get_malware_issue_count();
            $vulnerability_count = $this->get_vulnerability_issue_count();

            $ssl_expiry = !empty($ssl_data['expiry']) ? esc_html($ssl_data['expiry']) : esc_html__('Not available', 'wp-letsencrypt-ssl');
            $days_left = !empty($ssl_data['days_left']) ? esc_html($ssl_data['days_left']) : __('N/A', 'wp-letsencrypt-ssl');
            $days_left_raw = isset($ssl_data['days_left_raw']) ? (int) $ssl_data['days_left_raw'] : 0;

            $ssl_status_class = 'wple-dashboard-ssl-ok';
            if ($days_left_raw <= 30) {
                $ssl_status_class = 'wple-dashboard-ssl-danger';
            } elseif ($days_left_raw <= 60) {
                $ssl_status_class = 'wple-dashboard-ssl-warning';
            }

            $malware_class = ($malware_count > 0) ? 'wple-dashboard-ssl-danger' : '';
            $vulnerability_class = ($vulnerability_count > 0) ? 'wple-dashboard-ssl-danger' : '';

            echo '<div class="wple-dashboard-widget-wrapper" style="display:block;">';
            echo '<div class="wple-dashboard-grid">';
            echo '<div class="wple-dashboard-card"><small>' . esc_html__('Current SSL Expiry', 'wp-letsencrypt-ssl') . '</small><strong><a class="' . esc_attr($ssl_status_class) . '" href="' . esc_url(admin_url('admin.php?page=wp_encryption_ssl_health')) . '">' . $ssl_expiry . '</a></strong></div>';
            echo '<div class="wple-dashboard-card"><small>' . esc_html__('Days Left', 'wp-letsencrypt-ssl') . '</small><strong><a class="' . esc_attr($ssl_status_class) . '" href="' . esc_url(admin_url('admin.php?page=wp_encryption_ssl_health')) . '">' . $days_left . '</a></strong></div>';
            echo '<div class="wple-dashboard-card"><small>' . esc_html__('Vulnerability Issues', 'wp-letsencrypt-ssl') . '</small><strong><a class="' . esc_attr($vulnerability_class) . '" href="' . esc_url(admin_url('admin.php?page=wp_encryption_security')) . '">' . esc_html((string) $vulnerability_count) . '</a></strong></div>';
            echo '<div class="wple-dashboard-card"><small>' . esc_html__('Malware Issues', 'wp-letsencrypt-ssl') . '</small><strong><a class="' . esc_attr($malware_class) . '" href="' . esc_url(admin_url('admin.php?page=wp_encryption_security')) . '">' . esc_html((string) $malware_count) . '</a></strong></div>';
            echo '</div>';

            echo '<div class="wple-dashboard-footer">';
            echo '<a href="https://spoofing.wpencryption.com" target="_blank" rel="noopener noreferrer">Check Email Spoofing <span class="dashicons dashicons-external" aria-hidden="true"></span></a>';
            echo '<a href="https://monitor.wpencryption.com" target="_blank" rel="noopener noreferrer">Monitor Domains <span class="dashicons dashicons-external" aria-hidden="true"></span></a>';
            echo '<a href="https://scan.wpencryption.com/?domain=' . rawurlencode(home_url()) . '" target="_blank" rel="noopener noreferrer">Scan Security Headers <span class="dashicons dashicons-external" aria-hidden="true"></span></a>';
            echo '</div>';
            echo '</div>';
        }

        private function get_ssl_data()
        {
            $not_after = $this->get_ssl_expiry_from_socket();

            if (!$not_after) {
                return array(
                    'expiry' => '',
                    'days_left' => '',
                    'days_left_raw' => 0,
                );
            }

            $expiry_date = date_i18n(get_option('date_format'), $not_after);
            $days_left = (int) floor(($not_after - time()) / DAY_IN_SECONDS);

            return array(
                'expiry' => $expiry_date,
                'days_left' => $days_left . ' ' . __('days', 'wp-letsencrypt-ssl'),
                'days_left_raw' => $days_left,
            );
        }

        private function get_ssl_expiry_from_socket()
        {
            $host = WPLE_Trait::get_root_domain(true);
            $host = preg_replace('#^https?://#i', '', trim($host));
            $host = rtrim($host, '/');
            $host = strtok($host, '/');

            if (empty($host)) {
                return 0;
            }

            $context = stream_context_create(array(
                'ssl' => array(
                    'capture_peer_cert' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ),
            ));

            $stream = @stream_socket_client('ssl://' . $host . ':443', $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
            if (!$stream) {
                return 0;
            }

            $params = stream_context_get_params($stream);
            $certificate = isset($params['options']['ssl']['peer_certificate']) ? $params['options']['ssl']['peer_certificate'] : null;

            if (!$certificate) {
                fclose($stream);
                return 0;
            }

            $parsed = openssl_x509_parse($certificate);
            fclose($stream);

            if (!is_array($parsed) || empty($parsed['validTo_time_t'])) {
                return 0;
            }

            return (int) $parsed['validTo_time_t'];
        }

        private function get_malware_issue_count()
        {
            $report = get_option('wple_mscan_integrity', array());

            if (!is_array($report)) {
                return 0;
            }

            $ignore_list = get_option('wple_malware_ignorelist', array());
            if (!is_array($ignore_list)) {
                $ignore_list = array();
            }

            $count = 0;
            foreach ($report as $item) {
                if (is_string($item)) {
                    $file_path = urlencode(substr($item, strpos($item, ':') + 2));
                    $file_path = str_ireplace(array('http://', 'https://'), '', $file_path);
                    if (!in_array($file_path, $ignore_list, true)) {
                        $count++;
                    }
                }
            }

            return $count;
        }

        private function get_vulnerability_issue_count()
        {
            $threats = get_transient('wple_vulnerability_scan');

            if (!is_array($threats)) {
                return 0;
            }

            return $this->count_vulnerability_entries($threats);
        }

        private function count_vulnerability_entries($items)
        {
            $count = 0;

            foreach ($items as $item) {
                if (is_array($item)) {
                    if (isset($item['label']) || isset($item['desc']) || isset($item['severity'])) {
                        $count++;
                    } else {
                        $count += $this->count_vulnerability_entries($item);
                    }
                }
            }

            return $count;
        }
    }
}
