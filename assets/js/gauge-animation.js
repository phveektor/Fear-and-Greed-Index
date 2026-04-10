/* Gauge animation (vanilla JS) - Cache/Minify Safe */
(function(){
	'use strict';

	// Cache-safe: Store initial state to prevent reflow
	var initialized = false;

	// Simple debounce
	function debounce(fn, wait){
		var t;
		return function(){
			var ctx = this, args = arguments;
			clearTimeout(t);
			t = setTimeout(function(){ fn.apply(ctx,args); }, wait);
		};
	}

	// Rotate needle smoothly using requestAnimationFrame
	function animateNeedle(needleEl, fromDeg, toDeg){
		if (!needleEl) return;

		// CRITICAL FIX: Set initial transform immediately to prevent "jump" from default SVG state.
		// needleEl.setAttribute('transform', 'rotate(' + fromDeg + ' 100 95)');
		needleEl.style.transform = 'rotate(' + fromDeg + 'deg)';


		var start = null;
		var duration = 800; // ms
		
		function step(timestamp){
			if (!start) start = timestamp;
			var progress = Math.min((timestamp - start) / duration, 1);
			var current = fromDeg + (toDeg - fromDeg) * (1 - Math.pow(1 - progress, 3));
			
			needleEl.style.transform = 'rotate(' + current + 'deg)';
			
			if (progress < 1) requestAnimationFrame(step);
		}
		requestAnimationFrame(step);
	}

	// Counting animation for number
	function countTo(el, from, to, duration){
		if (!el) return;
		var start = null;
		function step(timestamp){
			if (!start) start = timestamp;
			var progress = Math.min((timestamp - start) / duration, 1);
			var current = Math.round(from + (to - from) * progress);
			el.textContent = current;
			if (progress < 1) requestAnimationFrame(step);
		}
		requestAnimationFrame(step);
	}

	function initGauge(root){
		if (!root) return;
		// Prevent double-initialization (cache compatibility)
		if (root.getAttribute('data-fgg-initialized') === 'true') return;
		root.setAttribute('data-fgg-initialized', 'true');

	// needle group for SVG or element for legacy markup
	var needle = root.querySelector('.fgg-needle-group') || root.querySelector('.fgg-needle');
	// Prefer .fgg-value but fallback to .fgg-value-visible if themes or older templates hide it
	var valueEl = root.querySelector('.fgg-value');
	if (!valueEl) valueEl = root.querySelector('.fgg-value-visible');
		var changeEl = root.querySelector('.fgg-change');
		var bars = root.querySelectorAll('.fgg-mini-chart .fgg-bar');

		var value = parseInt(root.getAttribute('data-value') || '0', 10);
		var prev = parseInt(root.getAttribute('data-prev') || value, 10);

		// Calculate degrees: (value - 50) * 1.8
		var toDeg = (value - 50) * 1.8;
		var fromDeg = (prev - 50) * 1.8;

	animateNeedle(needle, fromDeg, toDeg);
		// Only animate visible numeric value if the element exists and is not hidden
		if ( valueEl && window.getComputedStyle(valueEl).display !== 'none' ) {
			countTo(valueEl, prev, value, 800);
		}

		// change indicator: normalize and display with classes
		if (changeEl){
			var changeRaw = root.getAttribute('data-change');
			var change = 0;
			if (changeRaw !== null && changeRaw !== undefined && changeRaw !== '') {
				change = parseFloat(changeRaw) || 0;
			}
			changeEl.classList.remove('up','down');
			if (change > 0) changeEl.classList.add('up');
			else if (change < 0) changeEl.classList.add('down');
			changeEl.innerHTML = (change > 0 ? '▲ ' : change < 0 ? '▼ ' : '') + Math.abs(change) + '%';
		}

		// animate bars staggered
		if (bars && bars.length){
			bars.forEach(function(b,i){
				var h = parseInt(b.getAttribute('data-height') || '10', 10);
				setTimeout(function(){ b.style.height = h + 'px'; }, i * 80);
			});
		}
	}

	// Intersection Observer for lazy init
	function observeGauges(){
		var widgets = document.querySelectorAll('.fgg-widget');
		if (!widgets || !widgets.length) return;

		var onIntersect = function(entries, obs){
			entries.forEach(function(entry){
				if (entry.isIntersecting){
					initGauge(entry.target);
					obs.unobserve(entry.target);
				}
			});
		};

		var io = new IntersectionObserver(onIntersect, {root: null, threshold: 0.2});
		widgets.forEach(function(w){ io.observe(w); });
	}

	// Debounced resize handler - REMOVED as it was causing re-initialization on layout shifts.
	// The gauge is already responsive via SVG/CSS, so this is not needed.
	/*
	var onResize = debounce(function(){
		document.querySelectorAll('.fgg-widget').forEach(function(w){
			if (w.getBoundingClientRect().top < window.innerHeight && w.getBoundingClientRect().bottom > 0){
				initGauge(w);
			}
		});
	}, 250);
	*/

	// Auto init on DOM ready
	if (document.readyState === 'loading'){
		document.addEventListener('DOMContentLoaded', observeGauges);
	} else {
		observeGauges();
	}

})();