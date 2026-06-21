/**
 * Internal dependencies
 */
import AddressAutocompleteField from '../../../components/AddressAutocompleteField';

/**
 * Address field for venue details in the block editor (textarea + Popover).
 *
 * @param {Object} props - Passed through to AddressAutocompleteField (block variant).
 *
 * @return {JSX.Element} Field.
 */
export default function AddressField( props ) {
	return <AddressAutocompleteField variant="block" { ...props } />;
}
