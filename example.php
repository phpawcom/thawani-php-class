<?php
session_start();
include('thawani.php');
$thawani = new \s4d\payment\thawani([
    'isTestMode' => 1, ## set it to 0 to use the class in production mode
    'public_key' => 'HGvTMLDssJghr9tlN9gr4DVYt0qyBy',
    'private_key' => 'rRQ26GcsZzoEhbrP2HZvLYDbn9C9et',
]);
$thawani->debug = true;
$_REQUEST['op'] = !isset($_REQUEST['op'])? '' : $_REQUEST['op']; ## to avoid PHP notice message
switch ($_REQUEST['op']){
    default: ## Generate payment URL
        $orderId = '1001'; ## order number based on your existing system
        $input = [
            'client_reference_id' => rand(1000, 9999).$orderId, ## generating random 4 digits prefix to make sure there will be no duplicate ID error
            'products' => [
                ['name' => 'test test test test test test test test test test test test ', 'unit_amount' => 100, 'quantity' => 1],
                ['name' => 'test', 'unit_amount' => 100, 'quantity' => 1],
            ],
//            'customer_id' => 'cus_xxxxxxxxxxxxxxx', ## TODO: enable this when its activate from Thawani Side
            'success_url' => $thawani->currentPageUrl().'?op=checkPayment',
            'cancel_url' => $thawani->currentPageUrl().'?op=checkPayment',
            'metadata' => [
                'order_id' => $orderId
            ]
        ];
        $url = $thawani->generatePaymentUrl($input);
        echo '<pre dir="ltr">' . print_r($thawani->responseData, true) . '</pre>';
        $_SESSION['session_id'] = $thawani->payment_id; ## save session_id to use to check payment status later
        if(!empty($url)){
            ## method will provide you with a payment id from Thawani, you should save it to your order. You can get it using this: $thawani->payment_id
            ## header('location: '.$url); ## Redirect to payment page
            echo '<a href="'.$url.'">Click to Pay</a>';
        }
        break;
    case 'callback': ## handle Thawani callback, you need to update order status in your database or file system, in Thawani V2.0 you need to add a link to this page in Webhooks
        $result = $thawani->handleCallback(1);
        /*
         * $results contain some information, it will be like:
         * $results = [
         *  'is_success' => 0 for failed, 1 for successful
         *  'receipt' => receipt ID, generate for transaction
         *  'raw' => [ SESSION DATA ]
         * ];
         */
        if($thawani->payment_status == 1){
            ## successful payment
        }else{
            ## failed payment
        }
        break;
    case 'checkPayment':
        $check = $thawani->checkPaymentStatus($_SESSION['session_id']);
        if($thawani->payment_status == 1){
            ## successful payment
            echo '<h2>successful payment</h2>';
        }else{
            ## failed payment
            echo '<h2>payment failed</h2>';
        }
        $thawani->iprint_r($check);
        break;
    case 'createCustomer':
        $customer = $thawani->createCustomer('me@alrashdi.co');
        $thawani->iprint_r($customer);
        break;
    case 'getCustomer':
        $customer = $thawani->getCustomer('me@alrashdi.co');
        $thawani->iprint_r($customer);
        break;
    case 'deleteCustomer':
        $customer = $thawani->deleteCustomer('cus_xxxxxxxxxxxxxxx');
        $thawani->iprint_r($customer);
        break;
    case 'home':
        echo 'Get payment status from database';
        break;
}