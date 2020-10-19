import React, { useState } from 'react';

export const useAttendanceState = (value) => {
	const [status, setStatus] = useState(value);

	return [value, setStatus];
}
