<?php
/**
 * SEO: tytuły, opisy meta, Open Graph, dane strukturalne schema.org (AutoRepair, Service, BreadcrumbList, FAQPage),
 * porządki w mapie witryny. Jeśli zainstalujesz Yoast / Rank Math / AIOSEO / SEOPress, motyw ustąpi im
 * w kwestii tytułów, opisów i OG (dane AutoRepair zostają, bo te wtyczki ich nie generują).
 */

defined( 'ABSPATH' ) || exit;

function wm_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'THE_SEO_FRAMEWORK_VERSION' );
}

/* ---------------------------------------------------------------------
 * Pola „Tytuł SEO” i „Opis SEO” przy edycji stron i wpisów
 * ------------------------------------------------------------------- */
add_action( 'add_meta_boxes', function () {
	if ( wm_seo_plugin_active() ) {
		return;
	}
	foreach ( array( 'page', 'post' ) as $type ) {
		add_meta_box( 'wm_seo', 'SEO – jak strona wygląda w Google', 'wm_render_seo_box', $type, 'normal', 'default' );
	}
} );

function wm_render_seo_box( $post ) {
	wp_nonce_field( 'wm_seo_' . $post->ID, 'wm_seo_nonce' );
	$title = get_post_meta( $post->ID, '_wm_seo_title', true );
	$desc  = get_post_meta( $post->ID, '_wm_seo_desc', true );
	?>
	<p><label for="wm_seo_title"><strong>Tytuł w Google</strong> (do ~60 znaków; puste = automatycznie)</label><br>
		<input type="text" class="large-text" id="wm_seo_title" name="wm_seo_title" maxlength="90" value="<?php echo esc_attr( $title ); ?>"></p>
	<p><label for="wm_seo_desc"><strong>Opis w Google</strong> (120–160 znaków, zachęć do kliknięcia; wpisz dzielnicę i usługę)</label><br>
		<textarea class="large-text" rows="3" id="wm_seo_desc" name="wm_seo_desc" maxlength="200"><?php echo esc_textarea( $desc ); ?></textarea></p>
	<p><label><input type="checkbox" name="wm_seo_noindex" value="1" <?php checked( get_post_meta( $post->ID, '_wm_seo_noindex', true ), '1' ); ?>> Ukryj tę stronę przed Google (noindex)</label></p>
	<?php
}

add_action( 'save_post', function ( $post_id ) {
	if ( ! isset( $_POST['wm_seo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wm_seo_nonce'] ) ), 'wm_seo_' . $post_id ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, '_wm_seo_title', isset( $_POST['wm_seo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['wm_seo_title'] ) ) : '' );
	update_post_meta( $post_id, '_wm_seo_desc', isset( $_POST['wm_seo_desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wm_seo_desc'] ) ) : '' );
	update_post_meta( $post_id, '_wm_seo_noindex', empty( $_POST['wm_seo_noindex'] ) ? '' : '1' );
} );

/* ---------------------------------------------------------------------
 * Tytuł strony (<title>)
 * ------------------------------------------------------------------- */
add_filter( 'pre_get_document_title', function ( $title ) {
	if ( wm_seo_plugin_active() ) {
		return $title;
	}
	$id = wm_seo_current_id();
	if ( $id ) {
		$custom = get_post_meta( $id, '_wm_seo_title', true );
		if ( $custom ) {
			return $custom;
		}
	}
	if ( is_front_page() ) {
		// Krótko (Google pokazuje ~60 znaków); ulica tylko, jeśli nie ma jej w nazwie firmy.
		$street = preg_replace( '/^ul\.\s*/u', '', wm_opt( 'ulica' ) );
		if ( false !== mb_stripos( wm_opt( 'nazwa' ), preg_replace( '/\s*\d.*$/u', '', $street ) ) ) {
			return 'Mechanik Warszawa Mokotów – ' . wm_opt( 'nazwa' );
		}
		return sprintf( 'Mechanik Warszawa Mokotów, %s | %s', wm_opt( 'ulica' ), wm_opt( 'nazwa' ) );
	}
	return $title;
}, 20 );

add_filter( 'document_title_separator', function () {
	return '|';
} );

add_filter( 'document_title_parts', function ( $parts ) {
	if ( wm_seo_plugin_active() ) {
		return $parts;
	}
	$parts['site'] = wm_opt( 'nazwa' );
	unset( $parts['tagline'] );
	return $parts;
} );

function wm_seo_current_id() {
	if ( is_singular() ) {
		return get_queried_object_id();
	}
	if ( is_home() && get_option( 'page_for_posts' ) ) {
		return (int) get_option( 'page_for_posts' );
	}
	return 0;
}

/** Opis meta dla bieżącej strony. */
function wm_seo_description() {
	$id = wm_seo_current_id();
	if ( $id ) {
		$custom = get_post_meta( $id, '_wm_seo_desc', true );
		if ( $custom ) {
			return $custom;
		}
	}
	if ( is_front_page() ) {
		return sprintf(
			'Mechanik samochodowy na Mokotowie, %s, Warszawa. Diagnostyka komputerowa, hamulce, zawieszenie, wymiana oleju, klimatyzacja. Umów wizytę online!',
			wm_opt( 'ulica' )
		);
	}
	if ( $id ) {
		$post = get_post( $id );
		$text = has_excerpt( $post ) ? $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
		if ( $text ) {
			return mb_strlen( $text ) > 158 ? rtrim( mb_substr( $text, 0, 155 ), " ,.;:-–" ) . '…' : $text;
		}
	}
	return wm_opt( 'nazwa' ) . ' – ' . wm_opt( 'slogan' ) . ', ' . wm_address_line() . '.';
}

/** Obraz do udostępniania (Facebook, WhatsApp, Messenger). */
function wm_seo_image() {
	$id = wm_seo_current_id();
	if ( $id && has_post_thumbnail( $id ) ) {
		return get_the_post_thumbnail_url( $id, 'large' );
	}
	if ( wm_opt( 'hero_img' ) ) {
		return wm_opt( 'hero_img' );
	}
	$logo = get_theme_mod( 'custom_logo' );
	return $logo ? wp_get_attachment_image_url( $logo, 'full' ) : '';
}

function wm_seo_url() {
	if ( is_front_page() ) {
		return home_url( '/' );
	}
	$id = wm_seo_current_id();
	return $id ? get_permalink( $id ) : '';
}

/* ---------------------------------------------------------------------
 * <meta description>, Open Graph, Twitter
 * ------------------------------------------------------------------- */
add_action( 'wp_head', function () {
	if ( wm_seo_plugin_active() || is_404() || is_search() ) {
		return;
	}
	$desc  = wm_seo_description();
	$title = wp_get_document_title();
	$url   = wm_seo_url();
	$img   = wm_seo_image();

	echo "\n<!-- SEO: Warsztat Mokotów -->\n";
	printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
	printf( '<meta property="og:locale" content="pl_PL">' . "\n" );
	printf( '<meta property="og:type" content="%s">' . "\n", is_singular( 'post' ) ? 'article' : 'website' );
	printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( wm_opt( 'nazwa' ) ) );
	printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $title ) );
	printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $desc ) );
	if ( $url ) {
		printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );
	}
	if ( $img ) {
		printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $img ) );
	}
	printf( '<meta name="twitter:card" content="%s">' . "\n", $img ? 'summary_large_image' : 'summary' );
}, 2 );

/* Kanoniczny adres strony głównej (WordPress dodaje go sam na podstronach). */
add_filter( 'get_canonical_url', function ( $url ) {
	return is_front_page() ? home_url( '/' ) : $url;
} );

/* ---------------------------------------------------------------------
 * Robots: ukryj strony bez wartości dla Google
 * ------------------------------------------------------------------- */
add_filter( 'wp_robots', function ( $robots ) {
	if ( wm_seo_plugin_active() ) {
		return $robots;
	}
	$id = wm_seo_current_id();
	if ( is_author() || is_date() || is_attachment() || is_tag() || ( $id && '1' === get_post_meta( $id, '_wm_seo_noindex', true ) ) ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
		unset( $robots['max-image-preview'] );
	}
	return $robots;
} );

/* Strony autorów są zbędne na stronie firmowej – przekieruj na główną. */
add_action( 'template_redirect', function () {
	if ( is_author() ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
} );

/* Mapa witryny (/wp-sitemap.xml): bez użytkowników i tagów. */
add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
	return 'users' === $name ? false : $provider;
}, 10, 2 );
add_filter( 'wp_sitemaps_taxonomies', function ( $taxonomies ) {
	unset( $taxonomies['post_tag'], $taxonomies['post_format'] );
	return $taxonomies;
} );
add_filter( 'wp_sitemaps_posts_query_args', function ( $args, $post_type ) {
	if ( 'page' !== $post_type ) {
		return $args;
	}
	$noindex = get_posts( array(
		'post_type'      => 'page',
		'fields'         => 'ids',
		'posts_per_page' => 100,
		'no_found_rows'  => true,
		'meta_query'     => array( array( 'key' => '_wm_seo_noindex', 'value' => '1' ) ),
	) );
	if ( $noindex ) {
		$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(), $noindex );
	}
	return $args;
}, 10, 2 );

/* Odnośnik do mapy witryny w robots.txt (jeśli WP nie dodał go sam). */
add_filter( 'robots_txt', function ( $output, $public ) {
	if ( $public && false === stripos( $output, 'sitemap' ) ) {
		$output .= "\nSitemap: " . home_url( '/wp-sitemap.xml' ) . "\n";
	}
	return $output;
}, 10, 2 );

/* ---------------------------------------------------------------------
 * Dane strukturalne schema.org (JSON-LD)
 * ------------------------------------------------------------------- */
function wm_schema_business() {
	$id   = home_url( '/#warsztat' );
	$data = array(
		'@type'      => 'AutoRepair',
		'@id'        => $id,
		'name'       => wm_opt( 'nazwa' ),
		'description' => wm_opt( 'slogan' ),
		'url'        => home_url( '/' ),
		'address'    => array_filter( array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => wm_opt( 'ulica' ),
			'postalCode'      => wm_opt( 'kod' ),
			'addressLocality' => wm_opt( 'miasto' ),
			'addressRegion'   => 'mazowieckie',
			'addressCountry'  => 'PL',
		) ),
	);
	if ( wm_opt( 'telefon' ) ) {
		$data['telephone'] = str_replace( 'tel:', '', wm_phone_href() );
	}
	if ( wm_opt( 'email' ) ) {
		$data['email'] = wm_opt( 'email' );
	}
	if ( wm_opt( 'geo_lat' ) && wm_opt( 'geo_lng' ) ) {
		$data['geo'] = array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => (float) wm_opt( 'geo_lat' ),
			'longitude' => (float) wm_opt( 'geo_lng' ),
		);
		$data['hasMap'] = 'https://www.google.com/maps?q=' . rawurlencode( wm_opt( 'geo_lat' ) . ',' . wm_opt( 'geo_lng' ) );
	}
	$img = wm_opt( 'hero_img' );
	$logo = get_theme_mod( 'custom_logo' ) ? wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' ) : '';
	if ( $img || $logo ) {
		$data['image'] = $img ? $img : $logo;
	}
	if ( $logo ) {
		$data['logo'] = $logo;
	}
	if ( wm_opt( 'cennik' ) ) {
		$data['priceRange'] = wm_opt( 'cennik' );
	}

	$days  = array( 1 => 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' );
	$hours = array();
	foreach ( wm_hours() as $d => $range ) {
		if ( $range ) {
			$hours[] = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => 'https://schema.org/' . $days[ $d ],
				'opens'     => wm_min_to_time( $range[0] ),
				'closes'    => wm_min_to_time( $range[1] ),
			);
		}
	}
	if ( $hours ) {
		$data['openingHoursSpecification'] = $hours;
	}

	$areas = array_filter( array_map( 'trim', explode( ',', wm_opt( 'obszar' ) ) ) );
	if ( $areas ) {
		$data['areaServed'] = array_map( function ( $a ) {
			return array( '@type' => 'Place', 'name' => $a . ', Warszawa' );
		}, array_values( $areas ) );
	}

	$same = array_values( array_filter( array( wm_opt( 'google_url' ), wm_opt( 'fb_url' ) ) ) );
	if ( $same ) {
		$data['sameAs'] = $same;
	}

	$data['potentialAction'] = array(
		'@type'  => 'ReserveAction',
		'name'   => 'Umów wizytę online',
		'target' => array(
			'@type'          => 'EntryPoint',
			'urlTemplate'    => wm_booking_url(),
			'inLanguage'     => 'pl-PL',
			'actionPlatform' => array( 'https://schema.org/DesktopWebPlatform', 'https://schema.org/MobileWebPlatform' ),
		),
	);

	// Lista usług z podstron „Usługi”.
	$parent = get_page_by_path( 'uslugi' );
	if ( $parent ) {
		$items = array();
		foreach ( get_pages( array( 'parent' => $parent->ID, 'sort_column' => 'menu_order' ) ) as $p ) {
			$items[] = array(
				'@type'       => 'Offer',
				'itemOffered' => array( '@type' => 'Service', 'name' => get_the_title( $p ), 'url' => get_permalink( $p ) ),
			);
		}
		if ( $items ) {
			$data['hasOfferCatalog'] = array(
				'@type'           => 'OfferCatalog',
				'name'            => 'Usługi warsztatu',
				'itemListElement' => $items,
			);
		}
	}
	return $data;
}

add_action( 'wp_head', function () {
	if ( is_404() || is_search() ) {
		return;
	}
	$graph = array();
	$slug  = is_page() ? get_post_field( 'post_name', get_queried_object_id() ) : '';

	$graph[] = array(
		'@type'      => 'WebSite',
		'@id'        => home_url( '/#website' ),
		'url'        => home_url( '/' ),
		'name'       => wm_opt( 'nazwa' ),
		'inLanguage' => 'pl-PL',
		'publisher'  => array( '@id' => home_url( '/#warsztat' ) ),
	);

	// Pełne dane firmy na stronie głównej i w Kontakcie, w pozostałych miejscach odnośnik.
	if ( is_front_page() || 'kontakt' === $slug ) {
		$graph[] = wm_schema_business();
	}

	// Usługa na podstronie usługi.
	if ( is_page() ) {
		$parent = get_page_by_path( 'uslugi' );
		$pid    = get_queried_object_id();
		if ( $parent && (int) wp_get_post_parent_id( $pid ) === (int) $parent->ID ) {
			$graph[] = array(
				'@type'       => 'Service',
				'name'        => get_the_title( $pid ),
				'serviceType' => get_the_title( $pid ),
				'description' => wm_seo_description(),
				'url'         => get_permalink( $pid ),
				'areaServed'  => array( '@type' => 'City', 'name' => wm_opt( 'miasto' ) ),
				'provider'    => array(
					'@type'   => 'AutoRepair',
					'@id'     => home_url( '/#warsztat' ),
					'name'    => wm_opt( 'nazwa' ),
					'address' => array(
						'@type'           => 'PostalAddress',
						'streetAddress'   => wm_opt( 'ulica' ),
						'addressLocality' => wm_opt( 'miasto' ),
						'addressCountry'  => 'PL',
					),
				),
			);
		}
	}

	// Okruszki.
	if ( is_singular() && ! is_front_page() ) {
		$pid   = get_queried_object_id();
		$list  = array( array( 'Strona główna', home_url( '/' ) ) );
		foreach ( array_reverse( get_post_ancestors( $pid ) ) as $anc ) {
			$list[] = array( get_the_title( $anc ), get_permalink( $anc ) );
		}
		$list[] = array( get_the_title( $pid ), get_permalink( $pid ) );
		$items  = array();
		foreach ( $list as $i => $it ) {
			$items[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => wp_strip_all_tags( $it[0] ), 'item' => $it[1] );
		}
		$graph[] = array( '@type' => 'BreadcrumbList', 'itemListElement' => $items );
	}

	// FAQ na stronie głównej.
	if ( is_front_page() && wm_faq() ) {
		$graph[] = array(
			'@type'      => 'FAQPage',
			'mainEntity' => array_map( function ( $qa ) {
				return array(
					'@type'          => 'Question',
					'name'           => $qa[0],
					'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $qa[1] ),
				);
			}, wm_faq() ),
		);
	}

	echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_UNICODE ) . "</script>\n";
}, 5 );
