import React from 'react';

const AttendanceSelectorItem = ({ text, status, onAnchorClick }) => {
	return (
		<li
			className="gp-attendance-selector__option list-none m-0"
		>
			<a
				className="gp-attendance-selector__anchor no-underline rounded-t bg-gray-200 focus:bg-gray-400 hover:bg-gray-400 py-2 px-4 block whitespace-no-wrap"
				href="#"
				onClick={ e => onAnchorClick( e, status )}
			>
				{text}
			</a>
		</li>
	);
};

export default AttendanceSelectorItem;
