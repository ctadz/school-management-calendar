<?php
/**
 * GitHub-based Plugin Update Checker
 *
 * Enables automatic updates for WordPress plugins hosted on GitHub.
 * Checks GitHub releases for new versions and integrates with WordPress update system.
 *
 * @package    School_Management_Calendar
 * @subpackage School_Management_Calendar/includes
 * @since      1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SMC_GitHub_Updater
 *
 * Handles plugin updates from GitHub releases.
 */
class SMC_GitHub_Updater {

	/**
	 * Plugin slug
	 *
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Plugin basename (e.g., 'school-management-calendar/school-management-calendar.php')
	 *
	 * @var string
	 */
	private $plugin_basename;

	/**
	 * GitHub repository (e.g., 'username/repo-name')
	 *
	 * @var string
	 */
	private $github_repo;

	/**
	 * Current plugin version
	 *
	 * @var string
	 */
	private $current_version;

	/**
	 * Plugin data from get_plugin_data()
	 *
	 * @var array
	 */
	private $plugin_data;

	/**
	 * GitHub API token (optional, for private repos or higher rate limits)
	 *
	 * @var string|null
	 */
	private $github_token;

	/**
	 * Transient cache key
	 *
	 * @var string
	 */
	private $cache_key;

	/**
	 * Cache expiration in seconds (default: 12 hours)
	 *
	 * @var int
	 */
	private $cache_expiration = 43200;

	/**
	 * Constructor
	 *
	 * @param string      $plugin_file     Full path to main plugin file.
	 * @param string      $github_repo     GitHub repository in format 'username/repo'.
	 * @param string|null $github_token    Optional GitHub personal access token.
	 */
	public function __construct( $plugin_file, $github_repo, $github_token = null ) {
		// Require plugin.php for get_plugin_data()
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$this->plugin_basename   = plugin_basename( $plugin_file );
		$this->plugin_slug       = dirname( $this->plugin_basename );
		$this->github_repo       = $github_repo;
		$this->github_token      = $github_token;
		$this->plugin_data       = get_plugin_data( $plugin_file );
		$this->current_version   = $this->plugin_data['Version'];
		$this->cache_key         = 'smc_github_update_' . md5( $this->github_repo );

		// Register hooks
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		// Check for updates
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );

		// Provide plugin information for the update details screen
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );

		// After update, clear cache
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ), 10, 2 );
	}

	/**
	 * Check for plugin updates
	 *
	 * @param object $transient Update transient object.
	 * @return object Modified transient object.
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		// Get update information
		$update_data = $this->get_update_data();

		// If there's a newer version available
		if ( $update_data && version_compare( $this->current_version, $update_data->version, '<' ) ) {
			$transient->response[ $this->plugin_basename ] = $update_data;
		} else {
			// Mark as no update available
			$transient->no_update[ $this->plugin_basename ] = $update_data;
		}

		return $transient;
	}

	/**
	 * Get update data from GitHub
	 *
	 * @return object|false Update data object or false on failure.
	 */
	private function get_update_data() {
		// Check cache first
		$cached_data = get_transient( $this->cache_key );
		if ( false !== $cached_data ) {
			return $cached_data;
		}

		// Fetch latest release from GitHub
		$release = $this->fetch_github_release();
		if ( ! $release ) {
			return false;
		}

		// Build update object
		$update_data = (object) array(
			'slug'          => $this->plugin_slug,
			'plugin'        => $this->plugin_basename,
			'new_version'   => $release->tag_name,
			'url'           => $release->html_url,
			'package'       => $release->zipball_url,
			'tested'        => $this->get_tested_wp_version( $release ),
			'requires_php'  => $this->plugin_data['RequiresPHP'],
			'compatibility' => new stdClass(),
		);

		// Cache the result
		set_transient( $this->cache_key, $update_data, $this->cache_expiration );

		return $update_data;
	}

	/**
	 * Fetch latest release from GitHub API
	 *
	 * @return object|false Release object or false on failure.
	 */
	private function fetch_github_release() {
		$api_url = "https://api.github.com/repos/{$this->github_repo}/releases/latest";

		// Build request arguments
		$args = array(
			'headers' => array(
				'Accept' => 'application/vnd.github.v3+json',
			),
		);

		// Add authorization header if token is provided
		if ( $this->github_token ) {
			$args['headers']['Authorization'] = 'token ' . $this->github_token;
		}

		// Make API request
		$response = wp_remote_get( $api_url, $args );

		// Check for errors
		if ( is_wp_error( $response ) ) {
			error_log( 'GitHub API Error: ' . $response->get_error_message() );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			error_log( "GitHub API returned status code: {$response_code}" );
			return false;
		}

		// Parse response
		$body = wp_remote_retrieve_body( $response );
		$release = json_decode( $body );

		if ( ! $release || empty( $release->tag_name ) ) {
			return false;
		}

		return $release;
	}

	/**
	 * Provide plugin information for the update details screen
	 *
	 * @param false|object|array $result The result object or array.
	 * @param string             $action The type of information being requested.
	 * @param object             $args   Plugin API arguments.
	 * @return false|object Modified result object.
	 */
	public function plugin_info( $result, $action, $args ) {
		// Only proceed if this is our plugin
		if ( 'plugin_information' !== $action || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		// Fetch latest release
		$release = $this->fetch_github_release();
		if ( ! $release ) {
			return $result;
		}

		// Build plugin info object
		$plugin_info = (object) array(
			'name'          => $this->plugin_data['Name'],
			'slug'          => $this->plugin_slug,
			'version'       => $release->tag_name,
			'author'        => $this->plugin_data['Author'],
			'author_profile'=> $this->plugin_data['AuthorURI'],
			'homepage'      => $this->plugin_data['PluginURI'],
			'requires'      => $this->plugin_data['RequiresWP'],
			'requires_php'  => $this->plugin_data['RequiresPHP'],
			'tested'        => $this->get_tested_wp_version( $release ),
			'last_updated'  => $release->published_at,
			'sections'      => array(
				'description' => $this->plugin_data['Description'],
				'changelog'   => $this->parse_changelog( $release ),
			),
			'download_link' => $release->zipball_url,
		);

		return $plugin_info;
	}

	/**
	 * Parse changelog from release notes
	 *
	 * @param object $release GitHub release object.
	 * @return string Formatted changelog HTML.
	 */
	private function parse_changelog( $release ) {
		if ( empty( $release->body ) ) {
			return '<h4>' . esc_html( $release->tag_name ) . '</h4><p>No changelog available.</p>';
		}

		// Convert markdown to HTML (basic conversion)
		$changelog = wp_kses_post( $release->body );
		$changelog = wpautop( $changelog );

		return '<h4>' . esc_html( $release->tag_name ) . ' - ' . esc_html( date( 'Y-m-d', strtotime( $release->published_at ) ) ) . '</h4>' . $changelog;
	}

	/**
	 * Get tested WordPress version from release
	 *
	 * @param object $release GitHub release object.
	 * @return string WordPress version string.
	 */
	private function get_tested_wp_version( $release ) {
		// Try to extract from release body
		if ( ! empty( $release->body ) && preg_match( '/Tested up to:?\s*(\d+\.\d+(?:\.\d+)?)/i', $release->body, $matches ) ) {
			return $matches[1];
		}

		// Default to current WordPress version
		global $wp_version;
		return $wp_version;
	}

	/**
	 * Clear update cache after plugin update
	 *
	 * @param WP_Upgrader $upgrader WP_Upgrader instance.
	 * @param array       $options  Array of bulk item update data.
	 */
	public function clear_cache( $upgrader, $options ) {
		if ( 'update' === $options['action'] && 'plugin' === $options['type'] ) {
			if ( isset( $options['plugins'] ) && in_array( $this->plugin_basename, $options['plugins'], true ) ) {
				delete_transient( $this->cache_key );
			}
		}
	}

	/**
	 * Manually check for updates (bypass cache)
	 *
	 * @return object|false Update data or false.
	 */
	public function force_check() {
		delete_transient( $this->cache_key );
		return $this->get_update_data();
	}
}
