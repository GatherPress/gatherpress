/**
 * Helper functions for the venue-v2 block.
 *
 * @since 1.0.0
 */

/**
 * Calculate event mode from venue terms.
 *
 * Determines whether an event is in-person, online, or hybrid based on
 * the presence of venue terms and the special 'online-event' term.
 *
 * @since 1.0.0
 *
 * @param {Array} terms Array of venue term objects.
 * @return {string} Mode: 'in-person', 'online', or 'hybrid'.
 */
export function calculateMode( terms ) {
	if ( ! terms || ! terms.length ) {
		return 'in-person';
	}

	const hasOnline = terms.some( ( term ) => 'online-event' === term.slug );
	const hasVenue = terms.some( ( term ) => 'online-event' !== term.slug );

	if ( hasVenue && hasOnline ) {
		return 'hybrid';
	}
	if ( hasOnline ) {
		return 'online';
	}
	return 'in-person';
}

/**
 * Get new taxonomy IDs based on mode change.
 *
 * Calculates which taxonomy term IDs should be assigned to an event
 * when switching between in-person, online, and hybrid modes.
 *
 * @since 1.0.0
 *
 * @param {string} newMode            New mode to switch to ('in-person', 'online', or 'hybrid').
 * @param {number} onlineEventTermId  The term ID for the 'online-event' term.
 * @param {number} currentVenueTermId Current venue term ID (excluding online-event).
 * @return {Array} Array of taxonomy term IDs to assign.
 */
export function getNewTaxonomyIds( newMode, onlineEventTermId, currentVenueTermId ) {
	if ( 'in-person' === newMode ) {
		return currentVenueTermId ? [ currentVenueTermId ] : [];
	}

	if ( 'online' === newMode ) {
		return onlineEventTermId ? [ onlineEventTermId ] : [];
	}

	// Hybrid mode - combine both.
	const ids = [];
	if ( currentVenueTermId ) {
		ids.push( currentVenueTermId );
	}
	if ( onlineEventTermId ) {
		ids.push( onlineEventTermId );
	}
	return ids;
}
