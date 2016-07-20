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
        add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );

        add_action( 'init', array( $this, 'init' ) );

        add_action( 'wp_ajax_fame_helper_api', array( $this, 'ajax' ) );
    }

    function ajax(){

        $do = isset( $_REQUEST['fame_helper'] ) ? $_REQUEST['fame_helper'] : '';
        if ( $do == 'enable_auto_update' ) {
            $nonce = $_REQUEST['nonce'];
            if ( ! wp_verify_nonce( $nonce , 'fame-helper' ) ) {
                die( 'security_check' );
            }
            $id = $_REQUEST['id'];
            $r = $this->enable_auto_update( $id );
            if ( is_array( $r ) && $r['success'] ) {
                $activated_items = get_option( 'fam_api_activated_items' );
                if ( ! is_array( $activated_items ) ) {
                    $activated_items = array();
                }
                $activated_items[ $id ] = $r;
                update_option( 'fam_api_activated_items', $activated_items );
                wp_send_json( $r );
            } else {
                wp_send_json_error();
            }
        } else if ( $do == 'disable_auto_update' ) {

        }


        die();
    }

    function enable_auto_update( $license_id ) {
        $r = $this->api_request( 'enable_auto_update', array(
            'license_id' => $license_id,
        ) );

        return $r;
    }

    function init(){
        if ( is_admin() ) {
            if (isset($_REQUEST['page']) && $_REQUEST['page'] == 'famethemes-helper') {
                if ( isset( $_REQUEST['secret_key'] ) ) {
                    $this->update_secret_key( $_REQUEST['secret_key'] );
                    $redirect =  add_query_arg( array( 'page' => 'famethemes-helper' ), admin_url( 'admin.php' ) );
                    wp_redirect( $redirect );
                    die();
                }
            }
        }
    }

    function load_scripts( $hook ) {
        if ( $hook == 'dashboard_page_famethemes-helper' ) {
            $url = trailingslashit( plugins_url('/', __FILE__) );
            wp_enqueue_script( 'famethemes-helper', $url . 'js/helper.js', array( 'jquery' ), false, true );
            wp_localize_script( 'famethemes-helper', 'FtHelper', array(
                'nonce' => wp_create_nonce( 'fame-helper' ),
                'ajax'  => admin_url( 'admin-ajax.php' ),
            ) );
        }
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

    function update_secret_key( $key ){
        update_option( 'fame_api_secret_key', $key );
    }


    function check_api_key( ){

        $r = $this->api_request( 'check_api_key' );

        if ( ! $r ) {
            return false;
        } else if ( $r['success'] ) {
            update_option( 'fame_api_connect_info', $r['data'] );
            return true;
        }

        return false;
    }

    function disconnect(){
        $r = $this->api_request( 'unauthorize' );
        delete_option( 'fame_api_secret_key' );
    }

    function display(){
        $date_format = get_option( 'date_format' );

        if ( isset( $_REQUEST['disconnect'] ) && $_REQUEST['disconnect'] == 1 ) {
            $this->disconnect();
        }

        $url = add_query_arg( array(
                'fame_api_action' => 'authorize',
                'url' => base64_encode( home_url('') )
            ),
            $this->api_end_point
        );

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

                $activated_items = get_option( 'fam_api_activated_items' );
                if ( ! is_array( $activated_items ) ) {
                    $activated_items = array();
                }

                ?>
                <h1><?php esc_html_e( 'FameThemes Licenses', 'ft-helper' ); ?></h1>

                <table class="wp-list-table widefat striped fixed posts">
                    <thead>
                        <tr>
                            <th class="column-primary">License</th>
                            <th style="width: 120px;">Auto Update</th>
                            <th style="width: 120px;">Expiration</th>
                            <th style="width: 100px; text-align: center;">Activations</th>
                        </tr>
                    </thead>
                    <tbody id="the-list">
                        <?php foreach ( (array) $this->get_items( true ) as $id => $item ){

                            $is_installed = false;
                            if ( $item['files'] ) {
                                foreach ( $item['files'] as $item_slug => $file ) {
                                    if ( ! $is_installed ) {
                                        $is_installed = $this->is_installed( $item_slug );
                                    }
                                }
                            }

                            $is_auto_update = isset( $activated_items[ $item['id'] ] );

                            if ( $is_installed ) {
                                ?>
                                <tr>
                                    <td class="column-primary has-row-actions">
                                        <?php echo esc_html($item['title']); ?>
                                        <div class="row-actions">
                                            <span class="edit"><a class="enable-auto-update" title="<?php esc_html_e( 'Enable auto update', 'ft-helper' ); ?>" data-id="<?php echo esc_attr( $item['id'] ); ?>" href="#">Enable auto update</a></span>
                                        </div>
                                    </td>
                                    <td><?php echo $is_auto_update ? '<span class="dashicons dashicons-yes"></span>' : '<span class="dashicons dashicons-no-alt"></span>' ?></td>
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
                        '<a class="ft-change-account" href="'. esc_url( add_query_arg( array( 'page' => 'famethemes-helper', 'disconnect' => 1 ), admin_url('index.php') ) )
                          .'">'.esc_html__( 'Disconnect', 'ft-helper' ).'</a>'

                    ); ?></p>
                <?php
            }

            ?>
        </div>
        <?php
    }

    function api_request( $action = '', $data = array() ){
        $key = get_option( 'fame_api_secret_key' );
        if ( ! $key ) {
            return false;
        }
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


    function get_items( $force = false ){
        $key = 'fame_api_get_items';
        $items = false;
        if ( ! $force ) {
            $items = get_transient( $key );
            if (false !== $items) {
                return $items;
            }
        }

        $api_response = $this->api_request( 'get_items' );
        if ( is_array( $api_response ) && isset( $api_response['success'] ) && $api_response['success'] ) {
            $items = $api_response['data']['items'];
            set_transient( $key, $items, 1 * HOUR_IN_SECONDS );
        } else {
            delete_transient( $key );
        }

        return $items;
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

        //global $pagenow;
        //echo $pagenow; die();
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


