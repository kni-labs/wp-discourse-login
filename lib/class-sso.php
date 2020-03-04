<?php
/**
 *
 * SSO client
 *
 * @package WPDiscourseLogin
 */

namespace WPDiscourseLogin\SSO;

/**
 * Class SSO
 */
class SSO {



	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'parse_request' ), 5 );
		add_filter( 'query_vars', array( $this, 'discourse_sso_custom_query_vars' ) );
		add_action( 'parse_query', array( $this, 'discourse_sso_url_redirect' ) );
		add_filter( 'allowed_redirect_hosts', array( $this, 'add_allowed_redirects' ) );
		add_action( 'get_wpdlg_name', array( $this, 'get_wpdlg_name' ) );
		add_action( 'get_wpdlg_avatar', array( $this, 'get_wpdlg_avatar' ) );
		add_action( 'get_wpdlg_profile_link', array( $this, 'get_wpdlg_profile_link' ) );
	}

	/**
	 * Adds the configured WP Discourse URL to the list of allowed hosts
	 *
	 * @param array $hosts allowed redirect hosts.
	 * @return array
	 */
	public function add_allowed_redirects( $hosts ) {
		$hosts[] = wp_parse_url( get_option( 'wpdlg_discourse_url' ) )['host'];
		return $hosts;
	}

	/**
	 * Parse Request Hook
	 */
	public function parse_request() {
		if ( empty( $_GET['sso'] ) || empty( $_GET['sig'] ) ) {
			return;
		}

		if ( ! $this->is_valid_signature() ) {
			return;
		}

		$user_id = $this->get_user_id();

		if ( is_wp_error( $user_id ) ) {
			$this->handle_errors( $user_id );

			return;
		}

		$updated_user = $this->update_user( $user_id );
		if ( is_wp_error( $updated_user ) ) {
			$this->handle_errors( $updated_user );

			return;
		}

		$this->auth_user( $user_id );
	}

	/**
	 * Adds to query variabls
	 *
	 * @param array $vars query vars.
	 */
	public function discourse_sso_custom_query_vars( $vars ) {
		$vars[] = 'discourse_sso_new'; // naming?

		return $vars;
	}

	/**
	 * Adds redirects
	 *
	 * @param object $wp the wp_query.
	 */
	public function discourse_sso_url_redirect( $wp ) {
		if ( empty( $wp->query['discourse_sso_new'] ) ) {
			return;
		}

		$redirect_to = home_url( '/' );

		$payload_new = base64_encode(
			http_build_query(
				array(
					'nonce'          => Nonce::get_instance()->create( '_discourse_sso' ),
					'return_sso_url' => utf8_uri_encode( $redirect_to ),
				)
			)
		);

		$request = array(
			'sso' => $payload_new,
			'sig' => hash_hmac( 'sha256', $payload_new, get_option( 'wpdlg_discourse_secret' ) ),
		);

		$sso_login_url = get_option( 'wpdlg_discourse_url' ) . '/session/sso_provider?' . http_build_query( $request );
		wp_safe_redirect( esc_url_raw( $sso_login_url ) );
		exit;
	}

	/**
	 * Retrieves user ID
	 */
	public function get_user_id() {
		if ( is_user_logged_in() ) {
			$user_id  = get_current_user_id();
			$redirect = $this->get_sso_response( 'return_sso_url' );
			if ( get_user_meta( $user_id, get_option( 'wpdlg_discourse_meta' ), true ) ) {
				wp_safe_redirect( $redirect );

				exit;
			} else {
				$discourse_email = $this->get_sso_response( 'email' );
				$wp_email        = wp_get_current_user()->user_email;
				if ( $discourse_email === $wp_email ) {
					update_user_meta( $user_id, get_option( 'wpdlg_discourse_meta' ), $this->get_sso_response( 'external_id' ) );
					wp_safe_redirect( $redirect );
					exit;
				} else {
					$profile_url = get_edit_profile_url();
					wp_safe_redirect( $profile_url );
					exit;
				}
			}
		} else {
			$user_query         = new \WP_User_Query(
				array(
					'meta_key'   => get_option( 'wpdlg_discourse_meta' ),
					'meta_value' => $this->get_sso_response( 'external_id' ),
				)
			);
			$user_query_results = $user_query->get_results();
			if ( empty( $user_query_results ) ) {
				$user_password = wp_generate_password( 64, true );

				$user_id = wp_create_user(
					$this->get_sso_response( 'username' ),
					$user_password,
					$this->get_sso_response( 'email' )
				);
				return $user_id;
			}

			return $user_query_results[0]->ID;
		}
	}

	/**
	 * Parse SSO Response
	 *
	 * @param string $return_key The key to return.
	 *
	 * @return string|array
	 */
	public function get_sso_response( $return_key = '' ) {
		if ( empty( $_GET['sso'] ) ) {
			return null;
		}

		if ( 'raw' === $return_key ) {

			return sanitize_text_field( wp_unslash( $_GET['sso'] ) );
		}

		$sso = base64_decode( sanitize_text_field( wp_unslash( $_GET['sso'] ) ), true );

		if ( ! $sso ) {
			return null;
		}

		$response = array();

		parse_str( $sso, $response );
		$response = array_map( 'rawurldecode', $response );
		$response = array_map( 'sanitize_text_field', $response );

		if ( empty( $response['external_id'] ) ) {
			return null;
		}

		if ( ! empty( $return_key ) && isset( $response[ $return_key ] ) ) {
			return $response[ $return_key ];
		}

		return $response;
	}

	/**
	 * Set auth cookies
	 *
	 * @param  int $user_id the user ID.
	 * @return null
	 */
	public function auth_user( $user_id ) {
		$query = $this->get_sso_response();
		wp_set_current_user( $user_id, $query['username'] );
		wp_set_auth_cookie( $user_id );
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {

			return null;
		}
		do_action( 'wp_login', $query['username'], $user );

		$redirect_to = $query['return_sso_url'];
		wp_safe_redirect( $redirect_to );
		exit;

	}

	/**
	 * Handle Login errors
	 *
	 * @param \WP_Error $error WP_Error object.
	 */
	public function handle_errors( $error ) {
		$redirect_to = wp_login_url();

		$redirect_to = add_query_arg( 'discourse_sso_error', $error->get_error_code(), $redirect_to );

		wp_safe_redirect( $redirect_to );
		exit;
	}

	/**
	 * Update WP user with discourse user data
	 *
	 * @param int $user_id the user ID.
	 *
	 * @return int|\WP_Error integer if the update was successful, WP_Error otherwise.
	 */
	public function update_user( $user_id ) {
		$query = $this->get_sso_response();
		$nonce = Nonce::get_instance()->verify( $query['nonce'], '_discourse_sso' );
		if ( ! $nonce ) {
			return new \WP_Error( 'expired_nonce' );
		}

		$username     = $query['username'];
		$updated_user = array(
			'ID'            => $user_id,
			'user_nicename' => $username,
		);

		if ( ! empty( $query['name'] ) ) {
			$updated_user['first_name'] = explode( ' ', $query['name'] )[0];
			$updated_user['name']       = $query['name'];
		}

		$update = wp_update_user( $updated_user );
		if ( ! is_wp_error( $update ) ) {
			update_user_meta( $user_id, 'discourse_username', $username );

			if ( ! get_user_meta( $user_id, get_option( 'wpdlg_discourse_meta' ), true ) ) {
				update_user_meta( $user_id, get_option( 'wpdlg_discourse_meta' ), $query['external_id'] );
			}
		}

		return $update;
	}

	/**
	 * Validates SSO signature
	 *
	 * @return boolean
	 */
	public function is_valid_signature() {
		$sso = urldecode( $this->get_sso_response( 'raw' ) );

		return hash_hmac( 'sha256', $sso, get_option( 'wpdlg_discourse_secret' ) ) === $this->get_sso_signature();
	}

	/**
	 * Get SSO Signature
	 */
	public function get_sso_signature() {
		$sig = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : '';

		return sanitize_text_field( $sig );
	}

	/**
	 * Gets the username of the Discourse user
	 *
	 * @return string the username, otherwise empty.
	 */
	public static function get_wpdlg_name() {
		global $current_user;
		wp_get_current_user();
		$discourse_user = \WPDiscourseLogin\Utilities\Utilities::get_discourse_user( $current_user->ID, true );
		if ( ! is_wp_error( $discourse_user ) ) {
			return $discourse_user->username;
		}
		return '';
	}

	/**
	 * Gets the avatar of the Discourse user
	 *
	 * @return string the URL to the avatar, otherwise an empty string.
	 */
	public static function get_wpdlg_avatar() {

		global $current_user;
		wp_get_current_user();
		$discourse_user = \WPDiscourseLogin\Utilities\Utilities::get_discourse_user( $current_user->ID, true );
		if ( ! is_wp_error( $discourse_user ) ) {
			$avatar = $discourse_user->avatar_template;

			if ( ! preg_match( '/^http/', $avatar ) ) {
				return str_replace( '{size}', '42', get_option( 'wpdlg_discourse_url' ) . $avatar );
			} else {
				return str_replace( '{size}', '42', $avatar );
			}
		}
		return '';

	}

	/**
	 * Gets the Discourse profile link of the current user
	 *
	 * @return string the URL to the users profile.
	 */
	public static function get_wpdlg_profile_link() {

		$username = self::get_wpdlg_name();

		return get_option( 'wpdlg_discourse_url' ) . '/u/' . $username;

	}

}


