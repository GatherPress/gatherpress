/**
 * Internal dependencies.
 */
import RsvpResponseCard from './RsvpResponseCard';
import { getFromGlobal } from '../helpers/globals';
import { useState } from '@wordpress/element';
import { Autocomplete } from '@wordpress/components';
import { Listener } from '../helpers/broadcasting';

/**
 * RsvpResponseContent component for GatherPress.
 *
 * This component displays the content of RSVP responses based on the selected RSVP status.
 * It receives an array of items representing different RSVP statuses and renders the content
 * of the active status using the RsvpResponseCard component. The component dynamically updates
 * based on changes to the RSVP responses.
 *
 * @since 1.0.0
 *
 * @param {Object}         props               - Component props.
 * @param {Array}          props.items         - An array of objects representing different RSVP statuses.
 * @param {string}         props.activeValue   - The currently active RSVP status value.
 * @param {string}         props.editMode      - Edit mode of the component.
 * @param {number|boolean} [props.limit=false] - The maximum number of responses to display or false for no limit.
 *
 * @return {JSX.Element} The rendered React component.
 */
const RsvpResponseContent = ({
	items,
	activeValue,
	limit = false,
	editMode,
}) => {
	const eventId = getFromGlobal('post_id');
	const [rsvpResponse, setRsvpResponse] = useState(
		getFromGlobal('responses')
	);
	const autocompleters = [
		{
			name: 'Attendees',
			// The prefix that triggers this completer
			triggerPrefix: '~',
			// The option data
			options: rsvpResponse.attending.responses,
			// Returns a label for an option
			getOptionLabel: (option) => (
				<span>
					<span className="icon">{option.visual}</span>
					{option.name}
				</span>
			),
			// Declares that options should be matched by their name
			getOptionKeywords: (option) => [option.name],
			// Declares that this option is disabled
			isOptionDisabled: (option) => option.name === 'admin',
			// Declares completions should be inserted as abbreviations
			getOptionCompletion: (option) => (
				<abbr title={option.name}>{option.visual}</abbr>
			),
		},
	];

	Listener({ setRsvpResponse }, eventId);

	const renderedItems = items.map((item, index) => {
		const { value } = item;
		const active = value === activeValue;

		if (active) {
			return (
				<div
					key={index}
					className="gp-rsvp-response__items"
					id={`gp-rsvp-${value}`}
					role="tabpanel"
					aria-labelledby={`gp-rsvp-${value}-tab`}
				>
					{!editMode && (
						<RsvpResponseCard
							value={value}
							limit={limit}
							responses={rsvpResponse}
						/>
					)}
					{editMode && (
						<div>
							<Autocomplete
								completers={autocompleters}
							></Autocomplete>
						</div>
					)}
				</div>
			);
		}

		return '';
	});

	return <div className="gp-rsvp-response__content">{renderedItems}</div>;
};

export default RsvpResponseContent;
