<?php
/**
 * WooCommerce Marketing.
 */

namespace Automattic\WooCommerce\Internal\Admin;

use Automattic\WooCommerce\Admin\Features\Features;
use Automattic\WooCommerce\Admin\Marketing\InstalledExtensions;
use Automattic\WooCommerce\Internal\Admin\Loader;
use Automattic\WooCommerce\Admin\PageController;

/**
 * Contains backend logic for the Marketing feature.
 */
class Marketing {

	use CouponsMovedTrait;

	/**
	 * Name of recommended plugins transient.
	 *
	 * @var string
	 */
	const RECOMMENDED_PLUGINS_TRANSIENT = 'wc_marketing_recommended_plugins';

	/**
	 * Name of knowledge base post transient.
	 *
	 * @var string
	 */
	const KNOWLEDGE_BASE_TRANSIENT = 'wc_marketing_knowledge_base';

	/**
	 * Class instance.
	 *
	 * @var Marketing instance
	 */
	protected static $instance = null;

	/**
	 * Get class instance.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hook into WooCommerce.
	 */
	public function __construct() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', array( $this, 'register_pages' ), 5 );
		add_action( 'admin_menu', array( $this, 'add_parent_menu_item' ), 6 );

		add_filter( 'woocommerce_admin_preload_options', array( $this, 'preload_options' ) );
		add_filter( 'woocommerce_admin_shared_settings', array( $this, 'component_settings' ), 30 );
	}

	/**
	 * Add main marketing menu item.
	 *
	 * Uses priority of 9 so other items can easily be added at the default priority (10).
	 */
	public function add_parent_menu_item() {
		if ( ! Features::is_enabled( 'navigation' ) ) {
			add_menu_page(
				__( 'Marketing', 'woocommerce' ),
				__( 'Marketing', 'woocommerce' ),
				'manage_woocommerce',
				'woocommerce-marketing',
				null,
				'dashicons-megaphone',
				58
			);
		}

		PageController::get_instance()->connect_page(
			[
				'id'         => 'woocommerce-marketing',
				'title'      => 'Marketing',
				'capability' => 'manage_woocommerce',
				'path'       => 'wc-admin&path=/marketing',
			]
		);
	}

	/**
	 * Registers report pages.
	 */
	public function register_pages() {
		$this->register_overview_page();

		$controller = PageController::get_instance();
		$defaults   = [
			'parent'        => 'woocommerce-marketing',
			'existing_page' => false,
		];

		$marketing_pages = apply_filters( 'woocommerce_marketing_menu_items', [] );
		foreach ( $marketing_pages as $marketing_page ) {
			if ( ! is_array( $marketing_page ) ) {
				continue;
			}

			$marketing_page = array_merge( $defaults, $marketing_page );

			if ( $marketing_page['existing_page'] ) {
				$controller->connect_page( $marketing_page );
			} else {
				$controller->register_page( $marketing_page );
			}
		}
	}

	/**
	 * Register the main Marketing page, which is Marketing > Overview.
	 *
	 * This is done separately because we need to ensure the page is registered properly and
	 * that the link is done properly. For some reason the normal page registration process
	 * gives us the wrong menu link.
	 */
	protected function register_overview_page() {
		global $submenu;

		// First register the page.
		PageController::get_instance()->register_page(
			[
				'id'       => 'woocommerce-marketing-overview',
				'title'    => __( 'Overview', 'woocommerce' ),
				'path'     => 'wc-admin&path=/marketing',
				'parent'   => 'woocommerce-marketing',
				'nav_args' => array(
					'parent' => 'woocommerce-marketing',
					'order'  => 10,
				),
			]
		);

		// Now fix the path, since register_page() gets it wrong.
		if ( ! isset( $submenu['woocommerce-marketing'] ) ) {
			return;
		}

		foreach ( $submenu['woocommerce-marketing'] as &$item ) {
			// The "slug" (aka the path) is the third item in the array.
			if ( 0 === strpos( $item[2], 'wc-admin' ) ) {
				$item[2] = 'admin.php?page=' . $item[2];
			}
		}
	}

	/**
	 * Preload options to prime state of the application.
	 *
	 * @param array $options Array of options to preload.
	 * @return array
	 */
	public function preload_options( $options ) {
		$options[] = 'woocommerce_marketing_overview_welcome_hidden';

		return $options;
	}

	/**
	 * Add settings for marketing feature.
	 *
	 * @param array $settings Component settings.
	 * @return array
	 */
	public function component_settings( $settings ) {
		// Bail early if not on a wc-admin powered page.
		if ( ! PageController::is_admin_page() ) {
			return $settings;
		}

		$settings['marketing']['installedExtensions'] = InstalledExtensions::get_data();

		return $settings;
	}

	/**
	 * Load recommended plugins from WooCommerce.com
	 *
	 * @return array
	 */
	public function get_recommended_plugins() {
		$plugins = get_transient( self::RECOMMENDED_PLUGINS_TRANSIENT );

		if ( false === $plugins ) {
			$request = wp_remote_get(
				'https://woocommerce.com/wp-json/wccom/marketing-tab/1.1/recommendations.json',
				array(
					'user-agent' => 'WooCommerce/' . WC()->version . '; ' . get_bloginfo( 'url' ),
				)
			);
			$plugins = [];

			if ( ! is_wp_error( $request ) && 200 === $request['response']['code'] ) {
				$plugins = json_decode( $request['body'], true );
			}

			set_transient(
				self::RECOMMENDED_PLUGINS_TRANSIENT,
				$plugins,
				// Expire transient in 15 minutes if remote get failed.
				// Cache an empty result to avoid repeated failed requests.
				empty( $plugins ) ? 900 : 3 * DAY_IN_SECONDS
			);
		}

		return array_values( $plugins );
	}

	/**
	 * Load knowledge base posts from WooCommerce.com
	 *
	 * @param string $category Category of posts to retrieve.
	 * @return array
	 */
	public function get_knowledge_base_posts( $category ) {

		$kb_transient = self::KNOWLEDGE_BASE_TRANSIENT;

		$categories = array(
			'marketing' => 1744,
			'coupons'   => 25202,
		);

		// Default to marketing category (if no category set on the kb component).
		if ( ! empty( $category ) && array_key_exists( $category, $categories ) ) {
			$category_id  = $categories[ $category ];
			$kb_transient = $kb_transient . '_' . strtolower( $category );
		} else {
			$category_id = $categories['marketing'];
		}

		$posts = get_transient( $kb_transient );

		if ( false === $posts ) {
			$request_url = add_query_arg(
				array(
					'categories' => $category_id,
					'page'       => 1,
					'per_page'   => 8,
					'_embed'     => 1,
				),
				'https://woocommerce.com/wp-json/wp/v2/posts?utm_medium=product'
			);

			$request = wp_remote_get(
				$request_url,
				array(
					'user-agent' => 'WooCommerce/' . WC()->version . '; ' . get_bloginfo( 'url' ),
				)
			);
			$posts   = [];

			if ( ! is_wp_error( $request ) && 200 === $request['response']['code'] ) {
				$raw_posts = json_decode( $request['body'], true );

				foreach ( $raw_posts as $raw_post ) {
					$post = [
						'title'         => html_entity_decode( $raw_post['title']['rendered'] ),
						'date'          => $raw_post['date_gmt'],
						'link'          => $raw_post['link'],
						'author_name'   => isset( $raw_post['author_name'] ) ? html_entity_decode( $raw_post['author_name'] ) : '',
						'author_avatar' => isset( $raw_post['author_avatar_url'] ) ? $raw_post['author_avatar_url'] : '',
					];

					$featured_media = $raw_post['_embedded']['wp:featuredmedia'] ?? [];
					if ( count( $featured_media ) > 0 ) {
						$image         = current( $featured_media );
						$post['image'] = add_query_arg(
							array(
								'resize' => '650,340',
								'crop'   => 1,
							),
							$image['source_url']
						);
					}

					$posts[] = $post;
				}
			}

			set_transient(
				$kb_transient,
				$posts,
				// Expire transient in 15 minutes if remote get failed.
				empty( $posts ) ? 900 : DAY_IN_SECONDS
			);
		}

		return $posts;
	}
}
