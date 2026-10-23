<?php
/**
 * Panel „Warsztat” w Wygląd → Dostosuj.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'customize_register', function ( WP_Customize_Manager $wp ) {
	$d = wm_defaults();

	$wp->add_panel( 'wm_panel', array(
		'title'    => 'Warsztat – ustawienia',
		'priority' => 20,
	) );

	$sections = array(
		'wm_firma'  => array( 'Dane firmy (ważne dla Google)', 'Te dane muszą być identyczne jak w Profilu Firmy w Google (nazwa, adres, telefon).' ),
		'wm_godz'   => array( 'Godziny otwarcia', 'Format: 08:00-17:00. Puste pole = nieczynne. Na tej podstawie działa też kalendarz rezerwacji.' ),
		'wm_rez'    => array( 'Rezerwacje wizyt', 'Ustawienia kalendarza umawiania wizyt.' ),
		'wm_social' => array( 'Google i social media', '' ),
		'wm_faq'    => array( 'Najczęstsze pytania (FAQ)', 'Każde pytanie w osobnej linii w formacie: Pytanie | Odpowiedź' ),
	);
	foreach ( $sections as $id => $s ) {
		$wp->add_section( $id, array(
			'title'       => $s[0],
			'description' => $s[1],
			'panel'       => 'wm_panel',
		) );
	}

	$add = function ( $key, $section, $label, $type = 'text', $sanitize = 'sanitize_text_field', $extra = array() ) use ( $wp, $d ) {
		$wp->add_setting( 'wm_' . $key, array(
			'default'           => isset( $d[ $key ] ) ? $d[ $key ] : '',
			'sanitize_callback' => $sanitize,
		) );
		$wp->add_control( 'wm_' . $key, array_merge( array(
			'label'   => $label,
			'section' => $section,
			'type'    => $type,
		), $extra ) );
	};

	$textarea = function ( $v ) {
		return sanitize_textarea_field( $v );
	};
	$int = function ( $v ) {
		return max( 0, (int) $v );
	};
	$coord = function ( $v ) {
		$v = str_replace( ',', '.', trim( (string) $v ) );
		return is_numeric( $v ) ? $v : '';
	};

	// Dane firmy.
	$add( 'nazwa', 'wm_firma', 'Nazwa warsztatu' );
	$add( 'slogan', 'wm_firma', 'Krótki opis (pod nazwą)' );
	$add( 'telefon', 'wm_firma', 'Telefon', 'tel', 'sanitize_text_field', array( 'description' => 'np. 600 100 200' ) );
	$add( 'email', 'wm_firma', 'E-mail kontaktowy', 'email', 'sanitize_email' );
	$add( 'ulica', 'wm_firma', 'Ulica i numer' );
	$add( 'kod', 'wm_firma', 'Kod pocztowy', 'text', 'sanitize_text_field', array( 'description' => 'np. 02-xxx – sprawdź na poczta-polska.pl' ) );
	$add( 'miasto', 'wm_firma', 'Miasto' );
	$add( 'dzielnica', 'wm_firma', 'Dzielnica' );
	$add( 'nip', 'wm_firma', 'NIP (opcjonalnie, w stopce)' );
	$add( 'geo_lat', 'wm_firma', 'Szerokość geogr. (lat)', 'text', $coord, array( 'description' => 'W Google Maps kliknij prawym przyciskiem na warsztat – pierwsza liczba, np. 52.1801' ) );
	$add( 'geo_lng', 'wm_firma', 'Długość geogr. (lng)', 'text', $coord, array( 'description' => 'Druga liczba, np. 21.0702' ) );
	$add( 'obszar', 'wm_firma', 'Obsługiwane okolice (po przecinku)', 'textarea', $textarea );
	$add( 'cennik', 'wm_firma', 'Przedział cen (opcjonalnie)', 'text', 'sanitize_text_field', array( 'description' => 'np. 100–2000 zł' ) );

	$wp->add_setting( 'wm_hero_img', array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
	$wp->add_control( new WP_Customize_Image_Control( $wp, 'wm_hero_img', array(
		'label'       => 'Zdjęcie w tle nagłówka strony głównej',
		'description' => 'Najlepiej prawdziwe zdjęcie warsztatu (poziome, min. 1600 px szerokości).',
		'section'     => 'wm_firma',
	) ) );

	// Godziny.
	foreach ( wm_weekdays() as $n => $name ) {
		$add( 'h' . $n, 'wm_godz', $name );
	}

	// Rezerwacje.
	$add( 'rez_email', 'wm_rez', 'E-mail na powiadomienia o nowych wizytach', 'email', 'sanitize_email' );
	$add( 'rez_slot', 'wm_rez', 'Co ile minut kolejny termin', 'select', $int, array(
		'choices' => array( 30 => '30 min', 60 => '60 min', 90 => '90 min', 120 => '2 godziny', 180 => '3 godziny', 240 => '4 godziny' ),
	) );
	$add( 'rez_poj', 'wm_rez', 'Ile aut przyjmujesz na tę samą godzinę', 'number', $int, array( 'input_attrs' => array( 'min' => 1, 'max' => 10 ) ) );
	$add( 'rez_wyp', 'wm_rez', 'Minimalne wyprzedzenie rezerwacji (godziny)', 'number', $int, array( 'input_attrs' => array( 'min' => 0, 'max' => 72 ) ) );
	$add( 'rez_dni', 'wm_rez', 'Na ile dni do przodu można rezerwować', 'number', $int, array( 'input_attrs' => array( 'min' => 1, 'max' => 120 ) ) );
	$add( 'rez_swieta', 'wm_rez', 'Blokuj polskie święta ustawowe automatycznie', 'checkbox', function ( $v ) {
		return $v ? 1 : 0;
	} );
	$add( 'rez_wolne', 'wm_rez', 'Dni wolne / urlop (jedna data w linii: 2026-12-31 lub zakres 2026-08-01 do 2026-08-14)', 'textarea', $textarea );
	$add( 'rez_uslugi', 'wm_rez', 'Usługi do wyboru w formularzu (jedna w linii)', 'textarea', $textarea );

	// Social.
	$add( 'google_url', 'wm_social', 'Link do Profilu Firmy w Google (wizytówka w Mapach)', 'url', 'esc_url_raw' );
	$add( 'fb_url', 'wm_social', 'Facebook', 'url', 'esc_url_raw' );

	// FAQ.
	$add( 'faq', 'wm_faq', 'Pytania i odpowiedzi', 'textarea', $textarea );
} );
