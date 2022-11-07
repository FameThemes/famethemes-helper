<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GitHubPluginUpdater {

    private $slug; // plugin slug
    private $pluginData; // plugin data
    private $username; // GitHub username
    private $repo; // GitHub repo name
    private $pluginFile; // __FILE__ of our plugin
    private $githubAPIResult; // holds data from GitHub
    private $accessToken; // GitHub private repo token

    function __construct( $pluginFile, $gitHubUsername, $gitHubProjectName, $accessToken = '' ) {
        add_filter( "pre_set_site_transient_update_plugins", array( $this, "setTransitent" ) );
        add_filter( "plugins_api", array( $this, "setPluginInfo" ), 10, 3 );
        add_filter( "upgrader_post_install", array( $this, "postInstall" ), 10, 3 );

        $this->slug = plugin_basename( $this->pluginFile );

        $this->pluginFile   = $pluginFile;
        $this->username     = $gitHubUsername;
        $this->repo         = $gitHubProjectName;
        $this->accessToken  = $accessToken;
    }

    // Get information regarding our plugin from WordPress
    private function initPluginData() {
        $this->pluginData = get_plugin_data( $this->pluginFile );
    }

    // Get information regarding our plugin from GitHub
    public function getRepoReleaseInfo() {
        // Only do this once
        if ( ! empty( $this->githubAPIResult ) ) {
            return;
        }

        // Query the GitHub API
        $url = "https://api.github.com/repos/{$this->username}/{$this->repo}/releases";

        // We need the access token for private repos
        if ( ! empty( $this->accessToken ) ) {
            $url = add_query_arg( array( "access_token" => $this->accessToken ), $url );
        }

        // Get the results
        $this->githubAPIResult = wp_remote_retrieve_body( wp_remote_get( $url ) );
        if ( ! empty( $this->githubAPIResult ) ) {
            $this->githubAPIResult = @json_decode( $this->githubAPIResult );
        }

        // Use only the latest release
        if ( is_array( $this->githubAPIResult ) ) {
            $this->githubAPIResult = current( $this->githubAPIResult );
        }

    }

    // Push in plugin version information to get the update notification
    public function setTransitent( $transient ) {
        // If we have checked the plugin data before, don't re-check
        if ( empty( $transient->checked ) ) {
            return $transient;
        }
        $this->slug = plugin_basename( $this->pluginFile );
        // Get plugin & GitHub release information
        $this->initPluginData();
        $this->getRepoReleaseInfo();
        if ( empty( $this->githubAPIResult ) ) {
            return $transient;
        }

        if ( ! isset( $transient->checked[$this->slug] ) ) {
            return $transient;
        }

        // Check the versions if we need to do an update
        $ver = str_replace( 'v', '', $this->githubAPIResult->tag_name );
        $doUpdate = version_compare( $ver, $transient->checked[$this->slug] );

        // Update the transient to include our updated plugin data
        if ( $doUpdate == 1 ) {
            $package = $this->githubAPIResult->zipball_url;

            // Include the access token for private GitHub repos
            if ( !empty( $this->accessToken ) ) {
                $package = add_query_arg( array( "access_token" => $this->accessToken ), $package );
            }

            $obj = new stdClass();
            $obj->slug = $this->slug;
            $obj->new_version = $this->githubAPIResult->tag_name;
            $obj->url = $this->pluginData["PluginURI"];
            $obj->package = $package;
            $transient->response[$this->slug] = $obj;
        }


        return $transient;
    }

    // Push in plugin version information to display in the details lightbox
    public function setPluginInfo( $false, $action, $response ) {
        // Get plugin & GitHub release information
        $this->slug = plugin_basename( $this->pluginFile );
        $this->initPluginData();
        $this->getRepoReleaseInfo();

        // If nothing is found, do nothing
        if ( empty( $response->slug ) || $response->slug != $this->slug ) {
            return false;
        }
        
        var_dump(  $this->slug  );

        // Add our plugin information
        $response->last_updated = $this->githubAPIResult->published_at;
        $response->slug = $this->slug;
        $response->plugin_name  = $this->pluginData["Name"];
        $response->name  = $this->pluginData["Name"];
        $response->version = $this->githubAPIResult->tag_name;
        $response->author = $this->pluginData["AuthorName"];
        $response->homepage = $this->pluginData["PluginURI"];

        // This is our release download zip file
        $downloadLink = $this->githubAPIResult->zipball_url;

        // Include the access token for private GitHub repos
        if ( !empty( $this->accessToken ) ) {
            $downloadLink = add_query_arg(
                array( "access_token" => $this->accessToken ),
                $downloadLink
            );
        }
        $response->download_link = $downloadLink;

        // We're going to parse the GitHub markdown release notes, include the parser
        if ( ! class_exists( 'Parsedown' ) ) {
            require_once( dirname( __FILE__ ) . "/Parsedown.php" );
        }

        // Create tabs in the lightbox
        $response->sections = array(
            'description' => $this->pluginData["Description"],
            'changelog' => class_exists( "Parsedown" )
                ? Parsedown::instance()->parse( $this->githubAPIResult->body )
                : $this->githubAPIResult->body
        );

        // Gets the required version of WP if available
        $matches = null;
        preg_match( "/requires:\s([\d\.]+)/i", $this->githubAPIResult->body, $matches );
        if ( ! empty( $matches ) ) {
            if ( is_array( $matches ) ) {
                if ( count( $matches ) > 1 ) {
                    $response->requires = $matches[1];
                }
            }
        }

        // Gets the tested version of WP if available
        $matches = null;
        preg_match( "/tested:\s([\d\.]+)/i", $this->githubAPIResult->body, $matches );
        if ( ! empty( $matches ) ) {
            if ( is_array( $matches ) ) {
                if ( count( $matches ) > 1 ) {
                    $response->tested = $matches[1];
                }
            }
        }

        return $response;
    }

    // Perform additional actions to successfully install our plugin
    public function postInstall( $true, $hook_extra, $result ) {
        if ( $result && strpos( $result['destination_name'], 'famethemes-helper' ) !== false ) {
            // Since we are hosted in GitHub, our plugin folder would have a dirname of
            // reponame-tagname change it to our original one:
            global $wp_filesystem;
            $this->slug = plugin_basename( $this->pluginFile );
            $pluginFolder = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname($this->slug);
            $wp_filesystem->move($result['destination'], $pluginFolder);
            $result['destination'] = $pluginFolder;

            // Re-activate plugin if needed
            activate_plugin( $this->slug );
        }

        return $result;
    }
}

$updater = new GitHubPluginUpdater( FAMETHEMES_FILE, 'FameThemes', "famethemes-helper" );

