/**
 * Helper functions for the venue block.
 *
 * @since 0.34.0
 */

/**
 * Calculate event mode from venue terms.
 *
 * Determines whether an event is in-person, online, or hybrid based on
 * the presence of venue terms and the online-event sentinel term
 * (matched by pre-resolved ID rather than slug string).
 *
 * @since 0.34.0
 *
 * @param {Array} terms             Array of venue term objects.
 * @param {number|null} onlineEventTermId Pre-resolved term ID of the online-event
 *                                         sentinel for this venue taxonomy.
 *
 * @return {string} Mode: 'in-person', 'online', or 'hybrid'.
 */
export function calculateMode( terms, onlineEventTermId ) {
	if ( ! terms?.length ) {
		return 'in-person';
	}

	const sentinel = Number( onlineEventTermId ) || null;
	const matchesOnline = ( term ) =>
		null !== sentinel && Number( term.id ) === sentinel;
	const matchesVenue = ( term ) =>
		null === sentinel || Number( term.id ) !== sentinel;
	const hasOnline = terms.some( matchesOnline );
	const hasVenue = terms.some( matchesVenue );

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
 * @since 0.34.0
 *
 * @param {string} newMode            New mode to switch to ('in-person', 'online', or 'hybrid').
 * @param {number} onlineEventTermId  The term ID for the 'online-event' term.
 * @param {number} currentVenueTermId Current venue term ID (excluding online-event).
 *
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
