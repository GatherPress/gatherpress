/**
 * BlockGuard component for GatherPress.
 *
 * This component creates an overlay that covers inner blocks,
 * preventing direct interaction with them when enabled.
 *
 * @param {Object}          props           - Component properties.
 * @param {boolean}         props.isEnabled - Whether the guard is enabled.
 * @param {React.ReactNode} props.children  - Inner blocks content to guard.
 *
 * @return {JSX.Element} The rendered React component.
 */
const BlockGuard = (props) => {
	const { isEnabled, children } = props;

	return (
		<div style={{ position: 'relative' }}>
			{children}
			{isEnabled && (
				<div
					style={{
						position: 'absolute',
						top: '0',
						right: '0',
						bottom: '0',
						left: '0',
						zIndex: 99,
						cursor: 'default',
						background: 'transparent',
					}}
				></div>
			)}
		</div>
	);
};

export default BlockGuard;
