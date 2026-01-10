<?php
/**
 * Class handles unit tests for GatherPress\Core\Rsvp_Token.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp_Token;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Comment;
use WP_Post;

/**
 * Class Test_Rsvp_Token.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp_Token
 */
class Test_Rsvp_Token extends Base {
	/**
	 * Coverage for __construct method with invalid comment ID.
	 *
	 * @covers ::__construct
	 * @covers ::is_valid_rsvp_comment
	 *
	 * @return void
	 */
	public function test___construct_with_invalid_comment_id(): void {
		$token = new Rsvp_Token( 0 );
		$this->assertNull( $token->get_comment() );

		$token = new Rsvp_Token( -1 );
		$this->assertNull( $token->get_comment() );
	}

	/**
	 * Coverage for __construct method with non-existent comment.
	 *
	 * @covers ::__construct
	 * @covers ::is_valid_rsvp_comment
	 *
	 * @return void
	 */
	public function test___construct_with_non_existent_comment(): void {
		$token = new Rsvp_Token( 999999 );
		$this->assertNull( $token->get_comment() );
	}

	/**
	 * Coverage for __construct method with invalid comment type.
	 *
	 * @covers ::__construct
	 * @covers ::is_valid_rsvp_comment
	 *
	 * @return void
	 */
	public function test___construct_with_invalid_comment_type(): void {
		$post_id    = $this->factory->post->create();
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => 'comment',
			)
		);

		$token = new Rsvp_Token( $comment_id );
		$this->assertNull( $token->get_comment() );
	}

	/**
	 * Coverage for __construct method with valid RSVP comment.
	 *
	 * @covers ::__construct
	 * @covers ::is_valid_rsvp_comment
	 *
	 * @return void
	 */
	public function test___construct_with_valid_rsvp_comment(): void {
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID'      => $post->ID,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author_email' => 'test@example.com',
			)
		);

		$token = new Rsvp_Token( $comment_id );
		$this->assertInstanceOf( WP_Comment::class, $token->get_comment() );
		$this->assertEquals( $comment_id, $token->get_comment()->comment_ID );
	}

	/**
	 * Coverage for get_token method when no token exists.
	 *
	 * @covers ::get_token
	 * @covers ::get_meta_key
	 *
	 * @return void
	 */
	public function test_get_token_when_no_token_exists(): void {
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post->ID,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$token = new Rsvp_Token( $comment_id );
		$this->assertEmpty( $token->get_token() );
	}

	/**
	 * Coverage for get_token method when token exists.
	 *
	 * @covers ::get_token
	 * @covers ::get_meta_key
	 *
	 * @return void
	 */
	public function test_get_token_when_token_exists(): void {
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post->ID,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$expected_token = 'test-token-123';
		update_comment_meta( $comment_id, '_gatherpress_rsvp_token', $expected_token );

		$token = new Rsvp_Token( $comment_id );
		$this->assertEquals( $expected_token, $token->get_token() );
	}

	/**
	 * Coverage for get_token method caching.
	 *
	 * @covers ::get_token
	 *
	 * @return void
	 */
	public function test_get_token_caching(): void {
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post->ID,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$expected_token = 'cached-token-456';
		update_comment_meta( $comment_id, '_gatherpress_rsvp_token', $expected_token );

		$token = new Rsvp_Token( $comment_id );

		// First call should retrieve from meta.
		$this->assertEquals( $expected_token, $token->get_token() );

		// Change the meta value.
		update_comment_meta( $comment_id, '_gatherpress_rsvp_token', 'different-token' );

		// Second call should return cached value.
		$this->assertEquals( $expected_token, $token->get_token() );
	}

	/**
	 * Coverage for get_token method with no comment.
	 *
	 * @covers ::get_token
	 *
	 * @return void
	 */
	public function test_get_token_with_no_comment(): void {
		$token = new Rsvp_Token( 0 );
		$this->assertEmpty( $token->get_token() );
	}

	/**
	 * Coverage for generate_token method.
	 *
	 * @covers ::generate_token
	 * @covers ::create_secure_token
	 * @covers ::save_token_to_meta
	 *
	 * @return void
	 */
	public function test_generate_token(): void {
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post->ID,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$token  = new Rsvp_Token( $comment_id );
		$result = $token->generate_token();

		// Should return self for method chaining.
		$this->assertSame( $token, $result );

		// Should generate a token of correct length.
		$generated_token = $token->get_token();
		$this->assertNotEmpty( $generated_token );
		$this->assertEquals( Rsvp_Token::TOKEN_LENGTH, strlen( $generated_token ) );

		// Should save to comment meta.
		$meta_token = get_comment_meta( $comment_id, '_gatherpress_rsvp_token', true );
		$this->assertEquals( $generated_token, $meta_token );
	}

	/**
	 * Coverage for generate_token method with no comment.
	 *
	 * @covers ::generate_token
	 *
	 * @return void
	 */
	public function test_generate_token_with_no_comment(): void {
		$token  = new Rsvp_Token( 0 );
		$result = $token->generate_token();

		// Should return self for method chaining.
		$this->assertSame( $token, $result );

		// Should not generate a token.
		$this->assertEmpty( $token->get_token() );
	}

	/**
	 * Coverage for approve_comment method.
	 *
	 * @covers ::approve_comment
	 *
	 * @return void
	 */
	public function test_approve_comment(): void {
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID'  => $post->ID,
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_approved' => '0',
			)
		);

		$token = new Rsvp_Token( $comment_id );
		$token->approve_comment();

		$comment = get_comment( $comment_id );
		$this->assertEquals( '1', $comment->comment_approved );
	}

	/**
	 * Coverage for approve_comment method with no comment.
	 *
	 * @covers ::approve_comment
	 *
	 * @return void
	 */
	public function test_approve_comment_with_no_comment(): void {
		$token = new Rsvp_Token( 0 );

		// Should not throw an error.
		$token->approve_comment();
		$this->assertTrue( true );
	}

	/**
	 * Coverage for get_comment method.
	 *
	 * @covers ::get_comment
	 *
	 * @return void
	 */
	public function test_get_comment(): void {
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post->ID,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$token   = new Rsvp_Token( $comment_id );
		$comment = $token->get_comment();

		$this->assertInstanceOf( WP_Comment::class, $comment );
		$this->assertEquals( $comment_id, $comment->comment_ID );
	}

	/**
	 * Coverage for get_post method.
	 *
	 * @covers ::get_post
	 *
	 * @return void
	 */
	public function test_get_post(): void {
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post->ID,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$token          = new Rsvp_Token( $comment_id );
		$retrieved_post = $token->get_post();

		$this->assertInstanceOf( WP_Post::class, $retrieved_post );
		$this->assertEquals( $post->ID, $retrieved_post->ID );
		$this->assertEquals( Event::POST_TYPE, $retrieved_post->post_type );
	}

	/**
	 * Coverage for get_post method with no comment.
	 *
	 * @covers ::get_post
	 *
	 * @return void
	 */
	public function test_get_post_with_no_comment(): void {
		$token = new Rsvp_Token( 0 );
		$this->assertNull( $token->get_post() );
	}

	/**
	 * Coverage for get_post method with invalid post type.
	 *
	 * @covers ::get_post
	 *
	 * @return void
	 */
	public function test_get_post_with_invalid_post_type(): void {
		$post = $this->factory->post->create( array( 'post_type' => 'post' ) );

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$token = new Rsvp_Token( $comment_id );
		$this->assertNull( $token->get_post() );
	}

	/**
	 * Coverage for get_email method.
	 *
	 * @covers ::get_email
	 *
	 * @return void
	 */
	public function test_get_email(): void {
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		$email      = 'test@example.com';
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID'      => $post->ID,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author_email' => $email,
			)
		);

		$token = new Rsvp_Token( $comment_id );
		$this->assertEquals( $email, $token->get_email() );
	}

	/**
	 * Coverage for get_email method with no comment.
	 *
	 * @covers ::get_email
	 *
	 * @return void
	 */
	public function test_get_email_with_no_comment(): void {
		$token = new Rsvp_Token( 0 );
		$this->assertEmpty( $token->get_email() );
	}

	/**
	 * Coverage for is_valid method.
	 *
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_is_valid(): void {
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post->ID,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$token = new Rsvp_Token( $comment_id );
		$token->generate_token();
		$generated_token = $token->get_token();

		// Valid token.
		$this->assertTrue( $token->is_valid( $generated_token ) );

		// Invalid token.
		$this->assertFalse( $token->is_valid( 'invalid-token' ) );

		// Empty token.
		$this->assertFalse( $token->is_valid( '' ) );
	}

	/**
	 * Coverage for generate_url method.
	 *
	 * @covers ::generate_url
	 * @covers ::has_required_url_components
	 * @covers ::format_token_value
	 *
	 * @return void
	 */
	public function test_generate_url(): void {
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post->ID,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$token = new Rsvp_Token( $comment_id );
		$token->generate_token();

		$url = $token->generate_url();
		$this->assertNotEmpty( $url );

		// Check that URL contains the token parameter.
		$this->assertStringContainsString( Rsvp_Token::NAME, $url );

		// Check that URL contains the comment ID and token.
		$expected_token_value = sprintf( '%d_%s', $comment_id, $token->get_token() );
		$this->assertStringContainsString( $expected_token_value, $url );

		// Check that URL contains the event permalink.
		$this->assertStringContainsString( get_permalink( $post ), $url );
	}

	/**
	 * Coverage for generate_url method with no token.
	 *
	 * @covers ::generate_url
	 * @covers ::has_required_url_components
	 *
	 * @return void
	 */
	public function test_generate_url_with_no_token(): void {
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post->ID,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$token = new Rsvp_Token( $comment_id );
		$this->assertEmpty( $token->generate_url() );
	}

	/**
	 * Coverage for generate_url method with no comment.
	 *
	 * @covers ::generate_url
	 * @covers ::has_required_url_components
	 *
	 * @return void
	 */
	public function test_generate_url_with_no_comment(): void {
		$token = new Rsvp_Token( 0 );
		$this->assertEmpty( $token->generate_url() );
	}

	/**
	 * Coverage for generate_url method when permalink is unavailable.
	 *
	 * @covers ::generate_url
	 *
	 * @return void
	 */
	public function test_generate_url_with_no_permalink(): void {
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post->ID,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$token = new Rsvp_Token( $comment_id );
		$token->generate_token();

		// Filter get_permalink to return false.
		$filter = static function () {
			return false;
		};
		add_filter( 'post_type_link', $filter );

		$this->assertFalse( get_permalink( $post ) );
		$this->assertEmpty( $token->generate_url() );

		remove_filter( 'post_type_link', $filter );
	}

	/**
	 * Coverage for send_rsvp_confirmation_email method.
	 *
	 * @covers ::send_rsvp_confirmation_email
	 * @covers ::prepare_email_data
	 * @covers ::render_email_template
	 *
	 * @return void
	 */
	public function test_send_rsvp_confirmation_email(): void {
		$post = $this->mock->post(
			array(
				'post_type'  => Event::POST_TYPE,
				'post_title' => 'Test Event',
			)
		)->get();

		$email      = 'test@example.com';
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID'      => $post->ID,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author_email' => $email,
			)
		);

		$token = new Rsvp_Token( $comment_id );
		$token->generate_token();

		// Mock wp_mail to return true.
		add_filter( 'pre_wp_mail', '__return_true' );

		$result = $token->send_rsvp_confirmation_email();
		$this->assertTrue( $result );

		// Remove the filter.
		remove_filter( 'pre_wp_mail', '__return_true' );
	}

	/**
	 * Coverage for send_rsvp_confirmation_email method with no email.
	 *
	 * @covers ::send_rsvp_confirmation_email
	 *
	 * @return void
	 */
	public function test_send_rsvp_confirmation_email_with_no_email(): void {
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID'      => $post->ID,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author_email' => '',
			)
		);

		$token = new Rsvp_Token( $comment_id );
		$this->assertFalse( $token->send_rsvp_confirmation_email() );
	}

	/**
	 * Coverage for class constants.
	 *
	 * @return void
	 */
	public function test_constants(): void {
		$this->assertEquals( 'gatherpress_rsvp_token', Rsvp_Token::NAME );
		$this->assertEquals( 32, Rsvp_Token::TOKEN_LENGTH );
		$this->assertEquals( '_', Rsvp_Token::META_KEY_PREFIX );
	}

	/**
	 * Coverage for get_meta_key method.
	 *
	 * @covers ::get_meta_key
	 *
	 * @return void
	 */
	public function test_get_meta_key(): void {
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post->ID,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$token    = new Rsvp_Token( $comment_id );
		$meta_key = Utility::invoke_hidden_method( $token, 'get_meta_key' );

		$this->assertEquals( '_gatherpress_rsvp_token', $meta_key );
	}

	/**
	 * Coverage for create_secure_token method.
	 *
	 * @covers ::create_secure_token
	 *
	 * @return void
	 */
	public function test_create_secure_token(): void {
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post->ID,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$token        = new Rsvp_Token( $comment_id );
		$secure_token = Utility::invoke_hidden_method( $token, 'create_secure_token' );

		$this->assertIsString( $secure_token );
		$this->assertEquals( Rsvp_Token::TOKEN_LENGTH, strlen( $secure_token ) );

		// Generate another token to ensure they're different.
		$another_token = Utility::invoke_hidden_method( $token, 'create_secure_token' );
		$this->assertNotEquals( $secure_token, $another_token );
	}

	/**
	 * Coverage for format_token_value method.
	 *
	 * @covers ::format_token_value
	 *
	 * @return void
	 */
	public function test_format_token_value(): void {
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post->ID,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$token      = new Rsvp_Token( $comment_id );
		$test_token = 'test-token-123';

		$formatted_value = Utility::invoke_hidden_method(
			$token,
			'format_token_value',
			array( $comment_id, $test_token )
		);

		$expected = sprintf( '%d_%s', $comment_id, $test_token );
		$this->assertEquals( $expected, $formatted_value );
	}

	/**
	 * Coverage for has_required_url_components method.
	 *
	 * @covers ::has_required_url_components
	 *
	 * @return void
	 */
	public function test_has_required_url_components(): void {
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		$comment = $this->factory->comment->create_and_get(
			array(
				'comment_post_ID' => $post->ID,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$token_instance = new Rsvp_Token( $comment->comment_ID );

		// All valid components.
		$result = Utility::invoke_hidden_method(
			$token_instance,
			'has_required_url_components',
			array( $post, $comment, 'valid-token' )
		);
		$this->assertTrue( $result );

		// Null post.
		$result = Utility::invoke_hidden_method(
			$token_instance,
			'has_required_url_components',
			array( null, $comment, 'valid-token' )
		);
		$this->assertFalse( $result );

		// Null comment.
		$result = Utility::invoke_hidden_method(
			$token_instance,
			'has_required_url_components',
			array( $post, null, 'valid-token' )
		);
		$this->assertFalse( $result );

		// Empty token.
		$result = Utility::invoke_hidden_method(
			$token_instance,
			'has_required_url_components',
			array( $post, $comment, '' )
		);
		$this->assertFalse( $result );
	}

	/**
	 * Coverage for parse_token_string static method.
	 *
	 * @covers ::parse_token_string
	 *
	 * @return void
	 */
	public function test_parse_token_string(): void {
		// Valid token string.
		$result = Rsvp_Token::parse_token_string( '123_test-token-abc' );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'comment_id', $result );
		$this->assertArrayHasKey( 'token', $result );
		$this->assertEquals( 123, $result['comment_id'] );
		$this->assertEquals( 'test-token-abc', $result['token'] );

		// Token with underscore in token part.
		$result = Rsvp_Token::parse_token_string( '456_token_with_underscores' );
		$this->assertEquals( 456, $result['comment_id'] );
		$this->assertEquals( 'token_with_underscores', $result['token'] );

		// Empty string.
		$result = Rsvp_Token::parse_token_string( '' );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );

		// Null string.
		$result = Rsvp_Token::parse_token_string( null );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );

		// Invalid format - no underscore.
		$result = Rsvp_Token::parse_token_string( 'invalid-token' );
		$this->assertEmpty( $result );

		// Invalid format - no comment ID.
		$result = Rsvp_Token::parse_token_string( '_token-only' );
		$this->assertEmpty( $result );

		// Invalid format - non-numeric comment ID.
		$result = Rsvp_Token::parse_token_string( 'abc_token' );
		$this->assertEmpty( $result );

		// Invalid format - just underscore.
		$result = Rsvp_Token::parse_token_string( '_' );
		$this->assertEmpty( $result );

		// Zero comment ID (technically valid format but invalid ID).
		$result = Rsvp_Token::parse_token_string( '0_token' );
		$this->assertEquals( 0, $result['comment_id'] );
		$this->assertEquals( 'token', $result['token'] );
	}

	/**
	 * Coverage for from_token_string static factory method.
	 *
	 * @covers ::from_token_string
	 * @covers ::parse_token_string
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_from_token_string(): void {
		// Create a valid RSVP comment with token.
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID'      => $post->ID,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author_email' => 'test@example.com',
			)
		);

		// Generate token for this comment.
		$token = new Rsvp_Token( $comment_id );
		$token->generate_token();
		$generated_token = $token->get_token();

		// Test valid token string.
		$token_string = sprintf( '%d_%s', $comment_id, $generated_token );
		$result       = Rsvp_Token::from_token_string( $token_string );

		$this->assertInstanceOf( Rsvp_Token::class, $result );
		$this->assertNotNull( $result->get_comment() );
		$this->assertEquals( $comment_id, $result->get_comment()->comment_ID );
		$this->assertEquals( 'test@example.com', $result->get_email() );

		// Test invalid token for same comment.
		$invalid_token_string = sprintf( '%d_%s', $comment_id, 'invalid-token' );
		$result               = Rsvp_Token::from_token_string( $invalid_token_string );
		$this->assertNull( $result );

		// Test empty string.
		$result = Rsvp_Token::from_token_string( '' );
		$this->assertNull( $result );

		// Test null string.
		$result = Rsvp_Token::from_token_string( null );
		$this->assertNull( $result );

		// Test malformed token string.
		$result = Rsvp_Token::from_token_string( 'not-a-valid-token' );
		$this->assertNull( $result );

		// Test non-existent comment ID.
		$result = Rsvp_Token::from_token_string( '999999_some-token' );
		$this->assertNull( $result );

		// Test with non-RSVP comment.
		$regular_comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post->ID,
				'comment_type'    => 'comment',
			)
		);
		$result             = Rsvp_Token::from_token_string( sprintf( '%d_token', $regular_comment_id ) );
		$this->assertNull( $result );
	}

	/**
	 * Test that from_token_string returns null for zero comment ID.
	 *
	 * @covers ::from_token_string
	 *
	 * @return void
	 */
	public function test_from_token_string_with_zero_comment_id(): void {
		$result = Rsvp_Token::from_token_string( '0_some-token' );
		$this->assertNull( $result );
	}

	/**
	 * Test integration between parse_token_string and from_token_string.
	 *
	 * @covers ::from_token_string
	 * @covers ::parse_token_string
	 *
	 * @return void
	 */
	public function test_static_methods_integration(): void {
		// Create valid RSVP.
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post->ID,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$token = new Rsvp_Token( $comment_id );
		$token->generate_token();

		// Get the URL which includes the formatted token.
		$url = $token->generate_url();
		$this->assertNotEmpty( $url );

		// Extract token from URL.
		$parsed_url = wp_parse_url( $url );
		parse_str( $parsed_url['query'], $query_args );
		$token_string = $query_args[ Rsvp_Token::NAME ];

		// Parse should return valid array.
		$parsed = Rsvp_Token::parse_token_string( $token_string );
		$this->assertNotEmpty( $parsed );
		$this->assertEquals( $comment_id, $parsed['comment_id'] );

		// from_token_string should return valid instance.
		$new_instance = Rsvp_Token::from_token_string( $token_string );
		$this->assertInstanceOf( Rsvp_Token::class, $new_instance );
		$this->assertEquals( $comment_id, $new_instance->get_comment()->comment_ID );
	}

	/**
	 * Tests from_url_parameter static factory method.
	 *
	 * Verifies that the method correctly creates an instance from mocked GET parameters.
	 *
	 * @since 1.0.0
	 * @covers ::from_url_parameter
	 *
	 * @return void
	 */
	public function test_from_url_parameter(): void {
		$comment_id = $this->factory()->comment->create(
			array(
				'comment_post_ID' => $this->factory()->post->create( array( 'post_type' => Event::POST_TYPE ) ),
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$instance = new Rsvp_Token( $comment_id );
		$instance->generate_token();
		$token        = $instance->get_token();
		$token_string = sprintf( '%d_%s', $comment_id, $token );

		// Set up mock data using pre_ filter.
		add_filter(
			'gatherpress_pre_get_http_input',
			function ( $pre_value, $type, $var_name ) use ( $token_string ) {
				if ( INPUT_GET === $type && Rsvp_Token::NAME === $var_name ) {
					return $token_string;
				}
				return null;
			},
			10,
			3
		);

		// Test successful creation from URL parameter.
		$result = Rsvp_Token::from_url_parameter();
		$this->assertInstanceOf( Rsvp_Token::class, $result );
		$this->assertEquals( $comment_id, $result->get_comment()->comment_ID );

		// Test with no token parameter.
		remove_all_filters( 'gatherpress_pre_get_http_input' );
		add_filter(
			'gatherpress_pre_get_http_input',
			static function () {
				return null;
			}
		);

		$result = Rsvp_Token::from_url_parameter();
		$this->assertNull( $result );

		// Clean up filters.
		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}
}
