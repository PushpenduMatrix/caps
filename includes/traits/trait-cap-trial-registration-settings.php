<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CAP_Trial_Registration_Settings_Trait {
	public function register_settings() {
		register_setting(
			'cap_trial_registration_settings',
			self::OPT_KEY,
			array( $this, 'sanitize_settings' )
		);
	}

	public function sanitize_settings( $input ) {
		$existing       = $this->get_settings();
		$venue_date_map = $this->parse_venue_date_map_input( $input['trial_venue_date_map'] ?? '' );
		$batch_timings  = $this->parse_batch_timings_input( $input['batch_timings'] ?? '' );
		$output         = $existing;

		if ( array_key_exists( 'razorpay_key_id', $input ) ) {
			$output['razorpay_key_id'] = sanitize_text_field( $input['razorpay_key_id'] );
		}
		if ( array_key_exists( 'razorpay_key_secret', $input ) ) {
			$output['razorpay_key_secret'] = sanitize_text_field( $input['razorpay_key_secret'] );
		}
		if ( array_key_exists( 'base_amount', $input ) ) {
			$output['base_amount'] = absint( $input['base_amount'] );
		}
		if ( array_key_exists( 'tax_percent', $input ) ) {
			$output['tax_percent'] = floatval( $input['tax_percent'] );
		}
		if ( array_key_exists( 'terms_url', $input ) ) {
			$output['terms_url'] = esc_url_raw( $input['terms_url'] );
		}
		if ( array_key_exists( 'trial_venue_date_map', $input ) ) {
			$output['trial_venue_date_map'] = $venue_date_map;
		}
		if ( array_key_exists( 'batch_timings', $input ) ) {
			$output['batch_timings'] = $batch_timings;
		}
		if ( array_key_exists( 'email_subject_confirmation', $input ) ) {
			$output['email_subject_confirmation'] = sanitize_text_field( $input['email_subject_confirmation'] );
		}
		if ( array_key_exists( 'email_body_confirmation', $input ) ) {
			$output['email_body_confirmation'] = sanitize_textarea_field( $input['email_body_confirmation'] );
		}
		if ( array_key_exists( 'email_subject_approval', $input ) ) {
			$output['email_subject_approval'] = sanitize_text_field( $input['email_subject_approval'] );
		}
		if ( array_key_exists( 'email_body_approval', $input ) ) {
			$output['email_body_approval'] = sanitize_textarea_field( $input['email_body_approval'] );
		}
		if ( array_key_exists( 'admin_notification_email', $input ) ) {
			$output['admin_notification_email'] = sanitize_email( $input['admin_notification_email'] );
		}
		if ( array_key_exists( 'thank_you_content', $input ) ) {
			$output['thank_you_content'] = wp_kses_post( $input['thank_you_content'] );
		}
		if ( array_key_exists( 'thank_you_page_url', $input ) ) {
			$output['thank_you_page_url'] = esc_url_raw( $input['thank_you_page_url'] );
		}
		if ( array_key_exists( 'thank_you_page_id', $input ) ) {
			$output['thank_you_page_id'] = absint( $input['thank_you_page_id'] );
		}
		if ( array_key_exists( 'registrations_per_page', $input ) ) {
			$output['registrations_per_page'] = max( 1, absint( $input['registrations_per_page'] ) );
		}
		if ( array_key_exists( 'fast2sms_sms_api_url', $input ) ) {
			$output['fast2sms_sms_api_url'] = esc_url_raw( $input['fast2sms_sms_api_url'] );
		}
		if ( array_key_exists( 'fast2sms_whatsapp_api_url', $input ) ) {
			$output['fast2sms_whatsapp_api_url'] = esc_url_raw( $input['fast2sms_whatsapp_api_url'] );
		}
		if ( array_key_exists( 'fast2sms_api_key', $input ) ) {
			$output['fast2sms_api_key'] = sanitize_text_field( $input['fast2sms_api_key'] );
		}
		if ( array_key_exists( 'fast2sms_sender_id', $input ) ) {
			$output['fast2sms_sender_id'] = sanitize_text_field( $input['fast2sms_sender_id'] );
		}
		$output['fast2sms_whatsapp_enabled'] = ! empty( $input['fast2sms_whatsapp_enabled'] ) ? '1' : ( $output['fast2sms_whatsapp_enabled'] ?? '0' );

		return $output;
	}

	private function get_settings() {
		$defaults = array(
			'razorpay_key_id'            => '',
			'razorpay_key_secret'        => '',
			'base_amount'                => 1299,
			'tax_percent'                => 18,
			'terms_url'                  => '',
			'trial_venue_date_map'       => $this->default_venue_date_map(),
			'batch_timings'              => $this->default_batch_timings(),
			'email_subject_confirmation' => 'CAP National Scholarship 2026 - Registration Confirmation',
			'email_body_confirmation'    => "Thank you for registering for CAP National Scholarship 2026.\n\nYour final trial date, batch timing, reporting details and venue instructions will be shared after final slot allocation via Email / SMS.\nPlease check your email and registered mobile number for further updates.",
			'email_subject_approval'     => 'CAP National Scholarship 2026 - Application Status',
			'email_body_approval'        => 'Your application status has been updated: {{status}}.',
			'admin_notification_email'   => get_option( 'admin_email' ),
			'thank_you_content'          => '<h2>Thank you for your payment.</h2><p>Your registration has been confirmed successfully.</p>',
			'thank_you_page_url'         => '',
			'thank_you_page_id'          => 0,
			'registrations_per_page'     => 20,
			'fast2sms_sms_api_url'       => 'https://www.fast2sms.com/dev/bulkV2',
			'fast2sms_whatsapp_api_url'  => '',
			'fast2sms_api_key'           => '',
			'fast2sms_sender_id'         => '',
			'fast2sms_whatsapp_enabled'  => '0',
		);

		$settings = wp_parse_args( get_option( self::OPT_KEY, array() ), $defaults );
		if ( empty( $settings['fast2sms_sms_api_url'] ) && ! empty( $settings['free2sms_api_url'] ) ) {
			$settings['fast2sms_sms_api_url'] = $settings['free2sms_api_url'];
		}
		if ( empty( $settings['fast2sms_api_key'] ) && ! empty( $settings['free2sms_api_key'] ) ) {
			$settings['fast2sms_api_key'] = $settings['free2sms_api_key'];
		}
		if ( empty( $settings['fast2sms_sender_id'] ) && ! empty( $settings['free2sms_sender_id'] ) ) {
			$settings['fast2sms_sender_id'] = $settings['free2sms_sender_id'];
		}
		if ( empty( $settings['fast2sms_whatsapp_enabled'] ) && isset( $settings['free2sms_whatsapp_enabled'] ) ) {
			$settings['fast2sms_whatsapp_enabled'] = $settings['free2sms_whatsapp_enabled'];
		}
		return $settings;
	}

	private function default_venue_date_map() {
		return array(
			'CAP Panvel'      => array( '16 May 2026', '17 May 2026' ),
			'CAP Jaipur'      => array( '16 May 2026', '17 May 2026' ),
			'CAP Gopalganj'   => array( '23 May 2026', '24 May 2026' ),
			'CAP Lucknow'     => array( '23 May 2026', '24 May 2026' ),
			'CAP Bhubaneswar' => array( '23 May 2026', '24 May 2026' ),
		);
	}

	private function default_batch_timings() {
		return array(
			'Morning Batch (9:00 AM - 1:00 PM)',
			'Afternoon Batch (2:00 PM - 6:00 PM)',
		);
	}

	private function parse_venue_date_map_input( $raw_input ) {
		$raw_input = is_string( $raw_input ) ? wp_unslash( $raw_input ) : '';
		$raw_input = trim( $raw_input );

		if ( '' === $raw_input ) {
			return $this->default_venue_date_map();
		}

		$json_map = json_decode( $raw_input, true );
		if ( is_array( $json_map ) ) {
			$clean_map = array();
			foreach ( $json_map as $venue => $dates ) {
				$venue = sanitize_text_field( (string) $venue );
				if ( '' === $venue || ! is_array( $dates ) ) {
					continue;
				}
				$clean_dates = array_values(
					array_filter(
						array_map(
							static function ( $date ) {
								return sanitize_text_field( (string) $date );
							},
							$dates
						)
					)
				);
				if ( ! empty( $clean_dates ) ) {
					$clean_map[ $venue ] = $clean_dates;
				}
			}
			if ( ! empty( $clean_map ) ) {
				return $clean_map;
			}
		}

		$clean_map = array();
		$lines     = preg_split( '/\r\n|\r|\n/', $raw_input );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || false === strpos( $line, '|' ) ) {
				continue;
			}
			list( $venue_part, $dates_part ) = array_pad( explode( '|', $line, 2 ), 2, '' );
			$venue = sanitize_text_field( trim( $venue_part ) );
			if ( '' === $venue ) {
				continue;
			}
			$date_items = array_values(
				array_filter(
					array_map(
						'sanitize_text_field',
						array_map( 'trim', explode( ',', $dates_part ) )
					)
				)
			);
			if ( ! empty( $date_items ) ) {
				$clean_map[ $venue ] = $date_items;
			}
		}

		return ! empty( $clean_map ) ? $clean_map : $this->default_venue_date_map();
	}

	private function parse_batch_timings_input( $raw_input ) {
		$raw_input = is_string( $raw_input ) ? wp_unslash( $raw_input ) : '';
		$raw_input = trim( $raw_input );
		if ( '' === $raw_input ) {
			return $this->default_batch_timings();
		}

		$json_items = json_decode( $raw_input, true );
		if ( is_array( $json_items ) ) {
			$timings = array_values(
				array_filter(
					array_map(
						static function ( $item ) {
							return sanitize_text_field( (string) $item );
						},
						$json_items
					)
				)
			);
			return ! empty( $timings ) ? $timings : $this->default_batch_timings();
		}

		$lines = preg_split( '/\r\n|\r|\n/', $raw_input );
		$lines = array_values(
			array_filter(
				array_map(
					static function ( $line ) {
						return sanitize_text_field( trim( $line ) );
					},
					$lines
				)
			)
		);

		return ! empty( $lines ) ? $lines : $this->default_batch_timings();
	}

	private function venue_map_to_lines( $map ) {
		$lines = array();
		foreach ( (array) $map as $venue => $dates ) {
			$lines[] = $venue . '|' . implode( ',', (array) $dates );
		}
		return implode( "\n", $lines );
	}

	private function is_valid_venue_date( $venue, $date ) {
		$settings = $this->get_settings();
		$map      = $settings['trial_venue_date_map'];

		return isset( $map[ $venue ] ) && in_array( $date, $map[ $venue ], true );
	}

	private function get_thank_you_redirect_url( $settings ) {
		if ( ! empty( $settings['thank_you_page_id'] ) ) {
			$page_url = get_permalink( (int) $settings['thank_you_page_id'] );
			if ( $page_url ) {
				return esc_url_raw( $page_url );
			}
		}
		if ( ! empty( $settings['thank_you_page_url'] ) ) {
			return esc_url_raw( $settings['thank_you_page_url'] );
		}
		return esc_url_raw( add_query_arg( array( 'cap_thank_you' => '1' ), get_permalink() ) );
	}
}
