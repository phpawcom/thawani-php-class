<?php
namespace s4d\payment;

class thawani {
    public $config = [];
    public $rsa = [];
    public $connectTimeOut = 30;
    public $payment_id = '';
    public $payment_status = 0;
    public $debug = false;
    public $errorFatal;
    public $errorWarning;
    public $responseData = [];
    /**
     * thawani constructor.
     * @param array $config
     */
    public function __construct(array $config, $errorFatal = ''){
        date_default_timezone_set('Asia/Muscat'); ## this is important as Thawani using local time to check validity of invoice
        $this->config = array_merge([
            'isTestMode' => 1, ## Set 1 to work with test mode
            'public_key' => '',
            'private_key' => '',
        ], $config);
        $this->errorFatal = $errorFatal;
        foreach($this->config as $k=>$v){
            if($k == 'isTestMode') continue;
            if(empty($v)){
                if(is_callable($this->errorFatal)){
                    call_user_func_array($this->errorFatal, ['Thawani '.$k.' is not set']);
                }else{
                    trigger_error('Thawani '.$k.' is not set', E_USER_ERROR);
                }
            }
        }
    }

    /**
     * @return bool
     */
    public function is_ssl(){
        return isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off'? true : false;
    }

    /**
     * @return string
     */
    public function currentPageUrl(){
        $page = urldecode(($this->is_ssl()? 'https://' : 'http://').$_SERVER['SERVER_NAME'].''.$_SERVER['REQUEST_URI']);
        return $page;
    }

    /**
     * @param array $parameters
     * @return array
     * This method is from EPCMS: https://www.s4d.om/hosting/pages.php/epcms
     */
    public function post(array $parameters, $method = 'POST'){
        $output = ['is_success' => 0, 'response' => ''];
        $parameters = array_merge([
            'url' => '',
            'settings' => [],
            'fields' => [],
            'headers' => [],
            'printHeader' => false,
            'skipSSL' => false,
        ], $parameters);
        if(!is_array($parameters['fields'])) $parameters['fields'] = [];
        $fields_string = '';
        $fieldsCount = count($parameters['fields']);
        if($fieldsCount > 0){ $fields_string = json_encode($parameters['fields']); }
        $parameters['headers'] = array_merge(['content-type' => 'application/json'], $parameters['headers']);
        $headers = array_map(function($v, $k){ return $k.': '.$v; }, $parameters['headers'], array_keys($parameters['headers']));
        $settings = [
            CURLOPT_URL => $parameters['url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => $this->connectTimeOut,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeOut,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if($method == 'GET'){
            if($fieldsCount > 0){
                $settings[CURLOPT_URL] = $parameters['url'].http_build_query($parameters['fields']);
            }
        }else{
            $settings[CURLOPT_POST] = $fieldsCount;
            $settings[CURLOPT_POSTFIELDS] = $fields_string;
        }
        foreach($parameters['settings'] as $cons => $value){
            $settings[$cons] = $value;
        }
        $settings[CURLOPT_HEADER] = $parameters['printHeader']? 1 : 0;
        $settings[CURLOPT_SSL_VERIFYPEER] = $parameters['skipSSL']? 1 : 0;
        $connection = curl_init();
        $this->connection = $connection;
        curl_setopt_array($connection, $settings);
        $content = curl_exec($connection);
        $err = curl_error($connection);
        if($parameters['printHeader']){
            preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $content, $matches);
            $this->headerCookies = array();
            foreach($matches[1] as $item) {
                parse_str($item, $cookie);
                $this->headerCookies = array_merge($this->headerCookies, $cookie);
            }
            $header_len = curl_getinfo($connection, CURLINFO_HEADER_SIZE);
            $this->headerResponses = substr($content, 0, $header_len);
            $content = substr($content, $header_len);
        }
        curl_close($connection);
        if(!$err){
            $output['is_success'] = 1;
            $output['response'] = $content;
        }else{
            $output['response'] = $err;
        }
        return $output;
    }

    /**
     * @param array $input
     * @return array|mixed
     * This method will make sure that the item name is not longer than the maximum 40 characters
     */
    public function process_userMeta(array $input){
        if(is_array($input) && count($input) > 0){
            if(isset($input['products']) && is_array($input['products'])){
                foreach($input['products'] as $i => $product){
                    $input['products'][$i]['name'] = trim($input['products'][$i]['name']);
                    $input['products'][$i]['name'] = strlen($input['products'][$i]['name']) > 40? substr($input['products'][$i]['name'], 0, 37).'...' : $input['products'][$i]['name'];
                }
            }
        }
        return $input;
    }

    /**
     * @param array $input
     * @return mixed|string
     * This method will return Thawani payment URL if success
     */
    public function generatePaymentUrl(array $input){
        $output = '';
        $input = $this->process_userMeta($input);
        $server = $this->post([
            'url' => $this->config['isTestMode'] == 1? 'https://uatcheckout.thawani.om/api/v1/checkout/session' : 'https://checkout.thawani.om/api/v1/checkout/session',
            'fields' => $input,
            'headers' => [
                'Thawani-Api-Key' => $this->config['private_key'],
                'Content-Type' => 'application/json'
            ]
        ], 'POST');
        if(isset($server['response']) && !empty($server['response'])){
            $server['response'] = json_decode($server['response'], true);
            $this->responseData = $server['response'];
            if(is_array($server['response']) && count($server['response']) > 0 && $server['response']['success'] == 1 && is_array($server['response']['data']) && count(($server['response']['data']))){
                if (isset($server['response']['data']['session_id']) && !empty($server['response']['data']['session_id'])) {
                    $this->payment_id = $server['response']['data']['session_id'];
                    $output = $this->config['isTestMode'] == 1? 'https://uatcheckout.thawani.om/pay/'.$this->payment_id.'?key='.$this->config['public_key'] : 'https://checkout.thawani.om/pay/'.$this->payment_id.'?key='.$this->config['public_key'];
                }
            }elseif($this->debug){
                $this->iprint_r($server);
            }
        }elseif($this->debug){
            $this->iprint_r($server);
        }
        return $output;
    }

    /**
     * @param $session_id
     * @return array|mixed
     * This method is to check payment status using session_id which generated by generatePaymentUrl() method;
     */
    public function checkPaymentStatus($session_id){
        $output = [];
        $server = $this->post([
            'url' => ($this->config['isTestMode'] == 1? 'https://uatcheckout.thawani.om/api/v1/checkout/session' : 'https://checkout.thawani.om/api/v1/checkout/session').'/'.$session_id, // .'?',
//            'fields' => ['Session_id' => $session_id],
            'headers' => [
                'Thawani-Api-Key' => $this->config['private_key']
            ]
        ], 'GET');
        if(isset($server['response']) && !empty($server['response'])){
            $server['response'] = json_decode($server['response'], true);
            $server['response']['data'] = isset($server['response']['data'][0])? $server['response']['data'][0] : $server['response']['data'];
            if(is_array($server['response']) && count($server['response']) > 0 && $server['response']['success'] == 1 && is_array($server['response']['data']) && count(($server['response']['data']))){
                if(trim(strtolower($server['response']['data']['payment_status'])) == 'paid'){
                    $this->payment_status = 1;
                }
                $output = $server['response']['data'];
            }elseif($this->debug){
                $this->iprint_r($server);
            }
        }elseif($this->debug){
            $this->iprint_r($server);
        }
        return $output;
    }

    /**
     * @param $customer_id
     * @param string $method
     * @return array|mixed
     */
    private function customer($customer_id, $method = 'create'){
        $output = [];
        $methods = [
            'create' => 'POST',
            'get' => 'GET',
            'delete' => 'DELETE',
        ];
        $server = $this->post([
            'url' => ($this->config['isTestMode'] == 1? 'https://uatcheckout.thawani.om/api/v1/customers' : 'https://checkout.thawani.om/api/v1/customers').($method == 'delete'? '/'.$customer_id : ''),
            'fields' => ['client_customer_id' => $customer_id],
            'headers' => [
                'Thawani-Api-Key' => $this->config['private_key']
            ]
        ], $methods[$method]);
        if(isset($server['response']) && !empty($server['response'])){
            $server['response'] = json_decode($server['response'], true);
            if($method != 'delete'){
                $server['response']['data'] = isset($server['response']['data'][0])? $server['response']['data'][0] : $server['response']['data'];
            }
            if(is_array($server['response']) && count($server['response']) > 0) {
                if($method == 'delete' && $server['response']['success'] == 1){
                    $output = $server['response'];
                }elseif ($method != 'delete' && $server['response']['success'] == 1 && is_array($server['response']['data']) && count(($server['response']['data']))) {
                    $output = $server['response']['data'];
                } elseif ($this->debug) {
                    $this->iprint_r($server);
                }
            }elseif($this->debug){
                $this->iprint_r($server);
            }
        }elseif($this->debug){
            $this->iprint_r($server);
        }
        return $output;
    }

    /**
     * @param $customer_id
     * @return array|mixed
     */
    public function createCustomer($customer_id){
        return $this->customer($customer_id, 'create');
    }

    /**
     * @param $customer_id
     * @return array|mixed
     */
    public function getCustomer($customer_id){
        return $this->customer($customer_id, 'get');
    }

    /**
     * @param $customer_id
     * @return array|mixed
     */
    public function deleteCustomer($customer_id){ ## if output is empty array = customer does not exists
        return $this->customer($customer_id, 'delete');
    }

    /**
     * @param int $capture if set to 1, it will save Thawani $_POST in a file in /logs/{PAYMENT_ID}.json
     * @return array
     * This method to handle webhooks from Thawani Portal
     */
    public function handleCallback($capture = 0){
        $input = ['body' => file_get_contents("php://input"), 'headers' => getallheaders()];
        $output = ['is_success' => (int) 0, 'receipt' => (int) 0, 'session' => [], 'raw' => []];
        $input['body'] = !empty($input['body'])? json_decode($input['body'], true) : [];
        if(!is_array($input['body'])){ $input['body'] = []; }
        $output['raw'] = $input['body'];
        if(isset($input['body']['payment_status']) && isset($input['body']['session_id']) && strtolower($input['body']['payment_status']) == 'paid'){
            $output['session'] = $this->checkPaymentStatus($input['body']['session_id']);
            $output['is_success'] = $this->payment_status;
            $output['receipt'] = isset($input['body']['receipt'])? $input['body']['receipt'] : (int) 0;
        }
        if($capture == 1){
            $this->captureCallback($input);
        }
        return $output;
    }

    /**
     * @param $input
     * This can be used for debugging to save logs
     */
    public function captureCallback(array $input){
        $basePath = __DIR__;
        $filePath = $basePath.'/logs';
        $fileName = time();
        if(is_array($input['body']) && isset($input['body']['receipt'])){
            $fileName = $input['body']['receipt'];
        }
        if(!is_dir($filePath)){
            if(is_writable($basePath)){
                mkdir($filePath, 0777);
                $this->protect_directory_access($filePath, 'comprehensive');
            }else{
                trigger_error('Path <b>'.$filePath.'</b> is not writable', E_USER_ERROR);
            }
        }
        $fileName = $filePath.'/'.$fileName.'.json';
        $handle = fopen($fileName, 'w');
        fwrite($handle, json_encode($input));
        fclose($handle);
    }

    /**
     * @param $directory
     * @param string $type
     * This to protect logs directory
     */
    public function protect_directory_access($directory, $type = 'access'){
        $file = $directory.'/.htaccess';
        if(!is_dir($directory)){
            $directory = '../'.$directory;
        }
        if(!file_exists($file)):
            $file = '../'.$file;
        endif;
        if(!file_exists($file)):
            $file = is_dir($directory)? $directory.'/.htaccess' : '../'.$directory.'/.htaccess';
            $handle = fopen($file, 'w');
            fwrite($handle, ($type == 'comprehensive'? 'deny from all' : 'Options -Indexes'));
            fclose($handle);
        endif;
    }

    /**
     * @param $input
     * @param int $return
     * @param string $cssClass
     * @return string
     */
    public function iprint_r($input, $return = 0, $cssClass=''){
        $output = '<pre dir="ltr"'.(!empty($cssClass)? ' class="'.$cssClass.'"' : '').'>'.(is_array($input) || is_object($input)? print_r($input, 1) : $input).'</pre>';
        if(isset($this->errorWarning) && is_callable($this->errorWarning)){
            call_user_func_array($this->errorWarning, [$output]);
        }else{
            if($return != 0) return $output; else echo $output;
        }
    }
}