<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\GoogleSync;

/**
 * HTTP helper that follows Apps Script content redirects.
 *
 * Apps Script web apps redirect POST /exec to a one-time googleusercontent URL.
 * The first request is a POST; subsequent redirects are followed with GET.
 */
final class HttpClient {

	/**
	 * @param string $url          Request URL.
	 * @param array  $request_args wp_remote_* args.
	 * @param string $method       HTTP method.
	 * @param int    $redirects    Number of redirects already followed.
	 * @return array|\WP_Error
	 */
	public static function request( string $url, array $request_args, string $method = 'POST', int $redirects = 0 ) {
		$request_args['redirection'] = 0;

		$response = 'GET' === $method
			? wp_remote_get( $url, $request_args )
			: wp_remote_post( $url, $request_args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code     = (int) wp_remote_retrieve_response_code( $response );
		$location = wp_remote_retrieve_header( $response, 'location' );

		if ( $redirects >= 5 || ! in_array( $code, array( 301, 302, 307, 308 ), true ) || '' === $location ) {
			return $response;
		}

		return self::request(
			$location,
			array(
				'timeout'     => $request_args['timeout'] ?? 20,
				'redirection' => 0,
				'cookies'     => wp_remote_retrieve_cookies( $response ),
			),
			'GET',
			$redirects + 1
		);
	}
}
