<?php

namespace CoinGatePayment;

use Shopware\Components\Plugin;
use Doctrine\ORM\Tools\ToolsException;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Models\Payment\Payment;

class CoinGatePayment extends Plugin
{
    /**
     * @param InstallContext $context
     */
    public function install(InstallContext $context)
    {
        /** @var \Shopware\Components\Plugin\PaymentInstaller $installer */
        $installer = $this->container->get('shopware.plugin_payment_installer');

        $options = [
            'name' => 'cryptocurrency_payments_via_coingate',
            'description' => 'Cryptocurrency Payments via CoinGate',
            'action' => 'CoinGatePayment',
            'active' => 1,
            'position' => 0,
            'additionalDescription' =>
                '<img src="custom/plugins/CoinGatePayment/plugin.png" alt="Cryptocurrency Payments via CoinGate" style="max-width:20%;" />'
        ];
        $installer->createOrUpdate($context->getPlugin(), $options);
    }

    /**
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context)
    {
        //$this->setActiveFlag($context->getPlugin()->getPayments(), false);
		$db = $this->container->get('dbal_connection');
		 $paymentId = Shopware()->Db()->fetchOne('SELECT id FROM s_core_paymentmeans where name = "cryptocurrency_payments_via_coingate"');
		 $db->exec('delete from s_core_paymentmeans_subshops where paymentID = ' . $paymentId );
		 $db->exec('delete from s_core_paymentmeans_countries where paymentID = ' . $paymentId );
		 $db->exec('delete from s_core_paymentmeans where id= ' . $paymentId  );
		 
		 $context->scheduleClearCache(InstallContext::CACHE_LIST_ALL);
    }

    /**
     * @param DeactivateContext $context
     */
    public function deactivate(DeactivateContext $context)
    {
        //$this->setActiveFlag($context->getPlugin()->getPayments(), false);
		$db = $this->container->get('dbal_connection');
		$db->exec('update s_core_paymentmeans set active = 0 where name = "cryptocurrency_payments_via_coingate"');
		 
		$context->scheduleClearCache(InstallContext::CACHE_LIST_ALL);
    }

    /**
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context)
    {
        //$this->setActiveFlag($context->getPlugin()->getPayments(), true);
		 $db = $this->container->get('dbal_connection');
		 $db->exec('update s_core_paymentmeans set active = 1 where name = "cryptocurrency_payments_via_coingate"');
		 
		 $context->scheduleClearCache(InstallContext::CACHE_LIST_ALL);
    }

    /**
     * @param Payment[] $payments
     * @param $active bool
     */
    private function setActiveFlag($payments, $active)
    {
        $em = $this->container->get('models');

        foreach ($payments as $payment) {
            $payment->setActive($active);
        }
        $em->flush();
    }

}
