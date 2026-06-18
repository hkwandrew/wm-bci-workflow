<?php

declare( strict_types=1 );

namespace WatersMeet\BciWorkflow\Calendar;

use WatersMeet\BciWorkflow\Config;
use WatersMeet\BciWorkflow\Entry\FieldAccessor;

/**
 * Enriches calendar events with custom tooltip markup.
 */
final class EventCustomizer {

	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	public function register(): void {
		add_filter( 'gravityview/calendar/events', array( $this, 'customize' ), 10, 5 );
	}

	/**
	 * @param array $events    Calendar events.
	 * @param array $form      Form data.
	 * @param array $feed      Feed data.
	 * @param array $field_map Field mapping.
	 * @param array $entries   Source entries.
	 * @return array
	 */
	public function customize( array $events, array $form, array $feed, array $field_map, array $entries ): array {
		if ( $this->config->form_id() !== (int) rgar( $form, 'id' ) ) {
			return $events;
		}

		$entries_by_id = array();
		foreach ( $entries as $entry ) {
			$entries_by_id[ (int) rgar( $entry, 'id' ) ] = $entry;
		}

		$fields = new FieldAccessor( $this->config );
		$choice_map = $this->opportunity_type_choices( $form );
		$supports_other_choice = $this->field_supports_other_choice( $this->opportunity_type_field( $form ) );

		foreach ( $events as $index => $event ) {
			$event_id = isset( $event['event_id'] ) ? (int) $event['event_id'] : 0;

			if ( ! $event_id || empty( $entries_by_id[ $event_id ] ) ) {
				continue;
			}

			$events[ $index ]['description'] = $this->tooltip_markup(
				$event,
				$entries_by_id[ $event_id ],
				$fields,
				$choice_map,
				$supports_other_choice
			);
			$events[ $index ] = $this->apply_event_colors(
				$events[ $index ],
				$entries_by_id[ $event_id ],
				$fields,
				$choice_map,
				$supports_other_choice
			);
		}

		return $events;
	}

	private function tooltip_markup( array $event, array $entry, FieldAccessor $fields, array $choice_map, bool $supports_other_choice ): string {
		$title       = trim( (string) rgar( $event, 'title' ) );
		$raw_type    = $fields->opportunity_type( $entry );
		$type        = $fields->legacy_opportunity_type( $raw_type );
		$eyebrow     = $this->tooltip_eyebrow( $type, $raw_type, $fields, $choice_map, $supports_other_choice );
		$date_label  = $this->date_label( $event );
		$date_name   = 'Grant / RFP' === $raw_type ? 'Deadline' : 'Date';
		$time_label  = $fields->time_range( $entry );
		$location    = $fields->address( $entry );
		$org         = $fields->organization( $entry );
		$description = $this->tooltip_excerpt( $fields->description( $entry ) );
		$link        = esc_url_raw( trim( (string) rgar( $event, 'url' ) ) );
		$meta_items  = array();

		if ( '' !== $type ) {
			$meta_items[] = '<li><strong>Type:</strong> ' . esc_html( $type ) . '</li>';
		}
		if ( '' !== $date_label ) {
			$meta_items[] = '<li><strong>' . esc_html( $date_name ) . ':</strong> ' . esc_html( $date_label ) . '</li>';
		}
		if ( '' !== $time_label ) {
			$meta_items[] = '<li><strong>Time:</strong> ' . esc_html( $time_label ) . '</li>';
		}
		if ( '' !== $org ) {
			$meta_items[] = '<li><strong>Organization:</strong> ' . esc_html( $org ) . '</li>';
		}
		if ( '' !== $location ) {
			$meta_items[] = '<li><strong>Location:</strong> ' . esc_html( $location ) . '</li>';
		}

		$html  = '<div class="wm-bci-calendar-tooltip">';
		$html .= '<div class="wm-bci-calendar-tooltip__header">';
		$html .= '<span class="wm-bci-calendar-tooltip__eyebrow">' . esc_html( $eyebrow ) . '</span>';
		$html .= '<h3 class="wm-bci-calendar-tooltip__title">' . esc_html( $title ) . '</h3>';
		$html .= '</div>';

		if ( ! empty( $meta_items ) ) {
			$html .= '<ul class="wm-bci-calendar-tooltip__meta">' . implode( '', $meta_items ) . '</ul>';
		}

		if ( '' !== $description ) {
			$html .= '<div class="wm-bci-calendar-tooltip__body">' . wp_kses_post( wpautop( $description ) ) . '</div>';
		}

		if ( '' !== $link ) {
			$html .= '<p class="wm-bci-calendar-tooltip__footer"><a href="' . esc_url( $link ) . '" target="_blank" rel="noopener noreferrer">View full details</a></p>';
		}

		$html .= '</div>';

		return $html;
	}

	private function tooltip_eyebrow( string $type, string $raw_type, FieldAccessor $fields, array $choice_map, bool $supports_other_choice ): string {
		if ( '' === $type || 'Other' === $type || $this->is_other_choice_value( $raw_type, $fields, $choice_map, $supports_other_choice ) ) {
			return 'BCI Opportunity';
		}

		return $type;
	}

	private function apply_event_colors( array $event, array $entry, FieldAccessor $fields, array $choice_map, bool $supports_other_choice ): array {
		$color = $this->event_color( $entry, $fields, $choice_map, $supports_other_choice );

		if ( '' === $color ) {
			return $event;
		}

		$event['backgroundColor'] = $color;
		$event['borderColor']     = $color;
		$event['textColor']       = $this->text_color( $color );

		return $event;
	}

	private function event_color( array $entry, FieldAccessor $fields, array $choice_map, bool $supports_other_choice ): string {
		$type = $fields->opportunity_type( $entry );

		if ( '' === $type ) {
			return '';
		}

		$color = $this->config->calendar_event_color( $type );

		if ( '' !== $color ) {
			return $color;
		}

		$fallback_type = $fields->form_choice_from_legacy_type( $type );

		if ( '' === $fallback_type ) {
			if ( ! $this->is_other_choice_value( $type, $fields, $choice_map, $supports_other_choice ) ) {
				return '';
			}

			return $this->config->calendar_event_color( 'Other' );
		}

		return $this->config->calendar_event_color( $fallback_type );
	}

	private function is_other_choice_value( string $type, FieldAccessor $fields, array $choice_map, bool $supports_other_choice ): bool {
		if ( '' === $type || ! $supports_other_choice || isset( $choice_map[ $type ] ) ) {
			return false;
		}

		return '' === $fields->form_choice_from_legacy_type( $type );
	}

	/**
	 * @return array<string,string>
	 */
	private function opportunity_type_choices( array $form ): array {
		$field = $this->opportunity_type_field( $form );

		if ( null === $field ) {
			return array();
		}

		$choices = array();

		if ( is_object( $field ) && isset( $field->choices ) && is_array( $field->choices ) ) {
			$choices = $field->choices;
		} elseif ( is_array( $field ) && isset( $field['choices'] ) && is_array( $field['choices'] ) ) {
			$choices = $field['choices'];
		}

		return $this->normalize_choice_map( $choices );
	}

	/**
	 * @return array|object|null
	 */
	private function opportunity_type_field( array $form ) {
		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return null;
		}

		$field_id = $this->config->field( 'opportunity_type' );

		foreach ( $form['fields'] as $field ) {
			$current_id = '';

			if ( is_object( $field ) && isset( $field->id ) ) {
				$current_id = (string) $field->id;
			} elseif ( is_array( $field ) && isset( $field['id'] ) ) {
				$current_id = (string) $field['id'];
			}

			if ( $field_id === $current_id ) {
				return $field;
			}
		}

		return null;
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

	private function text_color( string $background_color ): string {
		$hex = ltrim( strtolower( $background_color ), '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = sprintf(
				'%1$s%1$s%2$s%2$s%3$s%3$s',
				$hex[0],
				$hex[1],
				$hex[2]
			);
		}

		if ( 6 !== strlen( $hex ) ) {
			return '#ffffff';
		}

		$red       = hexdec( substr( $hex, 0, 2 ) );
		$green     = hexdec( substr( $hex, 2, 2 ) );
		$blue      = hexdec( substr( $hex, 4, 2 ) );
		$luminance = ( ( 0.299 * $red ) + ( 0.587 * $green ) + ( 0.114 * $blue ) ) / 255;

		return $luminance >= 0.6 ? '#1f1f1f' : '#ffffff';
	}

	private function date_label( array $event ): string {
		$start = trim( (string) rgar( $event, 'start' ) );
		$end   = trim( (string) rgar( $event, 'end' ) );

		if ( '' === $start ) {
			return '';
		}

		$start_timestamp = strtotime( $start );
		$end_timestamp   = '' !== $end ? strtotime( $end ) : false;

		if ( false === $start_timestamp ) {
			return $start;
		}

		$start_label = wp_date( 'F j, Y', $start_timestamp );

		if ( false === $end_timestamp || gmdate( 'Y-m-d', $start_timestamp ) === gmdate( 'Y-m-d', $end_timestamp ) ) {
			return $start_label;
		}

		return sprintf( '%1$s to %2$s', $start_label, wp_date( 'F j, Y', $end_timestamp ) );
	}

	private function tooltip_excerpt( string $description ): string {
		if ( '' === $description ) {
			return '';
		}

		$description = wp_strip_all_tags( html_entity_decode( $description, ENT_QUOTES, 'UTF-8' ) );
		$description = preg_replace( '/\s+/', ' ', $description );
		$description = trim( (string) $description );
		$description = ltrim( $description, "\"'" . "\xe2\x80\x9c\xe2\x80\x9d\xe2\x80\x98\xe2\x80\x99" );

		if ( '' === $description ) {
			return '';
		}

		if ( strlen( $description ) <= 260 ) {
			return $description;
		}

		$excerpt = substr( $description, 0, 257 );
		$excerpt = preg_replace( '/\s+\S*$/', '', (string) $excerpt );

		return rtrim( (string) $excerpt, " ,.;:-\"'" . "\xe2\x80\x9c\xe2\x80\x9d\xe2\x80\x98\xe2\x80\x99" ) . '...';
	}
}
