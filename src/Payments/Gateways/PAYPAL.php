<?php
namespace LCSNG\Tools\Payments\Gateways;

use LCSNG\Tools\Payments\GatewayAbstract;

final class PAYPAL extends GatewayAbstract
{
    /**
     * PAYPAL constructor.
     *
     * @param string $secretKey Your PayPal secret key.
     */
    public function __construct($secretKey)
    {
        throw new \Exception("PAYPAL gateway is not yet implemented.");
    }

    public function initializePayment(array $fields): array
    {
        // PENDING: Implementation for initializing a payment with Paypal
        return [];
    }

    public function verifyPayment(string $reference): array
    {
        // PENDING: Implementation for verifying a payment with Paypal
        return [];
    }
}