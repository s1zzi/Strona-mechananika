/* Menu mobilne + mapa ładowana po kliknięciu. */
(function () {
	'use strict';

	var toggle = document.querySelector('.nav-toggle');
	var nav = document.getElementById('site-nav');
	if (toggle && nav) {
		toggle.addEventListener('click', function () {
			var open = nav.classList.toggle('is-open');
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
		nav.addEventListener('click', function (e) {
			if (e.target.tagName === 'A') {
				nav.classList.remove('is-open');
				toggle.setAttribute('aria-expanded', 'false');
			}
		});
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('[data-map-load]') : null;
		if (!btn) return;
		var box = btn.closest('[data-map]');
		if (!box) return;
		var iframe = document.createElement('iframe');
		iframe.src = box.getAttribute('data-map');
		iframe.loading = 'lazy';
		iframe.title = 'Mapa dojazdu do warsztatu';
		iframe.referrerPolicy = 'no-referrer-when-downgrade';
		iframe.allowFullscreen = true;
		box.innerHTML = '';
		box.appendChild(iframe);
		box.classList.add('is-loaded');
	});
})();
