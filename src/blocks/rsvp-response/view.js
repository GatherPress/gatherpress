/**
 * WordPress dependencies.
 */
import { store, getElement, getContext } from '@wordpress/interactivity';

/**
 * Internal dependencies.
 */
import { initPostContext } from '../../helpers/interactivity';
import { toCamelCase } from '../../helpers/globals';

const { state, actions } = store( 'gatherpress', {
	state: {
		posts: {},
	},
	actions: {
		processRsvpSelection( event ) {
			// Call the linkHandler action to handle the default link behavior.
			actions.linkHandler( event );
			const element = getElement();

			if ( element && element.ref ) {
				const status = element.ref.getAttribute( 'data-status' );

				if ( status ) {
					const context = getContext();
					const postId = context?.postId || 0;

					initPostContext( state, postId );

					if ( postId ) {
						state.posts[ postId ].rsvpSelection = status;
					}
				}
			}
		},
		toggleRsvpVisibility( event ) {
			event.preventDefault();

			const element = getElement();
			const rsvpResponseElement = element.ref.closest(
				'.wp-block-gatherpress-rsvp-response',
			);
			const limitEnabled =
				'1' === rsvpResponseElement.dataset.limitEnabled;

			if ( ! limitEnabled ) {
				return;
			}

			const limit = parseInt( rsvpResponseElement.dataset.limit, 10 ) || 8;
			const rsvpResponsesElement = rsvpResponseElement.querySelector(
				'.gatherpress--rsvp-responses',
			);

			if ( ! rsvpResponsesElement ) {
				return;
			}

			const showAll =
				element.ref.getAttribute( 'aria-label' ) ===
				element.ref.dataset.showAll;

			// Update the aria-label and inner text for the toggle link.
			if ( showAll ) {
				element.ref.setAttribute(
					'aria-label',
					element.ref.dataset.showFewer,
				);
				element.ref.textContent = element.ref.dataset.showFewer;

				// Show all RSVP responses by removing the 'gatherpress--is-hidden' class.
				const hiddenElements = rsvpResponsesElement.querySelectorAll(
					'[data-id].gatherpress--is-hidden',
				);
				hiddenElements.forEach( ( el ) =>
					el.classList.remove( 'gatherpress--is-hidden' ),
				);
			} else {
				element.ref.setAttribute(
					'aria-label',
					element.ref.dataset.showAll,
				);
				element.ref.textContent = element.ref.dataset.showAll;

				// Show only up to the limit and hide the rest.
				const rsvpItems =
					rsvpResponsesElement.querySelectorAll( '[data-id]' );
				rsvpItems.forEach( ( el, index ) => {
					if ( index >= limit ) {
						el.classList.add( 'gatherpress--is-hidden' );
					} else {
						el.classList.remove( 'gatherpress--is-hidden' );
					}
				} );
			}
		},
	},
	callbacks: {
		processRsvpDropdown() {
			const context = getContext();
			const postId = context?.postId || 0;
			const element = getElement();
			const rsvpResponseElement = element.ref.closest(
				'.wp-block-gatherpress-rsvp-response',
			);

			initPostContext( state, postId );

			const counts = JSON.parse(
				rsvpResponseElement.getAttribute( 'data-counts' ),
			);

			// Delete attribute after setting variable. This is just to kick things off...
			rsvpResponseElement.removeAttribute( 'data-counts' );

			if ( counts ) {
				state.posts[ postId ] = {
					...state.posts[ postId ],
					eventResponses: {
						attending: counts?.attending || 0,
						waitingList: counts?.waiting_list || 0,
						notAttending: counts?.not_attending || 0,
					},
				};
			}

			if ( element && element.ref ) {
				// Check if the `data-label` attribute is already set.
				if ( ! element.ref.hasAttribute( 'data-label' ) ) {
					// Set `data-label` to the element's text content.
					const textContent = element.ref.textContent.trim();
					if ( textContent ) {
						element.ref.setAttribute( 'data-label', textContent );
					}
				}
			}

			// Fetch the current label and responses data.
			const parentElement = element.ref.parentElement;
			const classList = parentElement?.classList || [];
			const dataLabel = element.ref.getAttribute( 'data-label' );
			const activeElement =
				element.ref.getAttribute( 'data-status' ) ===
					state.posts[ postId ]?.rsvpSelection ||
				( 'attending' === element.ref.getAttribute( 'data-status' ) &&
					'no_status' === state.posts[ postId ]?.rsvpSelection );

			const dropdownParent = element.ref.closest(
				'.wp-block-gatherpress-dropdown',
			);
			const triggerElement = dropdownParent.querySelector(
				'.wp-block-gatherpress-dropdown__trigger',
			);

			// Determine the count to replace %d with based on the class.
			let count = 0;

			if ( classList.contains( 'gatherpress--is-attending' ) ) {
				count = state.posts[ postId ]?.eventResponses?.attending || 0;
			} else if ( classList.contains( 'gatherpress--is-waiting-list' ) ) {
				count = state.posts[ postId ]?.eventResponses?.waitingList || 0;
			} else if ( classList.contains( 'gatherpress--is-not-attending' ) ) {
				count = state.posts[ postId ]?.eventResponses?.notAttending || 0;
			}

			// Replace %d in the data-label with the count and update the text content.
			if ( dataLabel ) {
				const updatedText = dataLabel.replace( '%d', count );
				element.ref.textContent = updatedText;
			}

			if ( activeElement ) {
				const activeText = element.ref.textContent.trim();
				const siblings =
					dropdownParent.querySelectorAll( '[data-status]' );
				siblings.forEach( ( sibling ) => {
					sibling.classList.remove( 'gatherpress--is-disabled' );
					sibling.removeAttribute( 'tabindex' );
					sibling.removeAttribute( 'aria-disabled' );
				} );

				element.ref.classList.add( 'gatherpress--is-disabled' );
				element.ref.setAttribute( 'tabindex', '-1' );
				element.ref.setAttribute( 'aira-disabled', 'true' );

				triggerElement.textContent = activeText;
			}

			if (
				0 === count &&
				! classList.contains( 'gatherpress--is-attending' )
			) {
				parentElement.classList.add( 'gatherpress--is-hidden' );
			} else {
				parentElement.classList.remove( 'gatherpress--is-hidden' );
			}

			const visibleItems = dropdownParent.querySelectorAll(
				'.wp-block-gatherpress-dropdown-item:not(.gatherpress--is-hidden)',
			);

			// Disable the dropdown if "Attending" is both:
			// 1. The only option available in the dropdown.
			// 2. Currently selected (matching the trigger element's text).
			if (
				1 === visibleItems.length &&
				visibleItems[ 0 ].classList.contains(
					'gatherpress--is-attending',
				) &&
				visibleItems[ 0 ].textContent === triggerElement.textContent
			) {
				triggerElement.classList.add( 'gatherpress--is-disabled' );
				triggerElement.setAttribute( 'tabindex', '-1' );
			} else {
				triggerElement.classList.remove( 'gatherpress--is-disabled' );
				triggerElement.setAttribute( 'tabindex', '0' );
			}

			// Clean up any existing event handlers and focus traps
			// before re-initializing the dropdown. This prevents memory leaks
			// and duplicate event handlers when the dropdown state changes.
			if ( 'function' === typeof triggerElement.cleanupFocusTrap ) {
				triggerElement.cleanupFocusTrap();
			}
			if ( 'function' === typeof triggerElement.cleanupCloseHandlers ) {
				triggerElement.cleanupCloseHandlers();
			}
		},
		showHideToggle() {
			const element = getElement();
			const context = getContext();
			const postId = context?.postId || 0;
			const rsvpResponseElement = element.ref.closest(
				'.wp-block-gatherpress-rsvp-response',
			);
			const limitEnabled =
				'1' === rsvpResponseElement.dataset.limitEnabled;

			if ( ! limitEnabled ) {
				return;
			}

			const rsvpSelection = toCamelCase(
				state.posts[ postId ]?.rsvpSelection ?? 'attending',
			);
			const count = state.posts[ postId ].eventResponses[ rsvpSelection ];
			const limit = parseInt( rsvpResponseElement.dataset.limit, 10 ) || 8;

			// If the count is less than or equal to the limit, apply the class.
			if ( count <= limit ) {
				element.ref.classList.add( 'gatherpress--is-hidden' );
			} else {
				element.ref.classList.remove( 'gatherpress--is-hidden' );
			}

			// Reset the anchor.
			const anchorElement = element.ref.querySelector( 'a[role="button"]' );

			if ( anchorElement ) {
				anchorElement.setAttribute(
					'aria-label',
					anchorElement.dataset.showAll,
				);
				anchorElement.textContent = anchorElement.dataset.showAll;
			}
		},
	},
} );
