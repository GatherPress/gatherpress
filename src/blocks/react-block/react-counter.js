import { render, useState, useEffect  } from '@wordpress/element';

import domReady from  '@wordpress/dom-ready';

const center = {
	textAlign: 'Center'
};

const btnStyle = {
	paddingRight: '1rem',
	paddingLeft: '1rem'
};

const textStyle = {
	fontSize: '2rem',
	padding: '1rem',
	verticalAlign: 'Middle'
};

function Buttn({ children, onClick }) {
	return (
		<button style={btnStyle} onClick={onClick}>
			{children}
		</button>
	);
}

function Counter() {
	const [counter, updateCounter] = useState(0);
	const [randomText, updaterandomText] = useState('This is random Text');

	function handleIncrement() {
		updateCounter(counter + 1);
	}

	function handleDecrement() {
		updateCounter(counter <= 0 ? 0 : counter - 1);
	}

	function handleReset() {
		updateCounter(0);
		updaterandomText(`Random text is updated`);
	}

	useEffect(() => {
		console.log('component lifecycle');
		// updaterandomText(`Random text is updated`);
	}, [counter]);

	return (
	<div style={center}>
		<h3>React Counter</h3>
		<Buttn onClick={handleIncrement}>+</Buttn>
		<span style={textStyle}>{counter}</span>
		<Buttn onClick={handleDecrement}>-</Buttn>
		<div style={center}>
		<Buttn onClick={handleReset}>Reset</Buttn>
		</div>
		<br />
		<span>{randomText}</span>
	</div>
	);
}

domReady(
    function() {
		const container = document.querySelector('#react-app');
		render( <Counter />, container );
    }
);

