/**
 * WordPress dependencies.
 */
import {
	Icon as IconComponent,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalZStack as ZStack,
} from '@wordpress/components';
import { sprintf } from '@wordpress/i18n';

/**
 * Icon Component
 *
 * This functional component renders an icon depending on the passed `iconName` prop, and layers it with a "nametag" icon.
 * The component leverages the WordPress `<Icon />` component for rendering both the main icon and the name tag icon.
 *
 * Component Structure:
 * - `OtherIcon`: Renders the main icon using the passed `iconName`.
 * - `NameTagIcon`: Always renders a fixed "nametag" icon.
 * - Both icons are stacked using the `ZStack` component, allowing for a visually layered effect.
 *
 * Styling:
 * - The name tag icon is styled using the accent color of
 *   the currently selected WordPress admin color scheme
 *   via the `--wp-components-color-accent` CSS variable.
 *
 * Example usage:
 * ```jsx
 * <Icon iconName="star" />
 * ```
 *
 * Original reference from the WordPress `<Icon />` component:
 * https://github.com/WordPress/gutenberg/blob/bbdf1a7f39dd75f672fe863c9d8ac7bf8faa874b/packages/components/src/icon/index.tsx#L54C2-L54C44
 *
 * @param {Object} props            - The props for the component.
 * @param {string} [props.iconName] - The name of the icon to display.
 *
 * @return {JSX.Element} A rendered icon, optionally layered with a name tag.
 */

const {
	Icon,
} = ({ iconName }) => {
	const BaseSize = 'string' === typeof iconName ? 20 : 24;
	const NameTagSize = 12; // BaseSize/2;
	const NameTagMargin = sprintf('-$%dpx', BaseSize / 4);

	const NameTagIcon = () => (
		<IconComponent icon={'nametag'} size={NameTagSize} />
	);
	const OtherIcon = () => <IconComponent icon={iconName} />;

	return (
		<ZStack offset={15} isLayered>
			<OtherIcon />
			<div
				style={{
					color: 'var(--wp-components-color-accent,var(--wp-admin-theme-color,#3858e9))',
					marginTop: NameTagMargin,
					marginRight: NameTagMargin,
				}}
			>
				<NameTagIcon />
			</div>
		</ZStack>
	);
};

export { Icon };
