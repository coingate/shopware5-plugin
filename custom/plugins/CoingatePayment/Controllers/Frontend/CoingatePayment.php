<?php

use CoingatePayment\Components\CoingatePayment\PaymentResponse;
use CoingatePayment\Components\CoingatePayment\CoingatePaymentService;
use Shopware\Components\Plugin\ConfigReader;
use Shopware\Components\CSRFWhitelistAware;

require_once __DIR__ . '/../../Components/coingate-php/init.php';

class Shopware_Controllers_Frontend_CoingatePayment extends Shopware_Controllers_Frontend_Payment implements CSRFWhitelistAware
{
    private $pluginDirectory;
    private $config;

    const PAYMENTSTATUSPAID = 12;
    const PAYMENTSTATUSCANCELED = 17;
    const PAYMENTSTATUSPENDING = 18;
    const PAYMENTSTATUSREFUNDED = 20;

    public function preDispatch()
    {
        /** @var \Shopware\Components\Plugin $plugin */
        $plugin = $this->get('kernel')->getPlugins()['CoingatePayment'];

        $this->get('template')->addTemplateDir($plugin->getPath() . '/Resources/views/');
    }

    public function indexAction()
    {
        switch ($this->getPaymentShortName()) {
            case 'cryptocurrency_payments_via_coingate':
                return $this->redirect(['action' => 'direct', 'forceSecure' => true]);
            default:
                return $this->redirect(['controller' => 'checkout']);
        }
    }

    /**
     * Direct action method.
     *
     * Collects the payment information and transmits it to the payment provider.
     */
    public function directAction()
    {
        $config = $this->container->get('shopware.plugin.cached_config_reader')->getByPluginName('CoingatePayment');
        $router = $this->Front()->Router();

        $data = $this->getOrderData();
        $order_id = $data[0]["orderID"];
        $shop = $this->getShopData();

        $post_params = array(
            'order_id'          => $order_id,
            'price_amount'      => $this->getAmount(),
            'price_currency'    => $this->getCurrencyShortName(),
            'receive_currency'  => $config['CoinGatePayout'],
            'title'             => $shop[0]["name"],
            'description'       => "Order ID: " . $order_id,
            'success_url'       => $router->assemble(['action' => 'return']),
            'cancel_url'        => $router->assemble(['action' => 'cancel']),
            'callback_url'      => $router->assemble(['action' => 'callback']),
        );


        $coingate_environment = $this->coingateEnvironment();

        $order = \CoinGate\Merchant\Order::create($post_params, array(), array(
            'environment' => $coingate_environment,
            'auth_token'  => $config['CoinGateCredentials'],
            'user_agent'  => $this->userAgent(),
        ));


        if ($order && $order->payment_url) {
            $this->redirect($order->payment_url);
        } else {
            error_log(print_r(array($order), true)."\n", 3, Shopware()->DocPath() . '/error.log');
        }

    }

    public function returnAction()
    {
        $service = $this->container->get('cryptocurrency_payments_via_coingate.coingate_payment_service');
        $token = $this->createPaymentToken($this->getAmount(), $billing['customernumber']);
        $config = $this->container->get('shopware.plugin.cached_config_reader')->getByPluginName('CoingatePayment');
        $coingate_environment = $this->coingateEnvironment();
        $agent = $this->userAgent();
        $id = $this->callbackAction();

        if (isset($id)) {
            $order_id = $id;
        } else {
            $order = $this->getOrderData();
            $order_id = $order[0]["coingate_callback_order_id"];
        }

        $response = $service->createPaymentResponse($order_id, $coingate_environment, $config['CoinGateCredentials'], $billing, $agent);

        if (empty($response->token) && $token != $response->token) {
            $this->forward('cancel');
        }

        switch ($response->status) {
            case 'paid':
                $this->saveOrder(
                    $response->transactionId,
                    $response->token,
                    self::PAYMENTSTATUSPAID
                );
                $this->redirect(['controller' => 'checkout', 'action' => 'finish']);
                break;
            case 'pending':
            case 'confirming':
                $this->saveOrder(
                    self::PAYMENTSTATUSPENDING
                );
                $this->forward('cancel');
                break;
            case 'invalid':
            case 'expired':
            case 'canceled':
                $this->saveOrder(
                    self::PAYMENTSTATUSCANCELED
                );
                $this->forward('cancel');
                break;
            case 'refunded':
                $this->saveOrder(
                    self::PAYMENTSTATUSREFUNDED
                );
                $this->forward('cancel');
                break;
            default:
                $this->forward('cancel');
                break;
        }
    }

    public function callbackAction()
    {
        $id = $this->Request()->getParam('id');

        if (isset($id)) {
            $this->insertOrderID($id);
        }

        return $id;

    }

    public function cancelAction()
    {
    }

    public function createPaymentToken($amount, $customerId)
    {
        return md5(implode('|', [$amount, $customerId]));
    }

    public function getWhitelistedCSRFActions()
    {
        return array(
            'callback',
        );
    }

    private function coingateEnvironment()
    {
        $config = $this->container->get('shopware.plugin.cached_config_reader')->getByPluginName('CoingatePayment');
        if ($config['CoinGateEnvironment'] == 'sandbox') {
            $environment = 'sandbox';
        } else {
            $environment = 'live';
        }

        return $environment;
    }

    private function getOrderData()
    {
        $queryBuilder = $this->container->get('dbal_connection')->createQueryBuilder();
        $queryBuilder->select('*')
            ->from('s_order_attributes');
        $data = $queryBuilder->execute()->fetchAll();
        $last_order = array_values(array_slice($data, -1));

        return $last_order;
    }

    private function getShopData()
    {
        $queryBuilder = $this->container->get('dbal_connection')->createQueryBuilder();
        $queryBuilder->select('*')
            ->from('s_core_shops');
        $data = $queryBuilder->execute()->fetchAll();

        return $data;
    }

    private function getPluginVersion()
    {
        $queryBuilder = $this->container->get('dbal_connection')->createQueryBuilder();
        $queryBuilder->select('version')
            ->from('s_core_plugins')
            ->where('label = "Cryptocurrency Payments via CoinGate"');
        $data = $queryBuilder->execute()->fetchAll();

        return $data;
    }

    private function insertOrderID($id)
    {
        /** @var \Shopware\Bundle\AttributeBundle\Service\CrudService $service */
        $service = $this->get('shopware_attribute.crud_service');
        $service->update('s_order_attributes', 'coingate_callback_order_id', 'text');
        $queryBuilder = $this->container->get('dbal_connection')->createQueryBuilder();
        $queryBuilder
            ->insert('s_order_attributes')
            ->values(
                array(
                    'coingate_callback_order_id' => $id,
                )
            );
        $data = $queryBuilder->execute();
    }

    private function userAgent()
    {
        $coingate_version = $this->getPluginVersion();
        return $agent = 'Shopware v' . Shopware::VERSION . ' CoinGate Extension v' . $coingate_version[0]["version"];
    }

}
