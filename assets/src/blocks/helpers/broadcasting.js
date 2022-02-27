export const Broadcaster = (payload) => {
	for (const [key, value] of Object.entries(payload)) {
		const dispatcher = new CustomEvent(
			key,
			{
				detail: value,
			}
		);

		dispatchEvent(dispatcher);
	}
};

export const Listener = (payload) => {
	for (const [key, value] of Object.entries(payload)) {
		addEventListener( key, ( e ) => {
			value( e.detail );
		}, false );
	}
}
