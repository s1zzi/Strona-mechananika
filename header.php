<?php defined( 'ABSPATH' ) || exit; ?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#1d2229">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main">Przejdź do treści</a>

<div class="topbar">
	<div class="wrap">
		<span>📍 <?php echo esc_html( wm_opt( 'ulica' ) . ', ' . wm_opt( 'miasto' ) . ' ' . wm_opt( 'dzielnica' ) ); ?></span>
		<span>🕗 <?php echo esc_html( wm_today_hours_text() ); ?></span>
		<?php if ( wm_phone_href() ) : ?>
			<a href="<?php echo esc_attr( wm_phone_href() ); ?>">📞 <?php echo esc_html( wm_opt( 'telefon' ) ); ?></a>
		<?php endif; ?>
	</div>
</div>

<header class="site-header">
	<div class="wrap">
		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php
			$logo_id = get_theme_mod( 'custom_logo' );
			if ( $logo_id ) {
				echo wp_get_attachment_image( $logo_id, 'medium', false, array( 'alt' => wm_opt( 'nazwa' ), 'loading' => 'eager' ) );
			} else {
				echo '<span>' . esc_html( wm_opt( 'nazwa' ) ) . '<small>' . esc_html( wm_opt( 'slogan' ) ) . '</small></span>';
			}
			?>
		</a>
		<button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">Menu</button>
		<nav class="nav" id="site-nav" aria-label="Menu główne">
			<?php
			wp_nav_menu( array(
				'theme_location' => 'primary',
				'container'      => false,
				'depth'          => 1,
				'fallback_cb'    => 'wm_menu_fallback',
			) );
			?>
		</nav>
		<a class="btn btn--primary header-cta" href="<?php echo esc_url( wm_booking_url() ); ?>">Umów wizytę</a>
	</div>
</header>
<main id="main">
