<?php

/**
 * Adds native WordPress update checks backed by GitHub releases or tags.
 */
class OILM_GitHub_Updater {

	private $plugin_file;
	private $plugin_basename;
	private $plugin_slug;
	private $version;
	private $owner;
	private $repo;
	private $api_url;
	private $cache_key;

	public function __construct( $plugin_file, $version, $owner, $repo ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_basename = plugin_basename( $plugin_file );
		$this->plugin_slug     = dirname( $this->plugin_basename );
		if ( '.' === $this->plugin_slug ) {
			$this->plugin_slug = basename( $this->plugin_basename, '.php' );
		}
		$this->version         = $version;
		$this->owner           = $owner;
		$this->repo            = $repo;
		$this->api_url         = 'https://api.github.com/repos/' . rawurlencode( $owner ) . '/' . rawurlencode( $repo );
		$this->cache_key       = 'oilm_github_update_' . md5( $owner . '/' . $repo );
	}

	public function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'rename_github_source' ), 10, 4 );
		add_filter( 'http_request_args', array( $this, 'add_auth_header' ), 10, 2 );
	}

	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) || ! isset( $transient->checked[ $this->plugin_basename ] ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();

		if ( empty( $release ) || ! version_compare( $release['version'], $this->version, '>' ) ) {
			unset( $transient->response[ $this->plugin_basename ] );
			return $transient;
		}

		$transient->response[ $this->plugin_basename ] = (object) array(
			'id'          => $this->plugin_basename,
			'slug'        => $this->plugin_slug,
			'plugin'      => $this->plugin_basename,
			'new_version' => $release['version'],
			'url'         => $release['html_url'],
			'package'     => $release['download_url'],
			'tested'      => $release['tested'],
				'requires'     => $release['requires'],
			'requires_php' => $release['requires_php'],
		);

		return $transient;
	}

	public function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		$release = $this->get_latest_release();

		if ( empty( $release ) ) {
			return $result;
		}

		return (object) array(
			'name'          => 'OP Internal Link Manager',
			'slug'          => $this->plugin_slug,
			'version'       => $release['version'],
			'author'        => '<a href="https://github.com/' . esc_attr( $this->owner ) . '">Ritesh OutpaceSeo</a>',
			'homepage'      => $release['html_url'],
			'download_link' => $release['download_url'],
			'requires'      => $release['requires'],
			'requires_php'  => $release['requires_php'],
			'tested'        => $release['tested'],
			'sections'      => array(
				'description' => 'Automatically insert internal links into post/page content based on keyword-to-URL rules defined in the admin.',
				'changelog'   => $release['body'] ? wp_kses_post( wpautop( $release['body'] ) ) : 'See the GitHub release for details.',
			),
		);
	}

	public function rename_github_source( $source, $remote_source, $upgrader, $hook_extra ) {
		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
			return $source;
		}

		global $wp_filesystem;

		$target = trailingslashit( $remote_source ) . $this->plugin_slug;

		if ( trailingslashit( $source ) === trailingslashit( $target ) ) {
			return $source;
		}

		if ( $wp_filesystem->exists( $target ) ) {
			$wp_filesystem->delete( $target, true );
		}

		if ( $wp_filesystem->move( $source, $target ) ) {
			return $target;
		}

		return $source;
	}

	public function add_auth_header( $args, $url ) {
		if ( false === strpos( $url, $this->api_url ) ) {
			return $args;
		}

		$token = apply_filters( 'oilm_github_updater_token', '' );

		if ( ! $token ) {
			return $args;
		}

		if ( empty( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = array();
		}

		$args['headers']['Authorization'] = 'Bearer ' . $token;

		return $args;
	}

	private function get_latest_release() {
		$release = get_site_transient( $this->cache_key );

		if ( false !== $release ) {
			return $release;
		}

		$release = $this->request_latest_release();

		if ( empty( $release ) ) {
			$release = $this->request_latest_tag();
		}

		set_site_transient( $this->cache_key, $release, 6 * HOUR_IN_SECONDS );

		return $release;
	}

	private function request_latest_release() {
		$response = $this->github_get( $this->api_url . '/releases/latest' );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['tag_name'] ) ) {
			return false;
		}

		return $this->format_release(
			$data['tag_name'],
			isset( $data['zipball_url'] ) ? $data['zipball_url'] : '',
			isset( $data['html_url'] ) ? $data['html_url'] : '',
			isset( $data['body'] ) ? $data['body'] : ''
		);
	}

	private function request_latest_tag() {
		$response = $this->github_get( $this->api_url . '/tags?per_page=30' );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$tags = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $tags ) || ! is_array( $tags ) ) {
			return false;
		}

		usort(
			$tags,
			function( $a, $b ) {
				$a_version = isset( $a['name'] ) ? ltrim( $a['name'], 'vV' ) : '0.0.0';
				$b_version = isset( $b['name'] ) ? ltrim( $b['name'], 'vV' ) : '0.0.0';

				return version_compare( $b_version, $a_version );
			}
		);

		foreach ( $tags as $tag ) {
			if ( empty( $tag['name'] ) ) {
				continue;
			}

			$release = $this->format_release(
				$tag['name'],
				$this->api_url . '/zipball/' . rawurlencode( $tag['name'] ),
				'https://github.com/' . rawurlencode( $this->owner ) . '/' . rawurlencode( $this->repo ) . '/releases/tag/' . rawurlencode( $tag['name'] ),
				''
			);

			if ( $release ) {
				return $release;
			}
		}

		return false;
	}

	private function format_release( $tag, $download_url, $html_url, $body ) {
		$version = ltrim( $tag, 'vV' );

		if ( ! $download_url || ! preg_match( '/^\d+\.\d+\.\d+/', $version ) ) {
			return false;
		}

		return array(
			'version'      => $version,
			'tag'          => $tag,
			'download_url' => $download_url,
			'html_url'     => $html_url ? $html_url : 'https://github.com/' . rawurlencode( $this->owner ) . '/' . rawurlencode( $this->repo ),
			'body'         => $body,
			'requires'     => '5.0',
			'requires_php' => '8.0',
			'tested'       => '6.8',
		);
	}

	private function github_get( $url ) {
		$args = array(
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'OP-Internal-Link-Manager-Updater',
			),
			'timeout' => 10,
		);

		$token = apply_filters( 'oilm_github_updater_token', '' );

		if ( $token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}

		return wp_remote_get( $url, $args );
	}
}
