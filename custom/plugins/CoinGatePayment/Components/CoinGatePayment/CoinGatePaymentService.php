<?php

namespace CoinGatePayment\Components\CoinGatePayment;

class CoinGatePaymentService
{
    /**
     * @param $request \Enlight_Controller_Request_Request
     * @return PaymentResponse
     */
    public function createPaymentResponse(\Enlight_Controller_Request_Request $request)
    {
        $response = new PaymentResponse();
        $response->id = $request->getParam('id', null);
        $response->status = $request->getParam('status', null);
        $response->token = $request->getParam('token', null);

        return $response;
    }
}
