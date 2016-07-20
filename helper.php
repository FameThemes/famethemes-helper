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

        // check plugins update
        add_filter('plugins_api', array( $this, 'plugin_api_call' ) , 35, 3);
        add_filter('pre_set_site_transient_update_plugins', array( $this, 'check_for_plugin_update' ) );

        // check themes update
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
        if ( is_dir( $theme_root.'/'.$item_slug ) ) {
            return 'theme';
        } else if ( is_dir( WP_PLUGIN_DIR .'/'. $item_slug  ) ) {
            return 'plugin';
        } else {
            return false;
        }
    }

    function enable_auto_update( $license_id ){

    }

    function update_secret_key( $key ){
        update_option( 'fame_api_secret_key', $key );
    }


    function check_api_key(){
        $key = get_option( 'fame_api_secret_key' );
        if ( ! $key ) {
            return false;
        }
        $r =  $this->api_request( 'check_api_key', array(
            'api_key' => $key
        ) );

        if ( !$r ) {
            return false;
        } else if ( $r['success'] ) {
            update_option( 'fame_api_connect_info', $r['data'] );
            return true;
        }
        return false;
    }

    function display(){
        $date_format = get_option( 'date_format' );

        $url = add_query_arg( array(
                'fame_api_action' => 'authorize',
                'url' => base64_encode( home_url('') )
            ),
            $this->api_end_point
        );


        if ( isset( $_REQUEST['secret_key'] ) && $_REQUEST['secret_key'] != '' ) {
            $this->update_secret_key(  $_REQUEST['secret_key'] );
        }

        if ( isset( $_REQUEST['auto_update_id'] ) && $_REQUEST['auto_update_id'] != '' ) {

        }

        $check_api = $this->check_api_key();

        ?>
        <div class="wrap">
            <?php
            if ( ! $check_api ) { ?>
                <h1><?php esc_html_e( 'Connect to FameThemes', 'ft-helper' ); ?></h1>
                <a class="button-primary" href="<?php echo esc_url( $url );  ?>"><?php esc_html_e( 'Connect', 'ft-helper' ); ?></a>
            <?php
            } else {
                ?>
                <h1><?php esc_html_e( 'FameThemes Licenses', 'ft-helper' ); ?></h1>

                <table class="wp-list-table widefat striped fixed posts">
                    <thead>
                        <tr>
                            <th class="column-primary">License</th>
                            <th style="width: 120px;">Expiration</th>
                            <th style="width: 100px; text-align: center;">Activations</th>
                        </tr>
                    </thead>
                    <tbody id="the-list">
                        <?php foreach ( (array) $this->get_items() as $id => $item ){

                            $is_installed = false;
                            if ( $item['files'] ) {
                                foreach ( $item['files'] as $item_slug => $file ) {
                                    if ( ! $is_installed ) {
                                        $is_installed = $this->is_installed( $item_slug );
                                    }
                                }
                            }
                            
                            if ( $is_installed ) {
                                ?>
                                <tr>
                                    <td class="column-primary has-row-actions">
                                        <?php echo esc_html($item['title']); ?>
                                        <div class="row-actions">
                                            <span class="edit"><a title="<?php esc_html_e( 'Enable auto update', 'ft-helper' ); ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'famethemes-helper', 'auto_update' => $item['key'], 'auto_update_id' => $id ), admin_url('index.php') ) ); ?>">Enable auto update</a></span>
                                        </div>
                                    </td>
                                    <td><?php echo $item['expiration'] ? date_i18n($date_format, $item['expiration']) : esc_html__('Never', 'fame-helper'); ?></td>
                                    <td style="text-align: center;"><?php echo esc_html($item['site_count']) . '/' . $item['limit']; ?></td>
                                </tr>
                                <?php
                            }
                        } ?>
                    </tbody>
                </table>
                <?php
                $connect_info = get_option( 'fame_api_connect_info' );
                ?>
                <p><?php
                    printf(
                        esc_html__( 'Your are connect as %1$s, %2$s | %3$s', 'ft-helper' ),
                        '<strong>'.$connect_info['display_name'].'</strong>',
                        '<a class="ft-change-account" href="'.esc_url( $url ).'">'.esc_html__( 'Change Account', 'ft-helper' ).'</a>',
                        '<a class="ft-change-account" href="'.esc_url( add_query_arg( array(
                            'fame_api_action' => 'unauthorize',
                            'url' => base64_encode( home_url('') )
                        ),
                            $this->api_end_point
                        ) ).'">'.esc_html__( 'Disconnect', 'ft-helper' ).'</a>'

                    ); ?></p>
                <?php
            }

            ?>
        </div>
        <?php
    }

    function api_request( $action = '', $data = array() ){
        $key = get_option( 'fame_api_secret_key' );
        $params = array(
            'api_key' => $key,
            'fame_api_action' => $action,
            'site_url' => home_url('')
        );

        global $wp_version;

        $params = wp_parse_args( $params, $data );

        $r =  wp_remote_post( $this->api_end_point, array(
                'timeout' => 15,
                'body' => $params,
                'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url')
            ) );

        if ( ! is_wp_error( $r ) && 200 == wp_remote_retrieve_response_code( $r ) ) {
            $api_response = @json_decode(wp_remote_retrieve_body($r), true);
            if ( ! is_array( $api_response ) ) {
                return false;
            }
            return $api_response;
        }
        return false;
    }


    function get_items(){
        $api_response = $this->api_request( 'get_items' );
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
        $items = $this->get_item_slugs( 'theme' );

        $response = $this->api_request( 'check_themes_update', array(
            'themes' => $items,
        ) );
        if ( is_array( $response ) && isset( $response['success'] ) && $response['success'] ) {
            foreach ( (array) $response['data'] as $theme_base => $info ) {
                $checked_data->response[ $theme_base ] = $info;
            }
        }

        return $checked_data;
    }


    function check_for_plugin_update( $checked_data ) {
        global $wp_version;

        if( ! is_object( $checked_data ) ) {
            $checked_data = new stdClass;
        }

        $items = $this->get_item_slugs( 'plugin' );
        $response = $this->api_request( 'check_plugins_update', array(
            'plugins' => $items,
        ) );
        if ( is_array( $response ) && isset( $response['success'] ) && $response['success'] ) {
            foreach ( (array) $response['data'] as $plugin_slug => $info ) {
                $info = (object) $info;
                $checked_data->response[ $plugin_slug ] = $info;
                $checked_data->checked[ $plugin_slug ] = $info->new_version;
            }
            $checked_data->last_checked = time();
        }

        return $checked_data;
    }

    function plugin_api_call($_data, $action, $args) {
        if ( $action != 'plugin_information' ) {
            return $_data;
        }

        $plugin_slug = $args->slug;
        $plugins = $this->get_item_slugs('plugin');
        if ( ! isset( $plugins[ $plugin_slug ] ) ) {
            return $_data;
        }

        // Get the current version
        $plugin_info = get_site_transient('update_plugins');
        $current_version = $plugin_info->checked[$plugin_slug .'/'. $plugin_slug .'.php'];
        $args->version = $current_version;

        if ( isset ( $plugin_info->response[ $plugin_slug .'/'. $plugin_slug .'.php' ] ) ) {
            $_data = $plugin_info->response[ $plugin_slug .'/'. $plugin_slug .'.php' ];
        }

        return $_data;
    }

}

if ( is_admin() ) {
    new FameThemes_Helper();
}


