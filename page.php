<?php
/**
 * Podstrony (usługi, kontakt, rezerwacja, polityka prywatności).
 */
defined( 'ABSPATH' ) || exit;
get_header();

while ( have_posts() ) :
	the_post();
	$slug      = get_post_field( 'post_name', get_the_ID() );
	$is_booking = has_shortcode( get_the_content(), 'rezerwacja' );
	$is_privacy = (int) get_option( 'wp_page_for_privacy_policy' ) === get_the_ID();
	?>
	<header class="page-head">
		<div class="wrap">
			<?php wm_breadcrumbs(); ?>
			<h1><?php the_title(); ?></h1>
		</div>
	</header>

	<section class="section">
		<div class="wrap">
			<div class="content">
				<?php the_content(); ?>
			</div>

			<?php if ( 'uslugi' === $slug ) : ?>
				<div style="margin-top:32px"><?php wm_service_cards(); ?></div>
			<?php endif; ?>

			<?php if ( 'kontakt' === $slug ) : ?>
				<div style="margin-top:32px"><?php wm_contact_block(); ?></div>
			<?php endif; ?>

			<?php if ( ! $is_booking && ! $is_privacy ) : ?>
				<div class="content"><?php wm_cta_box(); ?></div>
			<?php endif; ?>
		</div>
	</section>
	<?php
endwhile;

get_footer();
