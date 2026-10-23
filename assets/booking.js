/* Kalendarz rezerwacji wizyt. Konfiguracja w window.WM_BOOKING (z PHP). */
(function () {
	'use strict';

	var cfg = window.WM_BOOKING;
	var form = document.getElementById('booking-form');
	if (!cfg || !form) return;

	var started = Date.now();
	var DAYS = ['nd', 'pn', 'wt', 'śr', 'cz', 'pt', 'sb'];
	var DAYS_LONG = ['niedziela', 'poniedziałek', 'wtorek', 'środa', 'czwartek', 'piątek', 'sobota'];
	var MONTHS = ['sty', 'lut', 'mar', 'kwi', 'maj', 'cze', 'lip', 'sie', 'wrz', 'paź', 'lis', 'gru'];
	var MONTHS_GEN = ['stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca', 'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia'];

	var el = {
		days: form.querySelector('[data-days]'),
		slots: form.querySelector('[data-slots]'),
		hint: form.querySelector('[data-slots-hint]'),
		summary: form.querySelector('[data-summary]'),
		msg: form.querySelector('[data-msg]'),
		submit: form.querySelector('[data-submit]'),
		date: form.querySelector('input[name=date]'),
		time: form.querySelector('input[name=time]'),
		service: form.querySelector('[name=service]')
	};
	var loadSeq = 0;

	function api(path, params) {
		var base = cfg.restUrl + path;
		var qs = [];
		for (var k in params) {
			if (Object.prototype.hasOwnProperty.call(params, k)) qs.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]));
		}
		return qs.length ? base + (base.indexOf('?') === -1 ? '?' : '&') + qs.join('&') : base;
	}

	function pad(n) { return (n < 10 ? '0' : '') + n; }

	/* Dzisiejsza data w Polsce (niezależnie od strefy czasowej urządzenia). */
	function todayWarsaw() {
		try {
			var s = new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Warsaw', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
			var p = s.split('-');
			if (p.length === 3) return Date.UTC(+p[0], +p[1] - 1, +p[2]);
		} catch (e) { /* stare przeglądarki */ }
		var d = new Date();
		return Date.UTC(d.getFullYear(), d.getMonth(), d.getDate());
	}

	function ymdOf(d) { return d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate()); }

	function humanDate(ymd) {
		var p = ymd.split('-');
		var d = new Date(Date.UTC(+p[0], +p[1] - 1, +p[2]));
		return DAYS_LONG[d.getUTCDay()] + ', ' + d.getUTCDate() + ' ' + MONTHS_GEN[d.getUTCMonth()];
	}

	function showMsg(text, ok) {
		el.msg.hidden = !text;
		el.msg.textContent = text || '';
		el.msg.className = 'booking__msg ' + (ok ? 'booking__msg--ok' : 'booking__msg--err');
	}

	function updateSummary() {
		if (el.date.value && el.time.value) {
			el.summary.hidden = false;
			el.summary.textContent = '📅 ' + humanDate(el.date.value) + ', godz. ' + el.time.value + (el.service.value ? ' · ' + el.service.value : '');
		} else {
			el.summary.hidden = true;
		}
	}

	function buildDays() {
		var start = todayWarsaw();
		var first = null;
		el.days.innerHTML = '';
		for (var i = 0; i <= cfg.daysAhead; i++) {
			var d = new Date(start + i * 86400000);
			var ymd = ymdOf(d);
			var iso = d.getUTCDay() === 0 ? 7 : d.getUTCDay();
			var open = !!cfg.hours[iso] && cfg.closed.indexOf(ymd) === -1;
			if (!open) continue;
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'booking__day';
			b.setAttribute('data-date', ymd);
			b.setAttribute('aria-pressed', 'false');
			b.setAttribute('aria-label', humanDate(ymd));
			b.innerHTML = '<small>' + (i === 0 ? 'dziś' : i === 1 ? 'jutro' : DAYS[d.getUTCDay()]) + '</small><b>' + d.getUTCDate() + '</b><small>' + MONTHS[d.getUTCMonth()] + '</small>';
			el.days.appendChild(b);
			if (!first) first = b;
		}
		if (!first) {
			el.hint.textContent = 'Brak dostępnych terminów online. Zadzwoń do nas, proszę.';
			return;
		}
		selectDay(first);
	}

	function selectDay(btn) {
		var all = el.days.querySelectorAll('.booking__day');
		for (var i = 0; i < all.length; i++) {
			all[i].classList.remove('is-active');
			all[i].setAttribute('aria-pressed', 'false');
		}
		btn.classList.add('is-active');
		btn.setAttribute('aria-pressed', 'true');
		el.date.value = btn.getAttribute('data-date');
		el.time.value = '';
		updateSummary();
		loadSlots(el.date.value);
	}

	function loadSlots(ymd, keepMsg) {
		var seq = ++loadSeq;
		el.slots.innerHTML = '';
		el.hint.hidden = false;
		el.hint.textContent = 'Sprawdzam wolne godziny…';
		if (!keepMsg) showMsg('');

		fetch(api('slots', { date: ymd, _: Date.now() }), { credentials: 'same-origin', cache: 'no-store' })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (seq !== loadSeq) return;
				var slots = (data && data.slots) || [];
				var free = 0;
				slots.forEach(function (s) {
					var b = document.createElement('button');
					b.type = 'button';
					b.className = 'booking__slot';
					b.textContent = s.time;
					b.setAttribute('data-time', s.time);
					b.setAttribute('aria-pressed', 'false');
					if (!s.free) {
						b.disabled = true;
						b.title = 'Zajęte';
					} else {
						free++;
					}
					el.slots.appendChild(b);
				});
				if (!free) {
					el.hint.textContent = (data && data.message) || 'Brak wolnych godzin tego dnia – wybierz inny dzień.';
				} else {
					el.hint.hidden = true;
				}
			})
			.catch(function () {
				if (seq !== loadSeq) return;
				el.hint.textContent = 'Nie udało się pobrać terminów. Odśwież stronę lub zadzwoń.';
			});
	}

	el.days.addEventListener('click', function (e) {
		var btn = e.target.closest('.booking__day');
		if (btn && !btn.disabled) selectDay(btn);
	});

	el.slots.addEventListener('click', function (e) {
		var btn = e.target.closest('.booking__slot');
		if (!btn || btn.disabled) return;
		var all = el.slots.querySelectorAll('.booking__slot');
		for (var i = 0; i < all.length; i++) {
			all[i].classList.remove('is-active');
			all[i].setAttribute('aria-pressed', 'false');
		}
		btn.classList.add('is-active');
		btn.setAttribute('aria-pressed', 'true');
		el.time.value = btn.getAttribute('data-time');
		updateSummary();
		var name = form.querySelector('[name=name]');
		if (name && !name.value) name.focus({ preventScroll: false });
	});

	el.service.addEventListener('change', updateSummary);

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		var f = form.elements;
		var err = null;
		if (!f.service.value) err = 'Wybierz usługę.';
		else if (!el.date.value) err = 'Wybierz dzień wizyty.';
		else if (!el.time.value) err = 'Wybierz godzinę wizyty.';
		else if (f.name.value.trim().length < 2) err = 'Podaj imię i nazwisko.';
		else if (f.phone.value.replace(/\D/g, '').length < 9) err = 'Podaj poprawny numer telefonu.';
		else if (f.email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email.value)) err = 'Adres e-mail jest nieprawidłowy.';
		else if (f.car.value.trim().length < 2) err = 'Podaj markę i model samochodu.';
		else if (!f.consent.checked) err = 'Zaznacz zgodę na przetwarzanie danych.';
		if (err) {
			showMsg(err, false);
			return;
		}

		var payload = {
			service: f.service.value,
			date: el.date.value,
			time: el.time.value,
			name: f.name.value,
			phone: f.phone.value,
			email: f.email.value,
			car: f.car.value,
			plate: f.plate.value,
			message: f.message.value,
			consent: f.consent.checked ? 1 : 0,
			website: f.website.value,
			elapsed: Date.now() - started
		};

		el.submit.disabled = true;
		el.submit.textContent = 'Wysyłam…';
		showMsg('');

		fetch(api('book', {}), {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(payload)
		})
			.then(function (r) {
				return r.json().then(function (data) { return { status: r.status, data: data }; });
			})
			.then(function (res) {
				if (res.data && res.data.ok) {
					var steps = form.querySelectorAll('.booking__step');
					for (var i = 0; i < steps.length; i++) steps[i].hidden = true;
					el.submit.hidden = true;
					showMsg('✅ ' + res.data.message, true);
					form.scrollIntoView({ behavior: 'smooth', block: 'start' });
					return;
				}
				showMsg((res.data && res.data.message) || 'Coś poszło nie tak. Spróbuj ponownie lub zadzwoń.', false);
				if (res.status === 409) {
					el.time.value = '';
					updateSummary();
					loadSlots(el.date.value, true);
				}
			})
			.catch(function () {
				showMsg('Brak połączenia z serwerem. Spróbuj ponownie lub zadzwoń.', false);
			})
			.then(function () {
				el.submit.disabled = false;
				el.submit.textContent = 'Zarezerwuj wizytę';
			});
	});

	buildDays();
})();
