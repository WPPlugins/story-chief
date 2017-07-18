<?php

class Storychief {

	private static $initiated = false;

	public static function init() {
		if (!self::$initiated) {
			add_action('wp_head', array('Storychief', 'add_meta_tags'));
			self::init_hooks();
		}
	}

	public static function add_meta_tags() {
		echo '<meta property="fb:pages" content="' . self::get_meta_fb_pages() . '" />';
		global $post;

		if (!empty($post) && !empty($amphtml = get_post_meta($post->ID, '_amphtml', true))) {
			echo '<link rel="amphtml" href="' . $amphtml . '" />';
		}
	}

	/**
	 * Initializes WordPress hooks
	 */
	private static function init_hooks() {
		self::$initiated = true;
		add_action('rest_api_init', array('Storychief', 'register_routes'));
		add_filter('storychief_save_tags_filter', array('Storychief', 'saveTags'));
		add_filter('storychief_save_categories_filter', array('Storychief', 'saveCategories'));
		add_filter('storychief_save_featured_image_filter', array('Storychief', 'saveFeaturedImage'));
	}

	public static function register_routes() {
		register_rest_route('storychief', 'webhook', array(
			'methods'  => 'POST',
			'callback' => 'Storychief::handleWebhook',
		));
	}

	/**
	 * The Main webhook function, orchestrates the requested event to its corresponding function.
	 *
	 * @param WP_REST_Request $request
	 * @return mixed
	 */
	public static function handleWebhook(WP_REST_Request $request) {
		$payload = json_decode($request->get_body(), true);

		if (!Storychief::validMac($payload)) return new WP_Error('invalid_mac', 'The Mac is invalid', array('status' => 400));
		if (!isset($payload['meta']['event'])) return new WP_Error('no_event_type', 'The event is not set', array('status' => 400));

		$payload = apply_filters('storychief_before_handle_filter', $payload);

		Storychief::handleFacebookPageMeta($payload);

		$method = 'handle' . preg_replace('/\s+/', '', ucwords($payload['meta']['event']));

		if (method_exists('Storychief', $method)) {
			$response = Storychief::$method($payload);
			if (is_wp_error($response)) {
				return $response;
			}
			$response = Storychief::appendMac($response);
		} else {
			$response = Storychief::missingMethod();
		}

		return rest_ensure_response($response);
	}

	/**
	 * Update the FB page ids for Instant Articles
	 * @param $payload
	 */
	protected static function handleFacebookPageMeta($payload) {
		if (isset($payload['meta']['fb-page-ids'])) {
			Storychief::set_meta_fb_pages($payload['meta']['fb-page-ids']);
		}
	}

	/**
	 * Handle a publish webhook call
	 *
	 * @param $payload
	 * @return array
	 */
	protected static function handlePublish($payload) {
		$story = $payload['data'];

		// After publish action
		do_action('storychief_before_publish_action', array_merge($story));

		$post = array(
			'post_title'   => $story['title'],
			'post_content' => $story['content'],
			'post_excerpt' => $story['excerpt'] ? $story['excerpt'] : '',
			'post_status'  => (self::get_test_mode()) ? 'draft' : 'publish',
			'meta_input'   => [],
		);

		// Author
		if (isset($story['author']['data']['email'])) {
			$user_id = email_exists($story['author']['data']['email']);
			if (!$user_id && self::get_author_create()) {
				$user_id = wp_create_user($story['author']['data']['email'], '', $story['author']['data']['email']);
				wp_update_user([
					'ID'            => $user_id,
					'first_name'    => $story['author']['data']['first_name'],
					'last_name'     => $story['author']['data']['last_name'],
					'display_name'  => $story['author']['data']['first_name'] . ' ' . $story['author']['data']['last_name'],
					'user_nicename' => $story['author']['data']['first_name'] . ' ' . $story['author']['data']['last_name'],
					'description'   => $story['author']['data']['bio'],
					'role'          => 'author',
				]);
			}

			$post['post_author'] = $user_id ? $user_id : null;
		}

		if (isset($story['amphtml'])) {
			$post['meta_input']['_amphtml'] = $story['amphtml'];
		}

		// disable sanitation
		kses_remove_filters();
		// create post
		$post_ID = wp_insert_post($post);
		// enable sanitation
		kses_init_filters();

		$story = array_merge($story, ['external_id' => $post_ID]);

		// Tags
		$story = apply_filters('storychief_save_tags_filter', $story);

		// Categories
		$story = apply_filters('storychief_save_categories_filter', $story);

		// Featured Image
		$story = apply_filters('storychief_save_featured_image_filter', $story);

		// After publish action
		do_action('storychief_after_publish_action', $story);

		return array(
			'id'        => $post_ID,
			'permalink' => get_post_permalink($post_ID),
		);
	}

	/**
	 * Handle a update webhook call
	 *
	 * @param $payload
	 * @return array|WP_Error
	 */
	protected static function handleUpdate($payload) {
		$story = $payload['data'];

		if (!get_post_status($story['external_id'])) {
			return new WP_Error('post_not_found', 'The post could not be found', array('status' => 404));
		}

		// After publish action
		do_action('storychief_before_publish_action', array_merge($story));

		$post = array(
			'ID'           => $story['external_id'],
			'post_title'   => $story['title'],
			'post_content' => $story['content'],
			'post_excerpt' => $story['excerpt'] ? $story['excerpt'] : '',
			'post_status'  => (self::get_test_mode()) ? 'draft' : 'publish',
			'meta_input'   => [],
		);

		// Author
		if (isset($story['author']['data']['email'])) {
			$user_id = email_exists($story['author']['data']['email']);
			if (!$user_id && self::get_author_create()) {
				$user_id = wp_create_user($story['author']['data']['email'], '', $story['author']['data']['email']);
				wp_update_user([
					'ID'            => $user_id,
					'first_name'    => $story['author']['data']['first_name'],
					'last_name'     => $story['author']['data']['last_name'],
					'display_name'  => $story['author']['data']['first_name'] . ' ' . $story['author']['data']['last_name'],
					'user_nicename' => $story['author']['data']['first_name'] . ' ' . $story['author']['data']['last_name'],
					'description'   => $story['author']['data']['bio'],
					'role'          => 'author',
				]);
			}

			$post['post_author'] = $user_id ? $user_id : null;
		}

		if (isset($story['amphtml'])) {
			$post['meta_input']['_amphtml'] = $story['amphtml'];
		}

		// disable sanitation
		kses_remove_filters();
		// update post
		$post_ID = wp_update_post($post);
		// enable sanitation
		kses_init_filters();

		$story = array_merge($story, ['external_id' => $post_ID]);

		// Tags
		$story = apply_filters('storychief_save_tags_filter', $story);

		// Categories
		$story = apply_filters('storychief_save_categories_filter', $story);

		// Featured Image
		$story = apply_filters('storychief_save_featured_image_filter', $story);

		// After publish action
		do_action('storychief_after_publish_action', $story);

		return array(
			'id'        => $post_ID,
			'permalink' => get_post_permalink($post_ID),
		);
	}

	/**
	 * Handle a delete webhook call
	 *
	 * @param $payload
	 * @return array
	 */
	protected static function handleDelete($payload) {
		$story = $payload['data'];
		$post_ID = $story['external_id'];
		wp_delete_post($post_ID);

		do_action('storychief_after_delete_action', $story);

		return array(
			'id'        => $story['external_id'],
			'permalink' => null,
		);
	}

	/**
	 * Handle a connection test webhook call
	 * @param $payload
	 */
	protected static function handleTest($payload) {
		$story = $payload['data'];

		do_action('storychief_after_test_action', $story);

		return;
	}

	/**
	 * Sync tags
	 *
	 * @param $story
	 * @return array
	 */
	public static function saveTags($story) {
		if (isset($story['tags']['data'])) {
			$tags = array();
			foreach ($story['tags']['data'] as $tag) {
				$tags[] = $tag['name'];
			}
			wp_set_post_tags($story['external_id'], $tags, false);
		}

		return $story;
	}

	/**
	 * Sync categories
	 *
	 * @param $story
	 * @return array
	 */
	public static function saveCategories($story) {
		error_log(print_r($story, true));
		if (isset($story['categories']['data'])) {
			$categories = array();
			foreach ($story['categories']['data'] as $category) {
				if (!$categoryId = get_cat_ID($category['name'])) {
					if (!function_exists('wp_create_category')) require_once(ABSPATH . 'wp-admin/includes/taxonomy.php');
					$categoryId = wp_create_category($category['name']);
				}
				$categories[] = $categoryId;
			}
			wp_set_post_categories($story['external_id'], $categories, false);
		}

		return $story;
	}

	/**
	 * Save Featured Image
	 *
	 * @param $story
	 * @return array
	 */
	public static function saveFeaturedImage($story) {
		if (isset($story['featured_image']['data']['sizes']['full'])) {
			$image_url = $story['featured_image']['data']['sizes']['full'];
			$image_name = $story['featured_image']['data']['name'];

			$attachment = self::sideload_image($image_url, $image_name);
			$attach_id = self::import_image($attachment, $story['title'], $story['external_id']);
			set_post_thumbnail($story['external_id'], $attach_id);
		}

		return $story;
	}

	/**
	 * Handle calls to missing methods on the controller.
	 *
	 * @return mixed
	 */
	protected static function missingMethod() {
		return;
	}

	/**
	 * Append a MAC to the given payload.
	 *
	 * @param  $payload
	 * @return array
	 */
	private static function appendMac($payload) {
		$payload['mac'] = hash_hmac('sha256', json_encode($payload), Storychief::get_encryption_key());

		return $payload;
	}

	/**
	 * Determine if the MAC for the given payload is valid.
	 *
	 * @param  $payload
	 * @return bool
	 */
	private static function validMac($payload) {
		if (isset($payload['meta']['mac'])) {
			$givenMac = $payload['meta']['mac'];
			unset($payload['meta']['mac']);
			$calcMac = hash_hmac('sha256', json_encode($payload), Storychief::get_encryption_key());

			return hash_equals($givenMac, $calcMac);
		}

		return false;
	}

	/**
	 * Display a view
	 *
	 * @param $name
	 * @param array $args
	 */
	public static function view($name, array $args = array()) {
		$args = apply_filters('storychief_view_arguments', $args, $name);
		foreach ($args AS $key => $val) {
			$$key = $val;
		}

		load_plugin_textdomain('storychief');
		$file = STORYCHIEF__PLUGIN_DIR . 'views/' . $name . '.php';
		include($file);
	}

	/**
	 * Attached to activate_{ plugin_basename( __FILES__ ) } by register_activation_hook()
	 * @static
	 */
	public static function plugin_activation() {
		if (version_compare($GLOBALS['wp_version'], STORYCHIEF__MINIMUM_WP_VERSION, '<')) {
			Storychief_Admin::notice_invalid_version();
			Storychief_Admin::admin_notice();
			Storychief::bail_on_activation();
		} elseif (!has_action('rest_api_init')) {
			Storychief_Admin::notice_parent_plugin_required();
			Storychief_Admin::admin_notice();
			Storychief::bail_on_activation();
		}
	}

	/**
	 * Removes all connection options
	 * @static
	 */
	public static function plugin_deactivation() {
		self::remove_encryption_key();
		self::remove_test_mode();
		self::remove_author_create();
		self::remove_meta_fb_pages();
	}

	/**
	 * @param bool $deactivate
	 */
	private static function bail_on_activation($deactivate = true) {
		if ($deactivate) {
			$plugins = get_option('active_plugins');
			$storychief = plugin_basename(STORYCHIEF__PLUGIN_DIR . 'storychief.php');
			$update = false;
			foreach ($plugins as $i => $plugin) {
				if ($plugin === $storychief) {
					$plugins[$i] = false;
					$update = true;
				}
			}

			if ($update) {
				update_option('active_plugins', array_filter($plugins));
			}
		}
		exit;
	}


	public static function get_encryption_key() {
		return get_option('storychief_encryption_key');
	}

	public static function set_encryption_key($key) {
		update_option('storychief_encryption_key', $key);
	}

	public static function remove_encryption_key() {
		delete_option('storychief_encryption_key');
	}

	public static function get_test_mode() {
		return get_option('storychief_test_mode');
	}

	public static function set_test_mode($value) {
		update_option('storychief_test_mode', $value);
	}

	public static function remove_test_mode() {
		delete_option('storychief_test_mode');
	}

	public static function get_author_create() {
		return get_option('storychief_author_create');
	}

	public static function set_author_create($value) {
		update_option('storychief_author_create', $value);
	}

	public static function remove_author_create() {
		delete_option('storychief_author_create');
	}

	public static function get_meta_fb_pages() {
		return get_option('storychief_meta_fb_pages');
	}

	public static function set_meta_fb_pages($meta) {
		update_option('storychief_meta_fb_pages', $meta);
	}

	public static function remove_meta_fb_pages() {
		delete_option('storychief_meta_fb_pages');
	}

	public static function import_image($attachment, $post_name = null, $post_ID = null) {
		if (is_null($post_name)) $post_name = uniqid();

		if ($attachment && empty($attachment['error'])) {
			$filetype = wp_check_filetype(basename($attachment['file']), null);
			$postinfo = array(
				'post_mime_type' => $filetype['type'],
				'post_title'     => $post_name,
				'post_content'   => '',
				'post_status'    => 'inherit',
			);
			$filename = $attachment['file'];

			$attach_id = wp_insert_attachment($postinfo, $filename, $post_ID);

			if (!function_exists('wp_generate_attachment_data')) require_once(ABSPATH . 'wp-admin/includes/image.php');

			$attach_data = wp_generate_attachment_metadata($attach_id, $filename);
			wp_update_attachment_metadata($attach_id, $attach_data);

			return $attach_id;
		}

		return '';
	}

	public static function sideload_image($image_url, $image_name) {
		if (!class_exists('WP_Http')) include_once(ABSPATH . WPINC . '/class-http.php');

		$photo = new WP_Http();
		$photo = $photo->request($image_url);
		if ($photo['response']['code'] != 200) return false;

		return wp_upload_bits($image_name, null, $photo['body'], date("Y-m", strtotime($photo['headers']['last-modified'])));
	}
}