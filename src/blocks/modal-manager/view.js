/**
 * WordPress dependencies
 */
import { store } from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
import {
	manageFocusTrap,
	setupCloseHandlers,
} from '../../helpers/interactivity';

/**
 * Checks whether an element is shown, walking up to (but not including) a root.
 *
 * Looks only at the `hidden` attribute, GatherPress's hidden class and the
 * computed `display`/`visibility`, so it works without layout (e.g. in tests)
 * and while the modal's own open transition is still running.
 *
 * @since TBD
 *
 * @param {HTMLElement} element The element to check.
 * @param {HTMLElement} root    The ancestor to stop at.
 *
 * @return {boolean} True when nothing between the element and the root hides it.
 */
function isShownWithin( element, root ) {
	for ( let node = element; node && node !== root; node = node.parentElement ) {
		if ( node.hidden || node.classList.contains( 'gatherpress--is-hidden' ) ) {
			return false;
		}

		const style = window.getComputedStyle( node );

		if ( 'none' === style.display || 'hidden' === style.visibility ) {
			return false;
		}
	}

	return true;
}

/**
 * Names a modal without a custom name after its first visible heading.
 *
 * The server gives such a modal the generic "Modal" label and the
 * `data-gatherpress-default-label` marker. `aria-labelledby` overrides that
 * label while a visible heading exists; when none does, the attribute is
 * removed so the generic label applies again.
 *
 * @since TBD
 *
 * @param {HTMLElement} modal        The modal element (role="dialog").
 * @param {HTMLElement} modalContent The modal content element.
 *
 * @return {void}
 */
function labelModalByVisibleHeading( modal, modalContent ) {
	const heading = Array.from(
		modalContent.querySelectorAll( 'h1, h2, h3, h4, h5, h6' ),
	).find( ( el ) => isShownWithin( el, modalContent ) );

	if ( ! heading ) {
		modal.removeAttribute( 'aria-labelledby' );
		return;
	}

	if ( ! heading.id ) {
		let index = 1;

		while ( document.getElementById( `gatherpress-modal-heading-${ index }` ) ) {
			index++;
		}

		heading.id = `gatherpress-modal-heading-${ index }`;
	}

	modal.setAttribute( 'aria-labelledby', heading.id );
}

const { actions } = store( 'gatherpress', {
	actions: {
		openModal( event = null, element = null ) {
			if ( event ) {
				event.preventDefault();
			}

			element = element ?? event?.target;

			// Bail when called with neither an event nor an element (e.g. a
			// querySelector miss at the call site) instead of throwing (#1719).
			if ( ! element ) {
				return;
			}

			const modalManager = element.closest(
				'.wp-block-gatherpress-modal-manager',
			);

			if ( modalManager ) {
				const modal = modalManager.querySelector(
					'.wp-block-gatherpress-modal',
				);

				if ( modal ) {
					modal.classList.add( 'gatherpress--is-visible' );

					const modalContent = modal.querySelector(
						'.wp-block-gatherpress-modal-content',
					);

					if ( modalContent ) {
						if ( modal.hasAttribute( 'data-gatherpress-default-label' ) ) {
							labelModalByVisibleHeading( modal, modalContent );
						}

						// Define focusable elements inside the modal.
						const focusableSelectors = [
							'a[href]',
							'button:not([disabled])',
							'textarea:not([disabled])',
							'input[type="text"]:not([disabled])',
							'input[type="number"]:not([disabled])',
							'input[type="radio"]:not([disabled])',
							'input[type="checkbox"]:not([disabled])',
							'select:not([disabled])',
							'[tabindex]:not([tabindex="-1"])',
						];

						const focusableElements = Array.from(
							modalContent.querySelectorAll(
								focusableSelectors.join( ',' ),
							),
						).filter( ( el ) => {
							// Exclude if element itself is hidden.
							if ( el.classList.contains( 'gatherpress--is-hidden' ) ) {
								return false;
							}
							// Exclude if there's a hidden container between element and modalContent.
							let parent = el.parentElement;
							while ( parent && parent !== modalContent ) {
								if ( parent.classList.contains( 'gatherpress--is-hidden' ) ) {
									return false;
								}
								parent = parent.parentElement;
							}
							return true;
						} );

						// Focus the first focusable element, if available.
						if ( focusableElements[ 0 ] ) {
							setTimeout( () => {
								modal.setAttribute( 'aria-hidden', 'false' );
								focusableElements[ 0 ].focus();
							}, 10 );
						}

						// Set up focus trap using the helper function and store cleanup.
						// Use 11ms to ensure this runs AFTER the initial focus (10ms above).
						// This prevents focus trap conflicts when switching between RSVP modal states.
						setTimeout( () => {
							modalContent.cleanupFocusTrap =
								manageFocusTrap( focusableElements );
						}, 11 );

						// Set up close handlers and store cleanup function.
						modalContent.cleanupCloseHandlers = setupCloseHandlers(
							'.wp-block-gatherpress-modal',
							'.wp-block-gatherpress-modal-content',
							( e ) => {
								actions.closeModal( null, e );
							},
						);
					}
				}
			}
		},
		/**
		 * Opens the modal when the Enter or Space key is pressed.
		 *
		 * @since 0.35.0
		 *
		 * @param {KeyboardEvent} event The keyboard event.
		 *
		 * @return {void}
		 */
		openModalOnEnter( event ) {
			if ( 'Enter' === event.key || ' ' === event.key ) {
				event.preventDefault();
				actions.openModal( event );
			}
		},
		closeModal( event = null, element = null, findActiveSibling = true ) {
			if ( event ) {
				event.preventDefault();
			}

			// Determine the element to work with.
			element = element ?? event?.target;

			if ( ! element ) {
				return;
			}

			// Find the modal manager and modal.
			let modalManager = element.closest(
				'.wp-block-gatherpress-modal-manager',
			);

			/**
			 * When switching between RSVP states, modals are hidden/shown dynamically.
			 * If findActiveSibling=true, this code finds the currently visible modal manager
			 * when the original one is hidden (has a parent with gatherpress--is-not-visible class).
			 * This ensures focus and functionality transfer to the currently visible modal.
			 */
			if (
				findActiveSibling &&
				modalManager.closest( '.gatherpress--is-hidden' )
			) {
				const hiddenContainer = modalManager.closest(
					'.gatherpress--is-hidden',
				);
				const parent = hiddenContainer.parentElement;

				// Look for visible siblings (both previous and next).
				if ( parent ) {
					// Try siblings.
					for ( const sibling of parent.children ) {
						if (
							sibling !== hiddenContainer &&
							! sibling.classList.contains(
								'gatherpress--is-hidden',
							)
						) {
							const visibleModalManager = sibling.querySelector(
								'.wp-block-gatherpress-modal-manager',
							);
							if ( visibleModalManager ) {
								modalManager = visibleModalManager;
								break;
							}
						}
					}
				}
			}

			if ( ! modalManager ) {
				return;
			}

			const modal = modalManager.querySelector(
				'.wp-block-gatherpress-modal',
			);

			if ( ! modal ) {
				return;
			}

			// Handle modal closing.
			modal.classList.remove( 'gatherpress--is-visible' );
			modal.setAttribute( 'aria-hidden', 'true' );

			// Clean up focus trap if applicable.
			const modalContent = modal.querySelector( '.wp-block-gatherpress-modal-content' );

			if (
				modalContent &&
				'function' === typeof modalContent.cleanupFocusTrap
			) {
				modalContent.cleanupFocusTrap();
			}

			// Clean up close handlers if applicable.
			if (
				modalContent &&
				'function' === typeof modalContent.cleanupCloseHandlers
			) {
				modalContent.cleanupCloseHandlers();
			}

			// Return focus to the open modal trigger only when fully closing.
			// When switching modals (findActiveSibling=false), don't focus the trigger.
			if ( findActiveSibling ) {
				// The trigger class often sits on a block wrapper (for example the
				// Event Date block's <div>) with the focusable link or button inside it.
				// A link without an href is focusable only through the tabindex the
				// server adds, so match that too.
				let openTrigger = modalManager.querySelector(
					[
						'.gatherpress-modal--trigger-open button',
						'.gatherpress-modal--trigger-open a[href]',
						'.gatherpress-modal--trigger-open [tabindex]:not([tabindex="-1"])',
					].join( ', ' ),
				);

				// If no nested button or link, try the trigger element itself (could be anchor or button).
				if ( ! openTrigger ) {
					openTrigger = modalManager.querySelector(
						'.gatherpress-modal--trigger-open',
					);
				}

				if ( openTrigger ) {
					openTrigger.focus();
				}
			}
		},
		/**
		 * Closes the modal when the Enter or Space key is pressed.
		 *
		 * @since 0.35.0
		 *
		 * @param {KeyboardEvent} event The keyboard event.
		 *
		 * @return {void}
		 */
		closeModalOnEnter( event ) {
			if ( 'Enter' === event.key || ' ' === event.key ) {
				event.preventDefault();
				actions.closeModal( event );
			}
		},
	},
} );
