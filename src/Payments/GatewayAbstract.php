<?php
namespace LCSNG\Tools\Payments;

use LCSNG\Tools\WorldData\LCS_CurrencyConverter;

/**
 * Class GatewayAbstract
 * 
 * Base Abstract Payment Gateway Adapter.
 *
 * Provides shared input validation, request payload normalization, currency conversion utilities,
 * and standardized response formatting for all concrete payment gateway adapters implementing
 * {@see GatewayInterface}.
 *
 * ### Core Responsibilities:
 * - **Input Validation (`validateRequestData`):** Enforces presence of required base fields
 *   (`email`, `amount`, `currency`), positive numeric amounts, `USD` as the canonical input currency,
 *   and valid checkout and payment modes.
 * - **Outbound Request Normalization (`buildRequestFields`):** Translates unified 14-key payment
 *   inputs into provider-specific API schemas (converting `USD` to `NGN` kobo for Paystack, and
 *   mapping `checkout_mode`, `payment_mode`, `line_items`, and Stripe Connect `transfer_data` for Stripe).
 * - **Inbound Response Normalization (`normalizeInitializationResponse` & `normalizeVerificationResponse`):**
 *   Transforms heterogeneous raw provider payloads or SDK objects into uniform 19-key initialization
 *   and 22-key verification arrays, ensuring non-throwing fault tolerance on API or FX conversion errors.
 * - **Stateless FX Helpers (`convertDollarToNaira` & `convertNairaToDollar`):** Integrates with
 *   {@see \LCSNG\Tools\CurrencyConverter\LCS_CurrencyConverter} using runtime-injected API keys.
 *
 * @package LCSNG\Tools\Payments
 * @see     \LCSNG\Tools\Payments\GatewayInterface
 * @see     \LCSNG\Tools\Payments\LCS_PaymentController
 * @see     \LCSNG\Tools\Payments\Gateways\PAYSTACK
 * @see     \LCSNG\Tools\Payments\Gateways\STRIPE
 */
abstract class GatewayAbstract implements GatewayInterface 
{
    const REQUIRED_FIELDS = ['email', 'amount', 'currency'];
    const BASE_CURRENCY = 'USD'; // Base currency for conversion

    /**
     * Validate and normalize unified payment initialization fields into gateway-specific API payloads.
     *
     * Enforces USD as the base input currency across the application and transforms normalized keys
     * into the exact schema required by the target gateway:
     * - **Paystack:** Converts USD amount to NGN kobo (`int`), presets `currency` to `'NGN'`, maps
     *   `callback_url`, `channels`, `subaccount`, and folds `cancel_url` (`cancel_action`),
     *   `customer` (`customer_code`), and `expires_at` into `metadata`.
     * - **Stripe:** Configures `ui_mode` (`hosted`|`embedded`), `mode` (`payment`|`subscription`),
     *   enforces mutual exclusion between `customer` and `customer_email`, builds `line_items` in
     *   USD cents, appends `{CHECKOUT_SESSION_ID}` to `success_url`/`return_url`, flattens `metadata`
     *   to scalar strings, and nests `subaccount` inside `transfer_data.destination`.
     *
     * @param string $gateway Target payment gateway identifier (`'paystack'`, `'stripe'`, `'paypal'`).
     * @param array{
     *     email: string,
     *     amount: float|int,
     *     currency: string,
     *     customer?: string|null,
     *     reference?: string|null,
     *     success_url?: string|null,
     *     callback_url?: string|null,
     *     cancel_url?: string|null,
     *     payment_methods?: array<int, string>|null,
     *     metadata?: array<string, mixed>|null,
     *     plan_id?: string|null,
     *     checkout_mode?: 'hosted'|'embedded'|null,
     *     payment_mode?: 'payment'|'subscription'|null,
     *     subaccount?: string|null,
     *     expires_at?: int|string|null
     * } $fields Unified input parameters:
     *   - `email` (required): Customer email address.
     *   - `amount` (required): Positive payment amount in USD (major units, e.g., `25.50`).
     *   - `currency` (required): Must match `self::BASE_CURRENCY` (`'USD'`).
     *   - `customer` (optional): Gateway customer ID/code (`cus_...` for Stripe, `CUS_...` for Paystack).
     *   - `reference` (optional): Merchant-generated unique transaction reference.
     *   - `success_url` (optional): Post-checkout redirect URL for Stripe (`success_url` or embedded `return_url`).
     *   - `callback_url` (optional): Post-attempt redirect URL dedicated to Paystack (`callback_url`).
     *   - `cancel_url` (optional): Redirect URL when user cancels checkout (`cancel_url` in Stripe hosted mode; `metadata.cancel_action` in Paystack).
     *   - `payment_methods` (optional): Allowed channels (e.g., `['card', 'bank_transfer', 'bank']`).
     *   - `metadata` (optional): Associative array of custom key-value pairs attached to the transaction.
     *   - `plan_id` (optional): Paystack plan code; required when the gateway is Paystack and `payment_mode` is `'subscription'`.
     *   - `checkout_mode` (optional): UI rendering mode (`'hosted'` default or `'embedded'` for Stripe modal).
     *   - `payment_mode` (optional): Billing type (`'payment'` default or `'subscription'`).
     *   - `subaccount` (optional): Connected account/subaccount ID (`ACCT_...` or `acct_...`) for split/destination payouts.
     *   - `expires_at` (optional): Unix timestamp or date string specifying session expiration.
     * @param string $ccApiKey Currency converter API key for USD-to-NGN conversion (used by Paystack).
     * 
     * @return array<string, mixed> Gateway-ready associative payload formatted for the target provider's initialization API.
     *
     * @throws \InvalidArgumentException If required fields are missing, amount is invalid, currency is not USD, a Paystack subscription lacks `plan_id`, or gateway is unsupported.
     * @throws \Exception If the target gateway is not yet implemented (e.g., `'paypal'`) or currency conversion fails.
     */
    protected function buildRequestFields(string $gateway, array $fields, string $ccApiKey = ''): array
    {
        // Validate the request data before normalizing it
        $this->validateRequestData($fields);

        $normalizedFields = [];

        switch (strtolower($gateway)) {
            case 'paystack':
                $isSubscription = ($fields['payment_mode'] ?? 'payment') === 'subscription';
                $planId         = $fields['plan_id'] ?? null;
                if ($isSubscription) {
                    if (!is_string($planId) || trim($planId) === '') {
                        throw new \InvalidArgumentException(
                            'A non-empty Paystack plan_id is required when payment_mode is subscription.'
                        );
                    }
                    $planId = trim($planId);
                }

                if (empty(trim($ccApiKey))) {
                    throw new \InvalidArgumentException("Currency converter API key is required for Paystack.");
                }

                $normalizedFields = [
                    'email'    => $fields['email'],
                    'currency' => 'NGN',
                    'amount'   => (int) round($this->convertDollarToNaira((float) $fields['amount'] * 100, $ccApiKey)),
                ];

                if ($isSubscription) {
                    $normalizedFields['plan'] = $planId;
                }

                if (!empty($fields['reference'])) {
                    $normalizedFields['reference'] = $fields['reference'];
                }

                if (!empty($fields['subaccount'])) {
                    $normalizedFields['subaccount'] = $fields['subaccount'];
                }

                if (!empty($fields['callback_url'])) {
                    $normalizedFields['callback_url'] = $fields['callback_url'];
                }

                if (!empty($fields['payment_methods']) && is_array($fields['payment_methods'])) {
                    $normalizedFields['channels'] = array_values(array_unique(array_map('strtolower', $fields['payment_methods'])));
                }

                $metadata = is_array($fields['metadata'] ?? null) ? $fields['metadata'] : [];

                if (!empty($fields['cancel_url'])) {
                    $metadata['cancel_action'] = $fields['cancel_url'];
                }
                if (!empty($fields['customer'])) {
                    $metadata['customer_code'] = $fields['customer'];
                }
                if (!empty($fields['expires_at'])) {
                    $metadata['expires_at'] = $fields['expires_at'];
                }

                if (!empty($metadata)) {
                    $normalizedFields['metadata'] = $metadata;
                }
                break;

            case 'paypal':
                // NOT YET IMPLEMENTED
                throw new \Exception("Paypal gateway is not yet implemented.");

            case 'stripe':
                $isEmbedded     = ($fields['checkout_mode'] ?? 'hosted') === 'embedded';
                $isSubscription = ($fields['payment_mode'] ?? 'payment') === 'subscription';
                $unitAmount     = (int) round((float) $fields['amount'] * 100);

                if ($unitAmount < 1) {
                    throw new \InvalidArgumentException('Stripe amount must round to at least one cent.');
                }

                $normalizedFields = [
                    'ui_mode' => $isEmbedded ? 'embedded' : 'hosted',
                    'mode'    => $isSubscription ? 'subscription' : 'payment',
                ];

                // Customer vs. Email (Mutually exclusive in Stripe)
                if (!empty($fields['customer'])) {
                    $normalizedFields['customer'] = $fields['customer'];
                } else {
                    $normalizedFields['customer_email'] = $fields['email'];
                }

                if (!empty($fields['reference'])) {
                    $normalizedFields['client_reference_id'] = $fields['reference'];
                }

                // Line Items (Currency lives inside price_data for Stripe Checkout)
                $priceData = [
                    'currency'     => 'usd',
                    'product_data' => [
                        'name' => $isSubscription ? 'Subscription Payment' : 'Order Payment',
                    ],
                    'unit_amount'  => $unitAmount,
                ];

                if ($isSubscription) {
                    $priceData['recurring'] = ['interval' => 'month'];
                }

                $normalizedFields['line_items'] = [[
                    'price_data' => $priceData,
                    'quantity'   => 1,
                ]];

                // Redirect URLs based on checkout_mode (ui_mode)
                $appendSessionId = static function (string $url): string {
                    if (str_contains($url, '{CHECKOUT_SESSION_ID}')) {
                        return $url;
                    }

                    $fragmentPosition = strpos($url, '#');
                    $fragment = $fragmentPosition === false ? '' : substr($url, $fragmentPosition);
                    $urlWithoutFragment = $fragmentPosition === false ? $url : substr($url, 0, $fragmentPosition);

                    if (!str_contains($urlWithoutFragment, '?')) {
                        $separator = '?';
                    } elseif (str_ends_with($urlWithoutFragment, '?') || str_ends_with($urlWithoutFragment, '&')) {
                        $separator = '';
                    } else {
                        $separator = '&';
                    }

                    return $urlWithoutFragment . $separator . 'reference={CHECKOUT_SESSION_ID}' . $fragment;
                };

                if ($isEmbedded) {
                    if (!empty($fields['success_url'])) {
                        $normalizedFields['return_url'] = $appendSessionId($fields['success_url']);
                    } else {
                        $normalizedFields['redirect_on_completion'] = 'never';
                    }
                } else {
                    if (!empty($fields['success_url'])) {
                        $normalizedFields['success_url'] = $appendSessionId($fields['success_url']);
                    }
                    if (!empty($fields['cancel_url'])) {
                        $normalizedFields['cancel_url'] = $fields['cancel_url'];
                    }
                }

                // Payment Methods
                if (!empty($fields['payment_methods']) && is_array($fields['payment_methods'])) {
                    $methodMap = [
                        'card'          => 'card',
                        'bank_transfer' => 'customer_balance',
                        'bank'          => 'us_bank_account',
                    ];
                    $mapped = array_map(
                        static fn($m) => $methodMap[strtolower($m)] ?? $m,
                        $fields['payment_methods']
                    );
                    $normalizedFields['payment_method_types'] = array_values(array_unique($mapped));
                }

                // Metadata (Flattened to scalar strings for Stripe)
                if (!empty($fields['metadata']) && is_array($fields['metadata'])) {
                    $flatMeta = [];
                    foreach ($fields['metadata'] as $k => $v) {
                        $flatMeta[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v);
                    }
                    $normalizedFields['metadata'] = $flatMeta;
                }

                // Expiration Timestamp
                if (!empty($fields['expires_at'])) {
                    $normalizedFields['expires_at'] = is_numeric($fields['expires_at'])
                        ? (int) $fields['expires_at']
                        : strtotime((string) $fields['expires_at']);
                }

                // Subaccount (Stripe Connect destination transfer)
                if (!empty($fields['subaccount'])) {
                    $targetKey = $isSubscription ? 'subscription_data' : 'payment_intent_data';
                    $normalizedFields[$targetKey]['transfer_data'] = [
                        'destination' => $fields['subaccount'],
                    ];
                }
                break;

            default:
                throw new \InvalidArgumentException("Unsupported payment gateway: {$gateway}");
        }

        return $normalizedFields;
    }

    /**
     * Normalize raw payment initialization responses from any gateway into a unified 19-key array structure.
     *
     * Never throws on gateway API errors; instead returns `'status' => false` alongside the gateway error
     * message and safe `null`/`[]` defaults. Pre-populates missing gateway fields (such as Paystack's omitted
     * amount, currency, email, and metadata) using the original `$initFields` fallback array.
     *
     * @param string       $gateway    Target payment gateway identifier (`'paystack'`, `'stripe'`, `'paypal'`).
     * @param array|object $response   Raw associative array or SDK object (e.g., `\Stripe\Checkout\Session`) returned by the gateway.
     * @param array<string, mixed> $initFields Optional original initialization request array used to backfill fields not echoed by the gateway.
     *
     * @return array{
     *     status: bool,
     *     payment_status: string|null,
     *     payment_id: string|null,
     *     reference: string|null,
     *     checkout_url: string|null,
     *     checkout_code: string|null,
     *     gateway: string,
     *     checkout_mode: string|null,
     *     payment_mode: string|null,
     *     message: string,
     *     amount: float|null,
     *     currency: string|null,
     *     email: string|null,
     *     customer_data: array<string, mixed>,
     *     payment_methods: array<int, string>,
     *     metadata: array<string, mixed>,
     *     created_at: string|null,
     *     expires_at: string|null,
     *     gateway_mode: 'live'|'test'|null
     * } Normalized 19-key initialization result:
     *   - `status`: `true` if the initialization API request succeeded, `false` otherwise (does NOT indicate payment completion).
     *   - `payment_status`: Initial payment state (`'unpaid'`, `'no_payment_required'`, or `null`).
     *   - `payment_id`: Underlying payment/intent ID if already created, otherwise `null`.
     *   - `reference`: Primary lookup reference required for future verification (`cs_...` for Stripe, `reference` for Paystack).
     *   - `checkout_url`: Hosted payment page URL (`authorization_url` for Paystack, `url` for Stripe hosted mode, or `null`).
     *   - `checkout_code`: Inline/embedded modal token (`access_code` for Paystack, `client_secret` for Stripe embedded mode, or `null`).
     *   - `gateway`: Lowercase gateway identifier (`'paystack'`, `'stripe'`, etc.).
     *   - `checkout_mode`: UI mode (`'hosted'`, `'embedded'`, or `null`).
     *   - `payment_mode`: Billing mode (`'payment'`, `'subscription'`, or `null`).
     *   - `message`: Human-readable status or error message from the gateway.
     *   - `amount`: Requested amount in major currency units (`float`) or `null`.
     *   - `currency`: Uppercase 3-letter ISO currency code (e.g., `'USD'`) or `null`.
     *   - `email`: Customer email address or `null`.
     *   - `customer_data`: Customer identifiers/details (`id`, `email`, etc.) or `[]`.
     *   - `payment_methods`: Enabled payment channels/types or `[]`.
     *   - `metadata`: Attached transaction metadata key-value pairs or `[]`.
     *   - `created_at`: ISO 8601 creation timestamp or `null`.
     *   - `expires_at`: ISO 8601 expiration timestamp or `null`.
     *   - `gateway_mode`: Environment indicator (`'live'`, `'test'`, or `null`).
     */
    protected function normalizeInitializationResponse(string $gateway, array|object $response, array $initFields = []): array
    {
        $raw = is_object($response) && method_exists($response, 'toArray')
            ? $response->toArray()
            : (array) $response;

        $gatewayName = strtolower($gateway);

        // Normalize fallback customer data & expires_at from $initFields
        $defaultCustomer = !empty($initFields['customer']) ? ['id' => $initFields['customer']] : [];
        if (!empty($initFields['email'])) {
            $defaultCustomer['email'] = $initFields['email'];
        }

        $defaultExpiresAt = null;
        if (!empty($initFields['expires_at'])) {
            $defaultExpiresAt = is_numeric($initFields['expires_at'])
                ? date('c', (int) $initFields['expires_at'])
                : date('c', strtotime((string) $initFields['expires_at']));
        }

        // Shared base schema pre-populated with $initFields fallbacks
        $base = [
            'status'          => false,
            'payment_status'  => null,
            'payment_id'      => null,
            'reference'       => $initFields['reference'] ?? null,
            'checkout_url'    => null,
            'checkout_code'   => null,
            'gateway'         => $gatewayName,
            'checkout_mode'   => $initFields['checkout_mode'] ?? 'hosted',
            'payment_mode'    => $initFields['payment_mode'] ?? 'payment',
            'message'         => 'Initialization failed',
            'amount'          => isset($initFields['amount']) ? (float) $initFields['amount'] : null,
            'currency'        => isset($initFields['currency']) ? strtoupper($initFields['currency']) : self::BASE_CURRENCY,
            'email'           => $initFields['email'] ?? null,
            'customer_data'   => $defaultCustomer,
            'payment_methods' => is_array($initFields['payment_methods'] ?? null) ? $initFields['payment_methods'] : [],
            'metadata'        => is_array($initFields['metadata'] ?? null) ? $initFields['metadata'] : [],
            'created_at'      => date('c'),
            'expires_at'      => $defaultExpiresAt,
            'gateway_mode'    => null,
        ];

        switch ($gatewayName) {
            case 'paystack':
                $isSuccess = (bool) ($raw['status'] ?? false);
                $data      = is_array($raw['data'] ?? null) ? $raw['data'] : [];

                if (!$isSuccess) {
                    return array_merge($base, [
                        'message' => $raw['message'] ?? 'Paystack initialization failed.',
                    ]);
                }

                $reference   = $data['reference'] ?? $base['reference'];
                $checkoutUrl = $data['authorization_url'] ?? null;
                $checkoutCode = $data['access_code'] ?? null;
                $hasReference = is_string($reference) && trim($reference) !== '';
                $hasCheckoutTarget = (is_string($checkoutUrl) && trim($checkoutUrl) !== '')
                    || (is_string($checkoutCode) && trim($checkoutCode) !== '');

                if (!$hasReference || !$hasCheckoutTarget) {
                    return array_merge($base, [
                        'message' => 'Paystack initialization response is missing a transaction reference or checkout URL/access code.',
                    ]);
                }

                return array_merge($base, [
                    'status'        => true,
                    'reference'     => $reference,
                    'checkout_url'  => $checkoutUrl,
                    'checkout_code' => $checkoutCode,
                    'message'       => $raw['message'] ?? 'Authorization URL created',
                ]);

            case 'paypal':
                return array_merge($base, [
                    'message' => 'Paypal gateway is not yet implemented.',
                ]);

            case 'stripe':
                $hasId = !empty($raw['id']);

                if (!$hasId) {
                    $errorMsg = $raw['error']['message'] ?? ($raw['message'] ?? 'Stripe initialization failed.');
                    return array_merge($base, [
                        'message' => $errorMsg,
                    ]);
                }

                $customerDetails = is_array($raw['customer_details'] ?? null) ? $raw['customer_details'] : $base['customer_data'];
                if (!empty($raw['customer'])) {
                    $customerDetails['id'] = is_string($raw['customer']) ? $raw['customer'] : ($raw['customer']['id'] ?? null);
                }

                return array_merge($base, [
                    'status'          => true,
                    'payment_status'  => $raw['payment_status'] ?? null,
                    'payment_id'      => $raw['payment_intent'] ?? ($raw['subscription'] ?? null),
                    'reference'       => $raw['id'] ?? $base['reference'], // Stripe's cs_... overrides custom ref for lookup
                    'checkout_url'    => $raw['url'] ?? null,
                    'checkout_code'   => $raw['client_secret'] ?? null,
                    'checkout_mode'   => $raw['ui_mode'] ?? $base['checkout_mode'],
                    'payment_mode'    => $raw['mode'] ?? $base['payment_mode'],
                    'message'         => 'Checkout session created',
                    'amount'          => isset($raw['amount_total']) ? ((int) $raw['amount_total']) / 100 : $base['amount'],
                    'currency'        => isset($raw['currency']) ? strtoupper($raw['currency']) : $base['currency'],
                    'email'           => $raw['customer_email'] ?? ($customerDetails['email'] ?? $base['email']),
                    'customer_data'   => $customerDetails,
                    'payment_methods' => is_array($raw['payment_method_types'] ?? null) ? $raw['payment_method_types'] : $base['payment_methods'],
                    'metadata'        => is_array($raw['metadata'] ?? null) ? $raw['metadata'] : $base['metadata'],
                    'created_at'      => isset($raw['created']) ? date('c', (int) $raw['created']) : $base['created_at'],
                    'expires_at'      => isset($raw['expires_at']) ? date('c', (int) $raw['expires_at']) : $base['expires_at'],
                    'gateway_mode'    => isset($raw['livemode']) ? ($raw['livemode'] ? 'live' : 'test') : null,
                ]);

            default:
                return array_merge($base, [
                    'message' => "Unsupported payment gateway: {$gateway}",
                ]);
        }
    }

    /**
     * Normalize raw payment verification or webhook payloads into a unified 22-key array structure.
     *
     * Never throws on gateway or FX conversion errors; if verification fails, returns `'status' => false`
     * with safe `null`/`[]` defaults. Preserves the gateway's actual settled `amount` and uppercase `currency`
     * (`NGN` for Paystack, `USD` for Stripe) while computing `'amount_in_dollar'` (falling back to `null`
     * if NGN-to-USD conversion fails so verification itself is never disrupted). For Paystack, it is recalculated
     * from NGN using the converter's cached/current rate, rather than a rate saved when checkout was initialized.
     * Treat it as an estimate, not a reliable reconciliation value.
     *
     * @param string       $gateway  Target payment gateway identifier (`'paystack'`, `'stripe'`, `'paypal'`).
     * @param array|object $response Raw associative array or SDK object (e.g., `\Stripe\Checkout\Session`) from the gateway's verify endpoint or webhook.
     * @param string       $ccApiKey Currency converter API key for NGN-to-USD conversion (used by Paystack).
     * 
     * @return array{
     *     status: bool,
     *     payment_id: string|null,
     *     payment_status: 'paid'|'unpaid'|'no_payment_required'|'success'|'failed'|'abandoned'|'ongoing'|'pending'|'processing'|'queued'|'reversed'|string|null,
     *     payment_methods: array<int, string>,
     *     checkout_status: 'open'|'complete'|'expired'|string|null,
     *     checkout_url: string|null,
     *     checkout_code: string|null,
     *     amount: float|null,
     *     amount_in_dollar: float|null,
     *     currency: string|null,
     *     customer_data: array<string, mixed>,
     *     subscription_data: array<string, mixed>,
     *     metadata: array<string, mixed>,
     *     created_at: string|null,
     *     expires_at: string|null,
     *     paid_at: string|null,
     *     transaction_date: string|null,
     *     gateway_mode: 'live'|'test'|null,
     *     ip_address: string|null,
     *     log: array<string, mixed>,
     *     message: string,
     *     last_payment_error: array<string, mixed>|string|null
     * } Normalized 22-key verification result:
     *   - `status`: `true` if the verification API lookup succeeded, `false` if the lookup failed (check `payment_status` to confirm funds were collected).
     *   - `payment_id`: Gateway's internal transaction/intent ID (`pi_...` or `sub_...` for Stripe, numeric transaction ID string for Paystack).
     *   - `payment_status`: Normalized payment outcome (`'paid'`/`'success'` indicate confirmed payment; `'unpaid'`, `'failed'`, `'abandoned'`, `'pending'`, etc. indicate incomplete/unsuccessful payment).
     *   - `payment_methods`: Payment channel(s) used or enabled (e.g., `['card']`, `['bank_transfer']`).
     *   - `checkout_status`: Lifecycle state of the checkout session (`'complete'`, `'open'`, `'expired'`, or Paystack status).
     *   - `checkout_url`: Hosted checkout URL if still open, otherwise `null`.
     *   - `checkout_code`: Embedded client secret if applicable, otherwise `null`.
     *   - `amount`: Actual amount processed by the gateway in major currency units (`NGN` for Paystack, `USD` for Stripe).
     *   - `amount_in_dollar`: USD equivalent (`float`) of `amount`; mirrors `amount` on Stripe, converted from NGN on Paystack (or `null` if conversion fails).
     *     For Paystack, it is recalculated from NGN using the converter's cached/current rate, rather than a rate saved when checkout was initialized.
     *     Treat it as an estimate, not a reliable reconciliation value.
     *   - `currency`: Uppercase 3-letter ISO code of the actual charged currency (`'NGN'` or `'USD'`).
     *   - `customer_data`: Customer details (`id`, `customer_code`, `email`, `name`, `phone`, `address`, etc.) or `[]`.
     *   - `subscription_data`: Recurring subscription/plan/invoice details (`subscription`, `invoice`, `plan`, `plan_object`, `subaccount`) or `[]`.
     *   - `metadata`: Custom key-value metadata attached during initialization or `[]`.
     *   - `created_at`: ISO 8601 timestamp when the session/transaction was created.
     *   - `expires_at`: ISO 8601 timestamp when the session expires/expired, or `null`.
     *   - `paid_at`: Provider-reported ISO 8601 payment timestamp, or `null` when unavailable. Stripe only supplies it from a subscription invoice's `status_transitions.paid_at`; Stripe one-time sessions return `null`.
     *   - `transaction_date`: ISO 8601 timestamp of the transaction event.
     *   - `gateway_mode`: Environment mode (`'live'` or `'test'`).
     *   - `ip_address`: Customer IP address (populated on Paystack; `null` on Stripe Checkout Sessions).
     *   - `log`: Gateway checkout interaction audit logs (populated on Paystack; `[]` on Stripe).
     *   - `message`: Gateway response message or verification summary.
     *   - `last_payment_error`: Decline reason or error object/message if payment failed, otherwise `null`.
     */
    protected function normalizeVerificationResponse(string $gateway, array|object $response, string $ccApiKey): array
    {
        $raw = is_object($response) && method_exists($response, 'toArray')
            ? $response->toArray()
            : (array) $response;

        $gatewayName = strtolower($gateway);

        // Shared 22-key base schema (safe fallback if verification fails or fields don't apply)
        $base = [
            'status'             => false,
            'payment_id'         => null,
            'payment_status'     => null,
            'payment_methods'    => [],
            'checkout_status'    => null,
            'checkout_url'       => null,
            'checkout_code'      => null,
            'amount'             => null,
            'amount_in_dollar'   => null,
            'currency'           => null,
            'customer_data'      => [],
            'subscription_data'  => [],
            'metadata'           => [],
            'created_at'         => null,
            'expires_at'         => null,
            'paid_at'            => null,
            'transaction_date'   => null,
            'gateway_mode'       => null,
            'ip_address'         => null,
            'log'                => [],
            'message'            => 'Verification failed',
            'last_payment_error' => null,
        ];

        switch ($gatewayName) {
            case 'paystack':
                $isSuccess = (bool) ($raw['status'] ?? false);
                $data      = is_array($raw['data'] ?? null) ? $raw['data'] : [];

                if (!$isSuccess || empty($data)) {
                    return array_merge($base, [
                        'message' => $raw['message'] ?? 'Paystack verification failed.',
                    ]);
                }

                // Paystack returns "" (empty string) when metadata is empty
                $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];

                // Exact charged amount in major units (e.g., Kobo -> Naira)
                $amountCharged = isset($data['amount']) && is_numeric($data['amount'])
                    ? ((int) $data['amount']) / 100
                    : null;

                // Safely convert NGN to USD; return null if converter fails so verification still succeeds
                $amountInDollar = null;
                if ($amountCharged !== null) {
                    if ($amountCharged > 0) {
                        try {
                            $amountInDollar = round($this->convertNairaToDollar($amountCharged, $ccApiKey), 2);
                        } catch (\Throwable $e) {
                            $amountInDollar = null;
                        }
                    } else {
                        $amountInDollar = 0.0;
                    }
                }

                // Build subscription_data if a plan or subscription object is attached
                $subscriptionData = [];
                if (!empty($data['plan'])) {
                    $subscriptionData['plan'] = $data['plan'];
                }
                if (is_array($data['plan_object'] ?? null) && !empty($data['plan_object'])) {
                    $subscriptionData['plan_object'] = $data['plan_object'];
                }
                if (is_array($data['subaccount'] ?? null) && !empty($data['subaccount'])) {
                    $subscriptionData['subaccount'] = $data['subaccount'];
                }

                // Extract last payment error if transaction did not succeed
                $lastError = null;
                if (($data['status'] ?? '') !== 'success' && !empty($data['gateway_response'])) {
                    $lastError = $data['gateway_response'];
                }

                $expiresAt = null;
                if (!empty($metadata['expires_at'])) {
                    $expiresAt = is_numeric($metadata['expires_at'])
                        ? date('c', (int) $metadata['expires_at'])
                        : date('c', strtotime((string) $metadata['expires_at']));
                }

                return array_merge($base, [
                    'status'             => true,
                    'payment_id'         => isset($data['id']) ? (string) $data['id'] : null,
                    'payment_status'     => $data['status'] ?? null, // 'success' | 'failed' | 'abandoned' | 'pending' | 'ongoing' | 'processing' | 'queued' | 'reversed'
                    'payment_methods'    => !empty($data['channel']) ? [$data['channel']] : [],
                    'checkout_status'    => ($data['status'] ?? '') === 'success' ? 'complete' : ($data['status'] ?? null),
                    'checkout_url'       => null,
                    'checkout_code'      => null,
                    'amount'             => $amountCharged,
                    'amount_in_dollar'   => $amountInDollar,
                    'currency'           => isset($data['currency']) ? strtoupper($data['currency']) : 'NGN',
                    'customer_data'      => is_array($data['customer'] ?? null) ? $data['customer'] : [],
                    'subscription_data'  => $subscriptionData,
                    'metadata'           => $metadata,
                    'created_at'         => !empty($data['created_at']) ? date('c', strtotime((string) $data['created_at'])) : null,
                    'expires_at'         => $expiresAt,
                    'paid_at'            => !empty($data['paid_at']) ? date('c', strtotime((string) $data['paid_at'])) : null,
                    'transaction_date'   => !empty($data['transaction_date']) ? date('c', strtotime((string) $data['transaction_date'])) : null,
                    'gateway_mode'       => $data['domain'] ?? null, // 'live' | 'test'
                    'ip_address'         => $data['ip_address'] ?? null,
                    'log'                => is_array($data['log'] ?? null) ? $data['log'] : [],
                    'message'            => $data['gateway_response'] ?? ($raw['message'] ?? 'Verification successful'),
                    'last_payment_error' => $lastError,
                ]);

            case 'paypal':
                return array_merge($base, [
                    'message' => 'Paypal gateway is not yet implemented.',
                ]);

            case 'stripe':
                $hasId = !empty($raw['id']);

                if (!$hasId) {
                    $errorMsg = $raw['error']['message'] ?? ($raw['message'] ?? 'Stripe verification failed.');
                    return array_merge($base, [
                        'message'            => $errorMsg,
                        'last_payment_error' => $raw['error'] ?? $errorMsg,
                    ]);
                }

                // Extract payment_id and last_payment_error (handles both string IDs and expanded PaymentIntent objects)
                $paymentIntent = $raw['payment_intent'] ?? null;
                $paymentId     = is_array($paymentIntent)
                    ? ($paymentIntent['id'] ?? null)
                    : ($paymentIntent ?? ($raw['subscription'] ?? null));

                $lastError = is_array($paymentIntent)
                    ? ($paymentIntent['last_payment_error'] ?? null)
                    : null;

                // Merge customer ID and customer_details into customer_data
                $customerData = is_array($raw['customer_details'] ?? null) ? $raw['customer_details'] : [];
                if (!empty($raw['customer'])) {
                    $customerData['id'] = is_string($raw['customer'])
                        ? $raw['customer']
                        : ($raw['customer']['id'] ?? null);
                }

                // Build subscription_data if subscription or invoice is present
                $subscriptionData = [];
                if (!empty($raw['subscription'])) {
                    $subscriptionData['subscription'] = $raw['subscription'];
                }
                if (!empty($raw['invoice'])) {
                    $subscriptionData['invoice'] = $raw['invoice'];
                }

                $createdIso    = isset($raw['created']) ? date('c', (int) $raw['created']) : null;
                $isPaid        = ($raw['payment_status'] ?? '') === 'paid';
                $invoice       = is_array($raw['invoice'] ?? null) ? $raw['invoice'] : [];
                $transitions   = is_array($invoice['status_transitions'] ?? null)
                    ? $invoice['status_transitions']
                    : [];
                $invoicePaidAt = $transitions['paid_at'] ?? null;
                $paidAt        = ($raw['mode'] ?? '') === 'subscription'
                    && is_numeric($invoicePaidAt)
                    && (int) $invoicePaidAt > 0
                    ? date('c', (int) $invoicePaidAt)
                    : null;
                $amountCharged = isset($raw['amount_total']) ? ((int) $raw['amount_total']) / 100 : null;

                return array_merge($base, [
                    'status'             => true,
                    'payment_id'         => $paymentId,
                    'payment_status'     => $raw['payment_status'] ?? null, // 'paid' | 'unpaid' | 'no_payment_required'
                    'payment_methods'    => is_array($raw['payment_method_types'] ?? null) ? $raw['payment_method_types'] : [],
                    'checkout_status'    => $raw['status'] ?? null,         // 'open' | 'complete' | 'expired'
                    'checkout_url'       => $raw['url'] ?? null,
                    'checkout_code'      => $raw['client_secret'] ?? null,
                    'amount'             => $amountCharged,
                    'amount_in_dollar'   => $amountCharged, // Already USD
                    'currency'           => isset($raw['currency']) ? strtoupper($raw['currency']) : self::BASE_CURRENCY,
                    'customer_data'      => $customerData,
                    'subscription_data'  => $subscriptionData,
                    'metadata'           => is_array($raw['metadata'] ?? null) ? $raw['metadata'] : [],
                    'created_at'         => $createdIso,
                    'expires_at'         => isset($raw['expires_at']) ? date('c', (int) $raw['expires_at']) : null,
                    'paid_at'            => $paidAt,
                    'transaction_date'   => $createdIso,
                    'gateway_mode'       => isset($raw['livemode']) ? ($raw['livemode'] ? 'live' : 'test') : null,
                    'ip_address'         => null,
                    'log'                => [],
                    'message'            => $isPaid ? 'Payment verified' : ('Session status: ' . ($raw['status'] ?? 'unknown')),
                    'last_payment_error' => $lastError,
                ]);
            default:
            return array_merge($base, [
                'message' => "Unsupported payment gateway: {$gateway}",
            ]);
        }
    }

    private function validateRequestData(array $fields): void
    {
        // Validate that all required fields are present and valid
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!array_key_exists($field, $fields)) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }
        // Validate that the amount is a positive number
        if (!is_numeric($fields['amount']) || $fields['amount'] <= 0) {
            throw new \InvalidArgumentException("Invalid amount: {$fields['amount']}");
        }
        // Validate that the currency is USD
        if (strtoupper($fields['currency']) !== self::BASE_CURRENCY) {
            throw new \InvalidArgumentException("Unsupported currency: {$fields['currency']}. Only USD is supported. Value is normalized to NGN for Paystack.");
        }

        foreach ([
            'checkout_mode' => ['hosted', 'embedded'],
            'payment_mode' => ['payment', 'subscription'],
        ] as $field => $allowedValues) {
            if (!array_key_exists($field, $fields) || $fields[$field] === null) {
                continue;
            }
            if (!is_string($fields[$field]) || !in_array($fields[$field], $allowedValues, true)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        "Invalid %s: expected one of %s.",
                        $field,
                        implode(', ', $allowedValues)
                    )
                );
            }
        }
    }

    /**
     * Convert dollar amount to Nigerian Naira (NGN)
     *
     * Converts a dollar amount to NGN using the currency converter API.
     * Supports conversion from major currencies like USD and EUR.
     * 
     * @param float $amount Amount to convert
     * @param string $ccApiKey Currency converter API key
     *
     * @return float Converted amount in NGN
     *
     * @throws \Exception If conversion fails or invalid currency is provided
     */
    private function convertDollarToNaira(float $amount, string $ccApiKey): float
    {
        try {
            if ($amount <= 0) {
                throw new \Exception("Amount must be greater than zero");
            }

            $converter = new LCS_CurrencyConverter([
                'apiProvider' => 'exchangerate-api',
                'apiKey' => $ccApiKey,
                'baseCurrency' => 'USD'
            ]);

            // Convert currency
            $ngnAmount = $converter->convertRates('USD', 'NGN', $amount);

            if (empty($ngnAmount) || !is_numeric($ngnAmount)) {
                throw new \Exception('Invalid conversion result received');
            }

            return floatval($ngnAmount);
        } catch (\Throwable $e) {
            throw new \Exception('Error converting Dollar to Naira: ' . $e->getMessage());
        }
    }

    /**
     * Convert Nigerian Naira (NGN) amount to US Dollar (USD)
     *
     * Converts a NGN amount to USD using the currency converter API.
     * Supports conversion from major currencies like NGN and EUR.
     * 
     * @param float $amount Amount in NGN to convert
     * @param string $ccApiKey Currency converter API key
     *
     * @return float Converted amount in USD
     *
     * @throws \Exception If conversion fails or invalid currency is provided
     */
    private function convertNairaToDollar(float $amount, string $ccApiKey): float
    {
        try {
            if ($amount <= 0) {
                throw new \Exception("Amount must be greater than zero");
            }

            $converter = new LCS_CurrencyConverter([
                'apiProvider' => 'exchangerate-api',
                'apiKey' => $ccApiKey,
                'baseCurrency' => 'NGN'
            ]);

            // Convert currency
            $usdAmount = $converter->convertRates('NGN', 'USD', $amount);

            if (empty($usdAmount) || !is_numeric($usdAmount)) {
                throw new \Exception('Invalid conversion result received');
            }

            return floatval($usdAmount);
        } catch (\Throwable $e) {
            throw new \Exception('Error converting Naira to Dollar: ' . $e->getMessage());
        }
    }
}