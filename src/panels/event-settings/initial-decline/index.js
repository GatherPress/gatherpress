/**
 * Internal dependencies.
 */
import InitialDecline from '../../../components/InitialDecline';

/**
 * A panel component for managing the initial decline option.
 *
 * This component renders a section containing the `InitialDecline` component,
 * allowing users to set and manage the initial decline option for an event.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The JSX element for the InitialDeclinePanel.
 */
const InitialDeclinePanel = () => {
	return (
		<section>
			<InitialDecline />
		</section>
	);
};

export default InitialDeclinePanel;
