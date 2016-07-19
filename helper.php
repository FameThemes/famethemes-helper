<?php
/**
 * Plugin Name:       FameThemes Helper
 * Plugin URI:        http://famethemes.com/
 * Description:       Keep your FameThemes items always update.
 * Version:           1.0.1
 * Author:            famethemes
 * Author URI:        http://famethemes.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ft
 * Domain Path:       /languages
 */

$plugin_slug = basename(dirname(__FILE__));

class FameThemes_Helper {
    public $api_end_point = 'http://localhost/ft2020/';
    function __construct(){
        add_action('admin_menu', array( $this, 'menu') );

        //add_filter('plugins_api', array( $this, 'plugin_api_call' ) , 10, 3);
        //add_filter('pre_set_site_transient_update_plugins', array( $this, 'check_for_plugin_update' ) );

        add_filter('site_transient_update_themes', array( $this, 'check_theme_for_update' ) );

    }
    function menu(){
        add_dashboard_page( 'Fame helper', 'Fame helper', 'manage_options', 'famethemes-helper',  array( $this, 'display' ) );
    }

    function is_installed( $item_slug ){
        $theme_root = get_theme_root();
        if ( ! $item_slug ) {
            return false;
        }
        //echo $theme_root.'/'.$item_slug;
        if ( is_dir( $theme_root.'/'.$item_slug ) ) {
            return 'theme';
        } else if ( is_dir( WP_PLUGIN_DIR .'/'. $item_slug  ) ) {
            return 'plugin';
        } else {
            return false;
        }
    }

    function display(){
        $date_format = get_option( 'date_format' );
        $api_end_point = 'http://localhost/ft2020/';
        $url = $api_end_point.'?fame_api_action=authorize&url='.base64_encode( home_url('') );

        ?>
        <div class="wrap">
            <h1>My FameThemes Licenses <a class="page-title-action" href="http://localhost/ft2020/wp-admin/post-new.php">Add New</a></h1>
            <a href="<?php echo $url;  ?>">Connect</a>
            <table class="wp-list-table widefat striped fixed posts">
                <thead>
                    <tr>
                        <th class="column-primary">Name</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 100px;">Version</th>
                        <th style="width: 120px;">Expiration</th>
                        <th style="width: 100px; text-align: center;">Activations</th>

                    </tr>
                </thead>
                <tbody id="the-list">
                    <?php foreach ( (array) $this->get_items() as $k => $item ){

                        $is_active = false;
                        if ( $item['files'] ) {
                            foreach ( $item['files'] as $item_slug => $file ) {
                                if ( $this->is_installed( $item_slug ) ) {
                                    $is_active = true;
                                }
                            }
                        }

                        ?>
                        <tr>
                            <td class="column-primary has-row-actions">
                                <?php echo esc_html($item['title']); ?>
                                <div class="row-actions">
                                    <span class="edit"><a title="Edit this item" href="#">Active</a></span>
                                </div>
                            </td>
                            <td><?php echo $is_active ? 'installed' : ''; ?></td>
                            <td><?php echo esc_html($item['version']); ?></td>
                            <td><?php echo $item['expiration'] ? date_i18n($date_format, $item['expiration']) : esc_html__('Never', 'fame-helper'); ?></td>
                            <td style="text-align: center;"><?php echo esc_html($item['site_count']) . '/' . $item['limit']; ?></td>
                        </tr>
                        <?php

                    } ?>
                </tbody>
            </table>
        </div>
        <?php

        $themes =  wp_get_themes( array( 'errors' => false , 'allowed' => null ));
        echo '<h3>Themes</h3>';
        echo '<pre>';
        var_dump( $themes );
        echo '</pre>';

        echo '<h3>Plugins</h3>';

        $plugins =  get_plugins();
        echo '<pre>';
        var_dump( $plugins );
        echo '</pre>';

    }


    function get_items(){
        $key = 'b365045e25abf169a5be35859a3830dd';
        $params = array(
            'api_key' => $key,
            'fame_api_action' => 'get_items',
            'site_url' => home_url('')
        );

        $r =  wp_remote_post( $this->api_end_point, array( 'timeout' => 15, 'body' => $params ) );
        $api_response = @json_decode( wp_remote_retrieve_body( $r ), true );
        if ( is_array( $api_response ) && isset( $api_response['success'] ) && $api_response['success'] ) {
            return $api_response['data']['items'];
        }
        return false;
    }

    function get_item_slugs( $get_type = 'all' ){
        $items =  $this->get_items();
        if ( ! is_array( $items ) ) {
            return false;
        }

        $themes = wp_get_themes( array( 'errors' => false , 'allowed' => null ));
        $plugin_keys = array();

        $plugins = get_plugins();
        if ( $plugins ) {
            $plugins = array_keys(get_plugins());
        }


        foreach ( $plugins as $p ) {
            $k = explode('/', $p);
            $k = $k[0];
            $plugin_keys[ $k ] = $p;
        }

        $slugs = array();
        foreach ( $items as $item ) {
            if ( $item['files'] && is_array($item['files'])) {
               foreach ( $item['files'] as $slug => $file ) {
                   $type = $this->is_installed( $slug );

                   $data = array(
                       'download_id' => $item['download_id'],
                       'license_id'  => $item['id'],
                       'key'         => $item['key'],
                       'file_key'    => $file['file_key'],
                       'type'        => $type,
                       'slug'        => $slug,
                       'version'     => '',
                   );

                   if ( $get_type == 'theme' ) {
                       if ( $type == 'theme' ) {
                           if ( isset ( $themes[ $slug ] ) ) {
                               $data['version'] = $themes[ $slug ]->get('Version');
                           }
                           $slugs[ $slug ] = $data;
                       }
                   } elseif ( $get_type == 'plugin' ) {
                       if ( $type == 'plugin' ) {
                           if ( isset( $plugin_keys[ $slug ] ) ) {
                              $data['slug'] = $plugin_keys[ $slug ];
                           }
                           if ( isset ( $plugins[ $data['slug'] ] ) ) {
                               $data['version'] = $plugins[ $data['slug'] ]->get('Version');
                           }

                           $slugs[ $slug ] = $data;
                       }
                   } else {
                       if ( $type ) {
                           $slugs[ $slug ] = $data;
                       }
                   }

               }
            }
        }
        return $slugs;

    }

    function check_theme_for_update($checked_data) {

        /*
        $update_data = $this->check_for_update();
        if ( $update_data ) {
            $value->response[ $this->theme_slug ] = $update_data;
        }
        return $value;
        */


        global $wp_version, $theme_version, $theme_base, $api_url;

        $items = $this->get_item_slugs( 'theme' );

        // Start checking for an update
        $send_for_check = array(
            'body' => array(
                'fame_api_action' => 'check_themes_update',
                'themes' => $items,
                'api_key' => 'b365045e25abf169a5be35859a3830dd',
                'site_url' => home_url('')
            ),
            'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url')
        );

        $r = wp_remote_post( $this->api_end_point , $send_for_check);
        $response = null;
        // Make sure the response was successful
        if ( ! is_wp_error( $r ) && 200 == wp_remote_retrieve_response_code( $r ) ) {
            $response = json_decode( wp_remote_retrieve_body( $r ), true );
            //  var_dump( $response );
            if ( is_array( $response ) && isset( $response['success'] ) && $response['success'] ) {
                foreach ( (array) $response['data'] as $theme_base => $info ) {
                    $checked_data->response[ $theme_base ] = $info;
                }
            }
        }


        return $checked_data;
    }

    // Take over the Theme info screen on WP multisite
    function my_theme_api_call($def, $action, $args) {
        global $theme_base, $api_url, $theme_version, $api_url;

        if ($args->slug != $theme_base)
            return false;

        // Get the current version
        $args->version = $theme_version;
        $request_string = prepare_request($action, $args);
        $request = wp_remote_post($api_url, $request_string);
        if (is_wp_error($request)) {
            $res = new WP_Error('themes_api_failed', __('An Unexpected HTTP Error occurred during the API request.</p> <p><a href="?" onclick="document.location.reload(); return false;">Try again</a>'), $request->get_error_message());
        } else {
            $res = unserialize($request['body']);

            if ($res === false)
                $res = new WP_Error('themes_api_failed', __('An unknown error occurred'), $request['body']);
        }

        return $res;
    }


    function check_for_plugin_update($checked_data) {
        global $api_url, $plugin_slug, $wp_version;

        //Comment out these two lines during testing.
        if (empty($checked_data->checked))
            return $checked_data;

        $args = array(
            'slug' => $plugin_slug,
            'version' => $checked_data->checked[$plugin_slug .'/'. $plugin_slug .'.php'],
        );
        $request_string = array(
            'body' => array(
                'action' => 'basic_check',
                'request' => serialize($args),
                'api-key' => md5(get_bloginfo('url'))
            ),
            'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url')
        );

        // Start checking for an update
        $raw_response = wp_remote_post($api_url, $request_string);

        if (!is_wp_error($raw_response) && ($raw_response['response']['code'] == 200))
            $response = unserialize($raw_response['body']);

        if (is_object($response) && !empty($response)) // Feed the update data into WP updater
            $checked_data->response[$plugin_slug .'/'. $plugin_slug .'.php'] = $response;

        return $checked_data;
    }

    function plugin_api_call($def, $action, $args) {
        global $plugin_slug, $api_url, $wp_version;

        if (isset($args->slug) && ($args->slug != $plugin_slug))
            return false;

        // Get the current version
        $plugin_info = get_site_transient('update_plugins');
        $current_version = $plugin_info->checked[$plugin_slug .'/'. $plugin_slug .'.php'];
        $args->version = $current_version;

        $request_string = array(
            'body' => array(
                'action' => $action,
                'request' => serialize($args),
                'api-key' => md5(get_bloginfo('url'))
            ),
            'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url')
        );

        $request = wp_remote_post($api_url, $request_string);

        if (is_wp_error($request)) {
            $res = new WP_Error('plugins_api_failed', __('An Unexpected HTTP Error occurred during the API request.</p> <p><a href="?" onclick="document.location.reload(); return false;">Try again</a>'), $request->get_error_message());
        } else {
            $res = unserialize($request['body']);

            if ($res === false)
                $res = new WP_Error('plugins_api_failed', __('An unknown error occurred'), $request['body']);
        }

        return $res;
    }

}

if ( is_admin() ) {
    new FameThemes_Helper();
}


