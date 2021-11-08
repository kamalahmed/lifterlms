<?php
/**
 * Test LLMS_Certificates
 *
 * @package LifterLMS/Tests
 *
 * @group certificates
 *
 * @since 3.37.3
 * @since 4.21.0 Added tests on modify_dom_links() and modify_dom_images().
 * @version 4.21.0
 */
class LLMS_Test_Certificates extends LLMS_UnitTestCase {

	/**
	 * Retrieve a "real" image attachment ID
	 *
	 * @since [version]
	 *
	 * @return [type] [description]
	 */
	private function get_attachment() {

		// Fixes issue resulting from WP Core changes: https://github.com/gocodebox/lifterlms-groups/issues/137
		add_filter( 'wp_read_image_metadata_types', '__return_empty_array' );

		$file       = DIR_TESTDATA . '/images/waffles.jpg';
		$upload     = wp_upload_bits( basename( $file ), null, file_get_contents( $file ) );
		$attachment = $this->_make_attachment( $upload );

		remove_filter( 'wp_read_image_metadata_types', '__return_empty_array' );

		return $attachment;

	}

	/**
	 * Test get_default_image() and get_default_image_id()
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_get_default_image() {

		$opt_name = 'lifterlms_certificate_default_bg_img';

		// Non-existent option.
		delete_option( $opt_name );
		$this->assertEquals( 0, llms()->certificates()->get_default_image_id() );
		$this->assertStringContainsString( '/default-certificate.png', llms()->certificates()->get_default_image( 123 ) );

		// Empty option
		update_option( $opt_name, '' );
		$this->assertEquals( 0, llms()->certificates()->get_default_image_id() );
		$this->assertStringContainsString( '/default-certificate.png', llms()->certificates()->get_default_image( 123 ) );

		// Non-existent attachment.
		update_option( $opt_name, 123 );
		$this->assertEquals( 0, llms()->certificates()->get_default_image_id() );
		$this->assertStringContainsString( '/default-certificate.png', llms()->certificates()->get_default_image( 123 ) );

		// A "real" attachment.
		$attachment_id = $this->get_attachment();
		update_option( $opt_name, $attachment_id );
		$this->assertEquals( $attachment_id, llms()->certificates()->get_default_image_id() );
		$this->assertMatchesRegularExpression(
			'#http:\/\/example.org\/wp-content\/uploads\/\d{4}\/\d{2}\/waffles(-)?\d*.jpg#',
			llms()->certificates()->get_default_image( $attachment_id )
		);

	}

	/**
	 * Test trigger_engagement() method.
	 *
	 * @since 3.37.3
	 * @since 3.37.4 Use `$this->create_certificate_template()` from test case base.
	 * @since [version] Expect deprecated warning and actually call the method instead of using the abstract method `earn_certificate()`.
	 *
	 * @expectedDeprecated LLMS_Certificates::handle_certificate()
	 *
	 * @return void
	 */
	public function test_trigger_engagement() {

		$user = $this->factory->user->create();
		$template = $this->create_certificate_template();
		$related = $this->factory->post->create( array( 'post_type' => 'course' ) );

		llms_enroll_student( $user, $related );

		llms()->certificates()->trigger_engagement( $user, $template, $related );

		$student = llms_get_student( $user );

		$earned = $student->get_certificates()[0];

		// Related ID matches.
		$this->assertEquals( $related, $earned->post_id );

	}

	/**
	 * Retrieve a certificate export, bypassing the cache.
	 *
	 * @since 3.37.3
	 * @since 3.37.4 Use `$this->create_certificate_template()` from test case base.
	 *
	 * @return void
	 */
	public function test_get_export_no_cache() {

		$user = $this->factory->user->create();
		$template = $this->create_certificate_template();
		$related = $this->factory->post->create( array( 'post_type' => 'course' ) );

		$earned = $this->earn_certificate( $user, $template, $related );

		$cert_id = $earned[1];

		$path = llms()->certificates()->get_export( $cert_id );
		$this->assertTrue( false !== strpos( $path, '/uploads/llms-tmp/certificate-mock-certificate-title' ) );
		$this->assertTrue( false !== strpos( $path, '.html' ) );

	}

	/**
	 * Retrieve a certificate export using caching.
	 *
	 * @since 3.37.3
	 * @since 3.37.4 Use `$this->create_certificate_template()` from test case base.
	 *
	 * @return void
	 */
	public function test_get_export_with_cache() {

		$user = $this->factory->user->create();
		$template = $this->create_certificate_template();
		$related = $this->factory->post->create( array( 'post_type' => 'course' ) );

		$earned = $this->earn_certificate( $user, $template, $related );

		$cert_id = $earned[1];

		// Generate a new cert when item not found in the cache.
		$orig_path = LLMS()->certificates()->get_export( $cert_id, true );
		$this->assertTrue( false !== strpos( $orig_path, '/uploads/llms-tmp/certificate-mock-certificate-title' ) );

		// Store the filepath for future use.
		$this->assertEquals( $orig_path, get_post_meta( $cert_id, '_llms_export_filepath', true ) );

		// Get it again, should return the original path from the cache.
		$cached_path = LLMS()->certificates()->get_export( $cert_id, true );
		$this->assertEquals( $orig_path, $cached_path );

		// Delete the file (simulate LLMS_TMP_DIR file expiration).
		unlink( $orig_path );

		// Should regen since the file saved in meta data doesn't exist anymore.
		$new_path = LLMS()->certificates()->get_export( $cert_id, true );
		$this->assertTrue( $orig_path !== $new_path );

	}

	/**
	 * Test modify_dom_links()
	 *
	 * @since 4.21.0
	 *
	 * @return void
	 */
	public function test_modify_dom_links() {

		// Copy test CSSs to the local website for testing purpose.
		LLMS_Unit_Test_Files::copy_asset( 'example-style-1.css', WP_CONTENT_DIR );
		LLMS_Unit_Test_Files::copy_asset( 'example-style-2.css', WP_CONTENT_DIR );

		$stylesheet_hrefs = array(
			get_site_url() . '/wp-content/example-style-1.css'                                                                                                                      => true, // Local.
			get_home_url() . '/wp-content/example-style-2.css'                                                                                                                      => true, // Local.
			'https://fonts.googleapis.com/css?family=Open+Sans:300italic,400italic,600italic,700italic,800italic,400,300,600,700,800&#038;subset=latin,latin-ext&#038;display=swap' => false, // Blocked host.
			'https://raw.githubusercontent.com/gocodebox/lifterlms/trunk/tests/assets/example-style.css'                                                                            => true,
			'https://unreacha.ble/style.css'                                                                                                                                        => false,
		);

		$dom = $this->_get_certificate_dom(
			array(
				'head' => array_reduce(
					array_keys( $stylesheet_hrefs ),
					function( $carry, $stylesheet_href ) {
						return sprintf(
							'%1$s<link rel="stylesheet" href="%2$s" type="test/css" media="all">',
							$carry,
							$stylesheet_href
						);
					}
				)
			)
		);

		LLMS_Unit_Test_Util::call_method(
			LLMS()->certificates(),
			'modify_dom_links',
			array( $dom )
		);

		$dom->saveHTML();

		// Test there are no survived link tags (stylesheets are inlined).
		$this->assertEmpty( $dom->getElementsByTagName( 'link' )->length );

		$head = $dom->getElementsByTagName( 'head' )->item(0)->nodeValue;

		foreach ( $stylesheet_hrefs as $stylesheet_href => $contained ) {

			$stylesheet_raw = LLMS_Unit_Test_Util::call_method(
				LLMS()->certificates(),
				'get_stylesheet_raw',
				array( $stylesheet_href, false )
			);

			if ( ! $stylesheet_raw ) {
				$this->assertFalse( $contained, $stylesheet_href );
				continue;
			}

			if ( $contained ) {
				$this->assertStringContainsString(
					$stylesheet_raw,
					$head,
					$stylesheet_href
				);
			} else {
				$this->assertStringNotContainsString(
					$stylesheet_raw,
					$head,
					$stylesheet_href
				);
			}
		}

		// Delete copied assets.
		LLMS_Unit_Test_Files::remove( WP_CONTENT_DIR . '/example-style-1.css' );
		LLMS_Unit_Test_Files::remove( WP_CONTENT_DIR . '/example-style-2.css' );

	}


	/**
	 * Test modify_dom_images()
	 *
	 * @since 4.21.0
	 *
	 * @return void
	 */
	public function test_modify_dom_images() {

		// Copy test images to the local website for testing purpose.
		LLMS_Unit_Test_Files::copy_asset( 'klim-musalimov-rDMacl1FDjw-unsplash.jpeg', WP_CONTENT_DIR );
		LLMS_Unit_Test_Files::copy_asset( 'yura-timoshenko-R7ftweJR8ks-unsplash.jpeg', WP_CONTENT_DIR );

		$image_srcs = array(
			get_site_url() . '/wp-content/klim-musalimov-rDMacl1FDjw-unsplash.jpeg'                                   => true, // Local.
			get_home_url() . '/wp-content/yura-timoshenko-R7ftweJR8ks-unsplash.jpeg'                                  => true, // Local.
			'https://upload.wikimedia.org/wikipedia/commons/a/a9/Example.jpg'                                         => false, // Blocked host.
			'https://raw.githubusercontent.com/gocodebox/lifterlms/trunk/tests/assets/christian-fregnan-unsplash.jpg' => true,
			'https://unreach.able/christian-fregnan-unsplash.jpg'                                                     => false,
		);

		$dom = $this->_get_certificate_dom(
			array(
				'certificate' => array_reduce(
					array_keys( $image_srcs ),
					function( $carry, $image_src ) {
						return sprintf(
							'%1$s<img src="%2$s" loading="lazy" srcset="%2$s 320w" sizes="(max-width: 320px) 280px">',
							$carry,
							$image_src
						);
					}
				)
			)
		);

		// Block wikimedia host.
		add_filter(
			'llms_certificate_export_blocked_image_hosts',
			function () {
				return array(
					'upload.wikimedia.org'
				);
			}
		);

		// Re-init certificates to apply the filter above.
		LLMS()->certificates()->init();

		// Modify DOM images.
		LLMS_Unit_Test_Util::call_method(
			LLMS()->certificates(),
			'modify_dom_images',
			array( $dom )
		);

		$html = $dom->saveHTML();

		foreach ( $image_srcs as $image_src => $contained ) {

			// Test the image src URLS are removed.
			$this->assertStringNotContainsString(
				$image_src,
				$html,
				$image_src
			);

			$image_data_type = LLMS_Unit_Test_Util::call_method(
				LLMS()->certificates(),
				'get_image_data_and_type',
				array( $image_src, false )
			);

			if ( empty( $image_data_type['data'] ) || empty( $image_data_type['type'] ) ) {
				$this->assertFalse( $contained, $image_src );
				continue;
			}

			$image_data = base64_encode( $image_data_type['data'] );

			if ( $contained ) {
				$this->assertStringContainsString(
					$image_data,
					$html,
					$image_src
				);
			} else {
				$this->assertStringNotContainsString(
					$image_data,
					$html,
					$image_src
				);
			}

		}

		// Get images do not have loading, sizes, and srcset attibutes.
		foreach ( $dom->getElementsByTagName( 'img' ) as $img ) {
			$this->assertEmpty( $img->getAttribute( 'srcset' ) );
			$this->assertEmpty( $img->getAttribute( 'sizes' ) );
			$this->assertEmpty( $img->getAttribute( 'loading' ) );
		}

		// Clean added filters.
		remove_all_filters( 'llms_certificate_export_blocked_image_hosts' );

		// Delete copied images.
		LLMS_Unit_Test_Files::remove( WP_CONTENT_DIR . '/klim-musalimov-rDMacl1FDjw-unsplash.jpeg' );
		LLMS_Unit_Test_Files::remove( WP_CONTENT_DIR . '/yura-timoshenko-R7ftweJR8ks-unsplash.jpeg' );

	}

	/**
	 * Util to build a DOMDocument similar to the scraped certificate
	 *
	 * @since 4.21.0
	 *
	 * @param array $dom_sections Sections of the page.
	 * @return DOMDocument|WP_Error
	 */
	private function _get_certificate_dom( $dom_sections ) {
		$sections = array(
			'head'         => '',
			'certificate'  => '',
			'footer'       => '',
		);

		$sections = wp_parse_args( $dom_sections, $sections );

		$html = '
		<!DOCTYPE html>
<html lang="en-US">
	<head>
		<meta charset="UTF-8">
		'
		. $sections['head']  .
		'
	</head>
	<body>
		<div class="llms-certificate-container" style="width:800px; height:616px;">
			<div id="certificate-243" class="post-243 llms_certificate type-llms_certificate status-publish hentry">
				<div class="llms-summary">'
				. $sections['certificate'] .
				'</div>
			</div>
		</div>
		<footer>'
		. $sections['footer'] .
		'</footer>
	</body>
</html>';

		$dom = llms_get_dom_document( $html );
		if ( is_wp_error( $dom ) ) {
			return $dom;
		}

		// Don't throw or log warnings.
		$libxml_state = libxml_use_internal_errors( true );

		return $dom;
	}

}
