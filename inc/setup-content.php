<?php
/**
 * Po włączeniu motywu: ustawienia WordPressa + gotowe strony z treściami pod SEO + menu.
 * Wykonuje się tylko raz (nie nadpisuje stron, które już istnieją).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_switch_theme', 'wm_setup_site' );

function wm_setup_site() {
	if ( get_option( 'wm_setup_done' ) ) {
		return;
	}

	// Ustawienia regionalne.
	$tz = get_option( 'timezone_string' );
	if ( ! $tz || 'UTC' === $tz ) {
		update_option( 'timezone_string', 'Europe/Warsaw' );
		update_option( 'gmt_offset', '' );
	}
	update_option( 'start_of_week', 1 );
	update_option( 'date_format', 'j F Y' );
	update_option( 'time_format', 'H:i' );
	update_option( 'default_comment_status', 'closed' );
	update_option( 'default_ping_status', 'closed' );

	// Przyjazne adresy: /uslugi/diagnostyka-komputerowa/
	if ( ! get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
	}

	// Usuń przykładowe treści WordPressa (do kosza – można przywrócić).
	foreach ( array( 'hello-world', 'witaj-swiecie' ) as $slug ) {
		$p = get_page_by_path( $slug, OBJECT, 'post' );
		if ( $p ) {
			wp_trash_post( $p->ID );
		}
	}
	foreach ( array( 'sample-page', 'przykladowa-strona' ) as $slug ) {
		$p = get_page_by_path( $slug );
		if ( $p ) {
			wp_trash_post( $p->ID );
		}
	}

	$ids = array();
	foreach ( wm_setup_pages() as $slug => $page ) {
		$parent_id = 0;
		if ( ! empty( $page['parent'] ) && isset( $ids[ $page['parent'] ] ) ) {
			$parent_id = $ids[ $page['parent'] ];
		}
		$path     = $parent_id ? get_post_field( 'post_name', $parent_id ) . '/' . $slug : $slug;
		$existing = get_page_by_path( $path );
		if ( $existing && 'trash' !== $existing->post_status ) {
			// Np. szkic „Polityka prywatności” tworzony przez WordPressa – wypełnij i opublikuj.
			if ( 'draft' === $existing->post_status ) {
				wp_update_post( array(
					'ID'           => $existing->ID,
					'post_content' => $page['content'],
					'post_status'  => 'publish',
				) );
			}
			$ids[ $slug ] = $existing->ID;
			continue;
		}
		$id = wp_insert_post( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'post_title'     => $page['title'],
			'post_name'      => $slug,
			'post_content'   => $page['content'],
			'post_excerpt'   => isset( $page['excerpt'] ) ? $page['excerpt'] : '',
			'post_parent'    => $parent_id,
			'menu_order'     => isset( $page['order'] ) ? $page['order'] : 0,
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		) );
		if ( $id && ! is_wp_error( $id ) ) {
			$ids[ $slug ] = $id;
			if ( ! empty( $page['desc'] ) ) {
				update_post_meta( $id, '_wm_seo_desc', $page['desc'] );
			}
			if ( ! empty( $page['seo_title'] ) ) {
				update_post_meta( $id, '_wm_seo_title', $page['seo_title'] );
			}
		}
	}

	if ( isset( $ids['strona-glowna'] ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $ids['strona-glowna'] );
	}
	if ( isset( $ids['polityka-prywatnosci'] ) ) {
		update_option( 'wp_page_for_privacy_policy', $ids['polityka-prywatnosci'] );
	}

	// Menu główne.
	$locations = get_theme_mod( 'nav_menu_locations', array() );
	if ( empty( $locations['primary'] ) ) {
		$menu_id = wp_create_nav_menu( 'Menu główne' );
		if ( is_wp_error( $menu_id ) ) {
			$menu    = wp_get_nav_menu_object( 'Menu główne' );
			$menu_id = $menu ? $menu->term_id : 0;
		}
		if ( $menu_id ) {
			$labels = array(
				'strona-glowna' => 'Start',
				'uslugi'        => 'Usługi',
				'umow-wizyte'   => 'Umów wizytę',
				'kontakt'       => 'Kontakt',
			);
			foreach ( $labels as $slug => $label ) {
				if ( isset( $ids[ $slug ] ) ) {
					wp_update_nav_menu_item( $menu_id, 0, array(
						'menu-item-object-id' => $ids[ $slug ],
						'menu-item-object'    => 'page',
						'menu-item-type'      => 'post_type',
						'menu-item-status'    => 'publish',
						'menu-item-title'     => $label,
					) );
				}
			}
			$locations['primary'] = $menu_id;
			set_theme_mod( 'nav_menu_locations', $locations );
		}
	}

	flush_rewrite_rules();
	update_option( 'wm_setup_done', 1 );
}

/** Treści stron tworzonych przy instalacji. Wszystko możesz potem dowolnie edytować w Strony. */
function wm_setup_pages() {
	$addr = 'ul. Bonifacego 10';

	return array(
		'strona-glowna'        => array(
			'title'   => 'Mechanik samochodowy Warszawa Mokotów',
			'content' => "<p>Nasz warsztat samochodowy mieści się przy {$addr} na warszawskim Mokotowie. Zajmujemy się mechaniką, diagnostyką komputerową i bieżącym serwisem samochodów osobowych – od wymiany oleju i klocków hamulcowych, przez naprawy zawieszenia, po szukanie trudnych usterek elektronicznych.</p>\n"
				. "<p>Przed każdą naprawą mówimy, co trzeba zrobić i ile to będzie kosztować. Bez Twojej zgody nie wymieniamy żadnych części. Termin wizyty zarezerwujesz online w kilka kliknięć – wystarczy wybrać usługę, dzień i godzinę.</p>\n"
				. "<p>Obsługujemy kierowców z całego Mokotowa oraz okolic: Sadyby, Stegien, Sielc, Wilanowa, Służewa i Ursynowa.</p>",
		),

		'uslugi'               => array(
			'title'   => 'Usługi warsztatu samochodowego – Mokotów',
			'excerpt' => 'Naprawy mechaniczne, diagnostyka, hamulce, zawieszenie, klimatyzacja i wymiana oleju na Mokotowie.',
			'desc'    => 'Usługi mechanika na Mokotowie: diagnostyka komputerowa, hamulce, zawieszenie, wymiana oleju, serwis klimatyzacji, przegląd przed zakupem. Ul. Bonifacego 10.',
			'content' => "<p>W warsztacie przy {$addr} w Warszawie wykonujemy pełen zakres podstawowych napraw mechanicznych i serwisowych. Poniżej znajdziesz najczęściej wybierane usługi – kliknij, żeby zobaczyć szczegóły.</p>\n"
				. "<p>Nie widzisz swojej usterki na liście? Zarezerwuj wizytę z opcją „Inna usterka” i opisz problem – sprawdzimy auto i doradzimy.</p>",
		),

		'diagnostyka-komputerowa' => array(
			'parent'  => 'uslugi',
			'order'   => 1,
			'title'   => 'Diagnostyka komputerowa samochodu – Mokotów',
			'excerpt' => 'Świeci się kontrolka silnika? Odczytamy błędy, sprawdzimy ich przyczynę i powiemy, co dalej.',
			'desc'    => 'Diagnostyka komputerowa samochodu na Mokotowie: odczyt i analiza błędów, kontrolka silnika (check engine), ABS, airbag. Umów wizytę online – ul. Bonifacego 10.',
			'content' => "<p>Zapaliła się kontrolka silnika, ABS albo poduszki powietrznej? Auto nierówno pracuje, traci moc lub przeszło w tryb awaryjny? Diagnostyka komputerowa to pierwszy krok, żeby szybko znaleźć przyczynę i nie wymieniać części „na próbę”.</p>\n"
				. "<h2>Co obejmuje diagnostyka</h2>\n<ul>\n<li>odczyt i kasowanie kodów błędów ze sterowników (silnik, ABS/ESP, airbag, skrzynia i inne – w zależności od modelu),</li>\n<li>analizę parametrów pracy silnika na żywo,</li>\n<li>weryfikację, czy błąd jest aktualny, czy historyczny,</li>\n<li>omówienie wyników i propozycję naprawy z wyceną.</li>\n</ul>\n"
				. "<h2>Kiedy warto przyjechać</h2>\n<p>Gdy świeci się kontrolka, auto pali więcej niż zwykle, szarpie, gaśnie albo trudno odpala. Im wcześniej sprawdzisz usterkę, tym zwykle taniej ją naprawisz.</p>\n"
				. "<p>Warsztat znajduje się przy {$addr} na Mokotowie. Wybierz wolny termin w kalendarzu online.</p>",
		),

		'wymiana-oleju'        => array(
			'parent'  => 'uslugi',
			'order'   => 2,
			'title'   => 'Wymiana oleju i filtrów – Mokotów',
			'excerpt' => 'Wymiana oleju silnikowego i filtrów zgodnie z zaleceniami producenta auta.',
			'desc'    => 'Wymiana oleju silnikowego i filtrów (oleju, powietrza, kabinowego, paliwa) na Mokotowie. Dobór oleju do auta. Umów termin online – ul. Bonifacego 10, Warszawa.',
			'content' => "<p>Regularna wymiana oleju to najtańszy sposób, żeby silnik posłużył długo. Dobieramy olej zgodny ze specyfikacją producenta Twojego samochodu i wymieniamy filtry.</p>\n"
				. "<h2>W ramach serwisu olejowego</h2>\n<ul>\n<li>wymiana oleju silnikowego,</li>\n<li>wymiana filtra oleju,</li>\n<li>na życzenie: filtr powietrza, kabinowy i paliwa,</li>\n<li>kontrola poziomu pozostałych płynów,</li>\n<li>skasowanie inspekcji serwisowej (jeśli auto ją ma).</li>\n</ul>\n"
				. "<h2>Jak często wymieniać olej?</h2>\n<p>Najczęściej co 10–15 tys. km lub raz w roku – zależnie od silnika i sposobu jazdy. Przy jeździe głównie po mieście warto robić to częściej. Doradzimy, co jest najlepsze dla Twojego auta.</p>",
		),

		'naprawa-hamulcow'     => array(
			'parent'  => 'uslugi',
			'order'   => 3,
			'title'   => 'Naprawa hamulców – klocki i tarcze, Mokotów',
			'excerpt' => 'Wymiana klocków, tarcz i płynu hamulcowego. Sprawdzimy cały układ hamulcowy.',
			'desc'    => 'Naprawa hamulców na Mokotowie: wymiana klocków i tarcz hamulcowych, płynu hamulcowego, przewodów. Piszczy lub ściąga przy hamowaniu? Umów wizytę – ul. Bonifacego 10.',
			'content' => "<p>Sprawne hamulce to podstawa bezpieczeństwa. Jeśli coś piszczy lub zgrzyta przy hamowaniu, kierownica drży, auto ściąga w bok albo pedał „wpada” – nie zwlekaj z wizytą.</p>\n"
				. "<h2>Zakres usług</h2>\n<ul>\n<li>wymiana klocków i tarcz hamulcowych (przód i tył),</li>\n<li>wymiana płynu hamulcowego,</li>\n<li>serwis zacisków i hamulców bębnowych,</li>\n<li>wymiana przewodów hamulcowych,</li>\n<li>regulacja hamulca ręcznego.</li>\n</ul>\n"
				. "<p>Przed wymianą pokażemy, w jakim stanie są elementy i co faktycznie wymaga wymiany.</p>",
		),

		'naprawa-zawieszenia'  => array(
			'parent'  => 'uslugi',
			'order'   => 4,
			'title'   => 'Naprawa zawieszenia i układu kierowniczego – Mokotów',
			'excerpt' => 'Stuki, luzy, ściąganie auta? Sprawdzimy i naprawimy zawieszenie oraz układ kierowniczy.',
			'desc'    => 'Naprawa zawieszenia na Mokotowie: amortyzatory, wahacze, łączniki stabilizatora, sworznie, końcówki drążków. Stuki i luzy? Umów wizytę online – ul. Bonifacego 10.',
			'content' => "<p>Stuki na nierównościach, luz na kierownicy, nierówno ścierające się opony albo auto, które „pływa” na drodze – to typowe objawy zużytego zawieszenia. Warszawskie dziury i krawężniki dają mu się we znaki.</p>\n"
				. "<h2>Co naprawiamy</h2>\n<ul>\n<li>amortyzatory i sprężyny,</li>\n<li>wahacze, tuleje i sworznie,</li>\n<li>łączniki i gumy stabilizatora,</li>\n<li>końcówki i drążki kierownicze,</li>\n<li>łożyska kół.</li>\n</ul>\n"
				. "<p>Najpierw sprawdzamy zawieszenie i wskazujemy, co faktycznie jest zużyte. Po wymianie elementów układu kierowniczego zalecamy ustawienie geometrii kół.</p>",
		),

		'serwis-klimatyzacji'  => array(
			'parent'  => 'uslugi',
			'order'   => 5,
			'title'   => 'Serwis klimatyzacji samochodowej – Mokotów',
			'excerpt' => 'Klimatyzacja słabo chłodzi lub brzydko pachnie? Sprawdzimy szczelność, uzupełnimy czynnik, odgrzybimy.',
			'desc'    => 'Serwis klimatyzacji samochodowej na Mokotowie: kontrola szczelności, uzupełnienie czynnika, odgrzybianie, wymiana filtra kabinowego. Ul. Bonifacego 10, Warszawa.',
			'content' => "<p>Klimatyzacja z czasem traci czynnik chłodniczy, a w układzie rozwijają się bakterie i grzyby. Jeśli nawiew słabo chłodzi albo czuć nieprzyjemny zapach, czas na serwis.</p>\n"
				. "<h2>Serwis klimatyzacji obejmuje</h2>\n<ul>\n<li>sprawdzenie szczelności układu,</li>\n<li>odzysk i uzupełnienie czynnika oraz oleju,</li>\n<li>odgrzybianie parownika i kanałów,</li>\n<li>wymianę filtra kabinowego,</li>\n<li>kontrolę temperatury nawiewu.</li>\n</ul>\n"
				. "<p>Serwis warto robić raz w roku, najlepiej wiosną – przed upałami.</p>",
		),

		'przeglad-przed-zakupem' => array(
			'parent'  => 'uslugi',
			'order'   => 6,
			'title'   => 'Sprawdzenie auta przed zakupem – Mokotów',
			'excerpt' => 'Kupujesz używane auto? Przyjedź z nim do nas, zanim zapłacisz.',
			'desc'    => 'Sprawdzenie samochodu przed zakupem na Mokotowie: zawieszenie, hamulce, silnik, diagnostyka komputerowa, wycieki. Nie kupuj kota w worku – ul. Bonifacego 10, Warszawa.',
			'content' => "<p>Kupujesz używany samochód w Warszawie? Zanim zapłacisz, przyjedź z nim do warsztatu. Godzina kontroli może oszczędzić Ci kilku tysięcy złotych napraw.</p>\n"
				. "<h2>Co sprawdzamy</h2>\n<ul>\n<li>stan zawieszenia i układu kierowniczego (na podnośniku),</li>\n<li>hamulce, opony, wycieki płynów,</li>\n<li>pracę silnika i skrzyni biegów,</li>\n<li>diagnostykę komputerową – zapisane błędy,</li>\n<li>ślady korozji i napraw od spodu.</li>\n</ul>\n"
				. "<p>Po kontroli dostaniesz informację, co jest w porządku, a co wymaga naprawy i ile może kosztować – to dobry argument do negocjacji ceny.</p>",
		),

		'umow-wizyte'          => array(
			'title'   => 'Umów wizytę u mechanika – Mokotów',
			'desc'    => 'Zarezerwuj wizytę w warsztacie samochodowym na Mokotowie online. Wybierz usługę, dzień i godzinę. Ul. Bonifacego 10, Warszawa.',
			'content' => "<p>Wybierz usługę, dzień i godzinę, a potem podaj swoje dane i informacje o aucie. Potwierdzenie rezerwacji przyjdzie na e-mail. Jeśli będziemy mieć pytania, zadzwonimy.</p>\n\n[rezerwacja]\n\n<p>Nie możesz przyjechać w wybranym terminie? Zadzwoń lub odpisz na e-mail z potwierdzeniem – przełożymy wizytę.</p>",
		),

		'kontakt'              => array(
			'title'   => 'Kontakt i dojazd – warsztat Mokotów',
			'desc'    => 'Kontakt z warsztatem samochodowym przy ul. Bonifacego 10 w Warszawie (Mokotów): telefon, godziny otwarcia, mapa dojazdu.',
			'content' => "<p>Zapraszamy do warsztatu przy {$addr} w Warszawie (Mokotów). Najszybciej umówisz się przez kalendarz online, ale chętnie odbierzemy też telefon.</p>",
		),

		'polityka-prywatnosci' => array(
			'title'   => 'Polityka prywatności',
			'content' => wm_privacy_text(),
		),
	);
}

function wm_privacy_text() {
	return "<p><strong>Uzupełnij dane w nawiasach kwadratowych przed publikacją strony.</strong></p>\n"
		. "<h2>Administrator danych</h2>\n<p>Administratorem Twoich danych osobowych jest [pełna nazwa firmy], [adres: ul. Bonifacego 10, kod pocztowy Warszawa], NIP [numer]. Kontakt: [e-mail], [telefon].</p>\n"
		. "<h2>Jakie dane i po co</h2>\n<p>Przy rezerwacji wizyty zbieramy: imię i nazwisko, numer telefonu, adres e-mail (opcjonalnie), markę, model i numer rejestracyjny samochodu oraz opis usterki. Dane przetwarzamy wyłącznie w celu umówienia i realizacji wizyty oraz kontaktu w jej sprawie (art. 6 ust. 1 lit. b RODO), a także w celu rozliczeń i obowiązków podatkowych (art. 6 ust. 1 lit. c RODO).</p>\n"
		. "<h2>Jak długo</h2>\n<p>Dane z rezerwacji przechowujemy przez czas potrzebny do realizacji usługi, a następnie przez okres wymagany przepisami (np. podatkowymi) lub do czasu przedawnienia ewentualnych roszczeń.</p>\n"
		. "<h2>Komu przekazujemy dane</h2>\n<p>Dane mogą być przekazywane wyłącznie podmiotom, które pomagają nam prowadzić stronę i firmę (hosting, poczta e-mail, biuro rachunkowe). Nie sprzedajemy danych i nie wykorzystujemy ich do reklamy.</p>\n"
		. "<h2>Twoje prawa</h2>\n<p>Masz prawo do dostępu do danych, ich sprostowania, usunięcia, ograniczenia przetwarzania, przeniesienia oraz sprzeciwu. Możesz też złożyć skargę do Prezesa Urzędu Ochrony Danych Osobowych (uodo.gov.pl).</p>\n"
		. "<h2>Pliki cookies i mapa</h2>\n<p>Strona używa wyłącznie niezbędnych plików cookies. Mapa Google ładuje się dopiero po kliknięciu przycisku „Pokaż mapę” – wtedy Google może zapisać własne pliki cookies zgodnie ze swoją polityką prywatności.</p>";
}
