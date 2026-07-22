/**
 * WP Conversion Hub client bridge.
 *
 * Reads window.wpchData ({ destinations, events }) rendered by the server, loads
 * the pixel libraries that are not already present (GA4 gtag, Meta fbq), and
 * fires each queued conversion to every enabled client destination. Privacy
 * analytics (Fathom, Umami, Plausible, PostHog) are fired through the site's own
 * already-loaded global so we never double-load or leak an extra script.
 *
 * Also exposes window.wpch.track(type, params) for custom / client-origin events.
 */
( function () {
	'use strict';

	var data = window.wpchData || { destinations: {}, events: [] };
	var dest = data.destinations || {};
	var loaded = {};

	function loadScript( src, cb ) {
		if ( loaded[ src ] ) {
			cb && cb();
			return;
		}
		loaded[ src ] = true;
		var s = document.createElement( 'script' );
		s.async = true;
		s.src = src;
		s.onload = function () {
			cb && cb();
		};
		document.head.appendChild( s );
	}

	function ensureGtag( id ) {
		window.dataLayer = window.dataLayer || [];
		if ( ! window.gtag ) {
			window.gtag = function () {
				window.dataLayer.push( arguments );
			};
			window.gtag( 'js', new Date() );
		}
		if ( id && ! loaded[ 'gtag-config-' + id ] ) {
			loaded[ 'gtag-config-' + id ] = true;
			loadScript( 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent( id ), function () {
				window.gtag( 'config', id );
			} );
		}
	}

	function ensureFbq() {
		if ( window.fbq ) {
			return;
		}
		/* Standard Meta pixel bootstrap. */
		var n = ( window.fbq = function () {
			n.callMethod ? n.callMethod.apply( n, arguments ) : n.queue.push( arguments );
		} );
		if ( ! window._fbq ) {
			window._fbq = n;
		}
		n.push = n;
		n.loaded = true;
		n.version = '2.0';
		n.queue = [];
		loadScript( 'https://connect.facebook.net/en_US/fbevents.js' );
	}

	var EVENT_NAMES = {
		ga4: {
			purchase: 'purchase',
			add_to_cart: 'add_to_cart',
			begin_checkout: 'begin_checkout',
			view_item: 'view_item',
			search: 'search',
			login: 'login',
			register: 'sign_up',
			file_download: 'file_download',
			form_submission: 'generate_lead'
		},
		meta: {
			purchase: 'Purchase',
			add_to_cart: 'AddToCart',
			begin_checkout: 'InitiateCheckout',
			view_item: 'ViewContent',
			search: 'Search',
			register: 'CompleteRegistration',
			subscribe: 'Subscribe',
			form_submission: 'Lead',
			donation: 'Donate'
		}
	};

	var handlers = {
		ga4: function ( cfg, ev ) {
			ensureGtag( cfg.measurement_id );
			var params = { send_to: cfg.measurement_id, wpch_event_id: ev.event_id };
			if ( ev.value != null ) params.value = ev.value;
			if ( ev.currency ) params.currency = ev.currency;
			if ( ev.type === 'purchase' && ev.event_id ) params.transaction_id = ev.event_id;
			if ( ev.items && ev.items.length ) params.items = ev.items;
			window.gtag( 'event', EVENT_NAMES.ga4[ ev.type ] || ev.type, params );
		},
		google_ads: function ( cfg, ev ) {
			ensureGtag( cfg.conversion_id );
			var sendTo = cfg.conversion_label ? cfg.conversion_id + '/' + cfg.conversion_label : cfg.conversion_id;
			var params = { send_to: sendTo };
			if ( ev.value != null ) params.value = ev.value;
			if ( ev.currency ) params.currency = ev.currency;
			if ( ev.event_id ) params.transaction_id = ev.event_id;
			window.gtag( 'event', 'conversion', params );
		},
		meta: function ( cfg, ev ) {
			ensureFbq();
			if ( ! handlers.meta._init && cfg.pixel_id ) {
				window.fbq( 'init', cfg.pixel_id );
				handlers.meta._init = true;
			}
			var params = {};
			if ( ev.value != null ) params.value = ev.value;
			if ( ev.currency ) params.currency = ev.currency;
			window.fbq( 'track', EVENT_NAMES.meta[ ev.type ] || 'CustomEvent', params, { eventID: ev.event_id } );
		},
		fathom: function ( cfg, ev ) {
			if ( window.fathom && typeof window.fathom.trackEvent === 'function' ) {
				var opts = ev.value != null ? { _value: Math.round( ev.value * 100 ) } : {};
				window.fathom.trackEvent( ev.type, opts );
			}
		},
		umami: function ( cfg, ev ) {
			if ( window.umami && typeof window.umami.track === 'function' ) {
				window.umami.track( ev.type, { value: ev.value, currency: ev.currency, event_id: ev.event_id } );
			}
		},
		plausible: function ( cfg, ev ) {
			if ( typeof window.plausible === 'function' ) {
				var opts = { props: { event_id: ev.event_id } };
				if ( ev.value != null ) opts.revenue = { amount: ev.value, currency: ev.currency || 'USD' };
				window.plausible( ev.type, opts );
			}
		},
		posthog: function ( cfg, ev ) {
			if ( window.posthog && typeof window.posthog.capture === 'function' ) {
				window.posthog.capture( ev.type, { value: ev.value, currency: ev.currency, event_id: ev.event_id } );
			}
		}
	};

	function fire( ev, destinations ) {
		( destinations || Object.keys( dest ) ).forEach( function ( id ) {
			if ( dest[ id ] && handlers[ id ] ) {
				try {
					handlers[ id ]( dest[ id ], ev );
				} catch ( e ) {
					/* one destination failing must not break the rest */
				}
			}
		} );
	}

	// Drain the server-queued (redirect-safe) events.
	( data.events || [] ).forEach( function ( entry ) {
		if ( entry && entry.event ) {
			fire( entry.event, entry.destinations );
		}
	} );

	// Public API for custom + client-origin events.
	window.wpch = window.wpch || {};
	window.wpch.track = function ( type, params ) {
		params = params || {};
		var ev = {
			event_id: params.event_id || 'c-' + Math.random().toString( 36 ).slice( 2, 12 ),
			type: type,
			value: params.value != null ? params.value : null,
			currency: params.currency || null,
			items: params.items || []
		};
		fire( ev, params.destinations );

		if ( data.restUrl && params.server !== false ) {
			try {
				fetch( data.restUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': data.nonce },
					body: JSON.stringify( { type: type, value: ev.value, meta: params.meta || {}, url: location.href } ),
					keepalive: true
				} );
			} catch ( e ) {}
		}
	};

	// Custom-event tracking configured via CSS selectors: dest config { selectors: [ { selector, type } ] }.
	Object.keys( dest ).forEach( function ( id ) {
		var sels = dest[ id ] && dest[ id ].selectors;
		if ( ! sels || ! sels.length ) {
			return;
		}
		sels.forEach( function ( rule ) {
			document.addEventListener( 'click', function ( e ) {
				var t = e.target.closest && e.target.closest( rule.selector );
				if ( t ) {
					window.wpch.track( rule.type || 'click', { destinations: [ id ] } );
				}
			} );
		} );
	} );
}() );
