<?php
/**
 * Bootstrap minimal pour exécuter les tests PHP de Nook sans dépendre de
 * PHPUnit ni d'une installation WordPress complète.
 *
 * Fournit des stubs des fonctions WordPress réellement utilisées par le
 * code de Nook, avec un état en mémoire (options, filtres, capacités).
 *
 * @package Nook
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'NOOK_DB_VERSION' ) ) {
	define( 'NOOK_DB_VERSION', 1 );
}

if ( ! defined( 'NOOK_PLUGIN_DIR' ) ) {
	define( 'NOOK_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );
}

if ( ! defined( 'NOOK_VERSION' ) ) {
	define( 'NOOK_VERSION', '0.1.0' );
}

if ( ! defined( 'NOOK_PLUGIN_BASENAME' ) ) {
	define( 'NOOK_PLUGIN_BASENAME', 'nook/nook.php' );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

$GLOBALS['__nook_test_options']            = array();
$GLOBALS['__nook_test_filters']            = array();
$GLOBALS['__nook_test_current_user_can']   = true;

/**
 * Réinitialise l'état en mémoire entre deux tests (options, filtres,
 * permissions) pour garantir l'isolation des cas de test.
 *
 * @return void
 */
function nook_test_reset_state() {
	$GLOBALS['__nook_test_options']            = array();
	$GLOBALS['__nook_test_filters']            = array();
	$GLOBALS['__nook_test_current_user_can']   = true;
	$GLOBALS['__nook_test_http_responses']     = array();
	$GLOBALS['__nook_test_posts']              = array();
	$GLOBALS['__nook_test_pages_by_path']      = array();
	$GLOBALS['__nook_test_is_admin']           = false;
	$GLOBALS['__nook_test_is_feed']            = false;
	$GLOBALS['__nook_test_shortcodes']         = array();
	$GLOBALS['__nook_test_permalinks']         = array();
}

// --- i18n ------------------------------------------------------------------

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( '_x' ) ) {
	function _x( $text, $context, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES );
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return ! empty( $GLOBALS['__nook_test_is_admin'] );
	}
}

if ( ! function_exists( 'is_feed' ) ) {
	function is_feed() {
		return ! empty( $GLOBALS['__nook_test_is_feed'] );
	}
}

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	function wp_doing_ajax() {
		return false;
	}
}

if ( ! function_exists( 'wp_doing_cron' ) ) {
	function wp_doing_cron() {
		return false;
	}
}

if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( $tag, $callback ) {
		$GLOBALS['__nook_test_shortcodes'][ $tag ] = $callback;
	}
}

if ( ! function_exists( 'shortcode_atts' ) ) {
	function shortcode_atts( $defaults, $atts, $shortcode = '' ) {
		$atts = (array) $atts;
		return array_merge( $defaults, array_intersect_key( $atts, $defaults ) );
	}
}

// --- Options -----------------------------------------------------------------

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		return array_key_exists( $key, $GLOBALS['__nook_test_options'] ) ? $GLOBALS['__nook_test_options'][ $key ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value ) {
		$GLOBALS['__nook_test_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $key ) {
		unset( $GLOBALS['__nook_test_options'][ $key ] );
		return true;
	}
}

// --- Hooks ---------------------------------------------------------------------

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['__nook_test_filters'][ $tag ][] = $callback;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		$extra_args = array_slice( func_get_args(), 2 );

		if ( ! empty( $GLOBALS['__nook_test_filters'][ $tag ] ) ) {
			foreach ( $GLOBALS['__nook_test_filters'][ $tag ] as $callback ) {
				$value = call_user_func_array( $callback, array_merge( array( $value ), $extra_args ) );
			}
		}

		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		// No-op : les tests appellent directement les méthodes plutôt que
		// de déclencher les hooks WordPress.
	}
}

// --- Sanitation / validation -----------------------------------------------

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $data ) {
		return (string) $data;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		$str = strip_tags( (string) $str );
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', $str ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'rest_sanitize_boolean' ) ) {
	function rest_sanitize_boolean( $value ) {
		return (bool) $value;
	}
}

if ( ! function_exists( 'rest_authorization_required_code' ) ) {
	function rest_authorization_required_code() {
		return 401;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		return (bool) $GLOBALS['__nook_test_current_user_can'];
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) {
		return 'Nook Test Site';
	}
}

// --- HTTP / scanner ----------------------------------------------------------
//
// Les tests ne font jamais de vraie requête réseau : `wp_remote_get()` lit une
// réponse simulée déposée dans `$GLOBALS['__nook_test_http_responses']`
// (indexée par URL). Sans fixture, la requête échoue proprement (comme le
// ferait une vraie requête vers une URL invalide), ce qui permet de tester
// aussi la gestion des erreurs du scanner.

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = array() ) {
		if ( isset( $GLOBALS['__nook_test_http_responses'][ $url ] ) ) {
			return $GLOBALS['__nook_test_http_responses'][ $url ];
		}

		return new WP_Error( 'nook_test_no_fixture', 'Aucune réponse HTTP simulée pour ' . $url );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		return is_array( $response ) && isset( $response['body'] ) ? $response['body'] : '';
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		return is_array( $response ) && isset( $response['response']['code'] ) ? $response['response']['code'] : 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_cookies' ) ) {
	function wp_remote_retrieve_cookies( $response ) {
		return is_array( $response ) && isset( $response['cookies'] ) ? $response['cookies'] : array();
	}
}

if ( ! class_exists( 'WP_Http_Cookie' ) ) {
	class WP_Http_Cookie {
		public $name;
		public $value;

		public function __construct( $data = array() ) {
			$this->name  = isset( $data['name'] ) ? $data['name'] : '';
			$this->value = isset( $data['value'] ) ? $data['value'] : '';
		}
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '/' ) {
		return 'https://example.test' . $path;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = 0 ) {
		return gmdate( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $expiration = 0 ) {
		$GLOBALS['__nook_test_options'][ 'transient_' . $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return isset( $GLOBALS['__nook_test_options'][ 'transient_' . $key ] ) ? $GLOBALS['__nook_test_options'][ 'transient_' . $key ] : false;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['__nook_test_options'][ 'transient_' . $key ] );
		return true;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = array() ) {
		$post_type = isset( $args['post_type'] ) ? $args['post_type'] : 'post';
		return isset( $GLOBALS['__nook_test_posts'][ $post_type ] ) ? $GLOBALS['__nook_test_posts'][ $post_type ] : array();
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post ) {
		if ( is_array( $post ) && isset( $post['permalink'] ) ) {
			return $post['permalink'];
		}
		if ( is_object( $post ) && isset( $post->permalink ) ) {
			return $post->permalink;
		}
		if ( ( is_int( $post ) || is_string( $post ) ) && isset( $GLOBALS['__nook_test_permalinks'][ $post ] ) ) {
			return $GLOBALS['__nook_test_permalinks'][ $post ];
		}
		return false;
	}
}

if ( ! function_exists( 'get_page_by_path' ) ) {
	function get_page_by_path( $path ) {
		return isset( $GLOBALS['__nook_test_pages_by_path'][ $path ] ) ? $GLOBALS['__nook_test_pages_by_path'][ $path ] : null;
	}
}

if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $namespace, $route, $args = array(), $override = false ) {
		return true;
	}
}

// --- Classes WordPress minimales --------------------------------------------

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;

		public function __construct( $code = '', $message = '', $data = array() ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private $json_params;
		private $params;

		public function __construct( array $json_params = array(), array $params = array() ) {
			$this->json_params = $json_params;
			$this->params      = $params;
		}

		public function get_json_params() {
			return $this->json_params;
		}

		public function get_param( $key ) {
			return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		public $data;
		public $status;

		public function __construct( $data = null, $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		public function get_data() {
			return $this->data;
		}
	}
}

// --- Autoloader PSR-4 (identique à celui de nook.php) -----------------------

spl_autoload_register(
	function ( $class ) {
		$prefix = 'Nook\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$relative_path  = str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';
		$file           = NOOK_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . $relative_path;

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);
