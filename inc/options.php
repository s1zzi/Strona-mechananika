<?php
/**
 * Ustawienia motywu (wartości domyślne + pomocnicze funkcje odczytu).
 * Wszystko edytujesz w: Wygląd → Dostosuj → Warsztat.
 */

defined( 'ABSPATH' ) || exit;

/** Dni tygodnia wg ISO-8601 (1 = poniedziałek). */
function wm_weekdays() {
	return array(
		1 => 'Poniedziałek',
		2 => 'Wtorek',
		3 => 'Środa',
		4 => 'Czwartek',
		5 => 'Piątek',
		6 => 'Sobota',
		7 => 'Niedziela',
	);
}

function wm_defaults() {
	return array(
		// Dane firmy.
		'nazwa'      => 'Warsztat Samochodowy Bonifacego 10',
		'slogan'     => 'Mechanik samochodowy Warszawa Mokotów',
		'telefon'    => '',
		'email'      => get_option( 'admin_email' ),
		'ulica'      => 'ul. Bonifacego 10',
		'kod'        => '',
		'miasto'     => 'Warszawa',
		'dzielnica'  => 'Mokotów',
		'nip'        => '',
		'geo_lat'    => '',
		'geo_lng'    => '',
		'obszar'     => 'Mokotów, Sadyba, Stegny, Sielce, Wilanów, Ursynów, Służew',
		'cennik'     => '',
		'hero_img'   => '',
		// Godziny otwarcia (puste = nieczynne).
		'h1'         => '08:00-17:00',
		'h2'         => '08:00-17:00',
		'h3'         => '08:00-17:00',
		'h4'         => '08:00-17:00',
		'h5'         => '08:00-17:00',
		'h6'         => '09:00-13:00',
		'h7'         => '',
		// Rezerwacje.
		'rez_slot'   => 60,
		'rez_poj'    => 1,
		'rez_wyp'    => 2,
		'rez_dni'    => 30,
		'rez_wolne'  => '',
		'rez_email'  => get_option( 'admin_email' ),
		'rez_swieta' => 1,
		'rez_uslugi' => "Diagnostyka komputerowa\nWymiana oleju i filtrów\nHamulce (klocki, tarcze, płyn)\nZawieszenie i układ kierowniczy\nSerwis klimatyzacji\nPrzegląd przed zakupem auta\nWymiana opon\nInna usterka (opiszę w uwagach)",
		// Social / Google.
		'google_url' => '',
		'fb_url'     => '',
		// FAQ.
		'faq'        => "Czy muszę umawiać się wcześniej? | Najlepiej tak – rezerwacja online gwarantuje, że zajmiemy się autem o wybranej godzinie. Jeśli coś wydarzyło się nagle, zadzwoń – postaramy się znaleźć termin jak najszybciej.\n"
			. "Ile kosztuje diagnostyka? | Cenę podajemy przed rozpoczęciem pracy. Po diagnozie otrzymasz informację, co wymaga naprawy i ile to będzie kosztować – decyzja zawsze należy do Ciebie.\n"
			. "Czy mogę przywieźć własne części? | Zadzwoń i zapytaj – zależy to od rodzaju naprawy i części. Doradzimy, co będzie najkorzystniejsze.\n"
			. "Jak dojechać do warsztatu? | Warsztat znajduje się przy ul. Bonifacego 10 w Warszawie (Mokotów). Mapę i link do nawigacji znajdziesz w sekcji Kontakt.\n"
			. "Co jeśli nie mogę przyjechać w zarezerwowanym terminie? | Zadzwoń lub napisz – przełożymy wizytę na inny dzień.",
	);
}

/** Odczyt ustawienia (trim, z wartością domyślną). */
function wm_opt( $key ) {
	$defaults = wm_defaults();
	$default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	$value    = get_theme_mod( 'wm_' . $key, $default );
	return is_string( $value ) ? trim( $value ) : $value;
}

/** Niepuste linie z pola tekstowego. */
function wm_lines( $text ) {
	$lines = preg_split( '/\r\n|\r|\n/', (string) $text );
	return array_values( array_filter( array_map( 'trim', $lines ), 'strlen' ) );
}

/** Lista usług dostępnych w formularzu rezerwacji. */
function wm_services() {
	return wm_lines( wm_opt( 'rez_uslugi' ) );
}

/** FAQ jako tablica [ [pytanie, odpowiedź], ... ]. */
function wm_faq() {
	$out = array();
	foreach ( wm_lines( wm_opt( 'faq' ) ) as $line ) {
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		if ( 2 === count( $parts ) && '' !== $parts[0] && '' !== $parts[1] ) {
			$out[] = $parts;
		}
	}
	return $out;
}

/**
 * Parsuje zakres godzin „08:00-17:00”, „8-17”, „8:30 – 16”.
 * Zwraca [start_min, koniec_min] albo null (nieczynne).
 */
function wm_parse_range( $str ) {
	$str = trim( (string) $str );
	if ( '' === $str || preg_match( '/^(nieczynne|zamkni[eę]te|-|–)$/iu', $str ) ) {
		return null;
	}
	if ( ! preg_match( '/^(\d{1,2})(?:[:.](\d{2}))?\s*[-–—]\s*(\d{1,2})(?:[:.](\d{2}))?$/u', $str, $m ) ) {
		return null;
	}
	$start = (int) $m[1] * 60 + ( isset( $m[2] ) && '' !== $m[2] ? (int) $m[2] : 0 );
	$end   = (int) $m[3] * 60 + ( isset( $m[4] ) && '' !== $m[4] ? (int) $m[4] : 0 );
	if ( $start >= $end || $end > 24 * 60 ) {
		return null;
	}
	return array( $start, $end );
}

/** Godziny otwarcia: [1..7 => [start, koniec] | null]. */
function wm_hours() {
	$out = array();
	foreach ( array_keys( wm_weekdays() ) as $d ) {
		$out[ $d ] = wm_parse_range( wm_opt( 'h' . $d ) );
	}
	return $out;
}

function wm_min_to_time( $min ) {
	return sprintf( '%02d:%02d', floor( $min / 60 ), $min % 60 );
}

/** Numer telefonu w formacie do linku tel:. */
function wm_phone_href() {
	$digits = preg_replace( '/[^\d+]/', '', wm_opt( 'telefon' ) );
	if ( '' === $digits ) {
		return '';
	}
	if ( 0 !== strpos( $digits, '+' ) && 9 === strlen( $digits ) ) {
		$digits = '+48' . $digits;
	}
	return 'tel:' . $digits;
}

/** Pełny adres w jednej linii. */
function wm_address_line() {
	$city = trim( wm_opt( 'kod' ) . ' ' . wm_opt( 'miasto' ) );
	return trim( wm_opt( 'ulica' ) . ', ' . $city, ', ' );
}

/** Link do strony wg ścieżki (slug), z zapasowym adresem. */
function wm_page_url( $path, $fallback = '' ) {
	$page = get_page_by_path( $path );
	if ( $page && 'publish' === $page->post_status ) {
		return get_permalink( $page );
	}
	return $fallback ? $fallback : home_url( '/' . $path . '/' );
}

function wm_booking_url() {
	return wm_page_url( 'umow-wizyte' );
}

function wm_maps_query() {
	if ( wm_opt( 'geo_lat' ) && wm_opt( 'geo_lng' ) ) {
		return wm_opt( 'geo_lat' ) . ',' . wm_opt( 'geo_lng' );
	}
	return wm_opt( 'nazwa' ) . ', ' . wm_address_line();
}
