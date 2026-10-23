<?php
/**
 * Rezerwacje wizyt: typ wpisu „Wizyty”, API wolnych terminów, formularz [rezerwacja], e-maile.
 */

defined( 'ABSPATH' ) || exit;

function wm_statuses() {
	return array(
		'nowa'         => 'Nowa',
		'potwierdzona' => 'Potwierdzona',
		'zakonczona'   => 'Zakończona',
		'anulowana'    => 'Anulowana',
	);
}

/* =====================================================================
 * Typ wpisu „Wizyty” (niewidoczny publicznie, tylko w panelu)
 * ===================================================================== */
add_action( 'init', function () {
	register_post_type( 'wizyta', array(
		'labels'              => array(
			'name'               => 'Wizyty',
			'singular_name'      => 'Wizyta',
			'menu_name'          => 'Wizyty',
			'add_new'            => 'Dodaj wizytę',
			'add_new_item'       => 'Dodaj wizytę',
			'edit_item'          => 'Edytuj wizytę',
			'search_items'       => 'Szukaj wizyt',
			'not_found'          => 'Brak wizyt',
			'not_found_in_trash' => 'Brak wizyt w koszu',
			'all_items'          => 'Wszystkie wizyty',
		),
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_rest'        => false,
		'exclude_from_search' => true,
		'publicly_queryable'  => false,
		'has_archive'         => false,
		'rewrite'             => false,
		'menu_position'       => 3,
		'menu_icon'           => 'dashicons-calendar-alt',
		'supports'            => array( 'title' ),
	) );
} );

/* =====================================================================
 * Kalendarz: dni wolne, święta, wolne godziny
 * ===================================================================== */

/** Polskie święta ustawowo wolne od pracy w danym roku (Y-m-d). */
function wm_pl_holidays( $year ) {
	$year = (int) $year;
	// Wielkanoc – algorytm Meeusa/Jonesa/Butchera.
	$a     = $year % 19;
	$b     = intdiv( $year, 100 );
	$c     = $year % 100;
	$d     = intdiv( $b, 4 );
	$e     = $b % 4;
	$f     = intdiv( $b + 8, 25 );
	$g     = intdiv( $b - $f + 1, 3 );
	$h     = ( 19 * $a + $b - $d - $g + 15 ) % 30;
	$i     = intdiv( $c, 4 );
	$k     = $c % 4;
	$l     = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7;
	$m     = intdiv( $a + 11 * $h + 22 * $l, 451 );
	$month = intdiv( $h + $l - 7 * $m + 114, 31 );
	$day   = ( ( $h + $l - 7 * $m + 114 ) % 31 ) + 1;

	$easter = new DateTimeImmutable( sprintf( '%04d-%02d-%02d', $year, $month, $day ), wp_timezone() );
	$fixed  = array( '01-01', '01-06', '05-01', '05-03', '08-15', '11-01', '11-11', '12-25', '12-26' );
	if ( $year >= 2025 ) {
		$fixed[] = '12-24'; // Wigilia wolna od 2025 r.
	}
	$out = array();
	foreach ( $fixed as $md ) {
		$out[] = $year . '-' . $md;
	}
	$out[] = $easter->format( 'Y-m-d' );                     // Wielkanoc.
	$out[] = $easter->modify( '+1 day' )->format( 'Y-m-d' ); // Poniedziałek Wielkanocny.
	$out[] = $easter->modify( '+49 days' )->format( 'Y-m-d' ); // Zielone Świątki.
	$out[] = $easter->modify( '+60 days' )->format( 'Y-m-d' ); // Boże Ciało.
	return $out;
}

/** Wszystkie zablokowane daty (święta + urlopy) na najbliższy okres. */
function wm_closed_dates() {
	$now    = new DateTimeImmutable( 'now', wp_timezone() );
	$dates  = array();
	if ( wm_opt( 'rez_swieta' ) ) {
		$y     = (int) $now->format( 'Y' );
		$dates = array_merge( wm_pl_holidays( $y ), wm_pl_holidays( $y + 1 ) );
	}
	foreach ( wm_lines( wm_opt( 'rez_wolne' ) ) as $line ) {
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})\s*(?:do|-|–|—)\s*(\d{4}-\d{2}-\d{2})$/u', $line, $m ) ) {
			try {
				$from = new DateTimeImmutable( $m[1], wp_timezone() );
				$to   = new DateTimeImmutable( $m[2], wp_timezone() );
			} catch ( Exception $e ) {
				continue;
			}
			for ( $i = 0; $from <= $to && $i < 366; $i++, $from = $from->modify( '+1 day' ) ) {
				$dates[] = $from->format( 'Y-m-d' );
			}
		} elseif ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $line ) ) {
			$dates[] = $line;
		}
	}
	$dates = array_values( array_unique( $dates ) );
	sort( $dates );
	return $dates;
}

/** Konfiguracja kalendarza przekazywana do przeglądarki. */
function wm_booking_config() {
	$hours = array();
	foreach ( wm_hours() as $d => $range ) {
		$hours[ $d ] = $range;
	}
	return array(
		'slot'      => max( 15, (int) wm_opt( 'rez_slot' ) ),
		'capacity'  => max( 1, (int) wm_opt( 'rez_poj' ) ),
		'leadHours' => max( 0, (int) wm_opt( 'rez_wyp' ) ),
		'daysAhead' => min( 180, max( 1, (int) wm_opt( 'rez_dni' ) ) ),
		'hours'     => $hours,
		'closed'    => wm_closed_dates(),
	);
}

/** Liczba aktywnych rezerwacji na poszczególne godziny danego dnia. */
function wm_bookings_count( $ymd ) {
	$ids    = get_posts( array(
		'post_type'      => 'wizyta',
		'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array(
			array( 'key' => '_wm_date', 'value' => $ymd ),
		),
	) );
	$counts = array();
	foreach ( $ids as $id ) {
		if ( 'anulowana' === get_post_meta( $id, '_wm_status', true ) ) {
			continue;
		}
		$t            = get_post_meta( $id, '_wm_time', true );
		$counts[ $t ] = ( isset( $counts[ $t ] ) ? $counts[ $t ] : 0 ) + 1;
	}
	return $counts;
}

/**
 * Terminy dla danego dnia: [ ['time' => '08:00', 'free' => true], ... ].
 * Zwraca WP_Error, jeśli dzień jest poza zakresem lub nieczynny.
 */
function wm_slots_for_date( $ymd ) {
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $ymd ) ) {
		return new WP_Error( 'wm_date', 'Nieprawidłowa data.' );
	}
	$tz  = wp_timezone();
	$day = DateTimeImmutable::createFromFormat( '!Y-m-d', $ymd, $tz );
	if ( ! $day || $day->format( 'Y-m-d' ) !== $ymd ) {
		return new WP_Error( 'wm_date', 'Nieprawidłowa data.' );
	}
	$cfg   = wm_booking_config();
	$now   = new DateTimeImmutable( 'now', $tz );
	$today = $now->setTime( 0, 0 );
	$last  = $today->modify( '+' . $cfg['daysAhead'] . ' days' );
	if ( $day < $today || $day > $last ) {
		return new WP_Error( 'wm_range', 'Ten dzień jest poza zakresem rezerwacji.' );
	}
	$range = $cfg['hours'][ (int) $day->format( 'N' ) ];
	if ( ! $range || in_array( $ymd, $cfg['closed'], true ) ) {
		return new WP_Error( 'wm_closed', 'W tym dniu warsztat jest nieczynny.' );
	}

	$earliest = $now->modify( '+' . $cfg['leadHours'] . ' hours' );
	$counts   = wm_bookings_count( $ymd );
	$slots    = array();
	for ( $m = $range[0]; $m + $cfg['slot'] <= $range[1]; $m += $cfg['slot'] ) {
		$time  = wm_min_to_time( $m );
		$start = $day->setTime( intdiv( $m, 60 ), $m % 60 );
		if ( $start < $earliest ) {
			continue;
		}
		$taken   = isset( $counts[ $time ] ) ? $counts[ $time ] : 0;
		$slots[] = array( 'time' => $time, 'free' => $taken < $cfg['capacity'] );
	}
	return $slots;
}

/* =====================================================================
 * REST API: /wp-json/warsztat/v1/slots i /book
 * ===================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route( 'warsztat/v1', '/slots', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => array(
			'date' => array( 'required' => true, 'type' => 'string' ),
		),
		'callback'            => function ( WP_REST_Request $req ) {
			$slots = wm_slots_for_date( $req->get_param( 'date' ) );
			$data  = is_wp_error( $slots )
				? array( 'slots' => array(), 'message' => $slots->get_error_message() )
				: array( 'slots' => $slots );
			$res   = new WP_REST_Response( $data, 200 );
			$res->header( 'Cache-Control', 'no-store, max-age=0' );
			return $res;
		},
	) );

	register_rest_route( 'warsztat/v1', '/book', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'wm_rest_book',
	) );
} );

function wm_client_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
}

function wm_rest_error( $msg, $code = 400 ) {
	return new WP_REST_Response( array( 'ok' => false, 'message' => $msg ), $code );
}

function wm_rest_book( WP_REST_Request $req ) {
	$p = $req->get_json_params();
	if ( ! is_array( $p ) ) {
		$p = $req->get_body_params();
	}
	$get = function ( $k, $max = 200 ) use ( $p ) {
		$v = isset( $p[ $k ] ) && is_scalar( $p[ $k ] ) ? (string) $p[ $k ] : '';
		return mb_substr( trim( sanitize_text_field( $v ) ), 0, $max );
	};

	// Ochrona przed spamem: ukryte pole, minimalny czas wypełniania, limit na IP.
	if ( '' !== $get( 'website' ) || (int) $get( 'elapsed' ) < 3000 ) {
		return wm_rest_error( 'Nie udało się wysłać formularza. Spróbuj ponownie.' );
	}
	$rl_key = 'wm_rl_' . md5( wm_client_ip() );
	$tries  = (int) get_transient( $rl_key );
	if ( $tries >= 5 ) {
		return wm_rest_error( 'Zbyt wiele rezerwacji z tego urządzenia. Zadzwoń do nas, proszę.', 429 );
	}

	$data = array(
		'date'    => $get( 'date', 10 ),
		'time'    => $get( 'time', 5 ),
		'service' => $get( 'service', 120 ),
		'name'    => $get( 'name', 80 ),
		'phone'   => $get( 'phone', 30 ),
		'email'   => sanitize_email( $get( 'email', 120 ) ),
		'car'     => $get( 'car', 120 ),
		'plate'   => strtoupper( $get( 'plate', 12 ) ),
		'message' => isset( $p['message'] ) && is_scalar( $p['message'] ) ? mb_substr( sanitize_textarea_field( (string) $p['message'] ), 0, 1500 ) : '',
	);

	if ( mb_strlen( $data['name'] ) < 2 ) {
		return wm_rest_error( 'Podaj imię i nazwisko.' );
	}
	if ( strlen( preg_replace( '/\D/', '', $data['phone'] ) ) < 9 ) {
		return wm_rest_error( 'Podaj poprawny numer telefonu.' );
	}
	if ( '' !== $get( 'email' ) && ! is_email( $data['email'] ) ) {
		return wm_rest_error( 'Adres e-mail jest nieprawidłowy.' );
	}
	if ( mb_strlen( $data['car'] ) < 2 ) {
		return wm_rest_error( 'Podaj markę i model samochodu.' );
	}
	if ( ! in_array( $data['service'], wm_services(), true ) ) {
		return wm_rest_error( 'Wybierz usługę z listy.' );
	}
	if ( empty( $p['consent'] ) ) {
		return wm_rest_error( 'Zaznacz zgodę na przetwarzanie danych.' );
	}

	// Blokada MySQL, żeby dwie osoby nie zajęły jednocześnie ostatniego miejsca.
	global $wpdb;
	$lock = 'wm_book_' . md5( DB_NAME . $data['date'] . $data['time'] );
	$got  = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) );
	if ( '0' === (string) $got ) {
		return wm_rest_error( 'Serwer jest zajęty, spróbuj za chwilę.', 503 );
	}
	$unlock = function () use ( $wpdb, $lock ) {
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	};

	$slots = wm_slots_for_date( $data['date'] );
	$free  = false;
	if ( ! is_wp_error( $slots ) ) {
		foreach ( $slots as $s ) {
			if ( $s['time'] === $data['time'] && $s['free'] ) {
				$free = true;
				break;
			}
		}
	}
	if ( ! $free ) {
		$unlock();
		return wm_rest_error( 'Ten termin został właśnie zajęty. Wybierz inną godzinę.', 409 );
	}

	$post_id = wp_insert_post( array(
		'post_type'   => 'wizyta',
		'post_status' => 'publish',
		'post_title'  => sprintf( '%s %s – %s (%s)', $data['date'], $data['time'], $data['name'], $data['car'] ),
	), true );
	if ( ! is_wp_error( $post_id ) ) {
		foreach ( $data as $k => $v ) {
			update_post_meta( $post_id, '_wm_' . $k, $v );
		}
		update_post_meta( $post_id, '_wm_start', $data['date'] . ' ' . $data['time'] );
		update_post_meta( $post_id, '_wm_status', 'nowa' );
	}
	$unlock();

	if ( is_wp_error( $post_id ) ) {
		return wm_rest_error( 'Nie udało się zapisać rezerwacji. Zadzwoń do nas, proszę.', 500 );
	}

	set_transient( $rl_key, $tries + 1, HOUR_IN_SECONDS );
	wm_mail_owner( $post_id );
	wm_mail_client( $post_id, 'nowa' );

	return new WP_REST_Response( array(
		'ok'      => true,
		'message' => sprintf(
			'Dziękujemy! Rezerwacja na %s, godz. %s została przyjęta. %s',
			wm_format_date( $data['date'] ),
			$data['time'],
			$data['email'] ? 'Potwierdzenie wysłaliśmy na Twój e-mail.' : 'W razie potrzeby zadzwonimy na podany numer.'
		),
	), 200 );
}

/* =====================================================================
 * E-maile
 * ===================================================================== */
function wm_format_date( $ymd ) {
	$ts = strtotime( $ymd . ' 12:00:00' );
	return $ts ? wp_date( 'l, j F Y', $ts, wp_timezone() ) : $ymd;
}

function wm_booking_meta( $post_id ) {
	$out = array();
	foreach ( array( 'date', 'time', 'service', 'name', 'phone', 'email', 'car', 'plate', 'message', 'status' ) as $k ) {
		$out[ $k ] = (string) get_post_meta( $post_id, '_wm_' . $k, true );
	}
	return $out;
}

function wm_mail_headers( $reply_to = '' ) {
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	if ( $reply_to && is_email( $reply_to ) ) {
		$headers[] = 'Reply-To: ' . $reply_to;
	}
	return $headers;
}

function wm_mail_owner( $post_id ) {
	$b  = wm_booking_meta( $post_id );
	$to = wm_opt( 'rez_email' ) ? wm_opt( 'rez_email' ) : get_option( 'admin_email' );

	$body  = "Nowa rezerwacja wizyty ze strony internetowej:\n\n";
	$body .= 'Termin:   ' . wm_format_date( $b['date'] ) . ', godz. ' . $b['time'] . "\n";
	$body .= 'Usługa:   ' . $b['service'] . "\n";
	$body .= 'Klient:   ' . $b['name'] . "\n";
	$body .= 'Telefon:  ' . $b['phone'] . "\n";
	$body .= 'E-mail:   ' . ( $b['email'] ? $b['email'] : '—' ) . "\n";
	$body .= 'Samochód: ' . $b['car'] . ( $b['plate'] ? ' (' . $b['plate'] . ')' : '' ) . "\n";
	if ( $b['message'] ) {
		$body .= "\nOpis problemu:\n" . $b['message'] . "\n";
	}
	$body .= "\nZarządzaj wizytą: " . admin_url( 'post.php?post=' . $post_id . '&action=edit' ) . "\n";

	wp_mail( $to, sprintf( 'Nowa wizyta: %s %s – %s', $b['date'], $b['time'], $b['service'] ), $body, wm_mail_headers( $b['email'] ) );
}

function wm_mail_client( $post_id, $status ) {
	$b = wm_booking_meta( $post_id );
	if ( ! $b['email'] || ! is_email( $b['email'] ) ) {
		return;
	}
	$firm  = wm_opt( 'nazwa' );
	$when  = wm_format_date( $b['date'] ) . ', godz. ' . $b['time'];
	$phone = wm_opt( 'telefon' );

	switch ( $status ) {
		case 'potwierdzona':
			$subject = 'Wizyta potwierdzona – ' . $firm;
			$intro   = "Dzień dobry,\n\npotwierdzamy Twoją wizytę w naszym warsztacie.";
			break;
		case 'anulowana':
			$subject = 'Wizyta odwołana – ' . $firm;
			$intro   = "Dzień dobry,\n\nniestety Twoja wizyta została odwołana. Zadzwoń do nas lub zarezerwuj nowy termin: " . wm_booking_url();
			break;
		default:
			$subject = 'Przyjęliśmy rezerwację wizyty – ' . $firm;
			$intro   = "Dzień dobry,\n\ndziękujemy za rezerwację wizyty w naszym warsztacie.";
	}

	$body  = $intro . "\n\n";
	$body .= 'Termin:   ' . $when . "\n";
	$body .= 'Usługa:   ' . $b['service'] . "\n";
	$body .= 'Samochód: ' . $b['car'] . "\n\n";
	$body .= "Adres: {$firm}, " . wm_address_line() . "\n";
	$body .= 'Dojazd: https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( wm_maps_query() ) . "\n";
	if ( $phone ) {
		$body .= "Telefon: {$phone}\n";
	}
	if ( 'anulowana' !== $status ) {
		$body .= "\nJeśli nie możesz przyjechać, daj nam znać – przełożymy wizytę na inny termin.\n";
	}
	$body .= "\nPozdrawiamy,\n{$firm}\n" . home_url( '/' ) . "\n";

	$owner = wm_opt( 'email' );
	wp_mail( $b['email'], $subject, $body, wm_mail_headers( $owner ) );
}

/* =====================================================================
 * Formularz [rezerwacja]
 * ===================================================================== */
add_shortcode( 'rezerwacja', function () {
	$js = WM_DIR . '/assets/booking.js';
	wp_enqueue_script( 'warsztat-booking', WM_URI . '/assets/booking.js', array(), file_exists( $js ) ? filemtime( $js ) : WM_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	$cfg            = wm_booking_config();
	$cfg['restUrl'] = esc_url_raw( rest_url( 'warsztat/v1/' ) );
	wp_add_inline_script( 'warsztat-booking', 'window.WM_BOOKING = ' . wp_json_encode( $cfg ) . ';', 'before' );

	$privacy = get_privacy_policy_url();
	$phone   = wm_opt( 'telefon' );

	ob_start();
	?>
	<form class="booking" id="booking-form" novalidate>
		<noscript><p class="booking__msg booking__msg--err">Rezerwacja online wymaga włączonego JavaScriptu. <?php echo $phone ? 'Zadzwoń: ' . esc_html( $phone ) : ''; ?></p></noscript>

		<div class="booking__step">
			<label class="booking__label" for="bk-service"><span>1</span>Wybierz usługę</label>
			<select id="bk-service" name="service" required>
				<option value="">— wybierz —</option>
				<?php foreach ( wm_services() as $s ) : ?>
					<option value="<?php echo esc_attr( $s ); ?>"><?php echo esc_html( $s ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="booking__step">
			<span class="booking__label" id="bk-days-label"><span>2</span>Wybierz dzień</span>
			<div class="booking__days" role="group" aria-labelledby="bk-days-label" data-days></div>
		</div>

		<div class="booking__step">
			<span class="booking__label" id="bk-slots-label"><span>3</span>Wybierz godzinę</span>
			<div class="booking__slots" role="group" aria-labelledby="bk-slots-label" data-slots></div>
			<p class="booking__hint" data-slots-hint>Najpierw wybierz dzień.</p>
		</div>

		<div class="booking__step">
			<span class="booking__label"><span>4</span>Twoje dane</span>
			<div class="booking__summary" data-summary hidden></div>
			<div class="booking__fields">
				<div><label for="bk-name">Imię i nazwisko *</label><input id="bk-name" name="name" type="text" autocomplete="name" required maxlength="80"></div>
				<div><label for="bk-phone">Telefon *</label><input id="bk-phone" name="phone" type="tel" autocomplete="tel" required maxlength="30" inputmode="tel"></div>
				<div><label for="bk-email">E-mail (wyślemy potwierdzenie)</label><input id="bk-email" name="email" type="email" autocomplete="email" maxlength="120"></div>
				<div><label for="bk-car">Marka i model auta *</label><input id="bk-car" name="car" type="text" required maxlength="120" placeholder="np. Skoda Octavia 2017"></div>
				<div><label for="bk-plate">Nr rejestracyjny</label><input id="bk-plate" name="plate" type="text" maxlength="12" autocapitalize="characters"></div>
				<div class="full"><label for="bk-message">Opis problemu</label><textarea id="bk-message" name="message" rows="3" maxlength="1500" placeholder="Co się dzieje z autem? Np. stuki przy skręcaniu, kontrolka silnika…"></textarea></div>
				<div class="booking__hp" aria-hidden="true"><label for="bk-website">Strona www</label><input id="bk-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
				<label class="booking__consent full">
					<input type="checkbox" name="consent" value="1" required>
					<span>Wyrażam zgodę na przetwarzanie moich danych w celu realizacji rezerwacji i kontaktu w jej sprawie.<?php if ( $privacy ) : ?> Szczegóły w <a href="<?php echo esc_url( $privacy ); ?>">polityce prywatności</a>.<?php endif; ?> *</span>
				</label>
			</div>
		</div>

		<input type="hidden" name="date" value="">
		<input type="hidden" name="time" value="">
		<button type="submit" class="btn btn--primary" data-submit>Zarezerwuj wizytę</button>
		<div class="booking__msg" data-msg role="status" aria-live="polite" hidden></div>
	</form>
	<?php
	return ob_get_clean();
} );

/* =====================================================================
 * Panel administracyjny: kolumny, filtr, szczegóły, licznik nowych
 * ===================================================================== */
add_filter( 'manage_wizyta_posts_columns', function () {
	return array(
		'cb'         => '<input type="checkbox" />',
		'wm_termin'  => 'Termin',
		'wm_klient'  => 'Klient',
		'wm_telefon' => 'Telefon',
		'wm_auto'    => 'Samochód',
		'wm_usluga'  => 'Usługa',
		'wm_status'  => 'Status',
	);
} );

add_action( 'manage_wizyta_posts_custom_column', function ( $col, $id ) {
	$b = wm_booking_meta( $id );
	switch ( $col ) {
		case 'wm_termin':
			printf(
				'<strong><a class="row-title" href="%s">%s, %s</a></strong>',
				esc_url( get_edit_post_link( $id ) ),
				esc_html( $b['date'] ? wp_date( 'D j.m.Y', strtotime( $b['date'] . ' 12:00' ), wp_timezone() ) : '—' ),
				esc_html( $b['time'] )
			);
			break;
		case 'wm_klient':
			echo esc_html( $b['name'] );
			break;
		case 'wm_telefon':
			printf( '<a href="tel:%s">%s</a>', esc_attr( preg_replace( '/[^\d+]/', '', $b['phone'] ) ), esc_html( $b['phone'] ) );
			break;
		case 'wm_auto':
			echo esc_html( $b['car'] . ( $b['plate'] ? ' · ' . $b['plate'] : '' ) );
			break;
		case 'wm_usluga':
			echo esc_html( $b['service'] );
			break;
		case 'wm_status':
			$colors = array( 'nowa' => '#e8590c', 'potwierdzona' => '#2f9e44', 'zakonczona' => '#868e96', 'anulowana' => '#c92a2a' );
			$st     = wm_statuses();
			$s      = isset( $st[ $b['status'] ] ) ? $b['status'] : 'nowa';
			printf( '<span style="color:%s;font-weight:600">%s</span>', esc_attr( $colors[ $s ] ), esc_html( $st[ $s ] ) );
			break;
	}
}, 10, 2 );

add_filter( 'manage_edit-wizyta_sortable_columns', function ( $cols ) {
	$cols['wm_termin'] = 'wm_termin';
	return $cols;
} );

add_action( 'restrict_manage_posts', function ( $post_type ) {
	if ( 'wizyta' !== $post_type ) {
		return;
	}
	$cur = isset( $_GET['wm_okres'] ) ? sanitize_key( $_GET['wm_okres'] ) : 'nadchodzace'; // phpcs:ignore WordPress.Security.NonceVerification
	echo '<select name="wm_okres">';
	foreach ( array( 'nadchodzace' => 'Nadchodzące wizyty', 'minione' => 'Minione wizyty', 'wszystkie' => 'Wszystkie terminy' ) as $k => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $cur, $k, false ), esc_html( $label ) );
	}
	echo '</select>';
} );

add_action( 'pre_get_posts', function ( WP_Query $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || 'wizyta' !== $q->get( 'post_type' ) ) {
		return;
	}
	global $pagenow;
	if ( 'edit.php' !== $pagenow ) {
		return;
	}
	$okres = isset( $_GET['wm_okres'] ) ? sanitize_key( $_GET['wm_okres'] ) : 'nadchodzace'; // phpcs:ignore WordPress.Security.NonceVerification
	$today = wp_date( 'Y-m-d 00:00' );
	$q->set( 'meta_key', '_wm_start' );
	$q->set( 'orderby', 'meta_value' );
	$order = isset( $_GET['order'] ) ? ( 'desc' === strtolower( sanitize_key( $_GET['order'] ) ) ? 'DESC' : 'ASC' ) : null; // phpcs:ignore WordPress.Security.NonceVerification
	if ( 'nadchodzace' === $okres ) {
		$q->set( 'meta_query', array( array( 'key' => '_wm_start', 'value' => $today, 'compare' => '>=' ) ) );
		$q->set( 'order', $order ? $order : 'ASC' );
	} elseif ( 'minione' === $okres ) {
		$q->set( 'meta_query', array( array( 'key' => '_wm_start', 'value' => $today, 'compare' => '<' ) ) );
		$q->set( 'order', $order ? $order : 'DESC' );
	} else {
		$q->set( 'order', $order ? $order : 'DESC' );
	}
} );

/* Szczegóły wizyty – edycja w panelu. */
add_action( 'add_meta_boxes_wizyta', function () {
	add_meta_box( 'wm_details', 'Szczegóły wizyty', 'wm_render_details_box', 'wizyta', 'normal', 'high' );
} );

function wm_render_details_box( $post ) {
	$b = wm_booking_meta( $post->ID );
	if ( '' === $b['status'] ) {
		$b['status'] = 'nowa';
	}
	wp_nonce_field( 'wm_save_' . $post->ID, 'wm_nonce' );
	$fields = array(
		'date'    => array( 'Data', 'date' ),
		'time'    => array( 'Godzina', 'time' ),
		'name'    => array( 'Klient', 'text' ),
		'phone'   => array( 'Telefon', 'tel' ),
		'email'   => array( 'E-mail', 'email' ),
		'car'     => array( 'Samochód', 'text' ),
		'plate'   => array( 'Nr rejestracyjny', 'text' ),
		'service' => array( 'Usługa', 'text' ),
	);
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th><label for="wm_status">Status</label></th><td><select name="wm[status]" id="wm_status">';
	foreach ( wm_statuses() as $k => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $b['status'], $k, false ), esc_html( $label ) );
	}
	echo '</select> <label><input type="checkbox" name="wm_notify" value="1" checked> powiadom klienta e-mailem o potwierdzeniu / odwołaniu</label></td></tr>';
	foreach ( $fields as $k => $f ) {
		printf(
			'<tr><th><label for="wm_%1$s">%2$s</label></th><td><input class="regular-text" type="%3$s" id="wm_%1$s" name="wm[%1$s]" value="%4$s"></td></tr>',
			esc_attr( $k ),
			esc_html( $f[0] ),
			esc_attr( $f[1] ),
			esc_attr( $b[ $k ] )
		);
	}
	printf(
		'<tr><th><label for="wm_message">Opis problemu</label></th><td><textarea class="large-text" rows="4" id="wm_message" name="wm[message]">%s</textarea></td></tr>',
		esc_textarea( $b['message'] )
	);
	echo '</tbody></table>';
	if ( $b['phone'] ) {
		printf( '<p><a class="button" href="tel:%s">Zadzwoń do klienta</a></p>', esc_attr( preg_replace( '/[^\d+]/', '', $b['phone'] ) ) );
	}
}

add_action( 'save_post_wizyta', function ( $post_id, $post ) {
	static $running = false;
	if ( $running ) {
		return;
	}
	if ( ! isset( $_POST['wm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wm_nonce'] ) ), 'wm_save_' . $post_id ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$in  = isset( $_POST['wm'] ) && is_array( $_POST['wm'] ) ? wp_unslash( $_POST['wm'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$old = get_post_meta( $post_id, '_wm_status', true );

	foreach ( array( 'date', 'time', 'name', 'phone', 'car', 'plate', 'service' ) as $k ) {
		if ( isset( $in[ $k ] ) ) {
			update_post_meta( $post_id, '_wm_' . $k, sanitize_text_field( $in[ $k ] ) );
		}
	}
	if ( isset( $in['email'] ) ) {
		update_post_meta( $post_id, '_wm_email', sanitize_email( $in['email'] ) );
	}
	if ( isset( $in['message'] ) ) {
		update_post_meta( $post_id, '_wm_message', sanitize_textarea_field( $in['message'] ) );
	}
	$statuses = wm_statuses();
	$status   = isset( $in['status'] ) && is_string( $in['status'] ) && isset( $statuses[ $in['status'] ] ) ? $in['status'] : 'nowa';
	update_post_meta( $post_id, '_wm_status', $status );

	$date = get_post_meta( $post_id, '_wm_date', true );
	$time = get_post_meta( $post_id, '_wm_time', true );
	update_post_meta( $post_id, '_wm_start', trim( $date . ' ' . $time ) );

	// Tytuł wpisu aktualny względem danych.
	$title = sprintf( '%s %s – %s (%s)', $date, $time, get_post_meta( $post_id, '_wm_name', true ), get_post_meta( $post_id, '_wm_car', true ) );
	if ( $post->post_title !== $title ) {
		$running = true;
		wp_update_post( array( 'ID' => $post_id, 'post_title' => $title ) );
		$running = false;
	}

	if ( $status !== $old && ! empty( $_POST['wm_notify'] ) && in_array( $status, array( 'potwierdzona', 'anulowana' ), true ) ) {
		wm_mail_client( $post_id, $status );
	}
}, 10, 2 );

/* Licznik nowych wizyt przy menu. */
add_action( 'admin_menu', function () {
	global $menu;
	$count = count( get_posts( array(
		'post_type'      => 'wizyta',
		'posts_per_page' => 50,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array( array( 'key' => '_wm_status', 'value' => 'nowa' ) ),
	) ) );
	if ( ! $count ) {
		return;
	}
	foreach ( $menu as $i => $item ) {
		if ( isset( $item[2] ) && 'edit.php?post_type=wizyta' === $item[2] ) {
			$menu[ $i ][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . (int) $count . '</span></span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		}
	}
}, 99 );

/* Widżet na kokpicie: najbliższe wizyty. */
add_action( 'wp_dashboard_setup', function () {
	wp_add_dashboard_widget( 'wm_upcoming', 'Najbliższe wizyty w warsztacie', function () {
		$ids = get_posts( array(
			'post_type'      => 'wizyta',
			'posts_per_page' => 10,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => '_wm_start',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => array(
				array( 'key' => '_wm_start', 'value' => wp_date( 'Y-m-d 00:00' ), 'compare' => '>=' ),
			),
		) );
		if ( ! $ids ) {
			echo '<p>Brak zaplanowanych wizyt.</p>';
			return;
		}
		echo '<table class="widefat striped"><tbody>';
		foreach ( $ids as $id ) {
			$b = wm_booking_meta( $id );
			if ( 'anulowana' === $b['status'] ) {
				continue;
			}
			printf(
				'<tr><td><a href="%s"><strong>%s %s</strong></a></td><td>%s<br><small>%s</small></td><td>%s</td></tr>',
				esc_url( get_edit_post_link( $id ) ),
				esc_html( wp_date( 'D j.m', strtotime( $b['date'] . ' 12:00' ), wp_timezone() ) ),
				esc_html( $b['time'] ),
				esc_html( $b['name'] ),
				esc_html( $b['car'] ),
				esc_html( $b['service'] )
			);
		}
		echo '</tbody></table>';
		printf( '<p><a href="%s">Wszystkie wizyty &rarr;</a></p>', esc_url( admin_url( 'edit.php?post_type=wizyta' ) ) );
	} );
} );
