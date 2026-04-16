<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CAP_Email {

	public static function get_template( $template_name, $args = array() ) {

		// Use your plugin constant
		$template_path = CAP_TRIAL_REG_PATH . 'emails/templates/' . $template_name;

		if ( ! file_exists( $template_path ) ) {
			return '';
		}

		ob_start();

		if ( ! empty( $args ) && is_array( $args ) ) {
			extract( $args );
		}

		include $template_path;

		return ob_get_clean();
	}

	public static function send( $to, $subject, $template, $args = array(), $headers = array() ) {

		$content  = self::get_template( 'email-header.php', $args );
		$content .= self::get_template( $template, $args );
		$content .= self::get_template( 'email-footer.php', $args );

		$headers[] = 'Content-Type: text/html; charset=UTF-8';

		return wp_mail( $to, $subject, $content, $headers );
	}
}