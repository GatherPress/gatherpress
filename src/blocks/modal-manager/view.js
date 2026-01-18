/**
 * WordPress dependencies.
 */
import { store } from '@wordpress/interactivity';

/**
 * Internal dependencies.
 */
import {
	manageFocusTrap,
	setupCloseHandlers,
} from '../../helpers/interactivity';

const { actions } = store( 'gatherpress', {
	actions: {
		openModal( event = null, element = null ) {
			if ( event ) {
				event.preventDefault();
			}

			element = element ?? event.target;

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
				let openTrigger = modalManager.querySelector(
					'.gatherpress-modal--trigger-open button',
				);

				// If no nested button, try the trigger element itself (could be anchor or button).
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
	},
} );
