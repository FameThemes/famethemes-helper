<?php

/**
 * Plugin Name:       FameThemes Helper
 * Plugin URI:        https://www.famethemes.com/
 * Description:       Keep your FameThemes themes & plugins always up to date.
 * Version:           1.1.0
 * Author:            FameThemes
 * Author URI:        https://www.famethemes.com/contact/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ft-helper
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
	exit;
}
if (!defined('FAMETHEMES_FILE')) {
	define('FAMETHEMES_FILE', __FILE__);
}

if (!class_exists('FameThemes_Helper')) {

	class FameThemes_Helper
	{

		public $api_end_point = 'https://www.famethemes.com/';

		function __construct()
		{
			add_action('admin_menu', array($this, 'menu'));
			add_action('network_admin_menu', array($this, 'menu'));

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

			$act = isset($_REQUEST['fame_helper']) ? sanitize_text_field($_REQUEST['fame_helper']) : '';

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

			if ($hook == 'dashboard_page_famethemes-helper' || $hook == 'index_page_famethemes-helper') {
				$url = trailingslashit(plugins_url('/', __FILE__));
				wp_enqueue_script('famethemes-helper', $url . 'js/helper.js', array('jquery'), false, true);
				wp_enqueue_style('famethemes-helper', $url . 'css/helper.css');
				wp_localize_script('famethemes-helper', 'FtHelper', array(
					'nonce' => wp_create_nonce('fame-helper'),
					'ajax' => admin_url('admin-ajax.php'),
					'enable' => esc_html__('Enable', 'ft-helper'),
					'disable' => esc_html__('Disable', 'ft-helper'),
					'yes' => esc_html__('Yes', 'ft-helper'),
					'no' => esc_html__('No', 'ft-helper'),
					'loadingText' => esc_html__('Loading...', 'ft-helper'),
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

			$url = add_query_arg(
				array(
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

					$fame_items = $this->get_items(true);
					$url = str_replace(['https://', 'http://'], '', home_url('/'));


				?>
					<h2 class="nav-tab-wrapper">
						<span class="nav-tab nav-tab-active"><?php esc_html_e('Licenses', 'ft-helper'); ?></span>
					</h2>

					<div class="fame-licenses">

						<div id="fame-licenses-items">
							<?php


							$installed_items = [];
							$not_installed_items = [];

							foreach ((array)$fame_items as $id => $item) {

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

								if ($item['is_lifetime']) {
									$is_expired = false;
								}


								$is_auto_update = in_array($url, $item['sites']);

								$text = $is_auto_update ? esc_html__('Disable', 'ft-helper') : esc_html__('Enable', 'ft-helper');
								$found = true;
								$exp_text = !$item['is_lifetime'] ? date_i18n($date_format, $item['expiration']) : esc_html__('Never', 'ft-helper');

								ob_start();
							?>
								<div class="license-item <?php echo $is_installed ? 'installed' : 'not-installed'; ?>">
									<h3 class="license-item-name"><?php echo esc_html($item['title']); ?>
										<!-- <span class="i-status"><?php echo $is_installed ? __('Installed', 'ft-helper') : __('Not Installed', 'ft-helper'); ?></span> -->
										<?php foreach ($item['terms'] as $t) { ?>
											<span class="i-term"><?php echo esc_html($t); ?></span>
										<?php } ?>
									</h3>


									<div class="ft-manage-licenses">
										<a target="_blank" href="<?php echo esc_url(add_query_arg(array('license_id' => $item['id'], 'action' => 'manage_licenses', 'payment_id' => $item['payment_id']), $this->api_end_point . 'dashboard/purchase-history/')) ?>"><?php esc_html_e('Manage Licenses', 'ft-helper'); ?></a>
									</div>
									<?php if ($is_installed) { ?>
										<div class="ft-actions">
											<?php printf(__('Auto update: <span class="n-auto-update">%s</span>', 'ft-helper'), (!$is_expired && $is_auto_update) ? 'Yes' : 'No'); ?>
											(<a data-action="<?php echo $is_auto_update ? 'disable' : 'enable'; ?>" class="ft-auto-update-link" title="<?php echo esc_attr($text); ?>" data-id="<?php echo esc_attr($item['id']); ?>" href="#"><?php echo esc_html($text); ?></a>)
										</div>
									<?php } ?>

									<div class="<?php echo !$is_expired ? 'no-expired' : 'expired'; ?>"><?php printf(__('Expiration: %s', 'ft-helper'), $exp_text); ?></div>
									<div class="ft-activations"><?php printf(__('Activations: <span class="n-activations">%s</span>', 'ft-helper'), $item['site_count'] . '/' . $item['limit']); ?></div>
								</div>
								<?php
								$html = ob_get_contents();
								ob_end_clean();

								if ($is_installed) {
									$installed_items[] = $html;
								} else {
									$not_installed_items[] = $html;
								}
							}

							if (!empty($installed_items)) {
								echo "<div class='list-heading'>" . __('Installed', 'ft-helper') . "</div>";
								echo join(' ', $installed_items);
							}


							if (!empty($not_installed_items)) {
								echo "<div class='list-heading'>" . __('Not Installed', 'ft-helper') . "</div>";
								echo join(' ', $not_installed_items);
							}



							if (!$found) {
								?>
								<div>
									<div><?php esc_html_e('No FameThemes products found.', 'ft-helper'); ?></div>
								</div>
							<?php } ?>
						</div>

					</div>
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
			if (!function_exists('get_plugins')) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			$plugins = get_plugins();

			foreach ($plugins as $p => $p_data) {
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
								if (isset($themes[$slug])) {
									$data['version'] = $themes[$slug]->get('Version');
								}
								$slugs[$slug] = $data;
							}
						} elseif ($get_type == 'plugin') {
							if ($type == 'plugin') {
								if (isset($plugin_keys[$slug])) {
									$data['slug'] = $plugin_keys[$slug];
								}
								if (isset($plugins[$data['slug']])) {
									$data['version'] = $plugins[$data['slug']]['Version'];
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

			$key = 'fame_api_check_themes_updates';
			if (!isset($GLOBALS[$key])) {
				$items = $this->get_item_slugs('theme');
				$response = $this->api_request('check_themes_update', array(
					'themes' => $items,
				));
				$GLOBALS[$key] = $response;
			} else {
				$response = $GLOBALS[$key];
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

			$slug =  $plugins[$plugin_slug]['slug'];

			// Get the current version
			$plugin_info = get_site_transient('update_plugins');

			if (isset($plugin_info->checked[$slug])) {
				$current_version = $plugin_info->checked[$slug];
				$args->version = $current_version;
			}

			if (isset($plugin_info->response[$slug])) {
				$_data = $plugin_info->response[$slug];
			}

			return $_data;
		}
	}

	if (is_admin()) {
		new FameThemes_Helper();
		require_once dirname(__FILE__) . '/inc/github-updater.php';
	}
}
