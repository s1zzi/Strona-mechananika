<?php
/**
 * Motyw: Warsztat Mokotów
 */

defined( 'ABSPATH' ) || exit;

define( 'WM_VERSION', '1.0.0' );
define( 'WM_DIR', get_template_directory() );
define( 'WM_URI', get_template_directory_uri() );

require WM_DIR . '/inc/options.php';
require WM_DIR . '/inc/customizer.php';
require WM_DIR . '/inc/template-tags.php';
require WM_DIR . '/inc/booking.php';
require WM_DIR . '/inc/seo.php';
require WM_DIR . '/inc/setup-content.php';

add_action( 'after_setup_theme', function () {
	load_theme_textdomain( 'warsztat', WM_DIR . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array( 'height' => 104, 'width' => 320, 'flex-height' => true, 'flex-width' => true ) );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_post_type_support( 'page', 'excerpt' );
	register_nav_menus( array(
		'primary' => 'Menu główne',
		'footer'  => 'Menu w stopce',
	) );
} );

add_action( 'wp_enqueue_scripts', function () {
	$css = WM_DIR . '/style.css';
	wp_enqueue_style( 'warsztat', get_stylesheet_uri(), array(), file_exists( $css ) ? filemtime( $css ) : WM_VERSION );

	$js = WM_DIR . '/assets/main.js';
	wp_enqueue_script( 'warsztat-main', WM_URI . '/assets/main.js', array(), file_exists( $js ) ? filemtime( $js ) : WM_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
} );

/* ---------- Wydajność: wyłącz zbędne dodatki WordPressa ---------- */
add_action( 'init', function () {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
} );

/* Mniejszy rozmiar zajawek. */
add_filter( 'excerpt_length', function () {
	return 26;
} );
add_filter( 'excerpt_more', function () {
	return '…';
} );

/* Przypomnienie w panelu, gdy brakuje kluczowych danych firmy. */
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	$missing = array();
	if ( '' === wm_opt( 'telefon' ) ) {
		$missing[] = 'numer telefonu';
	}
	if ( '' === wm_opt( 'kod' ) ) {
		$missing[] = 'kod pocztowy';
	}
	if ( '' === wm_opt( 'geo_lat' ) || '' === wm_opt( 'geo_lng' ) ) {
		$missing[] = 'współrzędne na mapie';
	}
	if ( ! $missing ) {
		return;
	}
	$url = admin_url( 'customize.php?autofocus[panel]=wm_panel' );
	printf(
		'<div class="notice notice-warning"><p><strong>Warsztat:</strong> uzupełnij %s, żeby strona dobrze pozycjonowała się w Google. <a href="%s">Przejdź do ustawień &rarr;</a></p></div>',
		esc_html( implode( ', ', $missing ) ),
		esc_url( $url )
	);
} );
