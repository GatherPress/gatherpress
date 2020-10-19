import React, { useState } from 'react';

const AttendanceItem = ({ text, value, onAnchorClick }) => {
	return(
		<li
			className="list-none m-0"
			style={{ padding:0, margin:0 }}
		>
			<a
				className="no-underline rounded-t bg-gray-200 hover:bg-gray-400 py-2 px-4 block whitespace-no-wrap"
				href="#"
				data-value={value}
				onClick={ e => onAnchorClick(e, value)}
			>
				{text}
			</a>
		</li>
	);
}

export default AttendanceItem;
