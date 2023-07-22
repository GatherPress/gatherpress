/**
 * Internal dependencies
 */
import './event-settings';
import domReady from '@wordpress/dom-ready';
import {createRoot, useState} from '@wordpress/element';
import {Button, Modal} from '@wordpress/components';
import {Listener} from '../helpers/broadcasting';

const MyModal = () => {
	const [isOpen, setOpen] = useState(false);
	const openModal = () => setOpen(true);
	const closeModal = () => setOpen(false);
	Listener({ setOpen });

	return (
		<>
			<Button variant="secondary" onClick={ openModal }>
				Open Modal
			</Button>
			{isOpen && (
				<Modal title="This is my modal" onRequestClose={closeModal}>
					<Button variant="secondary" onClick={closeModal}>
						My custom close button
					</Button>
				</Modal>
			)}
		</>
	);
};

domReady(() => {
	createRoot(document.getElementById('gp-event-communication-modal')).render(
		<MyModal />
	);
});
