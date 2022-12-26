export const Broadcaster = (payload, identifier = false) => {
	for (const [key, value] of Object.entries(payload)) {
		let type = key;

		if (identifier) {
			type += identifier;
		}

		const dispatcher = new CustomEvent(type, {
			detail: value,
		});

		dispatchEvent(dispatcher);
	}
};

export const Listener = (payload, identifier = false) => {
	for (const [key, value] of Object.entries(payload)) {
		let type = key;

		if (identifier) {
			type += identifier;
		}

		addEventListener(
			type,
			(e) => {
				value(e.detail);
			},
			false
		);
	}
};
