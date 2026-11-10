/**
 * WordPress dependencies
 */
import { store, getElement } from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
import {
	activateOnSpace as activateOnSpaceHelper,
	setupCloseHandlers,
} from '../../helpers/interactivity';

const { actions } = store( 'gatherpress', {
	actions: {
		preventDefault( event ) {
			if ( event ) {
				event.preventDefault();
			}
		},
		activateOnSpace( event ) {
			activateOnSpaceHelper( event, getElement().ref );
		},
		linkHandler( event ) {
			actions.preventDefault( event );

			const element = getElement();
			const dropdownParent = element.ref.closest(
				'.wp-block-gatherpress-dropdown',
			);

			// Bail unless the dropdown is in select mode and the click landed
			// on a non-disabled item — flattens the original four-deep nest
			// into early returns so the per-item update can stay readable.
			if ( 'select' !== dropdownParent?.dataset.dropdownMode ) {
				return;
			}

			const clickedItem = element.ref.closest(
				'.wp-block-gatherpress-dropdown-item',
			);

			if ( ! clickedItem ) {
				return;
			}

			const clickedAnchor = clickedItem.querySelector( 'a' );

			if ( clickedAnchor?.classList.contains( 'gatherpress--is-disabled' ) ) {
				return;
			}

			const dropdownMenu = dropdownParent.querySelector(
				'.wp-block-gatherpress-dropdown__menu',
			);
			const dropdownTrigger = dropdownParent.querySelector(
				'.wp-block-gatherpress-dropdown__trigger',
			);

			// Disable the clicked item.
			if ( clickedAnchor ) {
				clickedAnchor.classList.add( 'gatherpress--is-disabled' );
				clickedAnchor.setAttribute( 'tabindex', '-1' );
				clickedAnchor.setAttribute( 'aria-disabled', 'true' );
			}

			// Enable sibling items.
			const siblingItems = dropdownMenu.querySelectorAll(
				'.wp-block-gatherpress-dropdown-item',
			);

			siblingItems.forEach( ( sibling ) => {
				const siblingAnchor = sibling.querySelector( 'a' );

				if ( siblingAnchor && sibling !== clickedItem ) {
					siblingAnchor.classList.remove( 'gatherpress--is-disabled' );
					siblingAnchor.removeAttribute( 'tabindex' );
					siblingAnchor.removeAttribute( 'aria-disabled' );
				}
			} );

			// Update the dropdown trigger text.
			if ( dropdownTrigger && clickedAnchor ) {
				dropdownTrigger.textContent =
					clickedAnchor.textContent.trim();
			}

			// Close the dropdown menu.
			if ( dropdownMenu ) {
				dropdownMenu.classList.remove( 'gatherpress--is-visible' );
				dropdownTrigger.setAttribute( 'aria-expanded', 'false' );
				dropdownTrigger.focus();
			}
		},
		/**
		 * Opens or closes the dropdown panel.
		 *
		 * The dropdown is a disclosure, not a menu: Tab moves through the
		 * items and on out of the dropdown, and the panel closes when focus
		 * leaves it. Escape and an outside click close it as well.
		 *
		 * @since 0.33.0
		 * @since TBD Closes when focus leaves the dropdown, with no focus trap. Added `returnFocus`.
		 *
		 * @param {Event|null}  event       The triggering event, if any.
		 * @param {Object|null} element     The element from `getElement()`; read when omitted.
		 * @param {boolean}     forceClose  Close the panel instead of toggling it.
		 * @param {boolean}     returnFocus Move focus to the trigger after closing.
		 *
		 * @return {void}
		 */
		toggleDropdown(
			event = null,
			element = null,
			forceClose = false,
			returnFocus = true,
		) {
			actions.preventDefault( event );
			element = element ?? getElement();

			const wrapper = element.ref.parentElement;
			const menu = wrapper.querySelector(
				'.wp-block-gatherpress-dropdown__menu',
			);

			const trigger = wrapper.querySelector(
				'.wp-block-gatherpress-dropdown__trigger',
			);

			if ( ! menu || ! trigger ) {
				return;
			}

			let isVisible = false;

			if ( forceClose ) {
				menu.classList.remove( 'gatherpress--is-visible' );
			} else {
				isVisible = menu.classList.toggle( 'gatherpress--is-visible' );
			}

			trigger.setAttribute( 'aria-expanded', isVisible ? 'true' : 'false' );

			// Remove the handlers of an earlier opening before adding new
			// ones, and remove them all when the panel closes.
			if ( 'function' === typeof element.ref.cleanupFocusOut ) {
				element.ref.cleanupFocusOut();
			}

			if ( 'function' === typeof element.ref.cleanupCloseHandlers ) {
				element.ref.cleanupCloseHandlers();
			}

			if ( isVisible ) {
				trigger.focus();

				// Close when focus moves to something outside the dropdown.
				// No related target means the window lost focus or the click
				// landed on something that takes no focus; the outside-click
				// handler covers the second case.
				const handleFocusOut = ( focusEvent ) => {
					const next = focusEvent.relatedTarget;

					if ( next && ! wrapper.contains( next ) ) {
						actions.toggleDropdown( null, element, true, false );
					}
				};

				wrapper.addEventListener( 'focusout', handleFocusOut );
				element.ref.cleanupFocusOut = () => {
					wrapper.removeEventListener( 'focusout', handleFocusOut );
				};

				element.ref.cleanupCloseHandlers = setupCloseHandlers(
					'.wp-block-gatherpress-dropdown__menu',
					null,
					() => {
						actions.toggleDropdown( null, element, true );
					},
				);
			} else if ( returnFocus ) {
				trigger.focus();
			}
		},
	},
} );
