/**
 * Timezone shim for `wp.date` on fresh WordPress installs.
 *
 * WordPress emits `"UTC+0"` / `"UTC-0"` as the default timezone string when
 * Settings → General is left at its defaults. Moment Timezone can't resolve
 * those strings and spams `"Moment Timezone has no data for UTC+0"` warnings
 * throughout the block editor. This shim runs immediately after
 * `wp.date.setSettings(...)` and rewrites the string to the IANA-valid `"UTC"`
 * so downstream consumers (Gutenberg, GatherPress, other plugins) don't log
 * the warning.
 *
 * Non-zero UTC offsets are left untouched — operators with those configured
 * should pick an IANA zone in Settings → General.
 */
( function () {
	if ( ! window.wp || ! window.wp.date ) {
		return;
	}

	if ( 'function' !== typeof window.wp.date.getSettings ) {
		return;
	}

	var settings = window.wp.date.getSettings();

	if ( ! settings || ! settings.timezone || ! settings.timezone.string ) {
		return;
	}

	if ( /^UTC[+-]0$/.test( settings.timezone.string ) ) {
		settings.timezone.string = 'UTC';
		window.wp.date.setSettings( settings );
	}
} )();
