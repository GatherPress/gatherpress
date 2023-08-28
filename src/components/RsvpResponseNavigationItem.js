const RsvpResponseNavigationItem = ({
	item,
	additionalClasses,
	count,
	onTitleClick,
}) => {
	const { title, value } = item;
	const active = 0 === count && 'attending' !== value ? 'hidden' : 'active';

	return (
		<div
			className={`gp-rsvp-response__navigation-item gp-rsvp-response__${active} ${additionalClasses}`}
		>
			{/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
			<a
				className="gp-rsvp-response__anchor"
				data-item={value}
				data-toggle="tab"
				href="#"
				role="tab"
				aria-controls={`#gp-rsvp-${value}`}
				onClick={(e) => onTitleClick(e, value)}
			>
				{title}
			</a>
			<span className="gp-rsvp-response__count">({count})</span>
		</div>
	);
};

export default RsvpResponseNavigationItem;
