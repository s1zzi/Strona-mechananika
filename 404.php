<?php
defined( 'ABSPATH' ) || exit;
get_header();
?>
<header class="page-head">
	<div class="wrap"><h1>Nie znaleziono strony</h1></div>
</header>
<section class="section">
	<div class="wrap content">
		<p>Ta strona nie istnieje albo została przeniesiona. Możesz:</p>
		<p>
			<a class="btn btn--primary" href="<?php echo esc_url( wm_booking_url() ); ?>">Umówić wizytę</a>
			<a class="btn btn--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>">Wrócić na stronę główną</a>
		</p>
	</div>
</section>
<?php
get_footer();
