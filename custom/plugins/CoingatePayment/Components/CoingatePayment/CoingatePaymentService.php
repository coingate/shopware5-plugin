<?php

namespace CoingatePayment\Components\CoingatePayment;

require_once __DIR__ . '/../coingate-php/init.php';

class CoingatePaymentService
{
    /**
     * @param $request \Enlight_Controller_Request_Request
     * @return PaymentResponse
     */
     public function createPaymentResponse($order_id, $coingate_environment, $auth_token, $billing, $agent)
     {
         $callback = $this->coingateCallback($order_id, $coingate_environment, $auth_token, $agent);
         $response = new PaymentResponse();
         $response->id = $callback->id;
         $response->status = $callback->status;
         $response->transactionId = $callback->payment_url;
         $token = $this->createPaymentToken($callback->price_amount, $billing['customernumber']);
         $response->token = $token;

         return $response;
     }

    /**
     * @param float $amount
     * @param int $customerId
     * @return string
     */
    public function createPaymentToken($amount, $customerId)
    {
        return md5(implode('|', [$amount, $customerId]));
    }

    private function coingateCallback($order_id, $coingate_environment, $auth_token, $agent)
    {
        try {
            $order = \CoinGate\Merchant\Order::find($order_id, array(), array(
                'environment' => $coingate_environment,
                'auth_token'  => $auth_token,
                'user_agent'  => $agent,
            ));
        } catch (Exception $e) {
          echo $e->getMessage(); // BadCredentials Not found App by Access-Key
        }

        return $order;
    }

}
