<?php defined( 'ABSPATH' ) || exit; ?>
</main>

<footer class="site-footer">
	<div class="wrap">
		<div class="footer-cols">
			<div>
				<h2><?php echo esc_html( wm_opt( 'nazwa' ) ); ?></h2>
				<p><?php echo esc_html( wm_opt( 'slogan' ) ); ?></p>
				<p>
					<?php echo esc_html( wm_opt( 'ulica' ) ); ?><br>
					<?php echo esc_html( trim( wm_opt( 'kod' ) . ' ' . wm_opt( 'miasto' ) ) ); ?><br>
					<?php if ( wm_phone_href() ) : ?>
						<a href="<?php echo esc_attr( wm_phone_href() ); ?>"><?php echo esc_html( wm_opt( 'telefon' ) ); ?></a><br>
					<?php endif; ?>
					<?php if ( wm_opt( 'nip' ) ) : ?>
						NIP: <?php echo esc_html( wm_opt( 'nip' ) ); ?>
					<?php endif; ?>
				</p>
			</div>
			<div>
				<h2>Godziny otwarcia</h2>
				<?php wm_hours_table(); ?>
			</div>
			<div>
				<h2>Usługi</h2>
				<ul>
					<?php
					$parent = get_page_by_path( 'uslugi' );
					if ( $parent ) {
						foreach ( get_pages( array( 'parent' => $parent->ID, 'sort_column' => 'menu_order,post_title' ) ) as $p ) {
							printf( '<li><a href="%s">%s</a></li>', esc_url( get_permalink( $p ) ), esc_html( get_the_title( $p ) ) );
						}
					}
					?>
				</ul>
			</div>
			<div>
				<h2>Na skróty</h2>
				<?php
				wp_nav_menu( array(
					'theme_location' => 'footer',
					'container'      => false,
					'depth'          => 1,
					'fallback_cb'    => 'wm_menu_fallback',
				) );
				?>
				<?php if ( wm_opt( 'google_url' ) || wm_opt( 'fb_url' ) ) : ?>
					<p style="margin-top:12px">
						<?php if ( wm_opt( 'google_url' ) ) : ?><a href="<?php echo esc_url( wm_opt( 'google_url' ) ); ?>" target="_blank" rel="noopener">Opinie w Google</a><?php endif; ?>
						<?php if ( wm_opt( 'fb_url' ) ) : ?> · <a href="<?php echo esc_url( wm_opt( 'fb_url' ) ); ?>" target="_blank" rel="noopener">Facebook</a><?php endif; ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<div class="footer-bottom">
			© <?php echo esc_html( wp_date( 'Y' ) . ' ' . wm_opt( 'nazwa' ) ); ?>
			<?php if ( get_privacy_policy_url() ) : ?> · <a href="<?php echo esc_url( get_privacy_policy_url() ); ?>">Polityka prywatności</a><?php endif; ?>
		</div>
	</div>
</footer>

<nav class="mobile-bar" aria-label="Szybki kontakt">
	<?php if ( wm_phone_href() ) : ?>
		<a href="<?php echo esc_attr( wm_phone_href() ); ?>">📞 Zadzwoń</a>
	<?php else : ?>
		<a href="<?php echo esc_url( wm_page_url( 'kontakt' ) ); ?>">📍 Kontakt</a>
	<?php endif; ?>
	<a href="<?php echo esc_url( wm_booking_url() ); ?>">📅 Umów wizytę</a>
</nav>

<?php wp_footer(); ?>
</body>
</html>
