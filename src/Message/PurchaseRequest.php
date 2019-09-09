<?php

/**
 * Stripe Purchase Request.
 */
namespace Omnipay\Stripe\Message;

use Omnipay\Common\ItemBag;

/**
 * Stripe Purchase Request.
 *
 * To charge a credit card, you create a new charge object. If your API key
 * is in test mode, the supplied card won't actually be charged, though
 * everything else will occur as if in live mode. (Stripe assumes that the
 * charge would have completed successfully).
 *
 * Example:
 *
 * <code>
 *   // Create a gateway for the Stripe Gateway
 *   // (routes to GatewayFactory::create)
 *   $gateway = Omnipay::create('Stripe');
 *
 *   // Initialise the gateway
 *   $gateway->initialize(array(
 *       'apiKey' => 'MyApiKey',
 *   ));
 *
 *   // Create a credit card object
 *   // This card can be used for testing.
 *   $card = new CreditCard(array(
 *               'firstName'    => 'Example',
 *               'lastName'     => 'Customer',
 *               'number'       => '4242424242424242',
 *               'expiryMonth'  => '01',
 *               'expiryYear'   => '2020',
 *               'cvv'          => '123',
 *               'email'                 => 'customer@example.com',
 *               'billingAddress1'       => '1 Scrubby Creek Road',
 *               'billingCountry'        => 'AU',
 *               'billingCity'           => 'Scrubby Creek',
 *               'billingPostcode'       => '4999',
 *               'billingState'          => 'QLD',
 *   ));
 *
 *   // Do a purchase transaction on the gateway
 *   $transaction = $gateway->purchase(array(
 *       'amount'                   => '10.00',
 *       'currency'                 => 'USD',
 *       'description'              => 'This is a test purchase transaction.',
 *       'card'                     => $card,
 *   ));
 *   $response = $transaction->send();
 *   if ($response->isSuccessful()) {
 *       echo "Purchase transaction was successful!\n";
 *       $sale_id = $response->getTransactionReference();
 *       echo "Transaction reference = " . $sale_id . "\n";
 *   }
 * </code>
 *
 * Because a purchase request in Stripe looks similar to an
 * Authorize request, this class simply extends the AuthorizeRequest
 * class and over-rides the getData method setting capture = true.
 *
 * @see \Omnipay\Stripe\Gateway
 * @link https://stripe.com/docs/api#charges
 */
class PurchaseRequest extends AuthorizeRequest
{
    const API_VERSION_STATEMENT_DESCRIPTOR = "2014-12-17";

    public function getData()
    {
        $data = parent::getData();
        $data['capture'] = 'true';
        if ($this->getMoto()) {
            $data['payment_method_details'] = ['card' => ['moto' => true]];
        }
        $items = $this->getItems();
        if ($items && $this->validateLineItemAmounts($items)) {
            $lineItems = array();
            foreach ($items as $item) {
                $lineItem = array();
                $lineItem['product_code'] = substr($item->getName(), 0, 12);
                $lineItem['product_description'] = substr($item->getDescription(), 0, 26);
                $lineItem['unit_cost'] = $this->getAmountWithCurrencyPrecision($item->getPrice());
                $lineItem['tax_amount'] = $this->getAmountWithCurrencyPrecision($item->getTaxes());
                $lineItem['discount_amount'] = $this->getAmountWithCurrencyPrecision($item->getDiscount());
                $lineItem['quantity'] = $item->getQuantity();
                $lineItems[] = $lineItem;
            }
            $data['level3'] = array(
                'merchant_reference' => $this->getTransactionId(),
                'line_items' => $lineItems
            );
        }

        return $data;
    }

    private function getAmountWithCurrencyPrecision($amount)
    {
        return (int)round($amount * pow(10, $this->getCurrencyDecimalPlaces()));
    }

    private function validateLineItemAmounts(ItemBag $lineItems)
    {
        $actualAmount = 0;
        foreach ($lineItems as $item) {
            $actualAmount += $item->getQuantity() * $item->getPrice() + $item->getTaxes() - $item->getDiscount();
        }
        return $actualAmount == $this->getAmount();
    }

    public function getEndpoint()
    {
        $endPoint = parent::getEndpoint();
        $expandParams = $this->getExpand();
        if ($expandParams && is_array($expandParams)) {
            $endPoint = $endPoint . '?';
            foreach ($expandParams as $idx => $param) {
                $endPoint .= "expand[]=" . urlencode($param);
                if ($idx !== count($expandParams) - 1) {
                    $endPoint .= "&";
                }
            }
        }
        return $endPoint;
    }
}
