import {
	FullAddress,
	PhoneNumber,
	Website,
} from '../../../components/VenueInformation';

const VenueInformationPanel = () => {
	return (
		<section>
			<FullAddress />
			<PhoneNumber />
			<Website />
		</section>
	);
};

export default VenueInformationPanel;
