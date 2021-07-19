<?php
/**
 * Test the [lifterlms_checkout] Shortcode
 *
 * @group shortcodes
 *
 * @since [version]
 * @version [version]
 */
class LLMS_Test_Shortcode_Checkout extends LLMS_ShortcodeTestCase {

	/**
	 * Test shortcode registration
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_registration() {
		$this->assertTrue( shortcode_exists( 'lifterlms_checkout' ) );
	}

	/**
	 * Test clean_form_fields
	 *
	 * @since [version]
	 *
	 * @return void
	 */
	public function test_clean_form_fields() {

		$checks = array(
			'<p></p>'               => '',
			'<p>a</p>'              => '<p>a</p>',
			"\n"                    => '',
			"\t"                    => '',
			"\n\r\t"                => '',
			"<p></p>\n<p>a</p>\r\t" => "<p></p>\n<p>a</p>\r\t",
		);

		foreach ( $checks as $check => $expect ) {
			$this->assertEquals( $expect, LLMS_Unit_Test_Util::call_method( 'LLMS_Shortcode_Checkout', 'clean_form_fields', array( $check ) ), $check );
		}

	}
}