<?php

defined('ABSPATH') || exit;

class KineticpayGravityFormsWPConnect
{
    public $kineticpay_secret_key;
	public $purpose;
	public $amount;
	public $phone;
	public $bank_id;
	public $buyer_name;
	public $email;
	public $button_lang;
	public $kineticpay_bill_url;
	public $redirect_url;
	public $fail_url;
	public $billcode;
	public $getBillTransactions;
	public $kineticpay_success_url;

    private static $instance;

    public static function get_instance() {
      if (null === self::$instance) {
        self::$instance = new self();
      }
      return self::$instance;
    }

    private function __clone() {}

    public function set_api_key($api_key)
    {
        $this->kineticpay_secret_key = $api_key;
    }

    public function create_billcode()
	{
		// Get ID from user
		$bankid = $this->bank_id;
		// This is merchant_key get from Collection page
		$secretkey = $this->kineticpay_secret_key;
		// This variable should be generated or populated from your system
		$name = $this->buyer_name;
		$phone = $this->phone;
		$email = $this->email;
		$order_id = $this->billcode;
		$amount = $this->amount;
		$description = "Payment for " . $this->purpose . ", Buyer Name " . $name .
			", Email " . $email . ", Phone No. " . $phone;
		if ( is_null($this->fail_url) ) {
			$this->fail_url = $this->kineticpay_success_url;
		}
		$body = [
			'merchant_key' => $secretkey,
			'invoice' => $order_id,
			'amount' => $amount,
			'description' => $description,
			'bank' => $bankid,
			'callback_success' => $this->kineticpay_success_url,
			'callback_error' => $this->fail_url,
			'callback_status' => $this->kineticpay_success_url
		];		
		// API Endpoint URL
		$url = "https://manage.kineticpay.my/payment/create";
		
		$args = array(
			'body'        => $body,
			'headers'     => array('Content-Type:application/json'),
		);
		
		$result = wp_remote_post( $url, $args );
		$response = json_decode($result["body"], true);
		if (isset($response["error"])) {
			foreach ($response["error"] as $error) {
				if (is_array($error) && isset($error[0])) {
					return esc_html($error[0]);
				}
				if (is_string($error)) {
					return esc_html($error);
				}
			}	
		} else {
			if (isset($response["html"])) {
				return $response["html"];
			} else {
				$eror = isset($response[0]) ? $response[0] : "Payment was declined. Something error with payment gateway, please contact admin.";
				return esc_html($eror);
			}
		}
	}	

    public function success_action()
	{
		$secretkey = $this->kineticpay_secret_key;
		// This variable should be generated or populated from your system
		$url = "https://manage.kineticpay.my/payment/status?merchant_key=". $secretkey . "&invoice=" . (string)$order_id;
			
		$result = wp_remote_get( $url );
		
		$response = json_decode($result["body"], true);		
		return $response;
		
	}

}
    
$GLOBALS['gfw_connect'] = KineticpayGravityFormsWPConnect::get_instance();