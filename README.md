## Thawani PHP Class

This class to help with the integration with Thawani Gateway (V2.0).
You can find more about how to use it by [clicking here](https://www.s4d.om/g/e)
## How to install
You can use composer:

```composer require phpawcom/thawani-php-class```

or clone the class using GIT:

    git clone https://github.com/phpawcom/thawani-php-class.git
or Download the archive by [clicking here](https://github.com/phpawcom/thawani-php-class/archive/master.zip).

## Usage
### Call the class:
```php
// include_once(__DIR__.'/thawani.php'); || you don't need this if you are using composer autoload
$thawani = new \s4d\payment\thawani([  
  'isTestMode' => 1, ## set it to 0 to use the class in production mode  
  'public_key' => 'HGvTMLDssJghr9tlN9gr4DVYt0qyBy',  
  'private_key' => 'rRQ26GcsZzoEhbrP2HZvLYDbn9C9et',  
]);
```
### Generate Payment URL:
```php
$url = $thawani->generatePaymentUrl([  
  'client_reference_id' => rand(1000, 9999).$orderId, ## generating random 4 digits prefix to make sure there will be no duplicate ID error  
  'products' => [  
     ['name' => 'test test test test test test test test test test test test ', 'unit_amount' => 100, 'quantity' => 1],  
     ['name' => 'test', 'unit_amount' => 100, 'quantity' => 1],  
   ],
  'success_url' => $thawani->currentPageUrl().'?op=checkPayment', ## Put the link to next a page with the method checkPaymentStatus()
  'cancel_url' => $thawani->currentPageUrl().'?op=checkPayment',  
  'metadata' => [
                'order_id' => $orderId,
                'customer_name' => 'Fulan Al Fulani',
                'customer_phone' => '90000000',
                'customer_email' => 'email@domain.tld'
   ]
 ]);
 if(!empty($url)){  
  ## method will provide you with a payment id from Thawani, you should save it to your order. You can get it using this: $thawani->payment_id  
  ## header('location: '.$url); ## Redirect to payment page  
  $_SESSION['session_id'] = $thawani->payment_id; ## save session_id to use to check payment status later
  echo '<a href="'.$url.'">Click to Pay</a>';  
}
```

### Check Payment Status:
```php
$check = $thawani->checkPaymentStatus($_SESSION['session_id']);  
if($thawani->payment_status == 1){  
  ## successful payment  
  echo '<h2>successful payment</h2>';  
}else{  
  ## failed payment  
  echo '<h2>payment failed</h2>';  
}
```
