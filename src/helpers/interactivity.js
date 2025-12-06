/**
 * Internal dependencies.
 */
import { getFromGlobal } from './globals';

/**
 * Initializes the post context within the application state.
 *
 * This function ensures that the given `postId` has an entry in the `state.posts` object.
 * If no entry exists, it creates one using the `eventDetails` global, which provides
 * initial data for event responses, the current user's RSVP status, and other RSVP-related details.
 *
 * @since 1.0.0
 *
 * @param {Object} state  - The application state object to be updated.
 *                        Should contain a `posts` property.
 * @param {number} postId - The ID of the post to initialize in the state.
 *
 * @return {void}
 *
 * @example
 * const appState = { posts: {} };
 * const postId = 123;
 *
 * initPostContext(appState, postId);
 *
 * console.log(appState.posts[postId]);
 * // Output:
 * // {
 * //   eventResponses: {
 * //     attending: 10,
 * //     waitingList: 2,
 * //     notAttending: 5,
 * //   },
 * //   currentUser: {
 * //     status: 'attending',
 * //     guests: 1,
 * //     anonymous: false,
 * //   },
 * //   rsvpSelection: 'attending',
 * // }
 */
export function initPostContext( state, postId ) {
	state.posts = state.posts ?? [];

	if ( postId && ! state.posts[ postId ] ) {
		state.posts[ postId ] = {
			eventResponses: {
				attending: 0,
				waitingList: 0,
				notAttending: 0,
			},
			currentUser: {
				status: 'no_status',
				guests: 0,
				anonymous: 0,
			},
			rsvpSelection: 'attending',
		};
	}
}

/**
 * Retrieves a WordPress REST API nonce, with caching to avoid duplicate requests.
 *
 * This function fetches a fresh nonce from the WordPress REST API endpoint and caches
 * it for subsequent requests. It prevents multiple simultaneous requests and handles
 * request failures gracefully. The nonce is required for authenticated AJAX requests
 * to WordPress REST API endpoints.
 *
 * @since 1.0.0
 *
 * @return {Promise<string|null>} A promise that resolves to the nonce string on success,
 *                                or null if the request fails.
 *
 * @example
 * const nonce = await getNonce();
 * if (nonce) {
 *     // Use the nonce in API requests
 *     fetch('/wp-json/api/endpoint', {
 *         headers: { 'X-WP-Nonce': nonce }
 *     });
 * }
 *
 * // Clear cached nonce if it expires
 * getNonce.clearCache();
 * const freshNonce = await getNonce();
 */
export const getNonce = ( () => {
	let cachedNonce = null;
	let noncePromise = null;

	const fetchNonce = async function() {
		if ( cachedNonce ) {
			return cachedNonce;
		}

		if ( noncePromise ) {
			return noncePromise;
		}

		noncePromise = fetch( getFromGlobal( 'urls.eventApiUrl' ) + '/nonce', {
			method: 'GET',
			credentials: 'same-origin',
		} )
			.then( ( response ) => response.json() )
			.then( ( data ) => {
				cachedNonce = data.nonce;
				noncePromise = null;
				return data.nonce;
			} )
			.catch( () => {
				noncePromise = null;
				return null;
			} );

		return noncePromise;
	};

	// Expose clear function.
	fetchNonce.clearCache = () => {
		cachedNonce = null;
		noncePromise = null;
	};

	return fetchNonce;
} )();

/**
 * Sends an RSVP API request to update the RSVP status for a given post.
 *
 * This function sends a POST request to the RSVP API endpoint with the provided
 * RSVP details. If the API call is successful, it updates the provided state
 * object and executes an optional success callback. The function prevents requests
 * with invalid statuses (`no_status`, `waiting_list`). If the nonce expires during
 * the request, it automatically retries once with a fresh nonce.
 *
 * @since 1.0.0
 *
 * @param {number}      postId                 - The ID of the post for which the RSVP is being updated.
 * @param {Object}      args                   - An object containing the RSVP details.
 * @param {string}      args.status            - The RSVP status (`attending`, `not_attending`, etc.).
 * @param {number}      [args.guests=0]        - The number of additional guests.
 * @param {boolean}     [args.anonymous=false] - Whether the RSVP is anonymous.
 * @param {string}      [args.rsvpToken]       - Optional RSVP token for anonymous users.
 * @param {Object}      [state=null]           - A state object to update with the API response data.
 * @param {Function}    [onSuccess=null]       - A callback function to execute on a successful API response.
 *                                             Receives the API response as its argument.
 * @param {HTMLElement} [loadingElement=null]  - Optional element to show loading state on during the request.
 *
 * @return {Promise<void>} A promise that resolves when the request completes.
 *
 * @example
 * await sendRsvpApiRequest(
 *     123,
 *     { status: 'attending', guests: 2, anonymous: false },
 *     appState,
 *     (response) => {
 *         console.log('RSVP updated successfully:', response);
 *     },
 *     buttonElement
 * );
 */
export async function sendRsvpApiRequest(
	postId,
	args,
	state = null,
	onSuccess = null,
	loadingElement = null,
) {
	if ( [ 'no_status', 'waiting_list' ].includes( args.status ) ) {
		return;
	}

	// Add loading class to element if provided.
	if ( loadingElement ) {
		loadingElement.classList.add( 'gatherpress--is-loading' );
	}

	const makeRequest = async ( isRetry = false ) => {
		const nonce = await getNonce();
		if ( ! nonce ) {
			return;
		}

		const response = await fetch(
			getFromGlobal( 'urls.eventApiUrl' ) + '/rsvp',
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				body: JSON.stringify( {
					post_id: postId,
					status: args.status,
					guests: args.guests,
					anonymous: args.anonymous,
					rsvp_token: args.rsvpToken,
				} ),
			},
		);

		// Check if nonce failed (403 Forbidden).
		if ( 403 === response.status && ! isRetry ) {
			// Clear cached nonce and retry once.
			getNonce.clearCache();
			return makeRequest( true );
		}

		return response.json();
	};

	try {
		const res = await makeRequest();

		if ( res.success ) {
			if ( state ) {
				state.posts[ postId ] = {
					...state.posts[ postId ],
					eventResponses: {
						attending: res.responses.attending.count,
						waitingList: res.responses.waiting_list.count,
						notAttending: res.responses.not_attending.count,
					},
					currentUser: {
						status: res.status,
						guests: res.guests,
						anonymous: res.anonymous,
					},
				};
			}

			if ( 'function' === typeof onSuccess ) {
				onSuccess( res );
			}
		}
	} catch ( error ) {
		// Handle error silently.
	} finally {
		// Always remove loading class when request completes.
		if ( loadingElement ) {
			loadingElement.classList.remove( 'gatherpress--is-loading' );
		}
	}
}

/**
 * Manages focus trapping within a specified set of elements.
 *
 * This function ensures that keyboard navigation (using the `Tab` key) is
 * confined to the provided focusable elements. It also handles cleanup
 * when the `Escape` key is pressed or when the function is explicitly
 * invoked.
 *
 * @since 1.0.0
 *
 * @param {HTMLElement[]} focusableElements - An array of focusable elements.
 *                                          These elements will be used to define
 *                                          the boundaries of the focus trap.
 *
 * @return {Function} A cleanup function that removes the event listeners
 *                    and disables the focus trap.
 *
 * @example
 * const focusableElements = document.querySelectorAll('a, button, input');
 * const cleanup = manageFocusTrap(focusableElements);
 *
 * // Call the cleanup function when focus trapping is no longer needed.
 * cleanup();
 */
export function manageFocusTrap( focusableElements ) {
	if ( ! focusableElements || 0 === focusableElements.length ) {
		return () => {}; // Return an empty cleanup function if no elements..
	}

	const isElementVisible = ( element ) => {
		return (
			null !== element.offsetParent && // Excludes elements with `display: none`.
			'hidden' !== global.window.getComputedStyle( element ).visibility && // Excludes elements with `visibility: hidden`.
			'0' !== global.window.getComputedStyle( element ).opacity // Excludes fully transparent elements..
		);
	};

	// Filter out hidden elements..
	const visibleFocusableElements = focusableElements.filter( isElementVisible );

	if ( 0 === visibleFocusableElements.length ) {
		return () => {}; // No visible elements, no trap needed..
	}

	const firstFocusableElement = visibleFocusableElements[ 0 ];
	const lastFocusableElement =
		visibleFocusableElements[ visibleFocusableElements.length - 1 ];

	const handleFocusTrap = ( e ) => {
		if ( 'Tab' === e.key ) {
			if (
				e.shiftKey && // Shift + Tab..
				global.document.activeElement === firstFocusableElement
			) {
				e.preventDefault();
				lastFocusableElement.focus();
			} else if (
				! e.shiftKey && // Tab..
				global.document.activeElement === lastFocusableElement
			) {
				e.preventDefault();
				firstFocusableElement.focus();
			}
		}
	};

	const handleEscapeKey = ( e ) => {
		if ( 'Escape' === e.key ) {
			cleanup(); // Trigger cleanup on Escape key..
		}
	};

	const cleanup = () => {
		global.document.removeEventListener( 'keydown', handleFocusTrap );
		global.document.removeEventListener( 'keydown', handleEscapeKey );
	};

	// Attach the event listeners for focus trap..
	global.document.addEventListener( 'keydown', handleFocusTrap );
	global.document.addEventListener( 'keydown', handleEscapeKey );

	// Return a cleanup function for the caller..
	return cleanup;
}

/**
 * Generalized function to handle close events for modals and dropdowns.
 *
 * @param {string}   elementSelector - Selector for the parent element (modal or dropdown).
 * @param {string}   contentSelector - Selector for the inner content element.
 * @param {Function} onClose         - Callback to execute when the element is closed.
 */
export function setupCloseHandlers( elementSelector, contentSelector, onClose ) {
	const handleClose = ( element ) => {
		// Remove the visible class..
		element.classList.remove( 'gatherpress--is-visible' );

		// Execute the custom close callback..
		if ( 'function' === typeof onClose ) {
			onClose( element );
		}
	};

	const handleEscapeKey = ( event ) => {
		if ( 'Escape' === event.key ) {
			const openElements = global.document.querySelectorAll(
				`${ elementSelector }.gatherpress--is-visible`,
			);
			openElements.forEach( ( element ) => handleClose( element ) );
		}
	};

	const handleOutsideClick = ( event ) => {
		const openElements = global.document.querySelectorAll(
			`${ elementSelector }.gatherpress--is-visible`,
		);
		openElements.forEach( ( element ) => {
			if ( contentSelector ) {
				const content = element.querySelector( contentSelector );
				if (
					element.contains( event.target ) &&
					! content.contains( event.target )
				) {
					handleClose( element );
				}
			} else {
				const parentContainer = element.parentElement;
				if ( ! parentContainer.contains( event.target ) ) {
					handleClose( element );
				}
			}
		} );
	};

	// Attach event listeners..
	global.document.addEventListener( 'keydown', handleEscapeKey );
	global.document.addEventListener( 'click', handleOutsideClick );

	// Return a cleanup function to remove event listeners if needed..
	return () => {
		global.document.removeEventListener( 'keydown', handleEscapeKey );
		global.document.removeEventListener( 'click', handleOutsideClick );
	};
}
