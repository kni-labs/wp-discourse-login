<?php
/*
Plugin Name: WP Discourse Login
Description: Allow SSO using Discourse as the provider
Version: 0.0.1
Author: Jake Ols
Author URI: https://ols.engineer
 *
 * Modified code from the official WP Discourse plugin: https://github.com/discourse/wp-discourse/
 */

require_once plugin_dir_path( __FILE__ ) . 'admin-options.php'; // admin options
require_once plugin_dir_path( __FILE__ ) . 'nonce.php'; // nonce options
require_once plugin_dir_path( __FILE__ ) . 'utilities.php'; // utilities options
add_shortcode( 'discourse_sso_login', 'login_link' );
add_filter( 'query_vars', 'discourse_sso_custom_query_vars' );
add_action( 'parse_query', 'discourse_sso_url_redirect' );

/**
 * Displays the login link
 */
function login_link() {
	 return '<a href="http://rao.czi.wordpress.test/?discourse_sso_new=1">Login with Discourse</a>';
}

/**
 * Parse Request Hook
 */
function parse_request() {
	if ( empty( $_GET['sso'] ) || empty( $_GET['sig'] ) ) {
		return;
	}

	if ( ! is_valid_signature() ) {
		return;
	}

	$user_id = get_user_id();

	if ( is_wp_error( $user_id ) ) {
		handle_errors( $user_id );

		return;
	}

	$updated_user = update_user( $user_id );
	if ( is_wp_error( $updated_user ) ) {
		handle_errors( $updated_user );

		return;
	}

	auth_user( $user_id );
}
add_action( 'init', 'parse_request' );

/**
 * Adds to query variabls
 */
function discourse_sso_custom_query_vars( $vars ) {
	$vars[] = 'discourse_sso_new';

	return $vars;
}

/**
 * Adds redirects
 */
function discourse_sso_url_redirect( $wp ) {
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
	wp_redirect( esc_url_raw( $sso_login_url ) );
	exit;
}

/**
 * Retrieves user ID
 */
function get_user_id() {
	if ( is_user_logged_in() ) {
		$user_id  = get_current_user_id();
		$redirect = get_sso_response( 'return_sso_url' );
		if ( get_user_meta( $user_id, get_option( 'wpdlg_discourse_meta' ), true ) ) {
			wp_safe_redirect( $redirect );

			exit;
		} else {
			$discourse_email = get_sso_response( 'email' );
			$wp_email        = wp_get_current_user()->user_email;
			if ( $discourse_email === $wp_email ) {
				update_user_meta( $user_id, get_option( 'wpdlg_discourse_meta' ), get_sso_response( 'external_id' ) );
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
				'meta_value' => get_sso_response( 'external_id' ),
			)
		);
		$user_query_results = $user_query->get_results();
		if ( empty( $user_query_results ) ) {
			$user_password = wp_generate_password( 12, true );

			$user_id = wp_create_user(
				get_sso_response( 'username' ),
				$user_password,
				get_sso_response( 'email' )
			);
			return $user_id;
		}

		return $user_query_results{0}->ID;
	}
}

function get_sso_response( $return_key = '' ) {
	if ( empty( $_GET['sso'] ) ) { // Input var okay.
		return null;
	}

	if ( 'raw' === $return_key ) {

		return sanitize_text_field( wp_unslash( $_GET['sso'] ) ); // Input var okay.
	}

	$sso = base64_decode( sanitize_text_field( wp_unslash( $_GET['sso'] ) ), true ); // Input var okay.

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

function auth_user( $user_id ) {
	$query = get_sso_response();
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

function handle_errors( $error ) {
	$redirect_to = wp_login_url();

	$redirect_to = add_query_arg( 'discourse_sso_error', $error->get_error_code(), $redirect_to );

	wp_safe_redirect( $redirect_to );
	exit;
}

function update_user( $user_id ) {
	$query = get_sso_response();
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
function is_valid_signature() {
	 $sso = urldecode( get_sso_response( 'raw' ) );

	return hash_hmac( 'sha256', $sso, get_option( 'wpdlg_discourse_secret' ) ) === get_sso_signature();
}

/**
 * Get SSO Signature
 */
function get_sso_signature() {
	$sig = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : ''; // Input var okay.

	return sanitize_text_field( $sig );
}
