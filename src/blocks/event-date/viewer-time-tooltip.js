/**
 * The datetime of the Event Date block in the editor, as a link, a viewer time tooltip, or both.
 *
 * Mirrors the markup of includes/templates/blocks/event-date.php.
 *
 * @since TBD
 *
 * @param {Object}      props          Component props.
 * @param {boolean}     props.isLink   Whether the block links to the event.
 * @param {string}      props.label    The viewer time label, or an empty string.
 * @param {JSX.Element} props.children The datetime.
 *
 * @return {JSX.Element} The datetime, wrapped when it is a link or has a tooltip.
 */
export default function ViewerTimeTooltip( { isLink, label, children } ) {
	if ( ! isLink && ! label ) {
		return children;
	}

	const Tag = isLink ? 'a' : 'span';
	const linkProps = isLink
		? {
			href: '#gatherpress-event-date-pseudo-link',
			onClick: ( event ) => event.preventDefault(),
		}
		: {};

	return (
		<Tag
			{ ...linkProps }
			className={ label ? 'gatherpress-tooltip' : undefined }
			data-gatherpress-tooltip={ label || undefined }
			tabIndex={ label ? 0 : undefined }
		>
			{ children }
			{ !! label && (
				<span className="screen-reader-text gatherpress--screen-reader-text gatherpress-tooltip-notice">
					{ ` (${ label })` }
				</span>
			) }
		</Tag>
	);
}
