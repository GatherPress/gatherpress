/**
 * Broadcasts custom events based on the provided payload, optionally appending an identifier to each event type.
 *
 * @since 1.0.0
 *
 * @param {Object} payload    - An object containing data to be dispatched with custom events.
 * @param {string} identifier - An optional identifier to append to each event type.
 *
 * @return {void}
 */
export const Broadcaster = ( payload, identifier = '' ) => {
	for ( const [ key, value ] of Object.entries( payload ) ) {
		let type = key;

		if ( identifier ) {
			type += '_' + String( identifier );
		}

		const dispatcher = new CustomEvent( type, {
			detail: value,
		} );

		dispatchEvent( dispatcher );
	}
};

/**
 * Sets up event listeners for custom events based on the provided payload, optionally appending an identifier to each event type.
 * When an event is triggered, the corresponding listener callback is executed with the event detail.
 *
 * @since 1.0.0
 *
 * @param {Object} payload    - An object specifying event types and their corresponding listener callbacks.
 * @param {string} identifier - An optional identifier to append to each event type.
 *
 * @return {void}
 */
export const Listener = ( payload, identifier = '' ) => {
	for ( const [ key, value ] of Object.entries( payload ) ) {
		let type = key;

		if ( identifier ) {
			type += '_' + String( identifier );
		}

		addEventListener(
			type,
			( e ) => {
				value( e.detail );
			},
			false,
		);
	}
};
