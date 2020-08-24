<?php
class ModelExtensionPaymentEripExpressPay extends Model
{

    private static $model;

    const CURRENCY = 933;
    const RETURN_TYPE = 'redirect';


    public function __construct($registry)
    {
        parent::__construct($registry);

        //self::$model = new TestExpressPayModel();
    }

    public function getMethod($address, $total)
    {

        $this->load->language('extension/payment/erip_expresspay');

        $status = false;

        if ($total > 0) {
            $status = true;
        }

        $method_data = array();

        if ($status) {
            $method_data = array(
                'code'       => 'erip_expresspay',
                'title'      => $this->language->get('text_title'),
                'terms'      => '',
                'sort_order' => $this->config->get('payment_erip_expresspay_sort_order')
            );
        }

        return $method_data;
    }

    public function setParams($data, $config)
    {

        self::$model = new EripExpressPayModel($config);

        $data['ServiceId'] = self::$model->getServiceId();
        $data['Currency'] = self::CURRENCY;
        $data['Info'] = str_replace('##order_id##', $data['AccountNo'], self::$model->getInfo());
        $data['IsNameEditable'] = self::$model->getIsNameEdit();
        $data['IsAmountEditable'] = self::$model->getIsAmountEdit();
        $data['IsAddressEditable'] = self::$model->getIsAddressEdit();
        $data['ReturnType'] = self::RETURN_TYPE;
        $data['ReturnUrl'] = $this->url->link('extension/payment/erip_expresspay/success');
        $data['FailUrl'] = $this->url->link('extension/payment/erip_expresspay/fail');

        $data['Signature'] = $this->compute_signature_add_invoice($data, self::$model->getSecretWord(), self::$model->getToken());

        $data['Action'] = self::$model->getActionUrl($config);

        return $data;
    }

    public function getQrCodeLink($config)
    {
        self::$model = new EripExpressPayModel(null);

        return self::$model->getQrCodeUrl($config);
    }

    public function checkResponse($signature, $request_params, $config)
    {

        self::$model = new EripExpressPayModel($config);

        $token = self::$model->getToken();

        $compute_signature = $this->compute_signature_success_invoice($request_params, $token);

        var_dump($compute_signature);

        var_dump($request_params);

        exit(1);

        return $signature == $compute_signature;
    }


    private function compute_signature_success_invoice($request_params, $token)
    {
        //$normalized_params = array_change_key_case($request_params, CASE_LOWER);
        $api_method = array(
            'ExpressPayAccountNumber',
            'ExpressPayInvoiceNo'
        );

        $result = $token;

        foreach ($api_method as $item)
            $result .= $request_params[$item];

        $hash = strtoupper(hash_hmac('sha1', $result, ''));

        return $hash;
    }

    private function compute_signature_add_invoice($request_params, $secret_word, $token)
    {
        $secret_word = trim($secret_word);
        $normalized_params = array_change_key_case($request_params, CASE_LOWER);
        $api_method = array(
            "serviceid",
            "accountno",
            "amount",
            "currency",
            "expiration",
            "info",
            "surname",
            "firstname",
            "patronymic",
            "city",
            "street",
            "house",
            "building",
            "apartment",
            "isnameeditable",
            "isaddresseditable",
            "isamounteditable",
            "emailnotification",
            "smsphone",
            "returntype",
            "returnurl",
            "failurl"
        );

        $result = $token;

        foreach ($api_method as $item)
            $result .= (isset($normalized_params[$item])) ? $normalized_params[$item] : '';

        $hash = strtoupper(hash_hmac('sha1', $result, $secret_word));

        return $hash;
    }
}

class EripExpressPayModel
{

    const TOKEN_PARAM_NAME                                  = 'payment_erip_expresspay_token';
    const SERVICE_ID_PARAM_NAME                             = 'payment_erip_expresspay_service_id';
    const SECRET_WORD_PARAM_NAME                            = 'payment_erip_expresspay_secret_word';
    const USE_SIGNATURE_FOR_NOTIFICATION_PARAM_NAME         = 'payment_erip_expresspay_is_use_signature_for_notification';
    const SECRET_WORD_NOTIFICATION_PARAM_NAME               = 'payment_erip_expresspay_secret_word_for_notification';
    const INFO_PARAM_NAME                                   = 'payment_erip_expresspay_info';
    const IS_NAME_EDIT_PARAM_NAME                           = 'payment_erip_expresspay_is_name_editable';
    const IS_AMOUNT_EDIT_PARAM_NAME                         = 'payment_erip_expresspay_is_amount_editable';
    const IS_ADDRESS_EDIT_PARAM_NAME                        = 'payment_erip_expresspay_is_address_editable';

    private $token;
    private $serviceId;
    private $secretWord;
    private $secretWordNotification;
    private $useSignatureNotification;
    private $info;
    private $isNameEdit;
    private $isAmountEdit;
    private $isAddressEdit;

    public function __construct($config)
    {
        if ($config == null)
            return;

        $this->token = $config->get(self::TOKEN_PARAM_NAME);
        $this->serviceId = $config->get(self::SERVICE_ID_PARAM_NAME);
        $this->secretWord = $config->get(self::SECRET_WORD_PARAM_NAME);
        $this->secretWordNotification = $config->get(self::USE_SIGNATURE_FOR_NOTIFICATION_PARAM_NAME);
        $this->useSignatureNotification = $config->get(self::SECRET_WORD_NOTIFICATION_PARAM_NAME);
        $this->info = $config->get(self::INFO_PARAM_NAME);
        $this->isNameEdit = $config->get(self::IS_NAME_EDIT_PARAM_NAME);
        $this->isAmountEdit = $config->get(self::IS_AMOUNT_EDIT_PARAM_NAME);
        $this->isAddressEdit = $config->get(self::IS_ADDRESS_EDIT_PARAM_NAME);
    }


    private $test_token_list = array(
        'a75b74cbcfe446509e8ee874f421bd63',
        'a75b74cbcfe446509e8ee874f421bd64',
        'a75b74cbcfe446509e8ee874f421bd65',
        'a75b74cbcfe446509e8ee874f421bd66'
    );

    public function getActionUrl($config)
    {
        if ($config->get('payment_erip_expresspay_is_test_mode')  == 'on') {
            return 'https://sandbox-api.express-pay.by/v1/web_invoices';
        } else {
            return 'https://api.express-pay.by/v1/web_invoices';
        }
    }

    public function getQrCodeUrl($config)
    {
        if ($config->get('payment_erip_expresspay_is_test_mode')  == 'on') {
            return 'https://sandbox-api.express-pay.by/v1/qrcode/getqrcode';
        } else {
            return 'https://api.express-pay.by/v1/qrcode/getqrcode';
        }
    }

    public function getToken()
    {
        return $this->token;
    }

    public function setToken($token)
    {
        $this->token = $token;
    }

    public function getServiceId()
    {
        return $this->serviceId;
    }

    public function setServiceId($serviceId)
    {
        $this->serviceId = $serviceId;
    }


    public function getSecretWord()
    {
        return $this->secretWord;
    }

    public function setSecretWord($secretWord)
    {
        $this->secretWord = $secretWord;
    }

    public function getSecretWordNotification()
    {
        return $this->secretWordNotification;
    }

    public function setSecretWordNotification($secretWordNotification)
    {
        $this->secretWordNotification = $secretWordNotification;
    }

    public function getUseSignatureNotification()
    {
        return $this->useSignatureNotification;
    }

    public function setUseSignatureNotification($useSignatureNotification)
    {
        $checkboxValue = $this->normCheckboxValue($useSignatureNotification);
        $this->useSignatureNotification = $checkboxValue;
    }

    public function getInfo()
    {
        return $this->info;
    }

    public function setInfo($info)
    {
        $this->info = $info;
    }

    public function getIsNameEdit()
    {
        return $this->isNameEdit;
    }

    public function setIsNameEdit($isNameEdit)
    {
        $checkboxValue = $this->normCheckboxValue($isNameEdit);
        $this->isNameEdit = $checkboxValue;
    }

    public function getIsAmountEdit()
    {
        return $this->isAmountEdit;
    }

    public function setIsAmountEdit($isAmountEdit)
    {
        $checkboxValue = $this->normCheckboxValue($isAmountEdit);
        $this->isAmountEdit = $checkboxValue;
    }

    public function getIsAddressEdit()
    {
        return $this->isAddressEdit;
    }

    public function setIsAddressEdit($isAddressEdit)
    {
        $checkboxValue = $this->normCheckboxValue($isAddressEdit);
        $this->isAddressEdit = $checkboxValue;
    }
}
