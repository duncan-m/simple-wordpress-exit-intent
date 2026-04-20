(function () {
	'use strict';

	if (typeof window.EIP_CONFIG === 'undefined') return;

	var root = document.getElementById('eip-root');
	if (!root) return;

	var cfg = window.EIP_CONFIG;
	var modal = root.querySelector('.eip-modal');
	var COOKIE_NAME = 'eip_shown';

	var shown = false;
	var armed = false;
	var inactivityTimer = null;
	var lastScrollY = window.scrollY || window.pageYOffset || 0;
	var scrollDownSeen = false;
	var previouslyFocused = null;

	// ---------------------------------------------------------------------
	// Frequency capping
	// ---------------------------------------------------------------------

	function setCookie(name, value, days) {
		var expires = '';
		if (days) {
			var d = new Date();
			d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
			expires = '; expires=' + d.toUTCString();
		}
		document.cookie = name + '=' + value + expires + '; path=/; SameSite=Lax';
	}

	function getCookie(name) {
		var parts = ('; ' + document.cookie).split('; ' + name + '=');
		if (parts.length === 2) return parts.pop().split(';').shift();
		return null;
	}

	function canShow() {
		if (cfg.preview) return true;
		if (cfg.frequency === 'always') return true;
		if (cfg.frequency === 'session') {
			try {
				return !sessionStorage.getItem(COOKIE_NAME);
			} catch (e) {
				return !getCookie(COOKIE_NAME);
			}
		}
		return !getCookie(COOKIE_NAME);
	}

	function markShown() {
		if (cfg.preview) return;
		if (cfg.frequency === 'session') {
			try { sessionStorage.setItem(COOKIE_NAME, '1'); } catch (e) {}
			return;
		}
		var days = cfg.frequency === 'day' ? 1 : cfg.frequency === 'week' ? 7 : 0;
		if (days > 0) setCookie(COOKIE_NAME, '1', days);
	}

	// ---------------------------------------------------------------------
	// Focus trap + keyboard
	// ---------------------------------------------------------------------

	function getFocusable() {
		return root.querySelectorAll(
			'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
		);
	}

	function trapFocus(e) {
		if (e.key !== 'Tab') return;
		var focusable = getFocusable();
		if (!focusable.length) {
			e.preventDefault();
			if (modal) modal.focus();
			return;
		}
		var first = focusable[0];
		var last = focusable[focusable.length - 1];
		if (e.shiftKey && document.activeElement === first) {
			e.preventDefault();
			last.focus();
		} else if (!e.shiftKey && document.activeElement === last) {
			e.preventDefault();
			first.focus();
		}
	}

	function onKeyDown(e) {
		if (cfg.closeOnEsc && e.key === 'Escape') {
			close();
			return;
		}
		trapFocus(e);
	}

	// ---------------------------------------------------------------------
	// Show / close
	// ---------------------------------------------------------------------

	function show() {
		if (shown) return;
		if (!canShow()) return;
		shown = true;
		previouslyFocused = document.activeElement;
		root.classList.add('eip-open');
		root.setAttribute('aria-hidden', 'false');
		document.body.classList.add('eip-body-open');
		if (modal) {
			try { modal.focus(); } catch (e) {}
		}
		document.addEventListener('keydown', onKeyDown);
		markShown();
		detachTriggers();
	}

	function close() {
		if (!shown) return;
		shown = false;
		root.classList.remove('eip-open');
		root.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('eip-body-open');
		document.removeEventListener('keydown', onKeyDown);
		if (previouslyFocused && typeof previouslyFocused.focus === 'function') {
			try { previouslyFocused.focus(); } catch (e) {}
		}
	}

	root.addEventListener('click', function (e) {
		var closer = e.target && e.target.closest ? e.target.closest('[data-eip-close]') : null;
		if (!closer) return;
		if (closer.classList.contains('eip-overlay') && !cfg.closeOnOverlay) return;
		close();
	});

	// ---------------------------------------------------------------------
	// Triggers
	// ---------------------------------------------------------------------

	function onMouseLeave(e) {
		// Only top edge, and only a real exit (not child-element transitions)
		if (e.clientY <= 5 && (!e.relatedTarget && !e.toElement)) {
			show();
		}
	}

	function onScroll() {
		var y = window.scrollY || window.pageYOffset || 0;
		if (y > lastScrollY + 50) scrollDownSeen = true;
		// After a real scroll-down, an upward movement of >80px while
		// near the top of the page is a strong exit signal on mobile.
		if (scrollDownSeen && y < lastScrollY - 80 && y < 400) {
			show();
		}
		lastScrollY = y;
	}

	function armBackButton() {
		try {
			history.pushState({ eip: 1 }, '', location.href);
		} catch (e) { return; }
		window.addEventListener('popstate', function () {
			if (!shown) {
				// Re-push so the next back press can still leave
				try { history.pushState({ eip: 1 }, '', location.href); } catch (e) {}
				show();
			}
		});
	}

	function resetInactivity() {
		if (inactivityTimer) clearTimeout(inactivityTimer);
		inactivityTimer = setTimeout(show, cfg.triggers.inactivitySeconds * 1000);
	}

	var timedTimer = null;

	function attachTriggers() {
		if (armed) return;
		armed = true;

		var isTouch = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
		var isNarrow = window.innerWidth < 768;
		var isMobile = isTouch || isNarrow;

		if (cfg.triggers.desktop && !isMobile) {
			document.addEventListener('mouseleave', onMouseLeave);
		}
		if (cfg.triggers.scrollUp && isMobile) {
			lastScrollY = window.scrollY || window.pageYOffset || 0;
			window.addEventListener('scroll', onScroll, { passive: true });
		}
		if (cfg.triggers.backButton && isMobile) {
			armBackButton();
		}
		if (cfg.triggers.inactivity) {
			['click', 'keydown', 'scroll', 'touchstart', 'mousemove'].forEach(function (ev) {
				window.addEventListener(ev, resetInactivity, { passive: true });
			});
			resetInactivity();
		}
		if (cfg.triggers.timed) {
			timedTimer = setTimeout(show, cfg.triggers.timedSeconds * 1000);
		}
	}

	function detachTriggers() {
		document.removeEventListener('mouseleave', onMouseLeave);
		window.removeEventListener('scroll', onScroll);
		if (inactivityTimer) { clearTimeout(inactivityTimer); inactivityTimer = null; }
		if (timedTimer) { clearTimeout(timedTimer); timedTimer = null; }
	}

	// ---------------------------------------------------------------------
	// Boot
	// ---------------------------------------------------------------------

	if (cfg.preview) {
		setTimeout(show, 300);
		return;
	}

	if (canShow()) {
		var delay = Math.max(0, (cfg.initialDelay || 0) * 1000);
		setTimeout(attachTriggers, delay);
	}
})();
