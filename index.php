<?php
/**
 * Wpisy (np. blog „Porady”) i widok zapasowy.
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<header class="page-head">
	<div class="wrap">
		<?php wm_breadcrumbs(); ?>
		<h1><?php echo is_singular() ? esc_html( get_the_title() ) : esc_html( wp_strip_all_tags( get_the_archive_title() ? get_the_archive_title() : 'Porady' ) ); ?></h1>
	</div>
</header>

<section class="section">
	<div class="wrap">
		<?php if ( is_singular() ) : ?>
			<?php while ( have_posts() ) : the_post(); ?>
				<article class="content">
					<?php if ( 'post' === get_post_type() ) : ?>
						<p class="booking__hint"><time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time></p>
					<?php endif; ?>
					<?php the_post_thumbnail( 'large' ); ?>
					<?php the_content(); ?>
					<?php wm_cta_box(); ?>
				</article>
			<?php endwhile; ?>
		<?php elseif ( have_posts() ) : ?>
			<div class="grid">
				<?php while ( have_posts() ) : the_post(); ?>
					<article class="card">
						<h2 style="font-size:1.2rem"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<p><?php echo esc_html( get_the_excerpt() ); ?></p>
						<a class="card__more" href="<?php the_permalink(); ?>">Czytaj &rarr;</a>
					</article>
				<?php endwhile; ?>
			</div>
			<div style="margin-top:28px"><?php the_posts_pagination(); ?></div>
		<?php else : ?>
			<p>Brak wpisów.</p>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
