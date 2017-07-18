<?php

class Storychief_Admin {
	const NONCE = 'storychief-update-key';

	private static $initiated = false;
	private static $notices = array();

	public static function init() {
		if (!self::$initiated) {
			self::init_hooks();
		}

		if (isset($_POST['action']) && $_POST['action'] == 'enter-key') {
			self::save_configuration();
		}
	}

	public static function init_hooks() {
		self::$initiated = true;

		add_action('admin_init', array('Storychief_Admin', 'admin_init'));
		add_action('admin_menu', array('Storychief_Admin', 'admin_menu'));
		add_action('admin_notices', array('Storychief_Admin', 'admin_notice'));

		add_filter('plugin_action_links', array('Storychief_Admin', 'plugin_action_links'), 10, 2);
	}

	public static function admin_init() {
		load_plugin_textdomain('storychief');
		if(class_exists('Polylang') && !class_exists('Storychief_PPL')){
			self::notice_polylang_plugin_available();
		}

		if(function_exists('icl_object_id') && !class_exists('Storychief_WPML')){
			self::notice_wpml_plugin_available();
		}

		if(class_exists('Acf') && !class_exists('Storychief_ACF')){
			self::notice_acf_plugin_available();
		}
	}

	public static function admin_menu() {
		$hook = add_options_page('Storychief', 'Storychief', 'manage_options', 'storychief', array(
			'Storychief_Admin',
			'display_configuration_page'
		));

		add_action("load-$hook", array('Storychief_Admin', 'admin_help'));
	}

	/**
	 * Add help to the Storychief page
	 *
	 * @return false if not the Storychief page
	 */
	public static function admin_help() {
		$current_screen = get_current_screen();
		// Screen Content
		if (current_user_can('manage_options')) {
			$current_screen->add_help_tab(
				array(
					'id'      => 'overview',
					'title'   => __('Overview', 'storychief'),
					'content' =>
						'<p><strong>' . esc_html__('Storychief Configuration', 'storychief') . '</strong></p>' .
						'<p>' . esc_html__('Storychief publishes posts, so you can focus on more important things.', 'storychief') . '</p>' .
						'<p>' . esc_html__('Save your given key here.', 'storychief') . '</p>',
				)
			);

			$current_screen->add_help_tab(
				array(
					'id'      => 'settings',
					'title'   => __('Settings', 'storychief'),
					'content' =>
						'<p><strong>' . esc_html__('Storychief Configuration', 'storychief') . '</strong></p>' .
						'<p><strong>' . esc_html__('Encryption Key', 'storychief') . '</strong> - ' . esc_html__('Enter your Encryption key.', 'storychief') . '</p>',
				)
			);
		}

		// Help Sidebar
		$current_screen->set_help_sidebar(
			'<p><strong>' . esc_html__('For more information:', 'storychief') . '</strong></p>' .
			'<p><a href="https://storychief.zendesk.com" target="_blank">' . esc_html__('Storychief FAQ', 'storychief') . '</a></p>' .
			'<p><a href="https://storychief.zendesk.com" target="_blank">' . esc_html__('Storychief Support', 'storychief') . '</a></p>'
		);
	}

	public static function save_configuration() {
		if (function_exists('current_user_can') && !current_user_can('manage_options')) {
			die(__('Cheatin&#8217; uh?', 'storychief'));
		}
		if (!wp_verify_nonce($_POST['_wpnonce'], self::NONCE)) {
			return false;
		}

		Storychief::set_encryption_key($_POST['key']);
		Storychief::set_test_mode(isset($_POST['test_mode']) ? true : false);
		Storychief::set_author_create(isset($_POST['author_create']) ? true : false);
		self::notice_config_saved();

		return true;
	}

	public static function settings_link($links) {
		$settings_link = '<a href="options-general.php?page=storychief">' . __('Settings') . '</a>';
		array_push($links, $settings_link);

		return $links;
	}

	public static function plugin_action_links($links, $file) {
		if ($file == plugin_basename(plugin_dir_url(__FILE__) . '/storychief.php')) {
			$links[] = '<a href="' . esc_url(self::get_page_url()) . '">' . esc_html__('Settings', 'storychief') . '</a>';
		}

		return $links;
	}

	public static function get_page_url() {
		$args = array('page' => 'storychief');
		$url = add_query_arg($args, admin_url('options-general.php'));

		return $url;
	}

	public static function display_configuration_page() {
		$encryption_key = Storychief::get_encryption_key();
		$test_mode = Storychief::get_test_mode();
		$author_create = Storychief::get_author_create();
		$wp_url = get_site_url();
		Storychief::view('config', compact('encryption_key', 'wp_url', 'test_mode', 'author_create'));
	}

	/*----------- NOTICES -----------*/
	public static function admin_notice() {
		if (!empty(self::$notices)) {
			foreach (self::$notices as $notice) {
				Storychief::view('notice', $notice);
			}

			self::$notices = array();
		}
	}

	public static function notice_undefined_error() {
		self::$notices[] = array(
			'type' => 'undefined',
		);
	}

	public static function notice_invalid_version() {
		self::$notices[] = array(
			'type' => 'version',
		);
	}

	public static function notice_parent_plugin_required() {
		self::$notices[] = array(
			'type' => 'parent-plugin',
		);
	}

	public static function notice_wpml_plugin_available() {
		self::$notices[] = array(
			'type' => 'wpml-plugin',
		);
	}

	public static function notice_polylang_plugin_available() {
		self::$notices[] = array(
			'type' => 'polylang-plugin',
		);
	}

	public static function notice_acf_plugin_available() {
		self::$notices[] = array(
			'type' => 'acf-plugin',
		);
	}

	public static function notice_config_saved() {
		self::$notices[] = array(
			'type' => 'config-set',
		);
	}
}