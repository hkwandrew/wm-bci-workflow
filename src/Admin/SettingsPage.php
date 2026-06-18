<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\Admin;

use WatersMeet\BciWorkflow\Config;

/**
 * WP Settings API page under Settings > BCI Workflow.
 */
final class SettingsPage {

	private Config $config;
	private const OPTION_NAME = 'wm_bci_workflow';
	private const PAGE_SLUG   = 'wm-bci-workflow';
	private const APP_HANDLE  = 'wm-bci-admin-app';

	/** @var array<string,mixed>|null */
	private ?array $admin_assets = null;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_page(): void {
		add_options_page(
			__( 'BCI Workflow', 'wm-bci-workflow' ),
			__( 'BCI Workflow', 'wm-bci-workflow' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';

		if ( ! $this->has_admin_assets() ) {
			$this->render_missing_build_notice();
			echo '</div>';
			return;
		}

		echo '<form method="post" action="options.php">';
		settings_fields( self::PAGE_SLUG );
		echo '<div id="wm-bci-settings-admin-root"></div>';
		echo '</form>';
		echo '</div>';
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix || ! $this->has_admin_assets() ) {
			return;
		}

		$assets = $this->admin_assets();

		if ( null === $assets ) {
			return;
		}

		wp_enqueue_script(
			self::APP_HANDLE,
			$assets['script_src'],
			$assets['dependencies'],
			$assets['version'],
			true
		);

		if ( ! empty( $assets['style_src'] ) ) {
			wp_enqueue_style(
				self::APP_HANDLE,
				$assets['style_src'],
				array( 'wp-components' ),
				$assets['version']
			);
		}

		wp_add_inline_script(
			self::APP_HANDLE,
			'window.wmBciWorkflowAdmin = ' . wp_json_encode( $this->admin_app_config() ) . ';',
			'before'
		);
	}

	public function register_settings(): void {
		register_setting(
			self::PAGE_SLUG,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * @param mixed $input
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$clean    = array();
		$existing = get_option( self::OPTION_NAME, array() );
		$existing = is_array( $existing ) ? $existing : array();

		if ( isset( $input['form_id'] ) ) {
			$clean['form_id'] = absint( $input['form_id'] );
		}

		if ( isset( $input['approval_field_id'] ) ) {
			$clean['approval_field_id'] = sanitize_text_field( $input['approval_field_id'] );
		}

		if ( isset( $input['notification_name'] ) ) {
			$clean['notification_name'] = sanitize_text_field( $input['notification_name'] );
		}

		if ( isset( $input['approval_notification_recipients'] ) ) {
			$recipients = Config::split_recipient_list( (string) $input['approval_notification_recipients'] );

			$clean['approval_notification_recipients'] = implode( ', ', $recipients['valid'] );

			if ( ! empty( $recipients['invalid'] ) ) {
				add_settings_error(
					self::OPTION_NAME,
					'wm_bci_invalid_recipients',
					sprintf(
						/* translators: %d: number of invalid email addresses. */
						_n(
							'%d invalid approval recipient email was ignored.',
							'%d invalid approval recipient emails were ignored.',
							count( $recipients['invalid'] ),
							'wm-bci-workflow'
						),
						count( $recipients['invalid'] )
					),
					'warning'
				);
			}
		}

		if ( isset( $input['auto_approved_user_ids_present'] ) || isset( $input['auto_approved_user_ids'] ) ) {
			$clean['auto_approved_user_ids'] = $this->valid_user_ids( $input['auto_approved_user_ids'] ?? array() );
		} elseif ( isset( $existing['auto_approved_user_ids'] ) ) {
			$clean['auto_approved_user_ids'] = Config::normalize_user_ids( $existing['auto_approved_user_ids'] );
		}

		if ( isset( $input['calendar_page_slug'] ) ) {
			$clean['calendar_page_slug'] = sanitize_title( $input['calendar_page_slug'] );
		}

		if ( isset( $input['calendar_feed_name'] ) ) {
			$clean['calendar_feed_name'] = sanitize_text_field( $input['calendar_feed_name'] );
		}

		if ( isset( $input['google_sync_url'] ) ) {
			$clean['google_sync_url'] = esc_url_raw( $input['google_sync_url'] );
		}

		if ( ! empty( $input['google_sync_secret'] ) ) {
			$clean['google_sync_secret'] = sanitize_text_field( $input['google_sync_secret'] );
		} elseif ( ! empty( $existing['google_sync_secret'] ) ) {
			$clean['google_sync_secret'] = $existing['google_sync_secret'];
		}

		if ( isset( $input['calendar_event_colors_present'] ) || isset( $input['calendar_event_colors'] ) ) {
			$clean['calendar_event_colors'] = isset( $input['calendar_event_colors'] ) && is_array( $input['calendar_event_colors'] )
				? Config::normalize_calendar_event_colors( $input['calendar_event_colors'] )
				: array();
		} elseif ( isset( $existing['calendar_event_colors'] ) ) {
			$clean['calendar_event_colors'] = Config::normalize_calendar_event_colors( $existing['calendar_event_colors'] );
		}

		if ( isset( $input['field_map'] ) && is_array( $input['field_map'] ) ) {
			$clean['field_map'] = array();
			foreach ( $input['field_map'] as $key => $field_id ) {
				$clean['field_map'][ sanitize_key( $key ) ] = sanitize_text_field( $field_id );
			}
		}

		return $clean;
	}

	private function render_missing_build_notice(): void {
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'The BCI settings admin app build is missing. Run npm run build in the wm-bci-workflow plugin before using this screen.', 'wm-bci-workflow' );
		echo '</p></div>';
	}

	private function has_admin_assets(): bool {
		return null !== $this->admin_assets();
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function admin_assets(): ?array {
		if ( null !== $this->admin_assets ) {
			return $this->admin_assets;
		}

		$build_dir = WM_BCI_WORKFLOW_DIR . 'build/admin/';
		$build_url = plugins_url( 'build/admin/', WM_BCI_WORKFLOW_FILE );
		$asset_file  = $build_dir . 'index.asset.php';
		$script_file = $build_dir . 'index.js';

		if ( ! file_exists( $asset_file ) || ! file_exists( $script_file ) ) {
			$this->admin_assets = null;
			return null;
		}

		$asset_data = include $asset_file;

		if ( ! is_array( $asset_data ) ) {
			$this->admin_assets = null;
			return null;
		}

		$style_file = $build_dir . 'style-index.css';

		$this->admin_assets = array(
			'dependencies' => is_array( $asset_data['dependencies'] ?? null ) ? $asset_data['dependencies'] : array(),
			'version'      => isset( $asset_data['version'] ) ? (string) $asset_data['version'] : WM_BCI_WORKFLOW_VERSION,
			'script_src'   => $build_url . 'index.js',
			'style_src'    => file_exists( $style_file ) ? $build_url . 'style-index.css' : '',
		);

		return $this->admin_assets;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function admin_app_config(): array {
		$field_map = $this->config->field_map();
		$palette   = array();

		foreach ( Config::calendar_event_palette() as $color => $label ) {
			$palette[] = array(
				'color' => $color,
				'name'  => $label,
			);
		}

		return array(
			'optionName'            => self::OPTION_NAME,
			'pageSlug'              => self::PAGE_SLUG,
			'values'                => array(
				'formId'                         => (string) $this->config->form_id(),
				'approvalFieldId'                => $this->config->approval_field_id(),
				'notificationName'               => $this->config->notification_name(),
				'approvalNotificationRecipients' => $this->config->approval_notification_recipients(),
				'autoApprovedUserIds'            => $this->config->auto_approved_user_ids(),
				'calendarPageSlug'               => $this->config->calendar_page_slug(),
				'calendarFeedName'               => $this->config->calendar_feed_name(),
				'googleSyncUrl'                  => $this->config->google_sync_url(),
				'googleSyncSecret'               => '',
				'hasGoogleSyncSecret'            => '' !== $this->config->google_sync_secret(),
				'calendarEventColors'            => $this->config->calendar_event_colors(),
				'fieldMap'                       => $field_map,
			),
			'fieldMapFields'        => $this->field_map_fields( $field_map ),
			'calendarPalette'       => $palette,
			'opportunityTypeChoices' => $this->opportunity_type_choices(),
			'users'                 => $this->available_users(),
		);
	}

	/**
	 * @param array<string,string> $field_map
	 * @return array<int,array{key:string,label:string,value:string}>
	 */
	private function field_map_fields( array $field_map ): array {
		$fields = array();

		foreach ( Config::default_field_map() as $key => $default ) {
			$fields[] = array(
				'key'   => $key,
				'label' => ucwords( str_replace( '_', ' ', $key ) ),
				'value' => $field_map[ $key ] ?? $default,
			);
		}

		return $fields;
	}

	/**
	 * @return array<int,array{id:int,label:string}>
	 */
	private function available_users(): array {
		if ( ! function_exists( 'get_users' ) ) {
			return array();
		}

		$users = get_users(
			array(
				'orderby' => 'display_name',
				'order'   => 'ASC',
			)
		);

		if ( ! is_array( $users ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $users as $user ) {
			$user_id = $this->user_id( $user );

			if ( ! $user_id ) {
				continue;
			}

			$normalized[] = array(
				'id'    => $user_id,
				'label' => $this->user_label( $user ),
			);
		}

		return $normalized;
	}

	/**
	 * @return array<int,array{value:string,label:string}>
	 */
	private function opportunity_type_choices(): array {
		if ( ! class_exists( 'GFAPI' ) ) {
			return array();
		}

		$form = \GFAPI::get_form( $this->config->form_id() );

		if ( ! is_array( $form ) || empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return array();
		}

		$field_id = $this->config->field( 'opportunity_type' );

		foreach ( $form['fields'] as $field ) {
			$current_id = '';

			if ( is_object( $field ) && isset( $field->id ) ) {
				$current_id = (string) $field->id;
			} elseif ( is_array( $field ) && isset( $field['id'] ) ) {
				$current_id = (string) $field['id'];
			}

			if ( $field_id !== $current_id ) {
				continue;
			}

			$choices = array();

			if ( is_object( $field ) && isset( $field->choices ) && is_array( $field->choices ) ) {
				$choices = $field->choices;
			} elseif ( is_array( $field ) && isset( $field['choices'] ) && is_array( $field['choices'] ) ) {
				$choices = $field['choices'];
			}

			$normalized = $this->normalize_choice_map( $choices );

			if ( $this->field_supports_other_choice( $field ) && ! isset( $normalized['Other'] ) ) {
				$normalized['Other'] = 'Other';
			}

			$output = array();

			foreach ( $normalized as $value => $label ) {
				$output[] = array(
					'value' => $value,
					'label' => $label,
				);
			}

			return $output;
		}

		return array();
	}

	/**
	 * @param array<int,mixed> $choices
	 * @return array<string,string>
	 */
	private function normalize_choice_map( array $choices ): array {
		$normalized = array();

		foreach ( $choices as $choice ) {
			$label           = '';
			$value           = '';
			$is_other_choice = false;

			if ( is_array( $choice ) ) {
				$is_other_choice = ! empty( $choice['isOtherChoice'] ) || 'gf_other_choice' === ( $choice['value'] ?? '' );
				$label           = trim( (string) ( $choice['text'] ?? '' ) );
				$value           = trim( (string) ( $choice['value'] ?? $label ) );
			} elseif ( is_object( $choice ) ) {
				$is_other_choice = ! empty( $choice->isOtherChoice ) || ( isset( $choice->value ) && 'gf_other_choice' === $choice->value );
				$label           = isset( $choice->text ) ? trim( (string) $choice->text ) : '';
				$value           = isset( $choice->value ) ? trim( (string) $choice->value ) : $label;
			}

			if ( $is_other_choice || '' === $label ) {
				continue;
			}

			if ( '' === $value ) {
				$value = $label;
			}

			$normalized[ $value ] = $label;
		}

		return $normalized;
	}

	/**
	 * @param mixed $raw
	 * @return array<int,int>
	 */
	private function valid_user_ids( $raw ): array {
		$user_ids = Config::normalize_user_ids( $raw );

		if ( empty( $user_ids ) || ! function_exists( 'get_users' ) ) {
			return $user_ids;
		}

		$existing_ids = get_users(
			array(
				'include' => $user_ids,
				'fields'  => 'ids',
			)
		);

		if ( ! is_array( $existing_ids ) ) {
			return array();
		}

		$lookup = array_fill_keys(
			array_map( 'absint', $existing_ids ),
			true
		);
		$valid  = array();

		foreach ( $user_ids as $user_id ) {
			if ( isset( $lookup[ $user_id ] ) ) {
				$valid[] = $user_id;
			}
		}

		return $valid;
	}

	/**
	 * @param mixed $user
	 */
	private function user_id( $user ): int {
		if ( is_object( $user ) && isset( $user->ID ) ) {
			return absint( $user->ID );
		}

		if ( is_array( $user ) && isset( $user['ID'] ) ) {
			return absint( $user['ID'] );
		}

		return 0;
	}

	/**
	 * @param mixed $user
	 */
	private function user_label( $user ): string {
		$display_name = $this->user_property( $user, 'display_name' );
		$user_login   = $this->user_property( $user, 'user_login' );
		$user_email   = $this->user_property( $user, 'user_email' );
		$name         = '' !== $display_name ? $display_name : ( '' !== $user_login ? $user_login : sprintf( 'User #%d', $this->user_id( $user ) ) );
		$meta         = array_filter( array( $user_login, $user_email ) );

		if ( empty( $meta ) ) {
			return $name;
		}

		return sprintf( '%1$s (%2$s)', $name, implode( ', ', $meta ) );
	}

	/**
	 * @param mixed $user
	 */
	private function user_property( $user, string $property ): string {
		if ( is_object( $user ) && isset( $user->{$property} ) ) {
			return trim( (string) $user->{$property} );
		}

		if ( is_array( $user ) && isset( $user[ $property ] ) ) {
			return trim( (string) $user[ $property ] );
		}

		return '';
	}

	/**
	 * @param mixed $field
	 */
	private function field_supports_other_choice( $field ): bool {
		if ( is_object( $field ) ) {
			return ! empty( $field->enableOtherChoice );
		}

		if ( is_array( $field ) ) {
			return ! empty( $field['enableOtherChoice'] );
		}

		return false;
	}
}
