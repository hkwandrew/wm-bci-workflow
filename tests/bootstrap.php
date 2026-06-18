<?php

declare( strict_types=1 );

if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 7 * 24 * 60 * 60 );
}

if ( ! defined( 'WM_BCI_WORKFLOW_VERSION' ) ) {
	define( 'WM_BCI_WORKFLOW_VERSION', '1.1.0' );
}

if ( ! defined( 'WM_BCI_WORKFLOW_FILE' ) ) {
	define( 'WM_BCI_WORKFLOW_FILE', dirname( __DIR__ ) . '/wm-bci-workflow.php' );
}

if ( ! defined( 'WM_BCI_WORKFLOW_DIR' ) ) {
	define( 'WM_BCI_WORKFLOW_DIR', dirname( __DIR__ ) . '/' );
}

$GLOBALS['wm_bci_test_options']   = array();
$GLOBALS['wm_bci_settings_errors'] = array();
$GLOBALS['wm_bci_enqueued_styles'] = array();
$GLOBALS['wm_bci_test_entry_meta'] = array();
$GLOBALS['wm_bci_test_users']      = array();
$GLOBALS['wm_bci_updated_entry_fields'] = array();
$GLOBALS['wm_bci_test_settings_fields'] = array();
$GLOBALS['wp_settings_sections']   = array();
$GLOBALS['wp_settings_fields']     = array();

if ( ! function_exists( '__' ) ) {
	function __( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( string $single, string $plural, int $number ): string {
		return 1 === $number ? $single : $plural;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $name, $default = false ) {
		return $GLOBALS['wm_bci_test_options'][ $name ] ?? $default;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;

		public function __construct( string $code = '', string $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ): bool {
		return $thing instanceof \WP_Error;
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( string $email ): string {
		return filter_var( trim( $email ), FILTER_SANITIZE_EMAIL ) ?: '';
	}
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( string $email ) {
		return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $value ): string {
		return trim( strip_tags( $value ) );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( string $value ): string {
		$value = strtolower( trim( $value ) );
		$value = preg_replace( '/[^a-z0-9]+/', '-', $value );
		return trim( (string) $value, '-' );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $value ): string {
		return trim( $value );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

if ( ! function_exists( 'add_settings_error' ) ) {
	function add_settings_error( string $setting, string $code, string $message, string $type = 'error' ): void {
		$GLOBALS['wm_bci_settings_errors'][] = compact( 'setting', 'code', 'message', 'type' );
	}
}

if ( ! function_exists( 'register_setting' ) ) {
	function register_setting( string $option_group, string $option_name, array $args = array() ): void {
	}
}

if ( ! function_exists( 'add_settings_section' ) ) {
	function add_settings_section( string $id, string $title, callable $callback, string $page ): void {
		$GLOBALS['wp_settings_sections'][ $page ][ $id ] = array(
			'id'       => $id,
			'title'    => $title,
			'callback' => $callback,
		);
	}
}

if ( ! function_exists( 'add_settings_field' ) ) {
	function add_settings_field( string $id, string $title, callable $callback, string $page, string $section = 'default', array $args = array() ): void {
		$GLOBALS['wp_settings_fields'][ $page ][ $section ][ $id ] = array(
			'id'       => $id,
			'title'    => $title,
			'callback' => $callback,
			'args'     => $args,
		);
	}
}

if ( ! function_exists( 'rgar' ) ) {
	function rgar( array $array, string $key ) {
		return $array[ $key ] ?? null;
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $value ): string {
		return $value;
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( string $value ): string {
		return $value;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, bool $display = true ): string {
		$result = (string) $checked === (string) $current ? 'checked="checked"' : '';

		if ( $display ) {
			echo $result;
		}

		return $result;
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( string $format, int $timestamp ): string {
		return gmdate( $format, $timestamp );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( array $args, string $url ): string {
		return $url . '?' . http_build_query( $args );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '' ): string {
		return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'plugins_url' ) ) {
	function plugins_url( string $path = '', string $plugin = '' ): string {
		return 'https://example.com/wp-content/plugins/wm-bci-workflow/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( string $handle, string $src = '', array $deps = array(), $ver = false, string $media = 'all' ): void {
		$GLOBALS['wm_bci_enqueued_styles'][] = compact( 'handle', 'src', 'deps', 'ver', 'media' );
	}
}

if ( ! function_exists( 'get_admin_page_title' ) ) {
	function get_admin_page_title(): string {
		return 'BCI Workflow';
	}
}

if ( ! function_exists( 'settings_errors' ) ) {
	function settings_errors( string $setting = '' ): void {
	}
}

if ( ! function_exists( 'settings_fields' ) ) {
	function settings_fields( string $option_group ): void {
		$GLOBALS['wm_bci_test_settings_fields'][] = $option_group;
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( string $text = 'Save Changes', string $type = 'primary', string $name = 'submit', bool $wrap = true ): void {
		$button = sprintf(
			'<button type="submit" class="button button-%1$s" name="%2$s">%3$s</button>',
			esc_attr( $type ),
			esc_attr( $name ),
			esc_html( $text )
		);

		if ( $wrap ) {
			echo '<p class="submit">' . $button . '</p>';
			return;
		}

		echo $button;
	}
}

if ( ! function_exists( 'do_settings_fields' ) ) {
	function do_settings_fields( string $page, string $section ): void {
		$fields = $GLOBALS['wp_settings_fields'][ $page ][ $section ] ?? array();

		foreach ( $fields as $field ) {
			echo '<tr>';
			echo '<th scope="row">' . esc_html( (string) $field['title'] ) . '</th>';
			echo '<td>';
			call_user_func( $field['callback'], $field['args'] ?? array() );
			echo '</td>';
			echo '</tr>';
		}
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ): int {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $value ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?? '';
	}
}

if ( ! class_exists( 'GFAPI' ) ) {
	class GFAPI {
		/** @var array<string,mixed> */
		public static array $wm_bci_test_form = array();
		/** @var array<int,array<string,mixed>> */
		public static array $wm_bci_entries = array();
		/** @var array<int,array<string,mixed>> */
		public static array $wm_bci_added_entries = array();
		/** @var array<int,array<string,mixed>> */
		public static array $wm_bci_notes = array();
		public static int $wm_bci_next_entry_id = 1000;

		/**
		 * @return array<string,mixed>
		 */
		public static function get_form( int $form_id ): array {
			return self::$wm_bci_test_form;
		}

		/**
		 * @return array<string,mixed>|\WP_Error
		 */
		public static function get_entry( int $entry_id ) {
			return self::$wm_bci_entries[ $entry_id ] ?? new \WP_Error( 'not_found', 'Entry not found.' );
		}

		/**
		 * @param array<string,mixed> $entry
		 * @return int|\WP_Error
		 */
		public static function add_entry( array $entry ) {
			$entry_id      = self::$wm_bci_next_entry_id++;
			$entry['id']   = $entry_id;
			self::$wm_bci_entries[ $entry_id ]       = $entry;
			self::$wm_bci_added_entries[ $entry_id ] = $entry;

			return $entry_id;
		}

		/**
		 * @param array<string,mixed>|null $search_criteria
		 * @param array<string,mixed>|null $sorting
		 * @param array<string,int>|null   $paging
		 * @param int|null                 $total_count
		 * @return array<int,array<string,mixed>>
		 */
		public static function get_entries( int $form_id, ?array $search_criteria = null, ?array $sorting = null, ?array $paging = null, ?int &$total_count = null ): array {
			$criteria = is_array( $search_criteria ) ? $search_criteria : array();
			$entries = array_values(
				array_filter(
					self::$wm_bci_entries,
					static function ( array $entry ) use ( $form_id, $criteria ): bool {
						if ( (int) ( $entry['form_id'] ?? 0 ) !== $form_id ) {
							return false;
						}

						$status = $criteria['status'] ?? '';
						if ( '' !== $status && (string) ( $entry['status'] ?? '' ) !== (string) $status ) {
							return false;
						}

						$filters = $criteria['field_filters'] ?? array();
						foreach ( $filters as $filter ) {
							$key   = (string) ( $filter['key'] ?? '' );
							$value = (string) ( $filter['value'] ?? '' );

							if ( '' !== $key && (string) ( $entry[ $key ] ?? '' ) !== $value ) {
								return false;
							}
						}

						return true;
					}
				)
			);

			$total_count = count( $entries );

			if ( is_array( $paging ) ) {
				$offset    = (int) ( $paging['offset'] ?? 0 );
				$page_size = (int) ( $paging['page_size'] ?? $total_count );
				$entries   = array_slice( $entries, $offset, $page_size );
			}

			return $entries;
		}

		public static function add_note( int $entry_id, int $user_id, string $user_name, string $note ): void {
			self::$wm_bci_notes[] = compact( 'entry_id', 'user_id', 'user_name', 'note' );
		}

		public static function update_entry_field( int $entry_id, $input_id, $value, string $item_index = '' ): bool {
			$input_key = (string) $input_id;

			if ( ! isset( self::$wm_bci_entries[ $entry_id ] ) ) {
				self::$wm_bci_entries[ $entry_id ] = array(
					'id' => $entry_id,
				);
			}

			self::$wm_bci_entries[ $entry_id ][ $input_key ] = $value;
			$GLOBALS['wm_bci_updated_entry_fields'][] = array(
				'entry_id'   => $entry_id,
				'input_id'   => $input_key,
				'value'      => $value,
				'item_index' => $item_index,
			);

			return true;
		}
	}
}

if ( ! function_exists( 'gform_get_meta' ) ) {
	function gform_get_meta( int $entry_id, string $meta_key ) {
		return $GLOBALS['wm_bci_test_entry_meta'][ $entry_id ][ $meta_key ] ?? '';
	}
}

if ( ! function_exists( 'gform_update_meta' ) ) {
	function gform_update_meta( int $entry_id, string $meta_key, $meta_value, int $form_id = 0 ): void {
		$GLOBALS['wm_bci_test_entry_meta'][ $entry_id ][ $meta_key ] = $meta_value;
	}
}

if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt(): string {
		return 'wm-bci-test-salt';
	}
}

if ( ! function_exists( 'get_users' ) ) {
	function get_users( array $args = array() ) {
		$users = $GLOBALS['wm_bci_test_users'];

		if ( isset( $args['include'] ) && is_array( $args['include'] ) ) {
			$include = array_fill_keys( array_map( 'absint', $args['include'] ), true );
			$users   = array_values(
				array_filter(
					$users,
					static function ( $user ) use ( $include ): bool {
						$user_id = is_object( $user ) ? ( $user->ID ?? 0 ) : ( $user['ID'] ?? 0 );
						return isset( $include[ absint( $user_id ) ] );
					}
				)
			);
		}

		if ( isset( $args['orderby'] ) && 'display_name' === $args['orderby'] ) {
			usort(
				$users,
				static function ( $left, $right ): int {
					$left_name  = is_object( $left ) ? (string) ( $left->display_name ?? '' ) : (string) ( $left['display_name'] ?? '' );
					$right_name = is_object( $right ) ? (string) ( $right->display_name ?? '' ) : (string) ( $right['display_name'] ?? '' );
					return strcmp( $left_name, $right_name );
				}
			);
		}

		if ( isset( $args['fields'] ) && 'ids' === $args['fields'] ) {
			return array_map(
				static function ( $user ): int {
					return absint( is_object( $user ) ? ( $user->ID ?? 0 ) : ( $user['ID'] ?? 0 ) );
				},
				$users
			);
		}

		return $users;
	}
}

if ( ! function_exists( 'get_user_by' ) ) {
	function get_user_by( string $field, $value ) {
		if ( 'id' !== strtolower( $field ) ) {
			return false;
		}

		foreach ( $GLOBALS['wm_bci_test_users'] as $user ) {
			$user_id = is_object( $user ) ? ( $user->ID ?? 0 ) : ( $user['ID'] ?? 0 );

			if ( absint( $user_id ) === absint( $value ) ) {
				return $user;
			}
		}

		return false;
	}
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
