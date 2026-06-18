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
		echo '<div class="wrap wm-bci-settings-page">';
		echo '<header class="wm-bci-settings-page__hero">';
		echo '<div class="wm-bci-settings-page__hero-copy">';
		echo '<p class="wm-bci-settings-page__eyebrow">' . esc_html__( 'Workflow Settings', 'wm-bci-workflow' ) . '</p>';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		echo '<p class="wm-bci-settings-page__intro">' . esc_html__( 'Manage the approval, publishing, and sync settings that power the BCI community opportunities workflow.', 'wm-bci-workflow' ) . '</p>';
		echo '</div>';
		echo '</header>';
		settings_errors( self::OPTION_NAME );

		echo '<form method="post" action="options.php" class="wm-bci-settings-form">';
		settings_fields( self::PAGE_SLUG );
		echo '<div class="wm-bci-settings-grid">';
		$this->render_registered_section( 'wm_bci_form_config' );
		$this->render_registered_section( 'wm_bci_field_map' );
		$this->render_calendar_event_colors_section();
		$this->render_registered_section( 'wm_bci_google_sync' );
		echo '</div>';
		echo '<div class="wm-bci-settings-submit">';
		submit_button();
		echo '</div>';
		echo '</form>';
		echo '</div>';
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$css_file = WM_BCI_WORKFLOW_DIR . 'assets/css/bci-calendar-settings.css';

		wp_enqueue_style(
			'wm-bci-admin-settings',
			plugins_url( 'assets/css/bci-calendar-settings.css', WM_BCI_WORKFLOW_FILE ),
			array(),
			file_exists( $css_file ) ? (string) filemtime( $css_file ) : WM_BCI_WORKFLOW_VERSION
		);
	}

	public function register_settings(): void {
		register_setting( self::PAGE_SLUG, self::OPTION_NAME, array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize' ),
			'default'           => array(),
		) );

		// Section: Form Configuration.
		add_settings_section(
			'wm_bci_form_config',
			__( 'Form Configuration', 'wm-bci-workflow' ),
			static function (): void {
				echo '<p>' . esc_html__( 'Configure which Gravity Forms form and fields power the BCI workflow.', 'wm-bci-workflow' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		$this->add_field( 'form_id', __( 'Form ID', 'wm-bci-workflow' ), 'number', 'wm_bci_form_config', (string) $this->config->form_id() );
		$this->add_field( 'approval_field_id', __( 'Approval Field ID', 'wm-bci-workflow' ), 'text', 'wm_bci_form_config', $this->config->approval_field_id() );
		$this->add_field( 'notification_name', __( 'Notification Name', 'wm-bci-workflow' ), 'text', 'wm_bci_form_config', $this->config->notification_name() );
		$this->add_field(
			'approval_notification_recipients',
			__( 'Approval Notification Recipients', 'wm-bci-workflow' ),
			'textarea',
			'wm_bci_form_config',
			$this->config->approval_notification_recipients(),
			'',
			__( 'Enter one or more email addresses separated by commas or new lines. When provided, this list overrides the Gravity Forms Send To setting for the approval notification. Leave blank to use the Gravity Forms setting.', 'wm-bci-workflow' )
		);
		$this->add_auto_approved_users_field();
		$this->add_field( 'calendar_page_slug', __( 'Calendar Page Slug', 'wm-bci-workflow' ), 'text', 'wm_bci_form_config', $this->config->calendar_page_slug() );
		$this->add_field( 'calendar_feed_name', __( 'Calendar Feed Name', 'wm-bci-workflow' ), 'text', 'wm_bci_form_config', $this->config->calendar_feed_name() );

		// Section: Field Mapping.
		add_settings_section(
			'wm_bci_field_map',
			__( 'Field Mapping', 'wm-bci-workflow' ),
			static function (): void {
				echo '<p>' . esc_html__( 'Map semantic field names to Gravity Forms field IDs.', 'wm-bci-workflow' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		$field_map = $this->config->field_map();
		foreach ( Config::default_field_map() as $key => $default ) {
			$label = ucwords( str_replace( '_', ' ', $key ) );
			$this->add_field(
				'field_map_' . $key,
				$label,
				'text',
				'wm_bci_field_map',
				$field_map[ $key ] ?? $default,
				'field_map'
			);
		}

		// Section: Google Sheets Sync.
		add_settings_section(
			'wm_bci_google_sync',
			__( 'Google Sheets Sync', 'wm-bci-workflow' ),
			function (): void {
				echo '<p>' . esc_html__( 'Configure the Google Apps Script sync endpoint. These can also be set as constants in wp-config.php.', 'wm-bci-workflow' ) . '</p>';
				if ( defined( 'WATERS_MEET_BCI_GOOGLE_SYNC_URL' ) ) {
					echo '<p class="description">' . esc_html__( 'Note: WATERS_MEET_BCI_GOOGLE_SYNC_URL is defined in wp-config.php and will be used as fallback.', 'wm-bci-workflow' ) . '</p>';
				}
			},
			self::PAGE_SLUG
		);

		$this->add_field( 'google_sync_url', __( 'Sync Endpoint URL', 'wm-bci-workflow' ), 'url', 'wm_bci_google_sync', $this->config->google_sync_url() );
		$this->add_field( 'google_sync_secret', __( 'Shared Secret', 'wm-bci-workflow' ), 'password', 'wm_bci_google_sync', '' );
	}

	private function add_field( string $id, string $label, string $type, string $section, string $value, string $group = '', string $description = '' ): void {
		add_settings_field(
			'wm_bci_' . $id,
			$label,
			function () use ( $id, $type, $value, $group, $description ): void {
				$name = $group
					? sprintf( '%s[%s][%s]', self::OPTION_NAME, $group, str_replace( 'field_map_', '', $id ) )
					: sprintf( '%s[%s]', self::OPTION_NAME, $id );

				if ( 'textarea' === $type ) {
					printf(
						'<textarea name="%1$s" rows="4" class="large-text">%2$s</textarea>',
						esc_attr( $name ),
						esc_textarea( $value )
					);
				} else {
					$display_value = 'password' === $type ? '' : esc_attr( $value );

					printf(
						'<input type="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
						esc_attr( $type ),
						esc_attr( $name ),
						$display_value
					);
				}

				if ( 'password' === $type && '' !== $value ) {
					echo '<p class="description">' . esc_html__( 'A secret is configured. Leave blank to keep the current value.', 'wm-bci-workflow' ) . '</p>';
				}

				if ( '' !== $description ) {
					echo '<p class="description">' . esc_html( $description ) . '</p>';
				}
			},
			self::PAGE_SLUG,
			$section
		);
	}

	private function add_auto_approved_users_field(): void {
		add_settings_field(
			'wm_bci_auto_approved_user_ids',
			__( 'Auto-Approved Submitters', 'wm-bci-workflow' ),
			array( $this, 'render_auto_approved_users_field' ),
			self::PAGE_SLUG,
			'wm_bci_form_config'
		);
	}

	public function render_auto_approved_users_field(): void {
		$selected_ids = $this->config->auto_approved_user_ids();
		$users        = $this->available_users();

		printf(
			'<input type="hidden" name="%1$s[auto_approved_user_ids_present]" value="1" />',
			esc_attr( self::OPTION_NAME )
		);

		if ( empty( $users ) ) {
			echo '<p class="description">' . esc_html__( 'No WordPress users are available to allowlist.', 'wm-bci-workflow' ) . '</p>';
			return;
		}

		echo '<div class="wm-bci-user-select">';
		printf(
			'<select name="%1$s[auto_approved_user_ids][]" multiple="multiple" size="8" class="wm-bci-user-select__control">',
			esc_attr( self::OPTION_NAME )
		);

		foreach ( $users as $user ) {
			$user_id = $this->user_id( $user );

			if ( ! $user_id ) {
				continue;
			}

			printf(
				'<option value="%1$d"%2$s>%3$s</option>',
				$user_id,
				in_array( $user_id, $selected_ids, true ) ? ' selected="selected"' : '',
				esc_html( $this->user_label( $user ) )
			);
		}

		echo '</select>';
		echo '<p class="wm-bci-user-select__hint">' . esc_html__( 'Use Command or Control-click to select multiple users.', 'wm-bci-workflow' ) . '</p>';
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'Select logged-in WordPress users whose future BCI submissions should be approved automatically. This only applies when Gravity Forms saves the entry created_by user.', 'wm-bci-workflow' ) . '</p>';
	}

	public function render_calendar_event_colors_section(): void {
		$choices = $this->opportunity_type_choices();
		$colors  = $this->config->calendar_event_colors();
		$palette = Config::calendar_event_palette();

		echo '<section class="wm-bci-settings-section wm-bci-settings-section--calendar">';
		$this->render_section_header(
			__( 'Calendar Event Colors', 'wm-bci-workflow' ),
			__( 'Choose event colors for each opportunity type. Leave a row unselected to use the default GravityCalendar feed color.', 'wm-bci-workflow' )
		);
		echo '<div class="wm-bci-settings-card">';

		if ( empty( $choices ) ) {
			echo '<p class="description">' . esc_html__( 'Opportunity type choices could not be loaded from Gravity Forms. Verify the Form ID and opportunity_type field mapping; existing saved colors will be preserved until this field can be loaded again.', 'wm-bci-workflow' ) . '</p>';
			echo '</div>';
			echo '</section>';
			return;
		}

		printf(
			'<input type="hidden" name="%1$s[calendar_event_colors_present]" value="1" />',
			esc_attr( self::OPTION_NAME )
		);

		echo '<table class="widefat striped wm-bci-calendar-colors-table" style="max-width: 780px;">';
		echo '<thead>';
		echo '<tr>';
		echo '<th class="wm-bci-calendar-colors-table__type-header" scope="col">' . esc_html__( 'Opportunity Type', 'wm-bci-workflow' ) . '</th>';
		printf(
			'<th class="wm-bci-calendar-colors-table__group-header" scope="col" colspan="%d">%s</th>',
			count( $palette ),
			esc_html__( 'Calendar Color', 'wm-bci-workflow' )
		);
		echo '</tr>';
		echo '</thead><tbody>';

		foreach ( $choices as $value => $label ) {
			$selected_color = $colors[ $value ] ?? '';
			$selected_color = isset( $palette[ $selected_color ] ) ? $selected_color : '';
			$input_name     = sprintf( '%s[calendar_event_colors][%s]', self::OPTION_NAME, $value );

			echo '<tr>';
			echo '<th scope="row"><span class="wm-bci-calendar-type-label">' . esc_html( $label ) . '</span>';

			if ( $label !== $value ) {
				echo '<span class="wm-bci-calendar-type-meta"><code>' . esc_html( $value ) . '</code></span>';
			}

			echo '</th>';

			foreach ( $palette as $color => $color_label ) {
				$this->render_calendar_color_option_cell(
					$input_name,
					$color,
					$selected_color,
					sprintf( '%s: %s (%s)', $label, $color_label, strtoupper( $color ) ),
					'wm-bci-calendar-palette-swatch',
					$color
				);
			}

			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
		echo '</section>';
	}

	/**
	 * @param mixed $input
	 * @return array
	 */
	public function sanitize( $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$clean = array();
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

		// Only update secret if a new one was provided.
		if ( ! empty( $input['google_sync_secret'] ) ) {
			$clean['google_sync_secret'] = sanitize_text_field( $input['google_sync_secret'] );
		} else {
			if ( ! empty( $existing['google_sync_secret'] ) ) {
				$clean['google_sync_secret'] = $existing['google_sync_secret'];
			}
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

	/**
	 * @return array<string,string>
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

			return $normalized;
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
			$label = '';
			$value = '';
			$is_other_choice = false;

			if ( is_array( $choice ) ) {
				$is_other_choice = ! empty( $choice['isOtherChoice'] ) || 'gf_other_choice' === ( $choice['value'] ?? '' );
				$label = trim( (string) ( $choice['text'] ?? '' ) );
				$value = trim( (string) ( $choice['value'] ?? $label ) );
			} elseif ( is_object( $choice ) ) {
				$is_other_choice = ! empty( $choice->isOtherChoice ) || ( isset( $choice->value ) && 'gf_other_choice' === $choice->value );
				$label = isset( $choice->text ) ? trim( (string) $choice->text ) : '';
				$value = isset( $choice->value ) ? trim( (string) $choice->value ) : $label;
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
	 * @return array<int,mixed>
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

		return is_array( $users ) ? $users : array();
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

	private function render_registered_section( string $section_id ): void {
		global $wp_settings_sections;

		if ( ! isset( $wp_settings_sections[ self::PAGE_SLUG ][ $section_id ] ) ) {
			return;
		}

		$section = $wp_settings_sections[ self::PAGE_SLUG ][ $section_id ];
		$title   = ! empty( $section['title'] ) ? (string) $section['title'] : '';
		$intro   = $this->capture_section_description( $section );

		if ( 'wm_bci_form_config' === $section_id ) {
			$this->render_form_config_section( $title, $intro );
			return;
		}

		if ( 'wm_bci_field_map' === $section_id ) {
			$this->render_field_mapping_section( $title, $intro );
			return;
		}

		$this->render_standard_section( $section_id, $title, $intro );
	}

	private function render_form_config_section( string $title, string $intro ): void {
		echo '<section class="wm-bci-settings-section wm-bci-settings-section--form-config">';
		$this->render_section_header( $title, $intro );
		echo '<div class="wm-bci-settings-card">';
		$this->render_field_group(
			'wm_bci_form_config',
			__( 'Workflow Setup', 'wm-bci-workflow' ),
			__( 'Connect the BCI workflow to the right Gravity Forms identifiers and notification trigger.', 'wm-bci-workflow' ),
			array(
				'wm_bci_form_id',
				'wm_bci_approval_field_id',
				'wm_bci_notification_name',
			)
		);
		$this->render_field_group(
			'wm_bci_form_config',
			__( 'Approvals', 'wm-bci-workflow' ),
			__( 'Control who gets review emails and which logged-in users bypass manual approval.', 'wm-bci-workflow' ),
			array(
				'wm_bci_approval_notification_recipients',
				'wm_bci_auto_approved_user_ids',
			)
		);
		$this->render_field_group(
			'wm_bci_form_config',
			__( 'Publishing', 'wm-bci-workflow' ),
			__( 'Set the public calendar destination and feed label used by the BCI workflow.', 'wm-bci-workflow' ),
			array(
				'wm_bci_calendar_page_slug',
				'wm_bci_calendar_feed_name',
			)
		);
		echo '</div>';
		echo '</section>';
	}

	private function render_field_mapping_section( string $title, string $intro ): void {
		echo '<section class="wm-bci-settings-section wm-bci-settings-section--advanced">';
		echo '<details class="wm-bci-settings-collapsible">';
		echo '<summary class="wm-bci-settings-collapsible__summary">';
		echo '<span class="wm-bci-settings-collapsible__title">' . esc_html( $title ) . '</span>';
		echo '<span class="wm-bci-settings-badge">' . esc_html__( 'Advanced', 'wm-bci-workflow' ) . '</span>';
		echo '</summary>';
		echo '<div class="wm-bci-settings-card wm-bci-settings-card--compact">';

		if ( '' !== $intro ) {
			echo '<div class="wm-bci-settings-note">' . wp_kses_post( $intro ) . '</div>';
		}

		echo '<p class="wm-bci-settings-inline-help">' . esc_html__( 'Only change these IDs when the underlying Gravity Forms form changes.', 'wm-bci-workflow' ) . '</p>';
		echo '<table class="form-table wm-bci-settings-table wm-bci-settings-table--mapping" role="presentation">';
		$this->render_field_rows(
			'wm_bci_field_map',
			array_keys( $this->registered_field_ids( 'wm_bci_field_map' ) )
		);
		echo '</table>';
		echo '</div>';
		echo '</details>';
		echo '</section>';
	}

	private function render_standard_section( string $section_id, string $title, string $intro ): void {
		echo '<section class="wm-bci-settings-section wm-bci-settings-section--' . esc_attr( str_replace( '_', '-', $section_id ) ) . '">';
		$this->render_section_header( $title, $intro );
		echo '<div class="wm-bci-settings-card">';
		echo '<table class="form-table wm-bci-settings-table" role="presentation">';
		$this->render_field_rows( $section_id, array_keys( $this->registered_field_ids( $section_id ) ) );
		echo '</table>';
		echo '</div>';
		echo '</section>';
	}

	private function render_section_header( string $title, string $description = '' ): void {
		echo '<div class="wm-bci-settings-section__header">';
		echo '<h2>' . esc_html( $title ) . '</h2>';

		if ( '' !== $description ) {
			echo '<div class="wm-bci-settings-section__description">' . wp_kses_post( $description ) . '</div>';
		}

		echo '</div>';
	}

	private function render_field_group( string $section_id, string $title, string $description, array $field_ids ): void {
		echo '<section class="wm-bci-settings-subsection">';
		echo '<div class="wm-bci-settings-subsection__header">';
		echo '<h3>' . esc_html( $title ) . '</h3>';
		echo '<p>' . esc_html( $description ) . '</p>';
		echo '</div>';
		echo '<table class="form-table wm-bci-settings-table" role="presentation">';
		$this->render_field_rows( $section_id, $field_ids );
		echo '</table>';
		echo '</section>';
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function registered_field_ids( string $section_id ): array {
		global $wp_settings_fields;

		$fields = $wp_settings_fields[ self::PAGE_SLUG ][ $section_id ] ?? array();

		return is_array( $fields ) ? $fields : array();
	}

	private function render_field_rows( string $section_id, array $field_ids ): void {
		$fields = $this->registered_field_ids( $section_id );

		foreach ( $field_ids as $field_id ) {
			if ( ! isset( $fields[ $field_id ] ) ) {
				continue;
			}

			$field = $fields[ $field_id ];

			echo '<tr class="wm-bci-settings-table__row">';
			echo '<th scope="row">' . esc_html( (string) $field['title'] ) . '</th>';
			echo '<td>';
			call_user_func( $field['callback'], $field['args'] ?? array() );
			echo '</td>';
			echo '</tr>';
		}
	}

	/**
	 * @param array<string,mixed> $section
	 */
	private function capture_section_description( array $section ): string {
		if ( empty( $section['callback'] ) || ! is_callable( $section['callback'] ) ) {
			return '';
		}

		ob_start();
		call_user_func( $section['callback'], $section );
		return trim( (string) ob_get_clean() );
	}

	private function render_calendar_color_option_cell( string $input_name, string $value, string $selected_color, string $label, string $swatch_class, string $color = '' ): void {
		echo '<td class="wm-bci-calendar-colors-table__option-cell">';
		printf(
			'<label class="wm-bci-calendar-palette-option" title="%1$s"><input type="radio" name="%2$s" value="%3$s" %4$s /><span class="%5$s"%6$s aria-hidden="true"></span><span class="screen-reader-text">%1$s</span></label>',
			esc_attr( $label ),
			esc_attr( $input_name ),
			esc_attr( $value ),
			checked( $value, $selected_color, false ),
			esc_attr( $swatch_class ),
			'' !== $color ? ' style="background-color:' . esc_attr( $color ) . ';"' : ''
		);
		echo '</td>';
	}
}
