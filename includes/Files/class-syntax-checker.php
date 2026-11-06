<?php
/**
 * PHP syntax check before saving.
 *
 * @package FileSmithEditor
 */

namespace FileSmithEditor\Files;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Syntax_Checker
 *
 * Parses PHP code with the tokenizer, which does not run the code. A file with a
 * parse error is not saved, so the site cannot break from a typo.
 */
class Syntax_Checker {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'fsed_pre_save', array( $this, 'filter_pre_save' ), 10, 5 );
	}

	/**
	 * Stop the save when the PHP code has a syntax error.
	 *
	 * @param true|\WP_Error $check   Current result.
	 * @param string         $path    Absolute file path.
	 * @param string         $content New content.
	 * @param string         $type    Either 'theme' or 'plugin'.
	 * @param string         $slug    Theme stylesheet or plugin basename.
	 * @return true|\WP_Error
	 */
	public function filter_pre_save( $check, $path, $content, $type, $slug ) {
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		if ( 'php' !== $extension ) {
			return $check;
		}

		/**
		 * Filters whether PHP syntax is checked before saving.
		 *
		 * @param bool   $enabled Whether to check. Default true.
		 * @param string $path    Absolute file path.
		 * @param string $type    Either 'theme' or 'plugin'.
		 * @param string $slug    Theme stylesheet or plugin basename.
		 */
		if ( ! apply_filters( 'fsed_check_php_syntax', true, $path, $type, $slug ) ) {
			return $check;
		}

		$error = $this->check_php( $content );
		if ( null === $error ) {
			return $check;
		}

		return new \WP_Error(
			'fsed_syntax_error',
			sprintf(
				/* translators: 1: line number, 2: PHP error message. */
				__( 'PHP syntax error on line %1$d: %2$s. The file was not saved.', 'filesmith-editor' ),
				$error['line'],
				$error['message']
			),
			array( 'status' => 400 )
		);
	}

	/**
	 * Check PHP code for parse errors.
	 *
	 * @param string $code PHP code.
	 * @return array|null Array with line and message, or null when the code is valid
	 *                    or the tokenizer is not available.
	 */
	public function check_php( $code ) {
		if ( ! function_exists( 'token_get_all' ) || ! defined( 'TOKEN_PARSE' ) ) {
			return null;
		}

		try {
			token_get_all( (string) $code, TOKEN_PARSE );
		} catch ( \ParseError $e ) {
			return array(
				'line'    => (int) $e->getLine(),
				'message' => $e->getMessage(),
			);
		}

		return null;
	}
}
