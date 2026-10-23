<?php
/**
 * Strona główna.
 */
defined( 'ABSPATH' ) || exit;
get_header();

$hero_img = wm_opt( 'hero_img' );
$style    = $hero_img ? ' style="--hero-img:url(' . esc_url( $hero_img ) . ')"' : '';
?>

<section class="hero"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<div class="wrap">
		<h1>Mechanik samochodowy Warszawa Mokotów – <?php echo esc_html( wm_opt( 'ulica' ) ); ?></h1>
		<p class="hero__lead">
			<?php echo esc_html( wm_opt( 'nazwa' ) ); ?> – naprawy mechaniczne, diagnostyka komputerowa i serwis samochodów na Mokotowie.
			Wybierz wolny termin w kalendarzu i przyjedź bez czekania w kolejce.
		</p>
		<div class="hero__actions">
			<a class="btn btn--primary" href="#rezerwacja">📅 Umów wizytę online</a>
			<?php wm_call_button( 'btn btn--ghost' ); ?>
		</div>
		<ul class="hero__facts">
			<li>Rezerwacja online 24/7</li>
			<li>Wycena przed naprawą</li>
			<li><?php echo esc_html( wm_today_hours_text() ); ?></li>
		</ul>
	</div>
</section>

<section class="section" id="uslugi">
	<div class="wrap">
		<h2>Usługi warsztatu na Mokotowie</h2>
		<p class="section__lead">Zajmujemy się bieżącą obsługą i naprawami samochodów osobowych. Kliknij usługę, żeby dowiedzieć się więcej.</p>
		<?php wm_service_cards(); ?>
	</div>
</section>

<section class="section section--alt">
	<div class="wrap">
		<h2>Dlaczego warto przyjechać do nas</h2>
		<div class="features">
			<div class="feature"><h3>Termin bez dzwonienia</h3><p>Wolne godziny widzisz od razu w kalendarzu. Rezerwujesz w minutę, o każdej porze.</p></div>
			<div class="feature"><h3>Najpierw diagnoza i cena</h3><p>Przed naprawą mówimy, co jest do zrobienia i ile to kosztuje. Bez niespodzianek przy odbiorze.</p></div>
			<div class="feature"><h3>Blisko, na Mokotowie</h3><p><?php echo esc_html( wm_opt( 'ulica' ) ); ?> – wygodny dojazd z Sadyby, Stegien, Sielc i Wilanowa.</p></div>
			<div class="feature"><h3>Mówimy po ludzku</h3><p>Wyjaśniamy, co się zepsuło i co może poczekać, a co trzeba naprawić od razu.</p></div>
		</div>
	</div>
</section>

<?php
while ( have_posts() ) :
	the_post();
	if ( '' !== trim( get_the_content() ) ) :
		?>
		<section class="section">
			<div class="wrap content">
				<h2>O warsztacie</h2>
				<?php the_content(); ?>
			</div>
		</section>
		<?php
	endif;
endwhile;
?>

<section class="section section--alt" id="rezerwacja">
	<div class="wrap">
		<h2>Umów wizytę u mechanika</h2>
		<p class="section__lead">Wybierz usługę, dzień i godzinę. Potwierdzenie przyjdzie na e-mail, a w razie pytań oddzwonimy.</p>
		<?php echo do_shortcode( '[rezerwacja]' ); ?>
	</div>
</section>

<?php if ( wm_faq() ) : ?>
<section class="section" id="faq">
	<div class="wrap content">
		<h2>Najczęstsze pytania</h2>
		<?php wm_faq_block(); ?>
	</div>
</section>
<?php endif; ?>

<section class="section section--alt" id="kontakt">
	<div class="wrap">
		<h2>Kontakt i dojazd – <?php echo esc_html( wm_opt( 'ulica' ) . ', ' . wm_opt( 'miasto' ) ); ?></h2>
		<?php wm_contact_block(); ?>
	</div>
</section>

<?php
get_footer();
