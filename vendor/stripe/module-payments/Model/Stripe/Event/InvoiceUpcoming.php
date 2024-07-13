<?php

namespace StripeIntegration\Payments\Model\Stripe\Event;

use StripeIntegration\Payments\Exception\WebhookException;
use StripeIntegration\Payments\Model\Stripe\StripeObjectTrait;

class InvoiceUpcoming
{
    use StripeObjectTrait;

    private $paymentsHelper;
    private $subscriptionsHelper;
    private $config;
    private $recurringOrderHelper;
    private $webhooksHelper;
    private $quoteHelper;
    private $orderHelper;

    public function __construct(
        \StripeIntegration\Payments\Model\Stripe\Service\StripeObjectServicePool $stripeObjectServicePool,
        \StripeIntegration\Payments\Model\Config $config,
        \StripeIntegration\Payments\Helper\Subscriptions $subscriptionsHelper,
        \StripeIntegration\Payments\Helper\Quote $quoteHelper,
        \StripeIntegration\Payments\Helper\Order $orderHelper,
        \StripeIntegration\Payments\Helper\Webhooks $webhooksHelper,
        \StripeIntegration\Payments\Helper\Generic $paymentsHelper,
        \StripeIntegration\Payments\Helper\RecurringOrder $recurringOrderHelper
    )
    {
        $stripeObjectService = $stripeObjectServicePool->getStripeObjectService('events');
        $this->setData($stripeObjectService);

        $this->config = $config;
        $this->paymentsHelper = $paymentsHelper;
        $this->quoteHelper = $quoteHelper;
        $this->orderHelper = $orderHelper;
        $this->subscriptionsHelper = $subscriptionsHelper;
        $this->recurringOrderHelper = $recurringOrderHelper;
        $this->webhooksHelper = $webhooksHelper;

    }

    public function process($object)
    {
        $metadata = null;

        foreach ($object['lines']['data'] as $lineItem)
        {
            if ($lineItem['type'] == "subscription" && !empty($lineItem['metadata']['Order #']))
            {
                $metadata = $lineItem['metadata'];
            }
        }

        if (!$metadata)
        {
            throw new WebhookException("No metadata found", 202);
        }

        /**
         * An easy way to test this in development is to do the following:
         * 1. Place a new subscription order for any subscription product
         * 2. Find the subscription from the Stripe dashboard, and copy the metadata and subscription ID to the 4 variables below
         * 3. Find a historical invoice.upcoming event from https://dashboard.stripe.com/test/events?type=invoice.upcoming
         * 4. Use the command bin/magento stripe:webhooks:process-event -f <event_id>
         *
         * Because you have overwritten the data below, the event will be processed on the new subscription created on step 1
         * and not on the original subscription from the event ID that you used.
         */
        // $metadata['Order #'] = "2000002394";
        // $metadata['SubscriptionProductIDs'] = "2044";
        // $metadata['Type'] = "SubscriptionsTotal";
        // $object['subscription'] = "sub_1NL157HLyfDWKHBqHEC9wLdt";

        // Fetch the subscription, expanding its discount
        $subscription = $this->config->getStripeClient()->subscriptions->retrieve(
            $object['subscription'],
            ['expand' => ['discount']]
        );

        // Initialize Stripe to match the store of the original order
        $originalOrder = $this->orderHelper->loadOrderByIncrementId($metadata['Order #']);
        $mode = ($object['livemode'] ? "live" : "test");
        $this->config->reInitStripe($originalOrder->getStoreId(), $originalOrder->getOrderCurrencyCode(), $mode);

        // Get the tax percent from the original order
        $originalOrderItem = $this->getSubscriptionOrderItem($originalOrder);
        $originalTaxPercent = $this->getTaxPercent($originalOrderItem);
        $latestTaxPercent = $originalOrder->getPayment()->getAdditionalInformation("latest_tax_percent");
        if ($latestTaxPercent === null)
        {
            $latestTaxPercent = $originalTaxPercent;
            $originalOrder->getPayment()->setAdditionalInformation("latest_tax_percent", $latestTaxPercent);
            $this->orderHelper->saveOrder($originalOrder);
        }

        // Get the upcoming invoice of the subscription
        $upcomingInvoice = $this->config->getStripeClient()->invoices->upcoming([
            'subscription' => $object['subscription']
        ]);
        $invoiceDetails = $this->recurringOrderHelper->getInvoiceDetails($upcomingInvoice, $originalOrder);

        // Create a recurring order quote, without saving the quote or the order
        $quote = $this->recurringOrderHelper->createQuoteFrom($originalOrder);
        $this->recurringOrderHelper->setQuoteCustomerFrom($originalOrder, $quote);
        $this->recurringOrderHelper->setQuoteAddressesFrom($originalOrder, $quote);
        $this->recurringOrderHelper->setQuoteItemsFrom($originalOrder, $invoiceDetails, $quote);
        $this->recurringOrderHelper->setQuoteShippingMethodFrom($originalOrder, $quote);
        $this->recurringOrderHelper->setQuoteDiscountFrom($originalOrder, $quote, $subscription->discount);
        $this->recurringOrderHelper->setQuotePaymentMethodFrom($originalOrder, $quote);
        $quote->setTotalsCollectedFlag(false)->collectTotals();
        $quote->setIsActive(false);
        $this->quoteHelper->saveQuote($quote);

        // Check if the tax percent has changed for the subscription item
        $newTaxPercent = $this->getNewTaxPercent($quote, $originalOrderItem);

        if ($newTaxPercent === null)
        {
            throw new WebhookException("The new tax percent could not be calculated");
        }

        // If the tax percentage has changed, update the subscription price to match it
        $latestTaxPercent = round(floatval($latestTaxPercent), 4);
        $newTaxPercent = round(floatval($newTaxPercent), 4);
        if ($latestTaxPercent != $newTaxPercent)
        {
            if (!empty($upcomingInvoice->discount))
            {
                throw new WebhookException("This subscription cannot be changed because it's upcoming invoice includes a discount coupon.");
            }

            $originalOrder->getPayment()->setAdditionalInformation("latest_tax_percent", $newTaxPercent);
            $this->orderHelper->saveOrder($originalOrder);

            $subscription = $this->config->getStripeClient()->subscriptions->retrieve($object['subscription']);
            $this->updateSubscriptionPriceFromQuote($subscription, $quote);
        }
    }

    protected function getNewTaxPercent($quote, $originalOrderItem)
    {
        $orderItem = $this->subscriptionsHelper->getVisibleSubscriptionItem($originalOrderItem);
        foreach ($quote->getAllItems() as $quoteItem)
        {
            if ($quoteItem->getProductId() == $orderItem->getProductId())
            {
                return $quoteItem->getTaxPercent();
            }
        }

        return null;
    }

    private function updateSubscriptionPriceFromQuote($originalSubscription, $quote, $prorate = false)
    {
        $params = $this->getSubscriptionParamsFromQuote($quote);

        if (empty($params['items']))
        {
            throw new WebhookException("Could not update subscription price.");
        }

        $deletedItems = [];
        foreach ($originalSubscription->items->data as $lineItem)
        {
            $deletedItems[] = [
                "id" => $lineItem['id'],
                "deleted" => true
            ];
        }

        $items = array_merge($deletedItems, $params['items']);
        $updateParams = [
            'items' => $items
        ];

        if (!$prorate)
        {
            $updateParams["proration_behavior"] = "none";
        }

        return $this->config->getStripeClient()->subscriptions->update($originalSubscription->id, $updateParams);
    }

    private function getSubscriptionParamsFromQuote($quote)
    {
        $subscription = $this->subscriptionsHelper->getSubscriptionFromQuote($quote);

        $params = [
            'items' => $this->getSubscriptionItemsFromQuote($subscription)
        ];

        return $params;
    }

    private function getSubscriptionItemsFromQuote($subscription)
    {
        if (empty($subscription))
        {
            throw new WebhookException("No subscription specified");
        }

        $recurringPrice = $this->subscriptionsHelper->createSubscriptionPriceForSubscription($subscription);

        $items = [];

        $items[] = [
            "price" => $recurringPrice->id,
            "quantity" => 1
        ];

        return $items;
    }

    public function getSubscriptionOrderItem($order)
    {
        // Get the tax percent from the original order
        $subscriptions = $this->subscriptionsHelper->getSubscriptionsFromOrder($order);
        if (count($subscriptions) < 1)
        {
            throw new WebhookException("No subscriptions found in original order");
        }
        $subscription = array_pop($subscriptions);
        return $subscription['order_item'];
    }

    public function getTaxPercent($orderItem)
    {
        if ($orderItem->getParentItem())
        {
            $orderItem = $orderItem->getParentItem();
        }

        return $orderItem->getTaxPercent();
    }
}