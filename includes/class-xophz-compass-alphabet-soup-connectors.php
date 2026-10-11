<?php

/**
 * Alphabet Soup credentials, resolved through the WordPress core Connectors API.
 *
 * The OpenAI key for the AI assistant is read here and nowhere else. Core exposes no public
 * function that returns a key's value, so get_key() follows the same precedence as core's
 * _wp_connectors_get_api_key_source(): environment variable, then PHP constant, then the database
 * option. Every name comes from the connector's own `authentication` definition, so this plugin
 * never hard-codes an option, constant or environment variable name.
 *
 * Values are for server-side use only. Never echo one, log one, return one from a REST route or
 * put one in a URL.
 *
 * @package    Xophz_Compass_Alphabet_Soup
 * @subpackage Xophz_Compass_Alphabet_Soup/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Xophz_Compass_Alphabet_Soup_Connectors {

	/** Core AI provider connector for OpenAI. */
	const OPENAI = 'openai';

	/**
	 * The credential for a registered connector, or '' when it is unregistered or unset.
	 *
	 * @param string $connector_id Connector id, e.g. 'openai'.
	 * @return string
	 */
	public static function get_key( $connector_id ) {
		$is_available = function_exists( 'wp_get_connector' ) && function_exists( 'wp_is_connector_registered' );
		if ( ! $is_available || ! wp_is_connector_registered( $connector_id ) ) {
			return '';
		}

		$connector = wp_get_connector( $connector_id );
		$auth      = is_array( $connector ) ? ( $connector['authentication'] ?? array() ) : array();

		$from_env = self::from_env( (string) ( $auth['env_var_name'] ?? '' ) );
		if ( '' !== $from_env ) {
			return $from_env;
		}

		$from_constant = self::from_constant( (string) ( $auth['constant_name'] ?? '' ) );
		if ( '' !== $from_constant ) {
			return $from_constant;
		}

		return self::from_option( (string) ( $auth['setting_name'] ?? '' ) );
	}

	private static function from_env( $env_var_name ) {
		return '' === $env_var_name ? '' : self::as_string( getenv( $env_var_name ) );
	}

	private static function from_constant( $constant_name ) {
		$is_defined = '' !== $constant_name && defined( $constant_name );
		return $is_defined ? self::as_string( constant( $constant_name ) ) : '';
	}

	private static function from_option( $setting_name ) {
		return '' === $setting_name ? '' : self::as_string( get_option( $setting_name, '' ) );
	}

	private static function as_string( $value ) {
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}
}
