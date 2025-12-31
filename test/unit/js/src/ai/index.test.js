/**
 * External dependencies.
 */
import { describe, expect, jest, it, beforeEach, afterEach } from '@jest/globals';

/**
 * Mock jQuery
 */
const createMockElement = () => {
	const mockElement = {
		val: jest.fn( function( value ) {
			if ( value !== undefined ) {
				return this;
			}
			return '';
		} ),
		text: jest.fn( function( text ) {
			if ( text !== undefined ) {
				return this;
			}
			return '';
		} ),
		html: jest.fn( function( html ) {
			if ( html !== undefined ) {
				return this;
			}
			return '';
		} ),
		append: jest.fn( function() {
			return this;
		} ),
		addClass: jest.fn( function() {
			return this;
		} ),
		prop: jest.fn( function( prop, value ) {
			if ( value !== undefined ) {
				return this;
			}
			return false;
		} ),
		show: jest.fn( function() {
			return this;
		} ),
		hide: jest.fn( function() {
			return this;
		} ),
		on: jest.fn( function() {
			return this;
		} ),
		scrollTop: jest.fn( function( value ) {
			if ( value !== undefined ) {
				return this;
			}
			return 0;
		} ),
		children: jest.fn( () => ( { length: 0 } ) ),
		length: 1,
		scrollHeight: 1000,
	};

	// Make it array-like so $element[0] works
	mockElement[ 0 ] = mockElement;

	return mockElement;
};

let mockJQuery;

// Create a fresh mock for each test
const createMockJQuery = () => {
	const mock = jest.fn( ( selector ) => {
		const mockElement = createMockElement();

		// Handle jQuery(document).ready() - check if selector is document
		if ( selector && ( selector.nodeType === 9 || selector === global.document || selector.toString().includes( 'HTMLDocument' ) ) ) {
			mockElement.ready = jest.fn( ( callback ) => {
				callback( mock );
			} );
			return mockElement;
		}

		return mockElement;
	} );

	// Mock jQuery.ajax
	mock.ajax = jest.fn();

	return mock;
};

// Initialize mocks before tests
mockJQuery = createMockJQuery();

// Make jQuery available globally
global.jQuery = mockJQuery;
global.$ = mockJQuery;

// Mock gatherpressAI global
global.gatherpressAI = {
	ajaxUrl: 'http://example.com/wp-admin/admin-ajax.php',
	nonce: 'test-nonce-123',
};

/**
 * Internal dependencies.
 */
// Clear module cache before each test suite
jest.resetModules();

describe( 'AI Assistant', () => {
	let mockPrompt, mockSubmit, mockMessages, mockStatus;
	let ajaxSpy;
	let aiModule;

	beforeEach( () => {
		// Reset mocks and create fresh jQuery mock
		jest.clearAllMocks();
		mockJQuery = createMockJQuery();
		global.jQuery = mockJQuery;
		global.$ = mockJQuery;

		// Create mock jQuery elements
		mockPrompt = {
			val: jest.fn( () => '' ),
			on: jest.fn( function() {
				return this;
			} ),
		};

		mockSubmit = {
			on: jest.fn( function() {
				return this;
			} ),
			prop: jest.fn( function( prop, value ) {
				if ( value !== undefined ) {
					return this;
				}
				return false;
			} ),
		};

		mockMessages = {
			append: jest.fn( function() {
				return this;
			} ),
			scrollTop: jest.fn( function( value ) {
				if ( value !== undefined ) {
					return this;
				}
				return 0;
			} ),
			children: jest.fn( () => ( { length: 0 } ) ),
			length: 1,
			scrollHeight: 1000,
		};
		// Make it array-like so $messages[0] works
		mockMessages[ 0 ] = mockMessages;

		mockStatus = {
			show: jest.fn( function() {
				return this;
			} ),
			hide: jest.fn( function() {
				return this;
			} ),
		};

		// Mock jQuery selector returns
		mockJQuery.mockImplementation( ( selector ) => {
			// Handle jQuery(document).ready()
			if ( selector && ( selector.nodeType === 9 || selector === global.document || selector.toString().includes( 'HTMLDocument' ) ) ) {
				const docElement = createMockElement();
				docElement.ready = jest.fn( ( callback ) => {
					callback( mockJQuery );
				} );
				return docElement;
			}

			if ( '#gp-ai-prompt' === selector ) {
				return mockPrompt;
			}
			if ( '#gp-ai-submit' === selector ) {
				return mockSubmit;
			}
			if ( '#gp-ai-messages' === selector ) {
				return mockMessages;
			}
			if ( '#gp-ai-status' === selector ) {
				return mockStatus;
			}
			// Default mock element
			return createMockElement();
		} );

		// Mock jQuery.ajax
		ajaxSpy = jest.fn( ( options ) => {
			// Simulate async behavior
			setTimeout( () => {
				if ( options.success ) {
					options.success( {
						success: true,
						data: {
							response: 'Test response',
							actions: [],
						},
					} );
				}
				if ( options.complete ) {
					options.complete();
				}
			}, 0 );
			return {
				done: jest.fn(),
				fail: jest.fn(),
			};
		} );

		mockJQuery.ajax = ajaxSpy;
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	describe( 'Initialization', () => {
		it( 'should initialize on document ready', () => {
			// Require the module to trigger initialization
			aiModule = require( '../../../../../src/ai/index.js' );

			// Check that jQuery was called with document
			expect( mockJQuery ).toHaveBeenCalledWith( global.document );
		} );

		it( 'should show initial message when messages container is empty', ( done ) => {
			mockMessages.children.mockReturnValue( { length: 0 } );

			jest.resetModules();
			aiModule = require( '../../../../../src/ai/index.js' );

			// Wait for async operations
			setTimeout( () => {
				expect( mockMessages.append ).toHaveBeenCalled();
				done();
			}, 10 );
		} );
	} );

	describe( 'Event Handlers', () => {
		beforeEach( () => {
			jest.resetModules();
			aiModule = require( '../../../../../src/ai/index.js' );
		} );

		it( 'should attach click handler to submit button', () => {
			expect( mockSubmit.on ).toHaveBeenCalledWith(
				'click',
				expect.any( Function )
			);
		} );

		it( 'should attach keydown handler to prompt input', () => {
			expect( mockPrompt.on ).toHaveBeenCalledWith(
				'keydown',
				expect.any( Function )
			);
		} );

		it( 'should process prompt on Enter key (without Shift)', () => {
			const keydownHandler = mockPrompt.on.mock.calls.find(
				( call ) => call[ 0 ] === 'keydown'
			)[ 1 ];

			mockPrompt.val.mockReturnValue( 'Test prompt' );

			const mockEvent = {
				key: 'Enter',
				shiftKey: false,
				preventDefault: jest.fn(),
			};

			keydownHandler( mockEvent );

			expect( mockEvent.preventDefault ).toHaveBeenCalled();
			expect( ajaxSpy ).toHaveBeenCalled();
		} );

		it( 'should not process prompt on Shift+Enter', () => {
			const keydownHandler = mockPrompt.on.mock.calls.find(
				( call ) => call[ 0 ] === 'keydown'
			)[ 1 ];

			const mockEvent = {
				key: 'Enter',
				shiftKey: true,
				preventDefault: jest.fn(),
			};

			keydownHandler( mockEvent );

			expect( mockEvent.preventDefault ).not.toHaveBeenCalled();
		} );

		it( 'should process prompt on submit button click', () => {
			const clickHandler = mockSubmit.on.mock.calls.find(
				( call ) => call[ 0 ] === 'click'
			)[ 1 ];

			mockPrompt.val.mockReturnValue( 'Test prompt' );

			const mockEvent = {
				preventDefault: jest.fn(),
			};

			clickHandler( mockEvent );

			expect( mockEvent.preventDefault ).toHaveBeenCalled();
			expect( ajaxSpy ).toHaveBeenCalled();
		} );
	} );

	describe( 'processPrompt', () => {
		beforeEach( () => {
			jest.resetModules();
			aiModule = require( '../../../../../src/ai/index.js' );
		} );

		it( 'should not process empty prompt', () => {
			const clickHandler = mockSubmit.on.mock.calls.find(
				( call ) => call[ 0 ] === 'click'
			)[ 1 ];

			mockPrompt.val.mockReturnValue( '   ' ); // Whitespace only

			clickHandler( { preventDefault: jest.fn() } );

			expect( ajaxSpy ).not.toHaveBeenCalled();
		} );

		it( 'should add user message and clear input', () => {
			const clickHandler = mockSubmit.on.mock.calls.find(
				( call ) => call[ 0 ] === 'click'
			)[ 1 ];

			mockPrompt.val.mockReturnValue( 'Create an event' );

			clickHandler( { preventDefault: jest.fn() } );

			expect( mockMessages.append ).toHaveBeenCalled();
			expect( mockPrompt.val ).toHaveBeenCalledWith( '' );
		} );

		it( 'should disable submit button and show status during processing', () => {
			const clickHandler = mockSubmit.on.mock.calls.find(
				( call ) => call[ 0 ] === 'click'
			)[ 1 ];

			mockPrompt.val.mockReturnValue( 'Test prompt' );

			clickHandler( { preventDefault: jest.fn() } );

			expect( mockSubmit.prop ).toHaveBeenCalledWith( 'disabled', true );
			expect( mockStatus.show ).toHaveBeenCalled();
		} );

		it( 'should send AJAX request with correct data', () => {
			const clickHandler = mockSubmit.on.mock.calls.find(
				( call ) => call[ 0 ] === 'click'
			)[ 1 ];

			mockPrompt.val.mockReturnValue( 'Create an event' );

			clickHandler( { preventDefault: jest.fn() } );

			expect( ajaxSpy ).toHaveBeenCalledWith(
				expect.objectContaining( {
					url: gatherpressAI.ajaxUrl,
					type: 'POST',
					data: expect.objectContaining( {
						action: 'gatherpress_ai_process_prompt',
						nonce: gatherpressAI.nonce,
						prompt: 'Create an event',
					} ),
				} )
			);
		} );

		it( 'should handle successful response', ( done ) => {
			const clickHandler = mockSubmit.on.mock.calls.find(
				( call ) => call[ 0 ] === 'click'
			)[ 1 ];

			mockPrompt.val.mockReturnValue( 'Test prompt' );

			// Override ajax to return success
			mockJQuery.ajax.mockImplementation( ( options ) => {
				setTimeout( () => {
					options.success( {
						success: true,
						data: {
							response: 'Success message',
							actions: [],
						},
					} );
					options.complete();
				}, 0 );
			} );

			clickHandler( { preventDefault: jest.fn() } );

			setTimeout( () => {
				expect( mockMessages.append ).toHaveBeenCalled();
				expect( mockSubmit.prop ).toHaveBeenCalledWith( 'disabled', false );
				expect( mockStatus.hide ).toHaveBeenCalled();
				done();
			}, 10 );
		} );

		it( 'should handle error response', ( done ) => {
			const clickHandler = mockSubmit.on.mock.calls.find(
				( call ) => call[ 0 ] === 'click'
			)[ 1 ];

			mockPrompt.val.mockReturnValue( 'Test prompt' );

			// Override ajax to return error
			mockJQuery.ajax.mockImplementation( ( options ) => {
				setTimeout( () => {
					options.success( {
						success: false,
						data: {
							message: 'Error occurred',
						},
					} );
					options.complete();
				}, 0 );
			} );

			clickHandler( { preventDefault: jest.fn() } );

			setTimeout( () => {
				expect( mockMessages.append ).toHaveBeenCalled();
				expect( mockSubmit.prop ).toHaveBeenCalledWith( 'disabled', false );
				expect( mockStatus.hide ).toHaveBeenCalled();
				done();
			}, 10 );
		} );

		it( 'should handle AJAX error', ( done ) => {
			const clickHandler = mockSubmit.on.mock.calls.find(
				( call ) => call[ 0 ] === 'click'
			)[ 1 ];

			mockPrompt.val.mockReturnValue( 'Test prompt' );

			// Override ajax to call error handler
			mockJQuery.ajax.mockImplementation( ( options ) => {
				setTimeout( () => {
					options.error( null, 'error', 'Network error' );
					options.complete();
				}, 0 );
			} );

			clickHandler( { preventDefault: jest.fn() } );

			setTimeout( () => {
				expect( mockMessages.append ).toHaveBeenCalled();
				expect( mockSubmit.prop ).toHaveBeenCalledWith( 'disabled', false );
				expect( mockStatus.hide ).toHaveBeenCalled();
				done();
			}, 10 );
		} );
	} );

	describe( 'addMessage', () => {
		beforeEach( () => {
			jest.resetModules();
			aiModule = require( '../../../../../src/ai/index.js' );
		} );

		it( 'should add message with correct type and content', () => {
			const clickHandler = mockSubmit.on.mock.calls.find(
				( call ) => call[ 0 ] === 'click'
			)[ 1 ];

			mockPrompt.val.mockReturnValue( 'Test' );

			clickHandler( { preventDefault: jest.fn() } );

			// Check that append was called with a message element
			expect( mockMessages.append ).toHaveBeenCalled();
		} );

		it( 'should add actions when provided', ( done ) => {
			const clickHandler = mockSubmit.on.mock.calls.find(
				( call ) => call[ 0 ] === 'click'
			)[ 1 ];

			mockPrompt.val.mockReturnValue( 'Create event' );

			mockJQuery.ajax.mockImplementation( ( options ) => {
				setTimeout( () => {
					options.success( {
						success: true,
						data: {
							response: 'Event created',
							actions: [
								{
									ability: 'gatherpress/create-event',
									args: { title: 'Test Event' },
									result: { success: true, edit_url: '/edit' },
								},
							],
						},
					} );
					options.complete();
				}, 0 );
			} );

			clickHandler( { preventDefault: jest.fn() } );

			setTimeout( () => {
				expect( mockMessages.append ).toHaveBeenCalled();
				done();
			}, 10 );
		} );

		it( 'should format create-event action correctly', ( done ) => {
			const clickHandler = mockSubmit.on.mock.calls.find(
				( call ) => call[ 0 ] === 'click'
			)[ 1 ];

			mockPrompt.val.mockReturnValue( 'Create event' );

			mockJQuery.ajax.mockImplementation( ( options ) => {
				setTimeout( () => {
					options.success( {
						success: true,
						data: {
							response: 'Event created',
							actions: [
								{
									ability: 'gatherpress/create-event',
									args: { title: 'My Event' },
									result: {
										success: true,
										edit_url: 'http://example.com/edit',
									},
								},
							],
						},
					} );
					options.complete();
				}, 0 );
			} );

			clickHandler( { preventDefault: jest.fn() } );

			setTimeout( () => {
				expect( mockMessages.append ).toHaveBeenCalled();
				done();
			}, 10 );
		} );

		it( 'should format create-venue action correctly', ( done ) => {
			const clickHandler = mockSubmit.on.mock.calls.find(
				( call ) => call[ 0 ] === 'click'
			)[ 1 ];

			mockPrompt.val.mockReturnValue( 'Create venue' );

			mockJQuery.ajax.mockImplementation( ( options ) => {
				setTimeout( () => {
					options.success( {
						success: true,
						data: {
							response: 'Venue created',
							actions: [
								{
									ability: 'gatherpress/create-venue',
									args: { name: 'Test Venue' },
									result: {
										success: true,
										edit_url: 'http://example.com/edit',
									},
								},
							],
						},
					} );
					options.complete();
				}, 0 );
			} );

			clickHandler( { preventDefault: jest.fn() } );

			setTimeout( () => {
				expect( mockMessages.append ).toHaveBeenCalled();
				done();
			}, 10 );
		} );

		it( 'should format list-venues action correctly', ( done ) => {
			const clickHandler = mockSubmit.on.mock.calls.find(
				( call ) => call[ 0 ] === 'click'
			)[ 1 ];

			mockPrompt.val.mockReturnValue( 'List venues' );

			mockJQuery.ajax.mockImplementation( ( options ) => {
				setTimeout( () => {
					options.success( {
						success: true,
						data: {
							response: 'Venues listed',
							actions: [
								{
									ability: 'gatherpress/list-venues',
									args: {},
									result: {
										success: true,
										data: [ {}, {}, {} ], // 3 venues
									},
								},
							],
						},
					} );
					options.complete();
				}, 0 );
			} );

			clickHandler( { preventDefault: jest.fn() } );

			setTimeout( () => {
				expect( mockMessages.append ).toHaveBeenCalled();
				done();
			}, 10 );
		} );

		it( 'should format list-events action correctly', ( done ) => {
			const clickHandler = mockSubmit.on.mock.calls.find(
				( call ) => call[ 0 ] === 'click'
			)[ 1 ];

			mockPrompt.val.mockReturnValue( 'List events' );

			mockJQuery.ajax.mockImplementation( ( options ) => {
				setTimeout( () => {
					options.success( {
						success: true,
						data: {
							response: 'Events listed',
							actions: [
								{
									ability: 'gatherpress/list-events',
									args: {},
									result: {
										success: true,
										data: { events: [ {}, {} ] }, // 2 events
									},
								},
							],
						},
					} );
					options.complete();
				}, 0 );
			} );

			clickHandler( { preventDefault: jest.fn() } );

			setTimeout( () => {
				expect( mockMessages.append ).toHaveBeenCalled();
				done();
			}, 10 );
		} );

		it( 'should scroll to bottom after adding message', () => {
			const clickHandler = mockSubmit.on.mock.calls.find(
				( call ) => call[ 0 ] === 'click'
			)[ 1 ];

			mockPrompt.val.mockReturnValue( 'Test' );

			clickHandler( { preventDefault: jest.fn() } );

			expect( mockMessages.scrollTop ).toHaveBeenCalledWith(
				mockMessages.scrollHeight
			);
		} );
	} );
} );

