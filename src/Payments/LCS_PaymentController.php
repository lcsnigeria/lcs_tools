<?php
namespace LCSNG\Tools\Payments;

use LCSNG\Tools\Payments\Gateways\PAYPAL;
use LCSNG\Tools\Payments\Gateways\PAYSTACK;
use LCSNG\Tools\Payments\Gateways\STRIPE;

/**
 * Payment Gateway Factory Controller.
 *
 * Serves as the unified entry point for instantiating concrete payment gateway adapters
 * implementing {@see GatewayInterface}. Validates required credentials upfront and abstracts
 * provider-specific constructor dependencies behind a single static factory method.
 *
 * ### Supported Gateways:
 * - `'paystack'` → {@see \LCSNG\Tools\Payments\Gateways\PAYSTACK} (requires `$secretKey` and `$ccApiKey`)
 * - `'stripe'`   → {@see \LCSNG\Tools\Payments\Gateways\STRIPE} (requires `$secretKey`)
 * - `'paypal'`   → {@see \LCSNG\Tools\Payments\Gateways\PAYPAL} (requires `$secretKey`)
 *
 * ### Example Usage:
 * ```php
 * \(gateway = LCS_PaymentController::create(LCS_PaymentController::GATEWAY_STRIPE,\)stripeSecretKey);
 * init = gateway->initializePayment([
 *     'email'    => 'customer@example.com',
 *     'amount'   => 25.00,
 *     'currency' => 'USD',
 * ]);
 * ```
 *
 * @package LCSNG\Tools\Payments
 * @see     \LCSNG\Tools\Payments\GatewayInterface
 * @see     \LCSNG\Tools\Payments\GatewayAbstract
 */
final class LCS_PaymentController
{
    public const GATEWAY_PAYSTACK = 'paystack';
    public const GATEWAY_STRIPE   = 'stripe';
    public const GATEWAY_PAYPAL   = 'paypal';

    /**
     * List of all recognized payment gateway identifiers.
     *
     * @var array<int, string>
     */
    public const SUPPORTED_GATEWAYS = [
        self::GATEWAY_PAYSTACK,
        self::GATEWAY_STRIPE,
        self::GATEWAY_PAYPAL,
    ];

    /**
     * Prevent direct instantiation of this static factory class.
     */
    private function __construct() {}

    /**
     * Instantiate and configure a payment gateway adapter by provider name.
     *
     * Resolves the requested gateway identifier (case-insensitive) into its corresponding
     * {@see GatewayInterface} implementation after validating required API keys.
     *
     * @param string $gateway   Payment provider identifier (`'paystack'`, `'stripe'`, or `'paypal'`). Case-insensitive.
     * @param string $secretKey Provider secret API key (`sk_test_...` / `sk_live_...` for Paystack/Stripe, or PayPal secret).
     * @param string $ccApiKey  Currency converter API key (`exchangerate-api`) for USD <-> NGN conversion.
     *                          Required when `$gateway` is `'paystack'`; ignored for `'stripe'` and `'paypal'`.
     *
     * @return GatewayInterface Configured gateway adapter instance ready for payment initialization and verification.
     *
     * @throws \InvalidArgumentException If `$gateway` is unsupported, `$secretKey` is empty, or `$ccApiKey` is missing for Paystack.
     * @throws \RuntimeException If `'paypal'` is requested before its gateway adapter class is implemented.
     */
    public static function create(string $gateway, string $secretKey, string $ccApiKey = ''): GatewayInterface
    {
        $normalizedGateway = strtolower(trim($gateway));
        $trimmedSecret     = trim($secretKey);
        $trimmedCcKey      = trim($ccApiKey);

        if ($trimmedSecret === '') {
            throw new \InvalidArgumentException("Secret API key is required for payment gateway: {$gateway}");
        }

        return match ($normalizedGateway) {
            self::GATEWAY_PAYSTACK => self::createPaystack($trimmedSecret, $trimmedCcKey),
            self::GATEWAY_STRIPE   => new STRIPE($trimmedSecret),
            self::GATEWAY_PAYPAL   => self::createPaypal($trimmedSecret),
            default                => throw new \InvalidArgumentException(
                sprintf(
                    "Unsupported payment gateway '%s'. Supported gateways: %s.",
                    $gateway,
                    implode(', ', self::SUPPORTED_GATEWAYS)
                )
            ),
        };
    }

    /**
     * Check whether a given gateway identifier is supported by this factory.
     *
     * @param string $gateway Gateway identifier to check (case-insensitive).
     * @return bool `true` if supported, `false` otherwise.
     */
    public static function isSupported(string $gateway): bool
    {
        return in_array(strtolower(trim($gateway)), self::SUPPORTED_GATEWAYS, true);
    }

    /**
     * Return all supported payment gateway identifiers.
     *
     * @return array<int, string>
     */
    public static function supportedGateways(): array
    {
        return self::SUPPORTED_GATEWAYS;
    }

    /**
     * Validate dependencies and instantiate the Paystack adapter.
     *
     * @param string $secretKey Trimmed Paystack secret key.
     * @param string $ccApiKey  Trimmed currency converter API key.
     * @return PAYSTACK
     *
     * @throws \InvalidArgumentException If `$ccApiKey` is empty.
     */
    private static function createPaystack(string $secretKey, string $ccApiKey): PAYSTACK
    {
        if ($ccApiKey === '') {
            throw new \InvalidArgumentException('Currency converter API key ($ccApiKey) is required when initializing Paystack.');
        }

        return new PAYSTACK($secretKey, $ccApiKey);
    }

    /**
     * Validate availability and instantiate the PayPal adapter.
     *
     * @param string $secretKey Trimmed PayPal secret key.
     * @return PAYPAL
     *
     * @throws \RuntimeException If the PAYPAL adapter class has not yet been implemented.
     */
    private static function createPaypal(string $secretKey): PAYPAL
    {
        if (!class_exists(PAYPAL::class)) {
            throw new \RuntimeException('PayPal gateway adapter is not yet implemented.');
        }

        return new PAYPAL($secretKey);
    }
}