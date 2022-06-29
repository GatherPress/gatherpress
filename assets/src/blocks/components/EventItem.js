import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { ButtonGroup } from '@wordpress/components';
import Modal from 'react-modal';
import apiFetch from '@wordpress/api-fetch';
import HtmlReactParser from 'html-react-parser';

const EventItem = ( props ) => {
	if ('object' !== typeof GatherPress) {
		return '';
	}

	const { type, event } = props;

	const event_class = `gp-${type}-event`;

	return (
		<div className={event_class}>
			<div className={`${event_class}__header`}>
				<div className={`${event_class}__info`}>
					<div className={`${event_class}__datetime has-small-font-size`}>
						<strong>
							{event.datetime_start}
						</strong>
					</div>
					<div className={`${event_class}__title has-large-font-size`}>
						<a href={event.permalink}>
							{HtmlReactParser( event.title )}
						</a>
					</div>
					<div className="gp-buttons-container wp-block-buttons">
						<div className="gp-button-container wp-block-button">
							<a href={event.permalink} className="gp-button wp-block-button__link">
								{__( 'Attend', 'gatherpress' )}
							</a>
						</div>
					</div>
				</div>
				<figure className={`${event_class}__image`}>
					<a href={event.permalink}>
						{HtmlReactParser(event.featured_image)}
					</a>
				</figure>
			</div>
			<div className={`${event_class}__content`}>
				<div className={`${event_class}__excerpt`}>
					{HtmlReactParser( event.excerpt )}
				</div>
			</div>
			<div className={`${event_class}__footer`}>
			</div>
		</div>
	);
}

export default EventItem;
