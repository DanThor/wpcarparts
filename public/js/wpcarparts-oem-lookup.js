(function () {
	'use strict';

	if (typeof wpcarpartsOemLookup === 'undefined') {
		return;
	}

	var config = wpcarpartsOemLookup;
	var MARKUP = config.markupMultiplier || 1.6;

	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	function retailPrice(priceExclVat) {
		var price = parseFloat(priceExclVat);
		if (isNaN(price) || price <= 0) {
			return null;
		}
		var retail = Math.floor(price * MARKUP);
		if (config.isB2b) {
			return Math.floor(retail * 0.8 * 0.85);
		}
		return retail;
	}

	function priceSuffix() {
		if (config.isB2b) {
			return config.i18n.priceSuffixExcl;
		}
		return config.i18n.priceSuffixIncl;
	}

	function formatPrice(priceExclVat) {
		var retail = retailPrice(priceExclVat);
		if (retail === null) {
			return '';
		}
		return retail + priceSuffix();
	}

	function formatKm(kilometrage) {
		if (kilometrage === null || kilometrage === undefined || kilometrage === '') {
			return null;
		}
		var km = parseInt(kilometrage, 10);
		if (isNaN(km)) {
			return null;
		}
		return km.toLocaleString('nb-NO') + ' km';
	}

	function buildLookupUrl(oePartNumber, manufacturer) {
		var url = new URL(config.restUrl);
		url.searchParams.set('oePartNumber', oePartNumber);
		url.searchParams.set('manufacturer', manufacturer);
		return url.toString();
	}

	function isNotFoundResponse(status, body) {
		if (status === 404) {
			return true;
		}
		if (!body || typeof body !== 'object') {
			return false;
		}
		if (body.found === false || body.code === 'NOT_FOUND') {
			return true;
		}
		var data = body.data;
		if (data && data.code === 'NOT_FOUND') {
			return true;
		}
		return false;
	}

	function hideSlot(slot) {
		slot.innerHTML = '';
		slot.classList.add('oem-third-party-slot--hidden');
		slot.setAttribute('aria-hidden', 'true');
	}

	function collectListings(data) {
		if (!data || typeof data !== 'object') {
			return [];
		}

		var listings = [];
		var main = {};
		var skipKeys = { alternatives: true, matchCount: true, supplier: true, sourceSupplier: true };

		Object.keys(data).forEach(function (key) {
			if (!skipKeys[key]) {
				main[key] = data[key];
			}
		});

		if (main.priceExclVat !== undefined && main.priceExclVat !== null) {
			listings.push(main);
		}

		if (Array.isArray(data.alternatives)) {
			data.alternatives.forEach(function (alt) {
				if (alt && alt.priceExclVat !== undefined && alt.priceExclVat !== null) {
					listings.push(alt);
				}
			});
		}

		return listings;
	}

	function imageUrl(item) {
		if (item.image) {
			return item.image;
		}
		return config.placeholderImage || '';
	}

	function buildCartForm(item) {
		if (!config.cart || !config.cart.canAddToCart || !config.cart.addToCartUrl) {
			return '';
		}

		var stockNumber = item.stockNumber || '';
		if (!stockNumber) {
			return '';
		}

		var kmValue =
			item.kilometrage !== null && item.kilometrage !== undefined
				? String(item.kilometrage)
				: '';

		return (
			'<form action="' +
			escapeHtml(config.cart.addToCartUrl) +
			'" class="partshub-cart oem-cart" method="post" enctype="multipart/form-data">' +
			'<input type="hidden" name="add-to-cart" value="' +
			escapeHtml(String(config.cart.productId)) +
			'" />' +
			'<input type="hidden" name="partshub_stock_number" value="' +
			escapeHtml(stockNumber) +
			'" />' +
			'<input type="hidden" name="partshub_price_excl_vat" value="' +
			escapeHtml(String(item.priceExclVat)) +
			'" />' +
			'<input type="hidden" name="partshub_model" value="' +
			escapeHtml(item.model || '') +
			'" />' +
			'<input type="hidden" name="partshub_year" value="' +
			escapeHtml(item.year !== null && item.year !== undefined ? String(item.year) : '') +
			'" />' +
			'<input type="hidden" name="partshub_kilometrage" value="' +
			escapeHtml(kmValue) +
			'" />' +
			'<button type="submit" class="button alt partshub-cart__button">' +
			escapeHtml(config.cart.partshubAddToCartText || 'Legg brukt del i handlekurv') +
			'</button></form>'
		);
	}

	function renderListingCard(item) {
		var imgSrc = imageUrl(item);
		var km = formatKm(item.kilometrage);
		var price = formatPrice(item.priceExclVat);
		var fields = '';

		if (price) {
			fields +=
				'<p class="partshub-card__price"><span class="partshub-card__value">' +
				escapeHtml(price) +
				'</span></p>';
		}

		if (item.model) {
			fields +=
				'<p class="partshub-card__field"><span class="partshub-card__label">' +
				escapeHtml(config.i18n.model) +
				':</span> <span class="partshub-card__value">' +
				escapeHtml(item.model) +
				'</span></p>';
		}

		if (item.year !== null && item.year !== undefined && item.year !== '') {
			fields +=
				'<p class="partshub-card__field"><span class="partshub-card__label">' +
				escapeHtml(config.i18n.year) +
				':</span> <span class="partshub-card__value">' +
				escapeHtml(String(item.year)) +
				'</span></p>';
		}

		if (km) {
			fields +=
				'<p class="partshub-card__field"><span class="partshub-card__label">' +
				escapeHtml(config.i18n.kmStand) +
				':</span> <span class="partshub-card__value">' +
				escapeHtml(km) +
				'</span></p>';
		}

		return (
			'<article class="partshub-card">' +
			'<div class="partshub-card__media">' +
			'<img src="' +
			escapeHtml(imgSrc) +
			'" alt="" width="120" height="120" loading="lazy" />' +
			'</div>' +
			'<div class="partshub-card__body">' +
			fields +
			'</div>' +
			'<div class="partshub-card__actions">' +
			buildCartForm(item) +
			'</div></article>'
		);
	}

	function renderPayload(container, payload) {
		var data = payload && payload.data !== undefined ? payload.data : payload;

		if (!data || typeof data !== 'object') {
			hideSlot(container);
			return;
		}

		var listings = collectListings(data);
		if (listings.length === 0) {
			hideSlot(container);
			return;
		}

		var html =
			'<div class="partshub-results">' +
			'<h4 class="partshub-results__heading">' +
			escapeHtml(config.i18n.heading) +
			'</h4>' +
			'<div class="partshub-results__grid">';

		listings.forEach(function (item) {
			html += renderListingCard(item);
		});

		html += '</div></div>';
		container.innerHTML = html;
		container.classList.remove('oem-third-party-slot--hidden');
		container.removeAttribute('aria-hidden');
	}

	function fetchLookup(row) {
		var slot = row.querySelector('.oem-third-party-slot');
		if (!slot) {
			return;
		}

		var oePartNumber = row.getAttribute('data-oe-part-number') || '';
		var manufacturer = row.getAttribute('data-manufacturer') || '';

		if (!oePartNumber || !manufacturer) {
			hideSlot(slot);
			return;
		}

		slot.innerHTML =
			'<span class="oem-third-party-loader">' + escapeHtml(config.i18n.loading) + '</span>';
		slot.classList.remove('oem-third-party-slot--hidden');
		slot.removeAttribute('aria-hidden');

		fetch(buildLookupUrl(oePartNumber, manufacturer), {
			method: 'GET',
			credentials: 'same-origin',
			headers: {
				Accept: 'application/json',
				'X-WP-Nonce': config.nonce,
			},
		})
			.then(function (response) {
				return response.json().then(function (body) {
					return { ok: response.ok, status: response.status, body: body };
				});
			})
			.then(function (result) {
				if (isNotFoundResponse(result.status, result.body)) {
					hideSlot(slot);
					return;
				}

				if (!result.ok) {
					slot.innerHTML =
						'<p class="oem-third-party-error">' + escapeHtml(config.i18n.error) + '</p>';
					return;
				}

				renderPayload(slot, result.body);
			})
			.catch(function () {
				slot.innerHTML =
					'<p class="oem-third-party-error">' + escapeHtml(config.i18n.error) + '</p>';
			});
	}

	function init() {
		var rows = document.querySelectorAll(
			'.wpcarparts-oem-results .oem-product[data-oe-part-number]'
		);
		rows.forEach(fetchLookup);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
