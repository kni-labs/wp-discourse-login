<?php
/**
 *
 * Static utility functions used throughout the plugin.
 *
 * @package WPDiscourseLogin
 */

namespace WPDiscourseLogin\Utilities;

/**
 * Class Utilities
 */
class Utilities {




	/**
	 * Validates the response from `wp_remote_get` or `wp_remote_post`.
	 *
	 * @param array $response The response from `wp_remote_get` or `wp_remote_post`.
	 *
	 * @return bool
	 */
	public static function validate( $response ) {
		if ( empty( $response ) ) {

			return false;

		}
		if ( is_wp_error( $response ) ) {

			return false;
		}
		if ( intval( wp_remote_retrieve_response_code( $response ) ) !== 200 ) {

			return false;
		}
		// Valid response.
		return true;

	}

	/**
	 * Get a Discourse user object.
	 *
	 * @param int  $user_id        The WordPress user_id.
	 * @param bool $match_by_email Whether or not to attempt to get the user by their email address.
	 *
	 * @return array|mixed|object|\WP_Error
	 */
	public static function get_discourse_user( $user_id, $match_by_email = false ) {
		$api_url = get_option( 'wpdlg_discourse_url' );

		$external_user_url = esc_url_raw( "{$api_url}/users/by-external/{$user_id}.json" );

		$body = self::get_from_discourse( $external_user_url );

		if ( isset( $body->user ) ) {

			return $body->user;
		}

		if ( $match_by_email ) {
			$user = get_user_by( 'id', $user_id );

			if ( ! empty( $user ) && ! is_wp_error( $user ) ) {

				return self::get_discourse_user_by_email( $user->user_email );
			} else {

				return new \WP_Error( 'wpdlg_param_error', 'There is no WordPress user with the supplied id.' );
			}
		}

		return new \WP_Error( 'wpdlg_response_error', 'The Discourse user could not be retrieved.' );
	}

	/**
	 * Gets a Discourse user by their email address.
	 *
	 * @param string $email The email address to search for.
	 *
	 * @return object \WP_Error
	 */
	public static function get_discourse_user_by_email( $email ) {
		$api_url   = get_option( 'wpdlg_discourse_url' );
		$users_url = "{$api_url}/admin/users/list/all.json";

		$users_url = esc_url_raw(
			add_query_arg(
				array(
					'email'  => rawurlencode_deep( $email ),
					'filter' => rawurlencode_deep( $email ),
				),
				$users_url
			)
		);

		$body = self::get_from_discourse( $users_url );

		if ( ! empty( $body[0] ) ) {
			return $body[0];
		} else {
			// A valid response was returned, but the user wasn't found.
			return new \WP_Error( 'wpdlg_response_error', 'The user could not be retrieved by their email address.' );
		}

	}

	/**
	 * Request from Discourse
	 *
	 * @param string $url the url to fetch.
	 * @return object|\WP_Error the request body if valid, otherwise WP_Error
	 */
	public static function get_from_discourse( $url ) {
		$api_key = get_option( 'wpdlg_discourse_api' );

		$response = wp_safe_remote_get(
			$url,
			array(
				'headers' => array(
					'Api-Key'      => $api_key,
					'Api-Username' => 'system',
				),
			)
		);

		if ( self::validate( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ) );
			// The reqest returns a valid response even if the user isn't found, so check for empty.
			if ( ! empty( $body ) ) {

				return $body;
			}
		} else {

			return new \WP_Error( 'wpdlg_response_error', 'An invalid response was returned when trying to make a request to Discourse.' );
		}
	}
}
