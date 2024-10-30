<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

class GF_Field_Kineticpay extends GF_Field {

	public $type = 'kineticpay';

	public function get_form_editor_field_title() {
		return esc_attr__( 'Kineticpay', 'gravityforms' );
	}

	/**
	 * Returns the field's form editor description.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_form_editor_field_description() {
		return esc_attr__( 'Places this block of kineticpay in your form.', 'gravityforms' );
	}

	/**
	 * Returns the field's form editor icon.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_form_editor_field_icon() {
		return 'gform-icon--kineticpay';
	}
	
	/**
	 * Returns the scripts to be included for this field type in the form editor.
	 *
	 * @since  1.0.0
	 *
	 * @return string
	 */
	public function get_form_editor_inline_script_on_page_render() {
		$js = /** @lang JavaScript */ "
			gform.addFilter('gform_form_editor_can_field_be_added', function(result, type) {
				if (type === 'kineticpay') {
				    if (GetFieldsByType(['kineticpay']).length > 0) {" .
				        sprintf( "alert(%s);", json_encode( esc_html__( 'Only one Kineticpay field can be added to the form', 'gravityformsstripe' ) ) )
				       . " result = false;
					}
				}
				
				return result;
			});
		";
		return $js;
	}
	
	/**
	 * Get form editor button.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_form_editor_button() {
		return array(
			'group' => 'pricing_fields',
			'text'  => $this->get_form_editor_field_title(),
		);
	}

	function get_form_editor_field_settings() {
		return array(
			'css_class_setting',
		);
	}

	public function get_field_input( $form, $value = '', $entry = null ) {

		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$imagesrc = GF_KINETICPAY_URL . 'assets/images/kineticpay.png';
		$button_lang = __("Pay with kineticpay", "give-kineticpay");		
		$title = __('Pay With Kineticpay', 'give-kineticpay');
		$banks = '<style>.kineticpay-title{align-items: center;display: flex;}.kineticpay-logo{width: 100px;margin-left: 20px;}#bank_id{height: 50px;padding: 10px;border: 1px solid #253d80;}#bank_id option{font-size: 14px;font-weight: 500;color: #253d80;padding: 20px;}</style><div class="customs-select" style="margin-top: 10px; margin-bottom: 20px;">
		<h3 class="kineticpay-title">'.$title.'<img class="rounded kineticpay-logo" src="http://localhost/dev/wp-content/plugins/kn/assets/images/kineticpay.png"></h3>
		<label style="font-weight: 600;">Select Bank:</label>
		<select id="bank_id" name="bank_id" required>
			<option value="">Select Your Bank</option>
			<option value="ABMB0212">Alliance Bank Malaysia Berhad</option>
			<option value="ABB0233">Affin Bank Berhad</option>
			<option value="AMBB0209">Ambank (M) Berhad</option>
			<option value="BCBB0235">CIMB Bank Berhad</option>
			<option value="BIMB0340">Bank Islam Malaysia Berhad</option>
			<option value="BKRM0602">Bank Kerjasama Rakyat Malaysia Berhad</option>
			<option value="BMMB0341">Bank Muamalat Malaysia Berhad</option>
			<option value="BSN0601">Bank Simpanan Nasional</option>
			<option value="CIT0219">Citibank Berhad</option>
			<option value="HLB0224">Hong Leong Bank Berhad</option>
			<option value="HSBC0223">HSBC Bank Malaysia Berhad</option>
			<option value="KFH0346">Kuwait Finance House</option>
			<option value="MB2U0227">Maybank2u / Malayan Banking Berhad</option>
			<option value="MBB0228">Maybank2E / Malayan Banking Berhad E</option>
			<option value="OCBC0229">OCBC Bank (Malaysia) Berhad</option>
			<option value="PBB0233">Public Bank Berhad</option>
			<option value="RHB0218">RHB Bank Berhad</option>
			<option value="SCB0216">Standard Chartered Bank Malaysia Berhad</option>
			<option value="UOB0226">United Overseas Bank (Malaysia) Berhad</option>
		</select></div>';
		$html = $banks . $button;
		return wp_kses( $html, array(
							'style' => array(),
            		    	'div' => array(
            		    		'class' => array(),
            		    		'style' => array(),
            		    		'id' => array(),
            		    	),
            		    	'label' => array(
            		    		'style' => array(),
            		    	),
							'h3' => array(
            		    		'class' => array(),
							),
            		    	'select' => array(
            		    		'id' => array(),
            		    		'name' => array(),
            		    	),
            		    	'option' => array(
            		    		'value' => array(),
            		    	),
            		    	'img' => array(
            		    		'class' => array(),
            		    		'src' => array(),
            		    		'style' => array(),
            		    	),
            		    ) );
	}

	public function get_field_content( $value, $force_frontend_label, $form ) {
		$form_id             = $form['id'];
		$admin_buttons       = $this->get_admin_buttons();
		$is_entry_detail     = $this->is_entry_detail();
		$is_form_editor      = $this->is_form_editor();
		$is_admin            = $is_entry_detail || $is_form_editor;
		$field_label         = $this->get_field_label( $force_frontend_label, $value );
		$field_id            = $is_admin || $form_id == 0 ? "input_{$this->id}" : 'input_' . $form_id . "_{$this->id}";
		$admin_hidden_markup = ( $this->visibility == 'hidden' ) ? $this->get_hidden_admin_markup() : '';
		$field_content       = ! $is_admin ? '{FIELD}' : $field_content = sprintf( "%s%s{FIELD}", $admin_buttons, $admin_hidden_markup );

		return $field_content;
	}

	public function sanitize_settings() {
		parent::sanitize_settings();
		$this->content = GFCommon::maybe_wp_kses( $this->content );
	}
}

GF_Fields::register( new GF_Field_Kineticpay() );
