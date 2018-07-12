# Shopware CoinGate Plugin

Accept Bitcoin & Altcoins on your Shopware store.

Read the module installation instructions below to get started with CoinGate Bitcoin & Altcoin payment gateway on your shop.

## Install

Sign up for CoinGate account at <https://coingate.com> for production and <https://sandbox.coingate.com> for testing (sandbox) environment.

Please note, that for "Test" mode you **must** generate separate API credentials on <https://sandbox.coingate.com>. API credentials generated on <https://coingate.com> will **not** work for "Test" mode.

Also note, that *Receive Currency* parameter in your module configuration window defines the currency of your settlements from CoinGate. Set it to BTC, EUR or USD, depending on how you wish to receive payouts. To receive settlements in **Euros** or **U.S. Dollars** to your bank, you have to verify as a merchant on CoinGate (login to your CoinGate account and click *Verification*). If you set your receive currency to **Bitcoin**, verification is not needed.

### via FTP

1. Download <releaselink>

2. Upload **custom** folder to your website's root directory

3. Go to your Shopware backend panel » **Configuration** » **Plugin Manager** » **Installed**.

3. Click on **Cryptocurrency Payments via CoinGate**.

4. Click on **Install** and **Activate**.

5. Enter your [API credentials](https://support.coingate.com/en/42/how-can-i-create-coingate-api-credentials) (*Auth Token*). Configure **Receive Currency**, select **Environment** and click **Save**.

6. In case you are unable to create an order in your Shopware store using our module, copy-paste the code snippet from below to your config.php file to allow the CoinGate plugin to display any exceptions if they occur.

```  
   "front" => array(
        "showException" => true
   ),
   "phpsettings" => array(
       'display_errors' => 1,
   ),
```
