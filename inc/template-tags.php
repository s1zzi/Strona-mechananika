<?php
/**
 * Elementy szablonu używane na wielu stronach.
 */

defined( 'ABSPATH' ) || exit;

/** Dzisiejsze godziny otwarcia w formie tekstu. */
function wm_today_hours_text() {
	$hours = wm_hours();
	$today = (int) wp_date( 'N' );
	$range = $hours[ $today ];
	if ( ! $range || in_array( wp_date( 'Y-m-d' ), wm_closed_dates(), true ) ) {
		return 'Dziś nieczynne';
	}
	return 'Dziś otwarte ' . wm_min_to_time( $range[0] ) . '–' . wm_min_to_time( $range[1] );
}

function wm_hours_table() {
	$today = (int) wp_date( 'N' );
	echo '<table class="hours"><tbody>';
	foreach ( wm_hours() as $d => $range ) {
		printf(
			'<tr%s><td>%s</td><td>%s</td></tr>',
			$d === $today ? ' class="is-today"' : '',
			esc_html( wm_weekdays()[ $d ] ),
			esc_html( $range ? wm_min_to_time( $range[0] ) . '–' . wm_min_to_time( $range[1] ) : 'nieczynne' )
		);
	}
	echo '</tbody></table>';
}

/** Przycisk „Zadzwoń”, o ile podano numer. */
function wm_call_button( $class = 'btn btn--ghost' ) {
	$href = wm_phone_href();
	if ( ! $href ) {
		return;
	}
	printf( '<a class="%s" href="%s">📞 %s</a>', esc_attr( $class ), esc_attr( $href ), esc_html( wm_opt( 'telefon' ) ) );
}

/** Okruszki (breadcrumbs) dla podstron. */
function wm_breadcrumbs() {
	if ( is_front_page() ) {
		return;
	}
	$items = array( array( 'Strona główna', home_url( '/' ) ) );
	if ( is_page() ) {
		foreach ( array_reverse( get_post_ancestors( get_the_ID() ) ) as $anc ) {
			$items[] = array( get_the_title( $anc ), get_permalink( $anc ) );
		}
	}
	echo '<nav class="breadcrumbs" aria-label="Okruszki">';
	foreach ( $items as $it ) {
		printf( '<a href="%s">%s</a> › ', esc_url( $it[1] ), esc_html( $it[0] ) );
	}
	echo '<span aria-current="page">' . esc_html( wp_strip_all_tags( is_singular() ? get_the_title() : wp_get_document_title() ) ) . '</span>';
	echo '</nav>';
}

/** Kafelki usług = podstrony strony „Usługi”. */
function wm_service_cards() {
	$parent = get_page_by_path( 'uslugi' );
	if ( ! $parent ) {
		return;
	}
	$pages = get_pages( array(
		'parent'      => $parent->ID,
		'sort_column' => 'menu_order,post_title',
	) );
	if ( ! $pages ) {
		return;
	}
	echo '<div class="grid">';
	foreach ( $pages as $p ) {
		$excerpt = has_excerpt( $p ) ? get_the_excerpt( $p ) : wp_trim_words( wp_strip_all_tags( $p->post_content ), 22 );
		printf(
			'<article class="card"><h3><a href="%1$s">%2$s</a></h3><p>%3$s</p><a class="card__more" href="%1$s">Szczegóły<span class="screen-reader-text">: %2$s</span> &rarr;</a></article>',
			esc_url( get_permalink( $p ) ),
			esc_html( get_the_title( $p ) ),
			esc_html( $excerpt )
		);
	}
	echo '</div>';
}

function wm_faq_block() {
	$faq = wm_faq();
	if ( ! $faq ) {
		return;
	}
	echo '<div class="faq">';
	foreach ( $faq as $i => $qa ) {
		printf( '<details%s><summary>%s</summary><div><p>%s</p></div></details>', 0 === $i ? ' open' : '', esc_html( $qa[0] ), esc_html( $qa[1] ) );
	}
	echo '</div>';
}

/** Zachęta do rezerwacji na końcu podstron. */
function wm_cta_box() {
	?>
	<aside class="cta-box">
		<p><strong>Potrzebujesz mechanika na Mokotowie?</strong> Zarezerwuj termin online w minutę albo zadzwoń – <?php echo esc_html( wm_opt( 'ulica' ) . ', ' . wm_opt( 'miasto' ) ); ?>.</p>
		<a class="btn btn--primary" href="<?php echo esc_url( wm_booking_url() ); ?>">Umów wizytę online</a>
		<?php wm_call_button( 'btn btn--ghost' ); ?>
	</aside>
	<?php
}

/** Blok kontaktowy: dane, godziny, mapa ładowana po kliknięciu (szybkość + RODO). */
function wm_contact_block() {
	$q       = wm_maps_query();
	$embed   = 'https://www.google.com/maps?q=' . rawurlencode( $q ) . '&z=16&output=embed';
	$route   = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $q );
	$email   = wm_opt( 'email' );
	?>
	<div class="contact">
		<div>
			<address style="font-style:normal">
				<dl>
					<dt>Adres</dt>
					<dd><?php echo esc_html( wm_opt( 'nazwa' ) ); ?><br><?php echo esc_html( wm_opt( 'ulica' ) ); ?><br><?php echo esc_html( trim( wm_opt( 'kod' ) . ' ' . wm_opt( 'miasto' ) ) ); ?> (<?php echo esc_html( wm_opt( 'dzielnica' ) ); ?>)</dd>
					<?php if ( wm_phone_href() ) : ?>
						<dt>Telefon</dt>
						<dd><a href="<?php echo esc_attr( wm_phone_href() ); ?>"><?php echo esc_html( wm_opt( 'telefon' ) ); ?></a></dd>
					<?php endif; ?>
					<?php if ( $email ) : ?>
						<dt>E-mail</dt>
						<dd><a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a></dd>
					<?php endif; ?>
				</dl>
			</address>
			<h3 style="margin-top:22px">Godziny otwarcia</h3>
			<?php wm_hours_table(); ?>
			<p style="margin-top:18px"><a class="btn btn--primary" href="<?php echo esc_url( $route ); ?>" target="_blank" rel="noopener">Wyznacz trasę</a></p>
		</div>
		<div class="map" data-map="<?php echo esc_attr( $embed ); ?>">
			<div>
				<p>Mapa Google zostanie załadowana po kliknięciu.</p>
				<button type="button" class="btn btn--primary" data-map-load>Pokaż mapę dojazdu</button>
			</div>
		</div>
	</div>
	<?php
}

/** Zapasowe menu, gdy nie przypisano żadnego. */
function wm_menu_fallback() {
	$links = array(
		array( 'Strona główna', home_url( '/' ) ),
		array( 'Usługi', wm_page_url( 'uslugi' ) ),
		array( 'Umów wizytę', wm_booking_url() ),
		array( 'Kontakt', wm_page_url( 'kontakt' ) ),
	);
	echo '<ul>';
	foreach ( $links as $l ) {
		printf( '<li><a href="%s">%s</a></li>', esc_url( $l[1] ), esc_html( $l[0] ) );
	}
	echo '</ul>';
}
