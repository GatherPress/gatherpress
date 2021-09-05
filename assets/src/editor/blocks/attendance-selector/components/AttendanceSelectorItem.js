import React from 'react';

const AttendanceSelectorItem = ({ text, status, onAnchorClick }) => {
	return (
		<li
			className="gp-attendance-selector__option"
		>
			<a
				className="gp-attendance-selector__anchor"
				href="#"
				onClick={ e => onAnchorClick( e, status )}
			>
				{text}
			</a>
		</li>
	);
};

export default AttendanceSelectorItem;
