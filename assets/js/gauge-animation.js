/* Fear & Greed Gauge — Animation Engine (vanilla JS) */
(function () {
	'use strict';

	/**
	 * Easing: ease-out cubic
	 */
	function easeOutCubic(t) {
		return 1 - Math.pow(1 - t, 3);
	}

	/**
	 * Animate a numeric counter from `from` to `to`.
	 */
	function countTo(el, from, to, duration) {
		if (!el) return;
		var start = null;
		function step(ts) {
			if (!start) start = ts;
			var p = Math.min((ts - start) / duration, 1);
			el.textContent = Math.round(from + (to - from) * easeOutCubic(p));
			if (p < 1) requestAnimationFrame(step);
		}
		requestAnimationFrame(step);
	}

	/**
	 * Convert a 0-100 gauge value to rotation degrees.
	 *   0   → -90° (far left)
	 *   50  →   0° (straight up)
	 *   100 → +90° (far right)
	 */
	function valueToDeg(v) {
		return (v - 50) * 1.8;
	}

	/**
	 * Apply a dynamic glow class to the SVG arc based on value.
	 */
	function applyGlow(root, value) {
		var arc = root.querySelector('.fgg-arc-track');
		if (!arc) return;
		arc.classList.remove('fgg-glow-fear', 'fgg-glow-neutral', 'fgg-glow-greed');
		if (value <= 35)      arc.classList.add('fgg-glow-fear');
		else if (value <= 65) arc.classList.add('fgg-glow-neutral');
		else                  arc.classList.add('fgg-glow-greed');
	}

	/**
	 * Initialise a single gauge widget.
	 */
	function initGauge(root) {
		if (!root) return;
		// Guard against double-init (e.g. Intersection Observer firing twice)
		if (root.getAttribute('data-fgg-initialized') === 'true') return;
		root.setAttribute('data-fgg-initialized', 'true');

		var needle   = root.querySelector('.fgg-pointer-group');
		var valueEl  = root.querySelector('.fgg-value-visible');
		var changeEl = root.querySelector('.fgg-change');

		var value = parseInt(root.getAttribute('data-value') || '0', 10);
		var prev  = parseInt(root.getAttribute('data-prev')  || value, 10);

		// ── Needle ────────────────────────────────────────────────────
		if (needle) {
			var fromDeg = valueToDeg(prev);
			var toDeg   = valueToDeg(value);

			/*
			 * Step 1: Snap needle to yesterday's position with NO transition.
			 *         This prevents the needle from always animating from center.
			 */
			needle.style.transition = 'none';
			needle.style.transform  = 'rotate(' + fromDeg + 'deg)';

			/*
			 * Step 2: Force a reflow so the browser registers the initial state
			 *         before we re-enable the transition.
			 */
			void needle.getBoundingClientRect();

			/*
			 * Step 3: Animate smoothly to today's value.
			 */
			needle.style.transition = 'transform 1.4s cubic-bezier(0.34, 1.4, 0.64, 1)';
			needle.style.transform  = 'rotate(' + toDeg + 'deg)';
		}

		// ── Numeric counter ───────────────────────────────────────────
		if (valueEl) {
			countTo(valueEl, prev, value, 1400);
		}

		// ── Change badge ──────────────────────────────────────────────
		if (changeEl) {
			var rawChange = parseFloat(root.getAttribute('data-change') || '0');
			changeEl.classList.remove('up', 'down');
			if (rawChange > 0) {
				changeEl.classList.add('up');
				changeEl.textContent = '▲ ' + rawChange.toFixed(2) + '%';
			} else if (rawChange < 0) {
				changeEl.classList.add('down');
				changeEl.textContent = '▼ ' + Math.abs(rawChange).toFixed(2) + '%';
			} else {
				changeEl.textContent = '–';
			}
		}

		// ── Dynamic glow on the arc track ─────────────────────────────
		applyGlow(root, value);

		// ── Staggered bar animations (chart) ─────────────────────────
		var bars = root.querySelectorAll('.fgg-svg-bar');
		if (bars.length) {
			bars.forEach(function (bar, i) {
				var originalH = bar.getAttribute('height');
				if (!originalH) return;
				bar.setAttribute('height', '0');
				bar.setAttribute('y', parseFloat(bar.getAttribute('y') || 0) + parseFloat(originalH));
				setTimeout(function () {
					bar.style.transition = 'height 0.5s ' + easeOutCubic(i / bars.length) + 's ease';
					bar.setAttribute('height', originalH);
					bar.setAttribute('y', bar.getAttribute('y') - parseFloat(originalH));
				}, i * 60 + 200);
			});
		}
	}

	/**
	 * Attach IntersectionObserver so gauges only animate when in view.
	 */
	function observeGauges() {
		var widgets = document.querySelectorAll('.fgg-widget');
		if (!widgets || !widgets.length) return;

		if (!window.IntersectionObserver) {
			// Fallback for very old browsers
			widgets.forEach(initGauge);
			return;
		}

		var io = new IntersectionObserver(function (entries, obs) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					initGauge(entry.target);
					obs.unobserve(entry.target);
				}
			});
		}, { threshold: 0.15 });

		widgets.forEach(function (w) { io.observe(w); });
	}

	// Boot
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', observeGauges);
	} else {
		observeGauges();
	}

})();