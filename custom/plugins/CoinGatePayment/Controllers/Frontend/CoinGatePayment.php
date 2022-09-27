<?php

use Shopware\Components\Plugin\ConfigReader;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Models\Order\Status;
use Shopware\Components\Cart\PaymentTokenService;

require_once __DIR__ . '/../../Components/coingate-php/init.php';

class Shopware_Controllers_Frontend_CoinGatePayment extends Shopware_Controllers_Frontend_Payment implements CSRFWhitelistAware
{
    const PAYMENTSTATUSPAID = 12;

    /**
     * Index action method.
     *
     * Forwards to the correct action.
     */
    public function indexAction()
    {
        if ($this->getPaymentShortName() != 'cryptocurrency_payments_via_coingate') {
            $this->redirect(['controller' => 'checkout', 'action' => 'index']);

            return;
        }

        // --------------------------------------------------------------------------

        $pluginConfig = $this->get('shopware.plugin.cached_config_reader')->getByPluginName('CoinGatePayment');
        $router = $this->Front()->Router();

        $orderAttr = $this->getLatestOrderAttributes();

        $orderParams = [
            'order_id'          => $orderAttr['orderID'],
            'price_amount'      => $this->getAmount(),
            'price_currency'    => $this->getCurrencyShortName(),
            'receive_currency'  => $pluginConfig['CoinGatePayout'],
            'title'             => $this->getShopData()['name'],
            'description'       => '',
            'success_url'       => $router->assemble(['action' => 'return']),
            'callback_url'      => $router->assemble(['action' => 'callback']),
            'cancel_url'        => $router->assemble(['action' => 'cancel']),

            'token'             => $this->persistBasket()
        ];

        $order = \CoinGate\Merchant\Order::create($orderParams, [], $this->getCoinGateCredentials($pluginConfig));

        if (! $order) {
            $this->redirect(sprintf('%s?%s=1', $router->assemble(['controller' => 'checkout', 'action' => 'cart']), 'CouldNotConnectToCoinGate'));

            return;
        }

        $this->updateOrderAttributes($orderAttr['id'], $order->id);

        $this->redirect($order->payment_url);
    }

    public function callbackAction()
    {
        $service = $this->container->get('cryptocurrency_payments_via_coingate.coingate_payment_service');

        $pluginConfig = $this->container->get('shopware.plugin.cached_config_reader')->getByPluginName('CoinGatePayment');

        // ----------------------------------------

        $paymentResponse = $service->createPaymentResponse($this->Request());

        // ----------------------------------------

        try {
            $order = \CoinGate\Merchant\Order::find($paymentResponse->id, [], $this->getCoinGateCredentials($pluginConfig));
        } catch (Exception $e) {
            return;
        }

        $orderAttr = $this->findOrderAttributesByCoinGateOrderId($paymentResponse->id);
        // validate if order id's match
        if ($orderAttr['orderID'] != $order->order_id) {
            return;
        }

        // ----------------------------------------

        if ($order->status == 'paid') {
            $this->loadBasketFromSignature($paymentResponse->token);
            $this->saveOrder(
                $order->payment_url,
                $paymentResponse->token,
                self::PAYMENTSTATUSPAID
            );
        }

        $this->Front()->Plugins()->ViewRenderer()->setNoRender();
        $this->Response()->setHttpResponseCode(200);
    }

    public function returnAction()
    {
        Shopware()->Modules()->Order()->sDeleteTemporaryOrder();

        Shopware()->Db()->executeUpdate('DELETE FROM s_order_basket WHERE sessionID=?', [$this->get('session')->offsetGet('sessionId')]);

        $this->redirect([
            'module'     => 'frontend',
            'controller' => 'checkout',
            'action'     => 'finish'
        ]);
    }

    /**
     * Cancel action method
     */
    public function cancelAction()
    {
        $this->redirect(['controller' => 'checkout', 'action' => 'confirm']);
    }

    // ------------------------------------------------------
    // ------------------------------------------------------

    private function getCoinGateCredentials($pluginConfig)
    {
        return [
            'environment' => $pluginConfig['CoinGateEnvironment'] == 'sandbox' ? 'sandbox' : 'live',
            'auth_token'  => $pluginConfig['CoinGateCredentials'],
            'user_agent'  => 'Shopware v' . Shopware()->Config()->get('Version') . ' CoinGate Extension v' . $this->getPluginVersion()
        ];
    }

    private function getPluginVersion()
    {
        $plugin = $this->get('kernel')->getPlugins()['CoinGatePayment'];
        $filename = $plugin->getPath() . '/plugin.xml';
        $xml = simplexml_load_file($filename);
        return (string)$xml->version;
    }

    private function getShopData()
    {
        return $this->get('dbal_connection')
            ->createQueryBuilder()
            ->select('*')
            ->from('s_core_shops')
            ->execute()
            ->fetchAssociative();
    }

    private function getLatestOrderAttributes()
    {
        return $this->get('dbal_connection')
            ->createQueryBuilder()
            ->select('*')
            ->from('s_order_attributes')
            ->orderBy('id', 'DESC')
            ->execute()
            ->fetchAssociative();
    }

    private function updateOrderAttributes($id, $cgOrderId)
    {
        /** @var \Shopware\Bundle\AttributeBundle\Service\CrudService $service */
        $service = $this->get('shopware_attribute.crud_service');
        $service->update('s_order_attributes', 'coingate_callback_order_id', 'text');

        $this->get('dbal_connection')->executeStatement('UPDATE s_order_attributes SET coingate_callback_order_id = ? WHERE id = ?', [$cgOrderId, $id]);
    }

    private function findOrderAttributesByCoinGateOrderId($cgOrderId)
    {
        $connection = $this->container->get('dbal_connection');
        return $connection->fetchAssociative('SELECT * FROM s_order_attributes WHERE coingate_callback_order_id = ?', [$cgOrderId]);
    }

    // ------------------------------------------------------
    // ------------------------------------------------------
    // ------------------------------------------------------

    public function getWhitelistedCSRFActions()
    {
        return ['callback'];
    }
}
