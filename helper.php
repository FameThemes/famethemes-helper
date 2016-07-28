<?php
/**
 * Plugin Name:       FameThemes Helper
 * Plugin URI:        http://famethemes.com/
 * Description:       Keep your FameThemes items always update.
 * Version:           1.0.1
 * Author:            famethemes, shrimp2t
 * Author URI:        http://famethemes.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ft-helper
 * Domain Path:       /languages
 */

if ( ! class_exists( 'FameThemes_Helper' ) ) {

    class FameThemes_Helper
    {

        public $api_end_point = 'https://www.famethemes.com/';

        function __construct()
        {
            add_action('admin_menu', array($this, 'menu'));

            $this->api_end_point = trailingslashit($this->api_end_point);

            // check plugins update
            add_filter('plugins_api', array($this, 'plugin_api_call'), 35, 3);
            add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_plugin_update'));

            // check themes update
            add_filter('site_transient_update_themes', array($this, 'check_theme_for_update'));
            add_action('admin_enqueue_scripts', array($this, 'load_scripts'));

            add_action('init', array($this, 'init'));

            add_action('wp_ajax_fame_helper_api', array($this, 'ajax'));
        }

        function ajax()
        {

            $act = isset($_REQUEST['fame_helper']) ? $_REQUEST['fame_helper'] : '';

            $nonce = $_REQUEST['nonce'];
            if (!wp_verify_nonce($nonce, 'fame-helper')) {
                die('security_check');
            }
            $id = $_REQUEST['id'];


            if ($act == 'enable') {
                $r = $this->enable_auto_update($id);
            } else {
                $r = $this->disable_auto_update($id);
            }

            if (is_array($r) && $r['success']) {
                wp_send_json($r);
            } else {
                wp_send_json_error();
            }

            die();
        }

        function enable_auto_update($license_id)
        {
            $r = $this->api_request('enable_auto_update', array(
                'license_id' => $license_id,
            ));

            return $r;
        }

        function disable_auto_update($license_id)
        {
            $r = $this->api_request('disable_auto_update', array(
                'license_id' => $license_id,
            ));

            return $r;
        }


        function init()
        {
            if (is_admin()) {
                if (isset($_REQUEST['page']) && $_REQUEST['page'] == 'famethemes-helper') {
                    if (isset($_REQUEST['secret_key'])) {
                        $this->update_secret_key($_REQUEST['secret_key']);
                        $redirect = add_query_arg(array('page' => 'famethemes-helper'), admin_url('admin.php'));
                        wp_redirect($redirect);
                        die();
                    }
                }
            }
        }

        function load_scripts($hook)
        {
            if ($hook == 'dashboard_page_famethemes-helper') {
                $url = trailingslashit(plugins_url('/', __FILE__));
                wp_enqueue_script('famethemes-helper', $url . 'js/helper.js', array('jquery'), false, true);
                wp_enqueue_style('famethemes-helper', $url . 'css/helper.css');
                wp_localize_script('famethemes-helper', 'FtHelper', array(
                    'nonce' => wp_create_nonce('fame-helper'),
                    'ajax' => admin_url('admin-ajax.php'),
                    'enable' => esc_html__('Enable auto update', 'ft-helper'),
                    'disable' => esc_html__('Disable auto update', 'ft-helper'),
                    'loading' => '<span class="spinner"></span>',
                ));
            }
        }


        function menu()
        {
            add_dashboard_page('FameThemes Helper', 'FameThemes Helper', 'manage_options', 'famethemes-helper', array($this, 'display'));
        }

        function is_installed($item_slug)
        {
            $theme_root = get_theme_root();
            if (!$item_slug) {
                return false;
            }
            if (is_dir($theme_root . '/' . $item_slug)) {
                return 'theme';
            } else if (is_dir(WP_PLUGIN_DIR . '/' . $item_slug)) {
                return 'plugin';
            } else {
                return false;
            }
        }

        function update_secret_key($key)
        {
            update_option('fame_api_secret_key', $key);
        }


        function check_api_key()
        {

            $r = $this->api_request('check_api_key');

            if (!$r) {
                return false;
            } else if ($r['success']) {
                update_option('fame_api_connect_info', $r['data']);
                return true;
            }

            return false;
        }

        function disconnect()
        {
            $r = $this->api_request('unauthorize');
            delete_option('fame_api_secret_key');
        }

        function display()
        {
            $date_format = get_option('date_format');

            if (isset($_REQUEST['disconnect']) && $_REQUEST['disconnect'] == 1) {
                $this->disconnect();
            }

            $url = add_query_arg(array(
                'fame_api_action' => 'authorize',
                'url' => base64_encode(home_url(''))
            ),
                $this->api_end_point
            );
            $check_api = $this->check_api_key();

            ?>
            <div class="wrap ft-helper-wrap">
                <div class="intro">
                    <h1><?php esc_html_e('Welcome to FameThemes Helper', 'ft-helper'); ?></h1>
                    <div class="about-text"><?php esc_html_e('This is your one-stop-spot for activating your licenses.', 'ft-helper'); ?></div>
                </div>
                <?php
                if (!$check_api) { ?>
                    <div class="intro">
                        <?php if (get_option('fame_api_secret_key')) {
                            ?>
                            <p class="api-error"><?php esc_html_e('Could not connect to server, please try again later.', 'ft-helper'); ?></p>
                            <?php
                        } ?>
                        <a class="button-primary" href="<?php echo esc_url($url); ?>"><?php esc_html_e('Connect to FameThemes', 'ft-helper'); ?></a>
                    </div>
                    <?php
                } else {

                    $now = current_time('gmt');
                    $found = false;
                    ?>
                    <h2 class="nav-tab-wrapper">
                        <span class="nav-tab nav-tab-active"><?php esc_html_e('Licenses', 'ft-helper'); ?></span>
                    </h2>

                    <table class="fame-licenses wp-list-table widefat striped fixed posts">
                        <thead>
                        <tr>
                            <th class="column-primary"><?php esc_html_e('License Name', 'ft-helper'); ?></th>
                            <th class="n-auto-update" style="width: 120px;"><?php esc_html_e('Auto Update', 'ft-helper'); ?></th>
                            <th style="width: 120px;"><?php esc_html_e('Expiration', 'ft-helper'); ?></th>
                            <th style="width: 100px; text-align: center;"><?php esc_html_e('Activations', 'ft-helper'); ?></th>
                        </tr>
                        </thead>
                        <tbody id="the-list">
                        <?php foreach ((array)$this->get_items(true) as $id => $item) {

                            $is_installed = false;
                            if ($item['files']) {
                                foreach ($item['files'] as $item_slug => $file) {
                                    if (!$is_installed) {
                                        $is_installed = $this->is_installed($item_slug);
                                    }
                                }
                            }

                            if ($item['expiration'] != '') {
                                $is_expired = $now > $item['expiration'];
                            } else {
                                $is_expired = false;
                            }
                            $is_auto_update = $item['is_active'];
                            if ($is_installed) {
                                $text = $is_auto_update ? esc_html__('Disable auto update', 'ft-helper') : esc_html__('Enable auto update', 'ft-helper');
                                $found = true;
                                ?>
                                <tr>
                                    <td class="column-primary has-row-actions">
                                        <strong><?php echo esc_html($item['title']); ?></strong>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <?php if ($is_expired) {
                                                    echo '<span>' . esc_html_e('Expired', 'ft-helper') . '</span>';
                                                } else if ($item['site_count'] >= $item['limit']) {
                                                    echo '<span>' . esc_html_e('Activations Limited', 'ft-helper') . '</span>';
                                                } else {
                                                    ?>
                                                    <a data-action="<?php echo $is_auto_update ? 'disable' : 'enable'; ?>" class="ft-auto-update-link" title="<?php echo esc_attr($text); ?>"
                                                       data-id="<?php echo esc_attr($item['id']); ?>" href="#"><?php echo esc_html($text); ?></a>
                                                <?php } ?>
                                                | <a target="_blank"
                                                     href="<?php echo esc_url(add_query_arg(array('license_id' => $item['id'], 'action' => 'manage_licenses', 'payment_id' => $item['payment_id']), $this->api_end_point . 'dashboard/purchase-history/')) ?>"><?php esc_html_e('Manage Licenses', 'ft-helper'); ?></a>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="n-auto-update"><?php echo $is_auto_update ? '<span class="dashicons dashicons-yes"></span>' : '<span class="dashicons dashicons-no-alt"></span>' ?></td>
                                    <td class="<?php echo $is_expired ? 'expired' : 'no-expired'; ?>"><?php echo $item['expiration'] ? date_i18n($date_format, $item['expiration']) : esc_html__('Never', 'ft-helper'); ?></td>
                                    <td style="text-align: center;" class="n-activations"><?php echo esc_html($item['site_count']) . '/' . $item['limit']; ?></td>
                                </tr>
                                <?php
                            }
                        }
                        if ( ! $found ) {
                        ?>
                        <tr>
                            <td colspan="4"><?php esc_html_e( 'No FameThemes products found.', 'ft-helper' ); ?></td>
                        </tr>
                        <?php } ?>
                        </tbody>
                        <tfoot>
                        <tr>
                            <th class="column-primary"><?php esc_html_e('License Name', 'ft-helper'); ?></th>
                            <th class="n-auto-update" ><?php esc_html_e('Auto Update', 'ft-helper'); ?></th>
                            <th ><?php esc_html_e('Expiration', 'ft-helper'); ?></th>
                            <th ><?php esc_html_e('Activations', 'ft-helper'); ?></th>
                        </tr>
                        </tfoot>
                    </table>
                    <?php
                    $connect_info = get_option('fame_api_connect_info');
                    ?>
                    <p><?php
                        printf(
                            esc_html__('Your are connecting as %1$s, %2$s | %3$s', 'ft-helper'),
                            '<strong>' . $connect_info['display_name'] . '</strong>',
                            '<a class="ft-change-account" href="' . esc_url($url) . '">' . esc_html__('Change Account', 'ft-helper') . '</a>',
                            '<a class="ft-change-account" href="' . esc_url(add_query_arg(array('page' => 'famethemes-helper', 'disconnect' => 1), admin_url('index.php')))
                            . '">' . esc_html__('Disconnect', 'ft-helper') . '</a>'

                        ); ?></p>
                    <?php
                }

                ?>
            </div>
            <?php
        }

        function api_request($action = '', $data = array())
        {
            $key = get_option('fame_api_secret_key');
            if (!$key) {
                return false;
            }
            $params = array(
                'api_key' => $key,
                'fame_api_action' => $action,
                'site_url' => home_url('')
            );

            global $wp_version;

            $params = wp_parse_args($params, $data);

            $r = wp_remote_post($this->api_end_point, array(
                'timeout' => 15,
                'body' => $params,
                'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url')
            ));

            if (!is_wp_error($r) && 200 == wp_remote_retrieve_response_code($r)) {
                $api_response = @json_decode(wp_remote_retrieve_body($r), true);
                if (!is_array($api_response)) {
                    return false;
                }
                return $api_response;
            }
            return false;
        }


        function get_items($force = false)
        {
            $key = 'fame_api_get_items';
            $items = false;
            if (!$force) {
                $items = get_transient($key);
                if (false !== $items) {
                    return $items;
                }
            }

            $api_response = $this->api_request('get_items');
            if (is_array($api_response) && isset($api_response['success']) && $api_response['success']) {
                $items = $api_response['data']['items'];
                set_transient($key, $items, 1 * HOUR_IN_SECONDS);
            } else {
                delete_transient($key);
            }

            return $items;
        }

        function get_item_slugs($get_type = 'all')
        {
            $items = $this->get_items();
            if (!is_array($items)) {
                return false;
            }

            $themes = wp_get_themes(array('errors' => false, 'allowed' => null));
            $plugin_keys = array();

            $plugins = get_plugins();
            if ($plugins) {
                $plugins = array_keys(get_plugins());
            }

            foreach ($plugins as $p) {
                $k = explode('/', $p);
                $k = $k[0];
                $plugin_keys[$k] = $p;
            }

            $slugs = array();
            foreach ($items as $item) {
                if ($item['files'] && is_array($item['files'])) {
                    foreach ($item['files'] as $slug => $file) {
                        $type = $this->is_installed($slug);

                        $data = array(
                            'download_id' => $item['download_id'],
                            'license_id' => $item['id'],
                            'key' => $item['key'],
                            'file_key' => $file['file_key'],
                            'type' => $type,
                            'slug' => $slug,
                            'version' => '',
                        );

                        if ($get_type == 'theme') {
                            if ($type == 'theme') {
                                if (isset ($themes[$slug])) {
                                    $data['version'] = $themes[$slug]->get('Version');
                                }
                                $slugs[$slug] = $data;
                            }
                        } elseif ($get_type == 'plugin') {
                            if ($type == 'plugin') {
                                if (isset($plugin_keys[$slug])) {
                                    $data['slug'] = $plugin_keys[$slug];
                                }
                                if (isset ($plugins[$data['slug']])) {
                                    $data['version'] = $plugins[$data['slug']]->get('Version');
                                }

                                $slugs[$slug] = $data;
                            }
                        } else {
                            if ($type) {
                                $slugs[$slug] = $data;
                            }
                        }
                    }
                }
            }
            return $slugs;

        }

        function check_theme_for_update($checked_data)
        {
            // global $pagenow;
            $key = 'fame_api_check_themes_updates';
            $response = get_transient('fame_api_check_themes_updates');
            if (false !== $response) {
                $items = $this->get_item_slugs('theme');
                $response = $this->api_request('check_themes_update', array(
                    'themes' => $items,
                ));
                set_transient($key, $response, 1 * 60 * 60); // 1 hour
            }

            if (is_array($response) && isset($response['success']) && $response['success']) {
                foreach ((array)$response['data'] as $theme_base => $info) {
                    $checked_data->response[$theme_base] = $info;
                }
            }

            return $checked_data;
        }


        function check_for_plugin_update($checked_data)
        {

            if (!is_object($checked_data)) {
                $checked_data = new stdClass;
            }

            $items = $this->get_item_slugs('plugin');
            $response = $this->api_request('check_plugins_update', array(
                'plugins' => $items,
            ));
            if (is_array($response) && isset($response['success']) && $response['success']) {
                foreach ((array)$response['data'] as $plugin_slug => $info) {
                    $info = (object)$info;
                    $checked_data->response[$plugin_slug] = $info;
                    $checked_data->checked[$plugin_slug] = $info->new_version;
                }
                $checked_data->last_checked = time();
            }

            return $checked_data;
        }

        function plugin_api_call($_data, $action, $args)
        {
            if ($action != 'plugin_information') {
                return $_data;
            }

            $plugin_slug = $args->slug;
            $plugins = $this->get_item_slugs('plugin');
            if (!isset($plugins[$plugin_slug])) {
                return $_data;
            }

            // Get the current version
            $plugin_info = get_site_transient('update_plugins');
            $current_version = $plugin_info->checked[$plugin_slug . '/' . $plugin_slug . '.php'];
            $args->version = $current_version;

            if (isset ($plugin_info->response[$plugin_slug . '/' . $plugin_slug . '.php'])) {
                $_data = $plugin_info->response[$plugin_slug . '/' . $plugin_slug . '.php'];
            }

            return $_data;
        }

    }

    if (is_admin()) {
        new FameThemes_Helper();
    }

}