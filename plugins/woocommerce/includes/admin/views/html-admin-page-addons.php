<?php
/**
 * Admin View: Page - Addons
 *
 * @package WooCommerce\Admin
 * @var string $view
 * @var object $addons
 * @var object $promotions
 */

use Automattic\WooCommerce\Admin\RemoteInboxNotifications as PromotionRuleEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_section_name = __( 'Browse Categories', 'woocommerce' );
?>
<div class="wrap woocommerce wc_addons_wrap">
	<?php if ( $sections ) : ?>
	<div class="marketplace-header">
		<h1 class="marketplace-header__title"><?php esc_html_e( 'WooCommerce Marketplace', 'woocommerce' ); ?></h1>
		<p class="marketplace-header__description"><?php esc_html_e( 'Grow your business with hundreds of free and paid WooCommerce extensions.', 'woocommerce' ); ?></p>
		<form class="marketplace-header__search-form" method="GET">
			<input
				type="text"
				name="search"
				value="<?php echo esc_attr( ! empty( $search ) ? sanitize_text_field( wp_unslash( $search ) ) : '' ); ?>"
				placeholder="<?php esc_attr_e( 'Search for extensions', 'woocommerce' ); ?>"
			/>
			<button type="submit">
				<span class="dashicons dashicons-search"></span>
			</button>
			<input type="hidden" name="page" value="wc-addons">
			<input type="hidden" name="section" value="_all">
		</form>
	</div>
	<div class="top-bar">
		<div id="marketplace-current-section-dropdown" class="current-section-dropdown">
			<ul>
				<?php foreach ( $sections as $section ) : ?>
					<?php
					if ( $current_section === $section->slug && '_featured' !== $section->slug ) {
						$current_section_name = $section->label;
					}
					?>
					<li>
						<a
							class="<?php echo $current_section === $section->slug ? 'current' : ''; ?>"
							href="<?php echo esc_url( admin_url( 'admin.php?page=wc-addons&section=' . esc_attr( $section->slug ) ) ); ?>">
							<?php echo esc_html( $section->label ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<div id="marketplace-current-section-name" class="current-section-name"><?php echo esc_html( $current_section_name ); ?></div>
		</div>
		</div>
	<div class="wrap">
		<div class="marketplace-content-wrapper">
			<?php if ( ! empty( $search ) && 0 === count( $addons ) ) : ?>
				<h1 class="search-form-title">
					<?php esc_html_e( 'Sorry, could not find anything. Try searching again using a different term.', 'woocommerce' ); ?></p>
				</h1>
			<?php endif; ?>
			<?php if ( ! empty( $search ) && count( $addons ) > 0 ) : ?>
				<h1 class="search-form-title">
					<?php // translators: search keyword. ?>
					<?php printf( esc_html__( 'Search results for "%s"', 'woocommerce' ), esc_html( sanitize_text_field( wp_unslash( $search ) ) ) ); ?>
				</h1>
			<?php endif; ?>

			<?php if ( '_featured' === $current_section ) : ?>
				<div class="addons-featured">
					<?php WC_Admin_Addons::render_featured(); ?>
				</div>
			<?php endif; ?>
			<?php if ( '_featured' !== $current_section && $addons ) : ?>
				<?php
				if ( ! empty( $promotions ) && WC()->is_wc_admin_active() ) {
					foreach ( $promotions as $promotion ) {
						WC_Admin_Addons::output_search_promotion_block( $promotion );
					}
				}
				?>
				<ul class="products">
					<?php foreach ( $addons as $addon ) : ?>
						<?php
						if ( 'shipping_methods' === $current_section ) {
							// Do not show USPS or Canada Post extensions for US and CA stores, respectively.
							$country = WC()->countries->get_base_country();
							if ( 'US' === $country
								&& false !== strpos(
									$addon->link,
									'woocommerce.com/products/usps-shipping-method'
								)
							) {
								continue;
							}
							if ( 'CA' === $country
								&& false !== strpos(
									$addon->link,
									'woocommerce.com/products/canada-post-shipping-method'
								)
							) {
								continue;
							}
						}
						?>
						<li class="product">
							<div class="product-details">
								<?php if ( ! empty( $addon->icon ) ) : ?>
									<span class="product-img-wrap">
										<?php /* Show an icon if it exists */ ?>
										<img src="<?php echo esc_url( $addon->icon ); ?>" />
									</span>
								<?php endif; ?>
								<a href="<?php echo esc_url( WC_Admin_Addons::add_in_app_purchase_url_params( $addon->link ) ); ?>">
									<h2><?php echo esc_html( $addon->title ); ?></h2>
								</a>
								<?php if ( ! empty( $addon->vendor_name ) && ! empty( $addon->vendor_url ) ) : ?>
									<div class="product-developed-by">
										<?php
										$parsed_vendor_url = parse_url( $addon->vendor_url );
										if ( null == $parsed_vendor_url['path'] ) {
											$addon->vendor_url .= '/';
										}
										$separator         = ( null == $parsed_vendor_url['query'] ) ? '?' : '&';
										$query             = http_build_query(
											array(
												'utm_source'   => 'extensionsscreen',
												'utm_medium'   => 'product',
												'utm_campaign' => 'wcaddons',
												'utm_content'  => 'devpartner',
											)
										);
										$addon->vendor_url .= $separator . $query;

										printf(
										/* translators: %s vendor link */
											esc_html__( 'Developed by %s', 'woocommerce' ),
											sprintf(
												'<a class="product-vendor-link" href="%1$s" target="_blank">%2$s</a>',
												esc_url_raw( $addon->vendor_url ),
												wp_kses_post( $addon->vendor_name )
											)
										);
										?>
									</div>
								<?php endif; ?>
								<p><?php echo wp_kses_post( $addon->excerpt ); ?></p>
							</div>
							<div class="product-footer">
								<div class="product-price-and-reviews-container">
									<div class="product-price-block">
										<?php if ( '&#36;0.00' === $addon->price ) : ?>
											<span class="price"><?php esc_html_e( 'Free', 'woocommerce' ); ?></span>
										<?php else : ?>
											<?php
											$price_suffix = __( 'per year', 'woocommerce' );
											if ( ! empty( $addon->price_suffix ) ) {
												$price_suffix = $addon->price_suffix;
											}
											?>
											<span class="price"><?php echo wp_kses_post( $addon->price ); ?></span>
											<span class="price-suffix"><?php echo esc_html( $price_suffix ); ?></span>
										<?php endif; ?>
									</div>
									<?php if ( ! empty( $addon->reviews_count ) && ! empty( $addon->rating ) ) : ?>
										<?php /* Show rating and the number of reviews */ ?>
										<div class="product-reviews-block">
											<?php for ( $index = 1; $index <= 5; ++$index ) : ?>
												<?php $rating_star_class = 'product-rating-star product-rating-star__' . WC_Admin_Addons::get_star_class( $addon->rating, $index ); ?>
												<div class="<?php echo esc_attr( $rating_star_class ); ?>"></div>
											<?php endfor; ?>
											<span class="product-reviews-count">(<?php echo wp_kses_post( $addon->reviews_count ); ?>)</span>
										</div>
									<?php endif; ?>
								</div>
								<a class="button" href="<?php echo esc_url( WC_Admin_Addons::add_in_app_purchase_url_params( $addon->link ) ); ?>">
									<?php esc_html_e( 'View details', 'woocommerce' ); ?>
								</a>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php else : ?>
			<?php /* translators: a url */ ?>
			<p><?php printf( wp_kses_post( __( 'Our catalog of WooCommerce Extensions can be found on WooCommerce.com here: <a href="%s">WooCommerce Extensions Catalog</a>', 'woocommerce' ) ), 'https://woocommerce.com/product-category/woocommerce-extensions/' ); ?></p>
		<?php endif; ?>

		<?php if ( 'Storefront' !== $theme['Name'] && '_featured' !== $current_section ) : ?>
			<?php
				$storefront_url = WC_Admin_Addons::add_in_app_purchase_url_params( 'https://woocommerce.com/storefront/?utm_source=extensionsscreen&utm_medium=product&utm_campaign=wcaddon' );
			?>
			<div class="storefront">
				<a href="<?php echo esc_url( $storefront_url ); ?>" target="_blank"><img src="<?php echo esc_url( WC()->plugin_url() ); ?>/assets/images/storefront.png" alt="<?php esc_attr_e( 'Storefront', 'woocommerce' ); ?>" /></a>
				<h2><?php esc_html_e( 'Looking for a WooCommerce theme?', 'woocommerce' ); ?></h2>
				<p><?php echo wp_kses_post( __( 'We recommend Storefront, the <em>official</em> WooCommerce theme.', 'woocommerce' ) ); ?></p>
				<p><?php echo wp_kses_post( __( 'Storefront is an intuitive, flexible and <strong>free</strong> WordPress theme offering deep integration with WooCommerce and many of the most popular customer-facing extensions.', 'woocommerce' ) ); ?></p>
				<p>
					<a href="<?php echo esc_url( $storefront_url ); ?>" target="_blank" class="button"><?php esc_html_e( 'Read all about it', 'woocommerce' ); ?></a>
					<a href="<?php echo esc_url( wp_nonce_url( self_admin_url( 'update.php?action=install-theme&theme=storefront' ), 'install-theme_storefront' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Download &amp; install', 'woocommerce' ); ?></a>
				</p>
			</div>
		<?php endif; ?>
	</div>
</div>