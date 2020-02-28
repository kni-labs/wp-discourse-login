<?php
/**
 * Static utility functions used throughout the plugin.
 *
 * @package WPDiscourse
 */

namespace WPDiscourseLogin\Utilities;

/**
 * Class Utilities
 *
 * @package WPDiscourse
 */
class Utilities {


	/**
	 * Validates the response from `wp_remote_get` or `wp_remote_post`.
	 *
	 * @param array $response The response from `wp_remote_get` or `wp_remote_post`.
	 *
	 * @return int
	 */
	public static function validate( $response ) {
		if ( empty( $response ) ) {

			return 0;
		} elseif ( is_wp_error( $response ) ) {

			return 0;

			// There is a response from the server, but it's not what we're looking for.
		} elseif ( intval( wp_remote_retrieve_response_code( $response ) ) !== 200 ) {

			return 0;
		} else {
			// Valid response.
			return 1;
		}
	}

	/**
	 * Get a Discourse user object.
	 *
	 * @param int  $user_id The WordPress user_id.
	 * @param bool $match_by_email Whether or not to attempt to get the user by their email address.
	 *
	 * @return array|mixed|object|\WP_Error
	 */
	public static function get_discourse_user( $user_id, $match_by_email = false ) {
		$api_url = get_option( 'wpdlg_discourse_url' );
		$api_key = get_option( 'wpdlg_discourse_api' );

		$external_user_url = esc_url_raw( "{$api_url}/users/by-external/{$user_id}.json" );

		$response = wp_remote_get(
			$external_user_url,
			array(
				'headers' => array(
					'Api-Key'      => sanitize_key( $api_key ),
					'Api-Username' => 'system',
				),
			)
		);

		if ( self::validate( $response ) ) {

			$body = json_decode( wp_remote_retrieve_body( $response ) );
			if ( isset( $body->user ) ) {

				return $body->user;
			}
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
		$api_url = get_option( 'wpdlg_discourse_url' );
		$api_key = get_option( 'wpdlg_discourse_api' );

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

		$response = wp_remote_get(
			$users_url,
			array(
				'headers' => array(
					'Api-Key'      => sanitize_key( $api_key ),
					'Api-Username' => 'system',
				),
			)
		);
		if ( self::validate( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ) );
			// The reqest returns a valid response even if the user isn't found, so check for empty.
			if ( ! empty( $body ) && ! empty( $body[0] ) ) {

				return $body[0];
			} else {

				// A valid response was returned, but the user wasn't found.
				return new \WP_Error( 'wpdlg_response_error', 'The user could not be retrieved by their email address.' );
			}
		} else {

			return new \WP_Error( 'wpdlg_response_error', 'An invalid response was returned when trying to find the user by email address.' );
		}
	}
}
