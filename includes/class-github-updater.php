<?php

/**
 * Adds native WordPress update checks backed by the GitHub main branch.
 */
class OILM_GitHub_Updater {

	private const CACHE_TTL = HOUR_IN_SECONDS;

	private $plugin_file;
	private $plugin_basename;
	private $plugin_slug;
	private $version;
	private $owner;
	private $repo;
	private $branch;
	private $api_url;
	private $cache_key;

	public function __construct( $plugin_file, $version, $owner, $repo, $branch = 'main' ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_basename = plugin_basename( $plugin_file );
		$this->plugin_slug     = dirname( $this->plugin_basename );
		if ( '.' === $this->plugin_slug ) {
			$this->plugin_slug = basename( $this->plugin_basename, '.php' );
		}
		$this->version         = $version;
		$this->owner           = $owner;
		$this->repo            = $repo;
		$this->branch          = $branch;
		$this->api_url         = 'https://api.github.com/repos/' . rawurlencode( $owner ) . '/' . rawurlencode( $repo );
		$this->cache_key       = 'oilm_github_update_' . md5( $owner . '/' . $repo . '/' . $branch );
	}

	public function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'site_transient_update_plugins', array( $this, 'remove_stale_update_notice' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'download_private_package' ), 10, 4 );
		add_filter( 'upgrader_source_selection', array( $this, 'rename_github_source' ), 10, 4 );
	}

	public function check_for_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$remote = $this->get_remote_plugin_data( true );

		if ( empty( $remote['version'] ) || ! version_compare( $remote['version'], $this->installed_version(), '>' ) ) {
			return $this->mark_as_current( $transient, isset( $remote['version'] ) ? $remote['version'] : '' );
		}

		$transient->response[ $this->plugin_basename ] = $this->update_payload( $remote );

		return $transient;
	}

	public function remove_stale_update_notice( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->response[ $this->plugin_basename ]->new_version ) ) {
			return $transient;
		}

		if ( version_compare( $transient->response[ $this->plugin_basename ]->new_version, $this->installed_version(), '<=' ) ) {
			return $this->mark_as_current( $transient, $transient->response[ $this->plugin_basename ]->new_version );
		}

		return $transient;
	}

	public function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		$remote = $this->get_remote_plugin_data( true );

		if ( empty( $remote['version'] ) ) {
			return $result;
		}

		return (object) array(
			'name'          => 'OP Internal Link Manager',
			'slug'          => $this->plugin_slug,
			'version'       => $remote['version'],
			'author'        => '<a href="https://github.com/' . esc_attr( $this->owner ) . '">Ritesh OutpaceSeo</a>',
			'homepage'      => $this->github_url(),
			'download_link' => $this->zip_url(),
			'requires'      => $remote['requires'],
			'requires_php'  => $remote['requires_php'],
			'tested'        => $remote['tested'],
			'sections'      => array(
				'description' => $remote['description'],
				'changelog'   => $remote['changelog'],
			),
		);
	}

	public function rename_github_source( $source, $remote_source, $upgrader, $hook_extra ) {
		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
			return $source;
		}

		$target = trailingslashit( $remote_source ) . $this->plugin_slug;

		if ( trailingslashit( $source ) === trailingslashit( $target ) ) {
			return $source;
		}

		if ( is_dir( $target ) ) {
			return $source;
		}

		if ( ! rename( $source, $target ) ) {
			return $source;
		}

		return trailingslashit( $target );
	}

	public function download_private_package( $reply, $package, $upgrader, $hook_extra ) {
		if ( ! empty( $reply ) || empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
			return $reply;
		}

		if ( $package !== $this->zip_url() || ! $this->github_token() ) {
			return $reply;
		}

		$download_file = wp_tempnam( $package );

		if ( ! $download_file ) {
			return new WP_Error( 'oilm_no_temp_file', __( 'Could not create a temporary file for the GitHub update.', 'op-internal-link-manager' ) );
		}

		$response = wp_remote_get(
			$package,
			array(
				'timeout'  => 300,
				'stream'   => true,
				'filename' => $download_file,
				'headers'  => $this->github_headers( 'application/vnd.github+json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			@unlink( $download_file );
			return $response;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			@unlink( $download_file );
			return new WP_Error( 'oilm_github_download_failed', __( 'GitHub returned an error while downloading the plugin update.', 'op-internal-link-manager' ) );
		}

		return $download_file;
	}

	private function get_remote_plugin_data( $force_refresh = false ) {
		$cached = $force_refresh ? false : get_site_transient( $this->cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$defaults = array(
			'version'      => '',
			'requires'     => '5.0',
			'tested'       => '6.8',
			'requires_php' => '8.0',
			'description'  => '',
			'changelog'    => '',
		);

		$plugin_file = $this->remote_get( $this->raw_url( basename( $this->plugin_file ) ) );
		$readme      = $this->remote_get( $this->raw_url( 'readme.txt' ) );

		if ( ! $plugin_file ) {
			set_site_transient( $this->cache_key, $defaults, $this->cache_ttl() );
			return $defaults;
		}

		$data = array_merge(
			$defaults,
			array(
				'version'      => $this->read_header( $plugin_file, 'Version' ),
				'requires'     => $this->read_readme_value( $readme, 'Requires at least' ) ?: $defaults['requires'],
				'tested'       => $this->read_readme_value( $readme, 'Tested up to' ) ?: $defaults['tested'],
				'requires_php' => $this->read_readme_value( $readme, 'Requires PHP' ) ?: $defaults['requires_php'],
				'description'  => $this->read_readme_section( $readme, 'Description' ),
				'changelog'    => $this->read_readme_section( $readme, 'Changelog' ),
			)
		);

		set_site_transient( $this->cache_key, $data, $this->cache_ttl() );

		return $data;
	}

	private function update_payload( $remote ) {
		return (object) array(
			'id'           => $this->github_url(),
			'slug'         => $this->plugin_slug,
			'plugin'       => $this->plugin_basename,
			'new_version'  => $remote['version'],
			'url'          => $this->github_url(),
			'package'      => $this->zip_url(),
			'tested'       => $remote['tested'],
			'requires'     => $remote['requires'],
			'requires_php' => $remote['requires_php'],
		);
	}

	private function mark_as_current( $transient, $remote_version ) {
		unset( $transient->response[ $this->plugin_basename ] );

		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = array();
		}

		$transient->no_update[ $this->plugin_basename ] = $this->update_payload(
			array(
				'version'      => $remote_version ?: $this->installed_version(),
				'tested'       => '6.8',
				'requires'     => '5.0',
				'requires_php' => '8.0',
			)
		);

		return $transient;
	}

	private function installed_version() {
		if ( function_exists( 'get_file_data' ) ) {
			$data = get_file_data( $this->plugin_file, array( 'Version' => 'Version' ), 'plugin' );

			if ( ! empty( $data['Version'] ) ) {
				return (string) $data['Version'];
			}
		}

		return $this->version;
	}

	private function remote_get( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => $this->github_headers( 'application/vnd.github.raw' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		return (string) wp_remote_retrieve_body( $response );
	}

	private function read_header( $contents, $header ) {
		if ( ! preg_match( '/^[ \t\/*#@]*' . preg_quote( $header, '/' ) . ':(.*)$/mi', $contents, $matches ) ) {
			return '';
		}

		return trim( $matches[1] );
	}

	private function read_readme_value( $readme, $label ) {
		if ( ! $readme || ! preg_match( '/^' . preg_quote( $label, '/' ) . ':\s*(.+)$/mi', $readme, $matches ) ) {
			return '';
		}

		return trim( $matches[1] );
	}

	private function read_readme_section( $readme, $section ) {
		if ( ! $readme || ! preg_match( '/==\s*' . preg_quote( $section, '/' ) . '\s*==\s*(.*?)(?=\n==\s*.+?\s*==|\z)/is', $readme, $matches ) ) {
			return '';
		}

		return wp_kses_post( wpautop( trim( $matches[1] ) ) );
	}

	private function raw_url( $path ) {
		return sprintf(
			'%s/contents/%s?ref=%s',
			$this->api_url,
			str_replace( '%2F', '/', rawurlencode( ltrim( $path, '/' ) ) ),
			rawurlencode( $this->branch )
		);
	}

	private function zip_url() {
		return sprintf(
			'%s/zipball/%s',
			$this->api_url,
			rawurlencode( $this->branch )
		);
	}

	private function github_url() {
		return sprintf(
			'https://github.com/%s/%s',
			rawurlencode( $this->owner ),
			rawurlencode( $this->repo )
		);
	}

	private function cache_ttl() {
		return (int) apply_filters( 'oilm_github_update_cache_ttl', self::CACHE_TTL );
	}

	private function github_headers( $accept ) {
		$headers = array(
			'Accept'               => $accept,
			'X-GitHub-Api-Version' => '2022-11-28',
			'User-Agent'           => 'OP-Internal-Link-Manager-Updater',
		);

		$token = $this->github_token();

		if ( $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		return $headers;
	}

	private function github_token() {
		$token = defined( 'OILM_GITHUB_TOKEN' ) ? OILM_GITHUB_TOKEN : '';

		return trim( (string) apply_filters( 'oilm_github_updater_token', $token ) );
	}
}
