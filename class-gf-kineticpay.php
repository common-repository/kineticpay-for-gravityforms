<?php

defined( 'ABSPATH' ) || die();

add_action('wp', array( 'GFKineticpay', 'maybe_thankyou_page' ), 5);

GFForms::include_payment_addon_framework();

class GFKineticpay extends GFPaymentAddOn
{
    protected $_version = GF_KINETICPAY_VERSION;
    protected $_min_gravityforms_version = '1.9.3';
    protected $_slug = 'gravityformskineticpay';
    protected $_full_path = __FILE__;
    protected $_url = 'https://kineticpay.my/';
    protected $_title = 'Kineticpay for GravityForms';
    protected $_short_title = 'Kineticpay';
    protected $_supports_callbacks = true;

    // Members plugin integration
    protected $_capabilities = array( 'gravityforms_kineticpay', 'gravityforms_kineticpay_uninstall' );

    // Permissions
    protected $_capabilities_settings_page = 'gravityforms_kineticpay';
    protected $_capabilities_form_settings = 'gravityforms_kineticpay';
    protected $_capabilities_uninstall = 'gravityforms_kineticpay_uninstall';

    // Automatic upgrade enabled
    protected $_enable_rg_autoupgrade = false;

    private static $_instance = null;

    public static function get_instance()
    {
        if (self::$_instance == null) {
            self::$_instance = new GFKineticpay();
        }

        return self::$_instance;
    }
	
	public function pre_init() {
		
		parent::pre_init();

		require_once GF_KINETICPAY_PATH . '/helpers/class-gf-field-kineticpay.php';
	}

    private function __clone()
    {
    } /* do nothing */

    public function get_path() {
        return basename(dirname(__FILE__)) . '/kineticpay.php';
    }

    public function init_frontend()
    {
        parent::init_frontend();

        add_filter('gform_disable_post_creation', array( $this, 'delay_post' ), 10, 3);
        add_filter('gform_disable_notification', array( $this, 'delay_notification' ), 10, 4);
    }

    public function get_payment_field( $feed ) {
        return rgars( $feed, 'meta/paymentAmount', 'form_total' );
    }
	
	public function get_menu_icon() {
		return 'gform-icon--kineticpay';
	}

    //----- SETTINGS PAGES ----------//
    
    public function feed_settings_fields()
    {
        $default_settings = parent::feed_settings_fields();

        $fields = array(
            array(
                'name' => 'api_key',
                'label' => esc_html__('Merchent Key ', 'gravityformskineticpay'),
                'type' => 'text',
                'class' => 'medium',
                'required' => true,
                'tooltip' => '<h6>' . esc_html__('Kineticpay API Merchent Key', 'gravityformskineticpay') . '</h6>' . esc_html__('Enter your Merchent Key, Obtain your merchant key from your kineticPay dashboard.', 'gravityformskineticpay')
            ),
            array(
                'label' => esc_html__('Invoice Description', 'gravityformskineticpay'),
                'type' => 'textarea',
                'name' => 'invoice_description',
                'tooltip' => '<h6>' . esc_html__('Kineticpay Invoice Description', 'gravityformskineticpay') . '</h6>' . esc_html__('Enter your description here. It will displayed on Invoice page.', 'gravityformskineticpay'),
                'class' => 'medium merge-tag-support mt-position-right',
                'required' => false,
            )
        );

        $default_settings = parent::add_field_after('feedName', $fields, $default_settings);

        //--------------------------------------------------------------------------------------

        //--remove subscription from transaction type drop down
        $transaction_type = parent::get_field('transactionType', $default_settings);
        unset($transaction_type['choices'][2]);
        $default_settings = $this->replace_field('transactionType', $transaction_type, $default_settings);
        //--------------------------------------------------------------------------------------
        
        $fields = array(
            array(
                'name'     => 'cancel_url',
                'label'    => esc_html__('Cancel URL', 'gravityformskineticpay'),
                'type'     => 'text',
                'class'    => 'medium',
                'required' => false,
                'tooltip'  => '<h6>' . esc_html__('Cancel URL', 'gravityformskineticpay') . '</h6>' . esc_html__('Enter the URL the user should be sent to should they cancel before completing their payment.', 'gravityformskineticpay')
            ),
        );

        if ($this->get_setting('delayNotification') || ! $this->is_gravityforms_supported('1.9.12')) {
            $fields[] = array(
                'name'    => 'notifications',
                'label'   => esc_html__('Notifications', 'gravityformskineticpay'),
                'type'    => 'notifications',
                'tooltip' => '<h6>' . esc_html__('Notifications', 'gravityformskineticpay') . '</h6>' . esc_html__("Enable this option if you would like to only send out this form's notifications for the 'Form is submitted' event after payment has been received. Leaving this option disabled will send these notifications immediately after the form is submitted. Notifications which are configured for other events will not be affected by this option.", 'gravityformskineticpay')
            );
        }

        //Add post fields if form has a post
        $form = $this->get_current_form();

        if (GFCommon::has_post_field($form['fields'])) {
            $post_settings = array(
                'name'    => 'post_checkboxes',
                'label'   => esc_html__('Posts', 'gravityformskineticpay'),
                'type'    => 'checkbox',
                'tooltip' => '<h6>' . esc_html__('Posts', 'gravityformskineticpay') . '</h6>' . esc_html__('Enable this option if you would like to only create the post after payment has been received.', 'gravityformskineticpay'),
                'choices' => array(
                    array( 'label' => esc_html__('Create post only when payment is received.', 'gravityformskineticpay'), 'name' => 'delayPost' ),
                ),
            );

            $fields[] = $post_settings;
        }

        //Adding custom settings for backwards compatibility with hook 'gform_kineticpay_add_option_group'
        $fields[] = array(
            'name'  => 'custom_options',
            'label' => '',
            'type'  => 'custom',
        );

        $default_settings = $this->add_field_after('billingInformation', $fields, $default_settings);
        //-----------------------------------------------------------------------------------------
        
        //--get billing info section and add customer first/last name
        $billing_info = parent::get_field('billingInformation', $default_settings);

        $add_name = true;
        $add_mobile = true;
		//$add_bankid = true;
        $add_email = true; //for better arrangement

        $remove_address = false;
        $remove_address2 = false;
        $remove_city = false;
        $remove_state = false;
        $remove_zip = false;
        $remove_country = false;
        $remove_email = false; //for better arrangement

        foreach ($billing_info['field_map'] as $mapping) {
            //add first/last name if it does not already exist in billing fields
            if ($mapping['name'] == 'name') {
                $add_name = false;
            } elseif ($mapping['name'] == 'mobile') {
                $add_mobile = false;
            } elseif ($mapping['name'] == 'email') {
                $add_email = false;
            //} elseif ($mapping['name'] == 'bank_id_is') {
            //    $add_bankid = false;
            } elseif ($mapping['name'] == 'address') {
                $remove_address = true;
            } elseif ($mapping['name'] == 'address2') {
                $remove_address2 = true;
            } elseif ($mapping['name'] == 'city') {
                $remove_city = true;
            } elseif ($mapping['name'] == 'state') {
                $remove_state = true;
            } elseif ($mapping['name'] == 'zip') {
                $remove_zip = true;
            } elseif ($mapping['name'] == 'country') {
                $remove_country = true;
            }
        }

        /*
         * Removing unrelated variable
         */

        if ($remove_address) {
            unset($billing_info['field_map'][1]);
        }
        if ($remove_address2) {
            unset($billing_info['field_map'][2]);
        }
        if ($remove_city) {
            unset($billing_info['field_map'][3]);
        }
        if ($remove_state) {
            unset($billing_info['field_map'][4]);
        }
        if ($remove_zip) {
            unset($billing_info['field_map'][5]);
        }
        if ($remove_country) {
            unset($billing_info['field_map'][6]);
        }

        /*
         * Adding kineticpay required variable. The last will be the first
         */
      //  if ($add_bankid) {
          //  array_unshift($billing_info['field_map'], array('name' => 'bank_id_is', 'label' => esc_html__('Bank ID', 'gravityformskineticpay'), 'required' => true));
        //}
        if ($add_mobile) {
            array_unshift($billing_info['field_map'], array('name' => 'mobile', 'label' => esc_html__('Mobile Phone Number', 'gravityformskineticpay'), 'required' => true));
        }
        if ($add_email) {
            array_unshift($billing_info['field_map'], array('name' => 'email', 'label' => esc_html__('Email', 'gravityformskineticpay'), 'required' => true));
        }
        if ($add_name) {
            array_unshift($billing_info['field_map'], array('name' => 'name', 'label' => esc_html__('Name', 'gravityformskineticpay'), 'required' => true));
        }

        $default_settings = parent::replace_field('billingInformation', $billing_info, $default_settings);

        //hide default display of setup fee, not used by kineticpay
        $default_settings = parent::remove_field('setupFee', $default_settings);

        /**
         * Filter through the feed settings fields for the kineticpay feed
         *
         * @param array $default_settings The Default feed settings
         * @param array $form The Form object to filter through
         */
        return apply_filters('gform_kineticpay_feed_settings_fields', $default_settings, $form);
    }

    public function field_map_title()
    {
        return esc_html__('Kineticpay Field', 'gravityformskineticpay');
    }

    public function settings_options($field, $echo = true)
    {
        $html = $this->settings_checkbox($field, false);

        //--------------------------------------------------------
        //For backwards compatibility.
        ob_start();
        do_action('gform_kineticpay_action_fields', $this->get_current_feed(), $this->get_current_form());
        $html .= ob_get_clean();
        //--------------------------------------------------------

        if ($echo) {
            echo $html;
        }

        return $html;
    }

    public function settings_custom($field, $echo = true)
    {

        ob_start();
        ?>
        <div id='gf_kineticpay_custom_settings'>
            <?php
            do_action('gform_kineticpay_add_option_group', $this->get_current_feed(), $this->get_current_form());
            ?>
        </div>

        <script type='text/javascript'>
            jQuery(document).ready(function () {
                jQuery('#gf_kineticpay_custom_settings label.left_header').css('margin-left', '-200px');
            });
        </script>

        <?php

        $html = ob_get_clean();

        if ($echo) {
            echo $html;
        }

        return $html;
    }

    public function settings_notifications($field, $echo = true)
    {
        $checkboxes = array(
            'name'    => 'delay_notification',
            'type'    => 'checkboxes',
            'onclick' => 'ToggleNotifications();',
            'choices' => array(
                array(
                    'label' => esc_html__("Send notifications for the 'Form is submitted' event only when payment is received.", 'gravityformskineticpay'),
                    'name'  => 'delayNotification',
                ),
            )
        );

        $html = $this->settings_checkbox($checkboxes, false);

        $html .= $this->settings_hidden(array( 'name' => 'selectedNotifications', 'id' => 'selectedNotifications' ), false);

        $form                      = $this->get_current_form();
        $has_delayed_notifications = $this->get_setting('delayNotification');
        ob_start();
        ?>
        <ul id="gf_kineticpay_notification_container" style="padding-left:20px; margin-top:10px; <?php echo $has_delayed_notifications ? '' : 'display:none;' ?>">
            <?php
            if (! empty($form) && is_array($form['notifications'])) {
                $selected_notifications = $this->get_setting('selectedNotifications');
                if (! is_array($selected_notifications)) {
                    $selected_notifications = array();
                }

                $notifications = GFCommon::get_notifications('form_submission', $form);

                foreach ($notifications as $notification) {
                    ?>
                    <li class="gf_kineticpay_notification">
                        <input type="checkbox" class="notification_checkbox" value="<?php echo $notification['id'] ?>" onclick="SaveNotifications();" <?php checked(true, in_array($notification['id'], $selected_notifications)) ?> />
                        <label class="inline" for="gf_kineticpay_selected_notifications"><?php echo $notification['name']; ?></label>
                    </li>
                    <?php
                }
            }
            ?>
        </ul>
        <script type='text/javascript'>
            function SaveNotifications() {
                var notifications = [];
                jQuery('.notification_checkbox').each(function () {
                    if (jQuery(this).is(':checked')) {
                        notifications.push(jQuery(this).val());
                    }
                });
                jQuery('#selectedNotifications').val(jQuery.toJSON(notifications));
            }

            function ToggleNotifications() {

                var container = jQuery('#gf_kineticpay_notification_container');
                var isChecked = jQuery('#delaynotification').is(':checked');

                if (isChecked) {
                    container.slideDown();
                    jQuery('.gf_kineticpay_notification input').prop('checked', true);
                }
                else {
                    container.slideUp();
                    jQuery('.gf_kineticpay_notification input').prop('checked', false);
                }

                SaveNotifications();
            }
        </script>
        <?php

        $html .= ob_get_clean();

        if ($echo) {
            echo $html;
        }

        return $html;
    }

    public function checkbox_input_change_post_status($choice, $attributes, $value, $tooltip)
    {
        $markup = $this->checkbox_input($choice, $attributes, $value, $tooltip);

        $dropdown_field = array(
            'name'     => 'update_post_action',
            'choices'  => array(
                array( 'label' => '' ),
                array( 'label' => esc_html__('Mark Post as Draft', 'gravityformskineticpay'), 'value' => 'draft' ),
                array( 'label' => esc_html__('Delete Post', 'gravityformskineticpay'), 'value' => 'delete' ),

            ),
            'onChange' => "var checked = jQuery(this).val() ? 'checked' : false; jQuery('#change_post_status').attr('checked', checked);",
        );
        $markup .= '&nbsp;&nbsp;' . $this->settings_select($dropdown_field, false);

        return $markup;
    }

    /**
     * Prevent the GFPaymentAddOn version of the options field being added to the feed settings.
     *
     * @return bool
     */
    public function option_choices()
    {
        
        return false;
    }

    public function save_feed_settings($feed_id, $form_id, $settings)
    {

        //--------------------------------------------------------
        //For backwards compatibility
        $feed = $this->get_feed($feed_id);

        //Saving new fields into old field names to maintain backwards compatibility for delayed payments
        $settings['type'] = $settings['transactionType'];

        $feed['meta'] = $settings;
        $feed         = apply_filters('gform_kineticpay_save_config', $feed);
        
        //call hook to validate custom settings/meta added using gform_kineticpay_action_fields or gform_kineticpay_add_option_group action hooks
        $is_validation_error = apply_filters('gform_kineticpay_config_validation', false, $feed);
        if ($is_validation_error) {
            //fail save
            return false;
        }

        $settings = $feed['meta'];
        
        //--------------------------------------------------------

        return parent::save_feed_settings($feed_id, $form_id, $settings);
    }

    //------ SENDING TO kineticpay -----------//
    
    public function redirect_url($feed, $submission_data, $form, $entry)
    {
        // Don't process redirect url if request is a kineticpay redirect
        if (!rgempty('kineticpay', $_GET)) {
            return false;
        }

        // Don't process redirect url if request is a kineticpay callback
        if (!rgempty('url', $_POST)) {
            return false;
        }

        // Update lead's payment_status to Processing
        GFAPI::update_entry_property($entry['id'], 'payment_status', 'Processing');

        $feed_meta = $feed['meta'];

        //get array key for required parameter
        $b = 'billingInformation_';

        $int_name = isset($feed_meta[$b.'name']) ? $feed_meta[$b.'name'] : '';
        $int_email = isset($feed_meta[$b.'email']) ? $feed_meta[$b.'email'] : '';
        $int_mobile = isset($feed_meta[$b.'mobile']) ? $feed_meta[$b.'mobile'] : '';

        $email = isset($entry[$int_email]) ? $entry[$int_email] : '';
        $mobile = isset($entry[$int_mobile]) ? $entry[$int_mobile] : '';
        $name = isset($entry[$int_name]) ? $entry[$int_name] : '';

        $description = mb_substr(GFCommon::replace_variables($feed_meta['invoice_description'], $form, $entry), 0, 200);
		$payment_id = $feed['id'];
        if (empty($mobile) && empty($email)) {
            $parameter['email'] = 'noreply@kineticpay.com';
        }

        if (empty($name)) {
            $blog_name = get_bloginfo('name');
            $name =  !empty($blog_name) ? $blog_name : 'Set your payer name';
        }

        if (empty($description)) {
            $blog_description = get_bloginfo('description');
            $description = !empty($blog_description) ? $blog_description : 'Set your payment description';
        }

        $kineticpay = KineticpayGravityFormsWPConnect::get_instance();
        $kineticpay->set_api_key(trim($feed_meta['api_key']));

        $user_bank = isset($_POST['bank_id']) ? sanitize_text_field($_POST['bank_id']) : '';
		$pid = (int)get_option('gf_kineticpay_last_pid');
		$pay_id = $pid + 1;
        $urlparts = parse_url(home_url());
        $domain = substr($urlparts['host'], 0, 5);
        $invoice_id = strtoupper($domain) . (string)$pay_id . 'KNGF';
		
        $url_arg = array(
            'kineticpay' => 'yes',
			'paymentid' => $invoice_id,
        );
		$return_url = $this->return_url($form['id'], $entry['id'], $invoice_id);
        	
		$kineticpay->purpose = $description;
		$kineticpay->amount = strval(rgar($submission_data, 'payment_amount'));
		$kineticpay->buyer_name = $name;
		$kineticpay->email = trim($email);
		$kineticpay->phone = trim($mobile);
		$kineticpay->billcode = $invoice_id;
		$kineticpay->bank_id = $user_bank;
		$kineticpay->kineticpay_success_url = $return_url;
		$kineticpay->fail_url = $return_url;
		$html = $kineticpay->create_billcode();
		gform_update_meta($entry['id'], 'return_url', $return_url);
        gform_update_meta($entry['id'], 'payment_id', $invoice_id);
		update_option('gf_kineticpay_last_pid', $pay_id);
		print_r( $html );
		exit();
    }

    public function return_url($form_id, $lead_id, $invoice_id)
    {
        $pageURL = GFCommon::is_ssl() ? 'https://' : 'http://';

        $server_port = apply_filters('gform_kineticpay_return_url_port', sanitize_text_field($_SERVER['SERVER_PORT']));

        if ($server_port != '80') {
            $pageURL .= $_SERVER['SERVER_NAME'] . ':' . $server_port . $_SERVER['REQUEST_URI'];
        } else {
            $pageURL .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
        }

        $ids_query = "ids={$form_id}|{$lead_id}|{$invoice_id}";
        $ids_query .= '&hash=' . wp_hash($ids_query);

        $url = add_query_arg('gf_kineticpay_return', base64_encode($ids_query), $pageURL);

        $query = 'gf_kineticpay_return=' . base64_encode($ids_query);

        return apply_filters('gform_kineticpay_return_url', $url, $form_id, $lead_id, $query);
    }

    public static function maybe_thankyou_page()
    {
        $instance = self::get_instance();

        if (! $instance->is_gravityforms_supported()) {
            return;
        }

        if ($str = rgget('gf_kineticpay_return')) {
            $str = base64_decode($str);

            parse_str($str, $query);
            if (wp_hash('ids=' . $query['ids']) == $query['hash']) {
                $hash_array = explode('|', $query['ids']);
				$form_id = $hash_array[0];
				$lead_id = $hash_array[1];
				$payment_id = $hash_array[2];

                $form = GFAPI::get_form($form_id);
                $lead = GFAPI::get_entry($lead_id);

                if (! class_exists('GFFormDisplay')) {
                    require_once(GFCommon::get_base_path() . '/form_display.php');
                }

                $confirmation = GFFormDisplay::handle_confirmation($form, $lead, false);

                if (is_array($confirmation) && isset($confirmation['redirect'])) {
                    header("Location: {$confirmation['redirect']}");
                    exit;
                }

                GFFormDisplay::$submission[ $form_id ] = array( 'is_confirmation' => true, 'confirmation_message' => $confirmation, 'form' => $form, 'lead' => $lead );
            }
        }
    }

    public function delay_post($is_disabled, $form, $entry)
    {
        $feed            = $this->get_payment_feed($entry);
        $submission_data = $this->get_submission_data($feed, $form, $entry);

        if (! $feed || empty($submission_data['payment_amount'])) {
            return $is_disabled;
        }

        return ! rgempty('delayPost', $feed['meta']);
    }

    public function delay_notification($is_disabled, $notification, $form, $entry)
    {
        if (rgar($notification, 'event') != 'form_submission') {
            return $is_disabled;
        }

        $feed            = $this->get_payment_feed($entry);
        $submission_data = $this->get_submission_data($feed, $form, $entry);

        if (! $feed || empty($submission_data['payment_amount'])) {
            return $is_disabled;
        }

        $selected_notifications = is_array(rgar($feed['meta'], 'selectedNotifications')) ? rgar($feed['meta'], 'selectedNotifications') : array();

        return isset($feed['meta']['delayNotification']) && in_array($notification['id'], $selected_notifications) ? true : $is_disabled;
    }

    //------- AFTER PAYMENT -----------//
    
    public function callback()
    {
        if (! $this->is_gravityforms_supported()) {
            return false;
        }

        $entry = GFAPI::get_entry(rgget('entry_id'));

        if (is_wp_error($entry)) {
            $this->log_error(__METHOD__ . '(): Entry could not be found. Aborting.');
            return false;
        }
        
        $this->log_debug(__METHOD__ . '(): Entry has been found => ' . print_r($entry, true));
        
        $payment_id = gform_get_meta($entry['id'], 'payment_id');
        
        if (!$payment_id) {
            $this->log_debug(__METHOD__ . '(): Invoice ID not found => ' . print_r($entry, true));
            return false;
        }
      
        if ($entry['status'] == 'spam') {
            $this->log_error(__METHOD__ . '(): Entry is marked as spam. Aborting.');
            return false;
        }

        $feed = $this->get_payment_feed($entry);
		$api_key = trim($feed['meta']['api_key']);
		$kineticpay = KineticpayGravityFormsWPConnect::get_instance();
        $kineticpay->set_api_key($api_key);
		$kineticpay->billcode = (int)$payment_id;
		
        //Ignore IPN messages from forms that are no longer configured with the kineticpay
        if (! $feed || ! rgar($feed, 'is_active')) {
            $this->log_error(__METHOD__ . "(): Form no longer is configured with kineticpay. Form ID: {$entry['form_id']}. Aborting.");
            return false;
        }
		
		$response_kineticpay = $kineticpay->success_action();
		if( isset($response_kineticpay['code']) && $response_kineticpay['code'] == '00' )
		{
			if ($payment_id !== $data['invoice']) {
				$this->log_debug(__METHOD__ . '(): Invoice ID not match with entry => ' . print_r($entry, true));
				return false;
			}

			return array(
                'id' => $data['id'],
                'transaction_id' => $data['id'],
                'amount' => strval($data['amount']),
                'entry_id' => $entry['id'],
                'payment_date' => get_the_date('y-m-d H:i:s'),
                'type' => 'complete_payment',
                'payment_method' => 'Kineticpay',
                'ready_to_fulfill' => !$entry['is_fulfilled'] ? true : false,
            );
		} else {
			$this->log_error(__METHOD__ . "(): Paymemt failed. Aborting.");
            return false;
		}
		
        return false;
    }

    public function get_payment_feed($entry, $form = false)
    {

        $feed = parent::get_payment_feed($entry, $form);

        if (empty($feed) && ! empty($entry['id'])) {
            //looking for feed created by legacy versions
            $feed = $this->get_kineticpay_feed_by_entry($entry['id']);
        }

        $feed = apply_filters('gform_kineticpay_get_payment_feed', $feed, $entry, $form ? $form : GFAPI::get_form($entry['form_id']));

        return $feed;
    }

    private function get_kineticpay_feed_by_entry($entry_id)
    {

        $feed_id = gform_get_meta($entry_id, 'kineticpay_feed_id');
        $feed    = $this->get_feed($feed_id);

        return ! empty($feed) ? $feed : false;
    }

    public function post_callback($callback_action, $callback_result)
    {
        if (is_wp_error($callback_action) || ! $callback_action) {
            return false;
        }

        //run the necessary hooks
        $entry          = GFAPI::get_entry($callback_action['entry_id']);
        $feed           = $this->get_payment_feed($entry);
        $transaction_id = rgar($callback_action, 'transaction_id');
        $amount         = rgar($callback_action, 'amount');
        
        $this->fulfill_order($entry, $transaction_id, $amount, $feed);

        do_action('gform_kineticpay_post_payment_status', $feed, $entry, $transaction_id, $amount);

        if (has_filter('gform_kineticpay_post_payment_status')) {
            $this->log_debug(__METHOD__ . '(): Executing functions hooked to gform_kineticpay_post_payment_status.');
        }
    }

    public function is_callback_valid()
    {
        return parent::is_callback_valid() || rgget( 'page' ) === 'gf_kineticpay';
    }

    public function get_callback_url($entry_id) {
        return add_query_arg( array(
            'callback' => $this->_slug,
            'entry_id' => $entry_id
        ), home_url( '/', 'https' ) );
    }

    //------- AJAX FUNCTIONS ------------------//

    public function init_ajax()
    {
        parent::init_ajax();
    }

    //------- ADMIN FUNCTIONS/HOOKS -----------//

    public function init_admin()
    {

        parent::init_admin();

        //add actions to allow the payment status to be modified
        add_action('gform_payment_status', array( $this, 'admin_edit_payment_status' ), 3, 3);
        add_action('gform_payment_date', array( $this, 'admin_edit_payment_date' ), 3, 3);
        add_action('gform_payment_transaction_id', array( $this, 'admin_edit_payment_transaction_id' ), 3, 3);
        add_action('gform_payment_amount', array( $this, 'admin_edit_payment_amount' ), 3, 3);
        add_action('gform_after_update_entry', array( $this, 'admin_update_payment' ), 4, 2);
    }

    public function supported_notification_events($form)
    {
        if (! $this->has_feed($form['id'])) {
            return false;
        }

        return array(
                'complete_payment'          => esc_html__('Payment Completed', 'gravityformskineticpay'),
                'fail_payment'              => esc_html__('Payment Failed', 'gravityformskineticpay'),
        );
    }

    public function admin_edit_payment_status($payment_status, $form, $entry)
    {
        if ($this->payment_details_editing_disabled($entry)) {
            return $payment_status;
        }

        //create drop down for payment status
        $payment_string = gform_tooltip('kineticpay_edit_payment_status', '', true);
        $payment_string .= '<select id="payment_status" name="payment_status">';
        $payment_string .= '<option value="' . $payment_status . '" selected>' . $payment_status . '</option>';
        $payment_string .= '<option value="Paid">Paid</option>';
        $payment_string .= '</select>';

        return $payment_string;
    }

    public function admin_edit_payment_date($payment_date, $form, $entry)
    {
        if ($this->payment_details_editing_disabled($entry)) {
            return $payment_date;
        }

        $payment_date = $entry['payment_date'];
        if (empty($payment_date)) {
            $payment_date = get_the_date('y-m-d H:i:s');
        }

        $input = '<input type="text" id="payment_date" name="payment_date" value="' . $payment_date . '">';

        return $input;
    }

    public function admin_edit_payment_transaction_id($transaction_id, $form, $entry)
    {
        if ($this->payment_details_editing_disabled($entry)) {
            return $transaction_id;
        }

        $input = '<input type="text" id="kineticpay_transaction_id" name="kineticpay_transaction_id" value="' . $transaction_id . '">';

        return $input;
    }

    public function admin_edit_payment_amount($payment_amount, $form, $entry)
    {
        if ($this->payment_details_editing_disabled($entry)) {
            return $payment_amount;
        }

        if (empty($payment_amount)) {
            $payment_amount = GFCommon::get_order_total($form, $entry);
        }

        $input = '<input type="text" id="payment_amount" name="payment_amount" class="gform_currency" value="' . $payment_amount . '">';

        return $input;
    }

    public function admin_update_payment($form, $entry_id)
    {
        check_admin_referer('gforms_save_entry', 'gforms_save_entry');

        //update payment information in admin, need to use this function so the lead data is updated before displayed in the sidebar info section
        $entry = GFFormsModel::get_lead($entry_id);

        if ($this->payment_details_editing_disabled($entry, 'update')) {
            return;
        }
        
        //get payment fields to update
        $payment_status = rgpost('payment_status');
        //when updating, payment status may not be editable, if no value in post, set to lead payment status
        if (empty($payment_status)) {
            $payment_status = $entry['payment_status'];
        }

        $payment_amount      = GFCommon::to_number(rgpost('payment_amount'));
        $payment_transaction = rgpost('kineticpay_transaction_id');
        $payment_date        = rgpost('payment_date');

        $status_unchanged = $entry['payment_status'] == $payment_status;
        $amount_unchanged = $entry['payment_amount'] == $payment_amount;
        $id_unchanged     = $entry['transaction_id'] == $payment_transaction;
        $date_unchanged   = $entry['payment_date'] == $payment_date;

        if ($status_unchanged && $amount_unchanged && $id_unchanged && $date_unchanged) {
            return;
        }

        if (empty($payment_date)) {
            $payment_date = get_the_date('y-m-d H:i:s');
        } else {
            //format date entered by user
            $payment_date = date('Y-m-d H:i:s', strtotime($payment_date));
        }

        global $current_user;
        $user_id   = 0;
        $user_name = 'System';
        if ($current_user && $user_data = get_userdata($current_user->ID)) {
            $user_id   = $current_user->ID;
            $user_name = $user_data->display_name;
        }

        $entry['payment_status'] = $payment_status;
        $entry['payment_amount'] = $payment_amount;
        $entry['payment_date']   = $payment_date;
        $entry['transaction_id'] = $payment_transaction;

        // if payment status does not equal approved/paid or the lead has already been fulfilled, do not continue with fulfillment
        if (( $payment_status == 'Approved' || $payment_status == 'Paid' ) && ! $entry['is_fulfilled']) {
            $action['id']             = $payment_transaction;
            $action['type']           = 'complete_payment';
            $action['transaction_id'] = $payment_transaction;
            $action['amount']         = $payment_amount;
            $action['entry_id']       = $entry['id'];

            $this->complete_payment($entry, $action);
            $this->fulfill_order($entry, $payment_transaction, $payment_amount);
        }
        //update lead, add a note
        GFAPI::update_entry($entry);
        // translators: %1$s is replaced with payment status
        // translators: %2$s is replaced with payment amount
        // translators: %3$s is replaced with currency
        // translators: %4$s is replaced with payment date
        GFFormsModel::add_note($entry['id'], $user_id, $user_name, sprintf(esc_html__('Payment information was manually updated. Status: %1$s. Amount: %2$s. Transaction ID: %3$s. Date: %4$s', 'gravityformskineticpay'), $entry['payment_status'], GFCommon::to_money($entry['payment_amount'], $entry['currency']), $payment_transaction, $entry['payment_date']));
    }

    public function fulfill_order(&$entry, $transaction_id, $amount, $feed = null)
    {

        if (! $feed) {
            $feed = $this->get_payment_feed($entry);
        }

        $form = GFFormsModel::get_form_meta($entry['form_id']);
        if (rgars($feed, 'meta/delayPost')) {
            $this->log_debug(__METHOD__ . '(): Creating post.');
            $entry['post_id'] = GFFormsModel::create_post($form, $entry);
            $this->log_debug(__METHOD__ . '(): Post created.');
        }

        if (rgars($feed, 'meta/delayNotification')) {
            //sending delayed notifications
            $notifications = $this->get_notifications_to_send($form, $feed);
            GFCommon::send_notifications($notifications, $form, $entry, true, 'form_submission');
        }

        do_action('gform_kineticpay_fulfillment', $entry, $feed, $transaction_id, $amount);
        if (has_filter('gform_kineticpay_fulfillment')) {
            $this->log_debug(__METHOD__ . '(): Executing functions hooked to gform_kineticpay_fulfillment.');
        }
    }

    public function get_notifications_to_send($form, $feed)
    {
        $notifications_to_send  = array();
        $selected_notifications = rgars($feed, 'meta/selectedNotifications');

        if (is_array($selected_notifications)) {
            // Make sure that the notifications being sent belong to the form submission event, just in case the notification event was changed after the feed was configured.
            foreach ($form['notifications'] as $notification) {
                if (rgar($notification, 'event') != 'form_submission' || ! in_array($notification['id'], $selected_notifications)) {
                    continue;
                }

                $notifications_to_send[] = $notification['id'];
            }
        }

        return $notifications_to_send;
    }

    public function payment_details_editing_disabled($entry, $action = 'edit')
    {
        if (! $this->is_payment_gateway($entry['id'])) {
            // Entry was not processed by this add-on, don't allow editing.
            return true;
        }

        $payment_status = rgar($entry, 'payment_status');
        if ($payment_status == 'Approved' || $payment_status == 'Paid' || rgar($entry, 'transaction_type') == 2) {
            // Editing not allowed for this entries transaction type or payment status.
            return true;
        }

        if ($action == 'edit' && rgpost('screen_mode') == 'edit') {
            // Editing is allowed for this entry.
            return false;
        }

        if ($action == 'update' && rgpost('screen_mode') == 'view' && rgpost('action') == 'update') {
            // Updating the payment details for this entry is allowed.
            return false;
        }

        // In all other cases editing is not allowed.

        return true;
    }

    public function uninstall()
    {
        parent::uninstall();
    }
}
