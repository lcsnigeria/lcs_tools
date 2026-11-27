<?php
namespace LCSNG\Tools\Payments\Gateways;

use LCSNG\Tools\Payments\GatewayAbstract;

/**
 * Class STRIPE
 * 
 * Stripe Payment Gateway Adapter.
 *
 * Implements standardized payment initialization and verification against Stripe's Checkout Sessions API
 * using the official `stripe-php` SDK (`\Stripe\StripeClient`) while adhering to the application's
 * USD-first payment architecture.
 *
 * ### Architectural Behavior:
 * - **Native USD Processing:** Accepts payment amounts strictly in `USD` (major units) and converts
 *   them to integer cents (`unit_amount`) via {@see GatewayAbstract::buildRequestFields()} without
 *   requiring external currency conversion.
 * - **Dual Checkout UI Support:** Supports both Stripe-hosted redirects (`checkout_mode => 'hosted'`,
 *   returning `checkout_url`) and inline modal checkouts (`checkout_mode => 'embedded'`, returning
 *   `checkout_code` / `client_secret` with `redirect_on_completion => 'never'` when no `success_url` is set).
 * - **Expanded Verification:** Retrieves sessions with `['expand' => ['payment_intent', 'invoice']]` so
 *   underlying `PaymentIntent` IDs, `last_payment_error` objects, and subscription invoice payment timestamps
 *   are captured in a single API call.
 * - **Non-Throwing API Responses:** Catches `\Stripe\Exception\ApiErrorException` during API calls and
 *   routes errors through {@see GatewayAbstract::normalizeInitializationResponse()} and
 *   {@see GatewayAbstract::normalizeVerificationResponse()} with `'status' => false`.
 *
 * @package LCSNG\Tools\Payments\Gateways
 * @see     \LCSNG\Tools\Payments\GatewayAbstract
 * @see     \LCSNG\Tools\Payments\GatewayInterface
 */
final class STRIPE extends GatewayAbstract
{
    private string $secretKey;

    /**
     * Initialize a new Stripe gateway instance.
     *
     * @param string $secretKey Stripe secret API key (`sk_test_...` for test mode, `sk_live_...` for live mode).
     *                          Never expose this key on the frontend.
     */
    public function __construct($secretKey)
    {
        $this->secretKey = $secretKey;
    }

    /**
     * Initialize a new Stripe Checkout Session.
     *
     * Validates the unified USD input fields, maps them to Stripe's Checkout Session schema via
     * {@see GatewayAbstract::buildRequestFields()}, creates the session via `\Stripe\StripeClient`,
     * and normalizes the response into a standardized 19-key array.
     *
     * **Note on References:** Stripe generates its own session ID (`cs_test_...` or `cs_live_...`) and
     * returns it in `$result['reference']` (while storing your custom input `reference` in `client_reference_id`).
     * Always persist the returned `$result['reference']` (`cs_...`) for subsequent verification.
     *
     * **Note on Idempotency:** When `$fields['reference']` is provided, it is automatically forwarded
     * as Stripe's `idempotency_key` (`['idempotency_key' => $fields['reference']]`). Retrying an identical
     * request within 24 hours safely returns the existing session without creating duplicates. However,
     * Stripe enforces strict payload matching—reusing the same `reference` with modified parameters (such
     * as a changed `amount` or `email`) will fail with an `IdempotencyException` (`status => false`). Always
     * generate a fresh `reference` whenever order details change before re-initializing.
     *
     * @param array{
     *     email: string,
     *     amount: float|int,
     *     currency: string,
     *     customer?: string|null,
     *     reference?: string|null,
     *     success_url?: string|null,
     *     cancel_url?: string|null,
     *     payment_methods?: array<int, string>|null,
     *     metadata?: array<string, mixed>|null,
     *     plan_id?: string|null,
     *     checkout_mode?: 'hosted'|'embedded'|null,
     *     payment_mode?: 'payment'|'subscription'|null,
     *     subaccount?: string|null,
     *     expires_at?: int|string|null
     * } $fields Unified payment initialization parameters:
     *   - `email` (required): Customer's email address (ignored if `customer` ID is provided).
     *   - `amount` (required): Positive charge amount in `USD` (major units, e.g., `25.00`) that must round to at least one cent.
     *   - `currency` (required): Must be `'USD'`.
     *   - `customer` (optional): Existing Stripe Customer ID (`cus_...`); mutually exclusive with `customer_email`.
     *   - `reference` (optional): Merchant reference stored in `client_reference_id` and sent as Stripe's `idempotency_key`.
     *   - `success_url` (optional): Post-checkout redirect URL (`success_url` in hosted mode, `return_url` in embedded mode).
     *   - `cancel_url` (optional): Cancellation redirect URL (used only when `checkout_mode === 'hosted'`).
     *   - `payment_methods` (optional): Allowed payment methods (e.g., `['card', 'bank_transfer', 'bank']`).
     *   - `metadata` (optional): Associative array of custom metadata (automatically flattened to scalar strings).
     *   - `plan_id` (optional): Paystack-only option; unused by Stripe.
     *   - `checkout_mode` (optional): UI rendering mode (`'hosted'` default or `'embedded'`).
     *   - `payment_mode` (optional): Billing mode (`'payment'` default or `'subscription'`).
     *   - `subaccount` (optional): Connected Stripe account ID (`acct_...`) for Stripe Connect destination transfers.
     *   - `expires_at` (optional): Unix timestamp or date string between 30 minutes and 24 hours from creation.
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
     * } Standardized 19-key initialization response:
     *   - `status`: `true` if Stripe created the Checkout Session, `false` if an API error occurred.
     *   - `payment_status`: Initial Stripe payment state (typically `'unpaid'` at initialization).
     *   - `payment_id`: Underlying `PaymentIntent` or `Subscription` ID if created, otherwise `null`.
     *   - `reference`: Stripe Checkout Session ID (`cs_test_...` / `cs_live_...`) required for verification.
     *   - `checkout_url`: Hosted Stripe Checkout URL (`$session->url`) when `checkout_mode === 'hosted'`, else `null`.
     *   - `checkout_code`: Embedded Checkout `client_secret` when `checkout_mode === 'embedded'`, else `null`.
     *   - `gateway`: Always `'stripe'`.
     *   - `checkout_mode`: Active UI mode (`'hosted'` or `'embedded'`).
     *   - `payment_mode`: Active billing mode (`'payment'` or `'subscription'`).
     *   - `message`: `'Checkout session created'` on success, or the Stripe API error message on failure.
     *   - `amount`: Total session amount in `USD` (`float`).
     *   - `currency`: Uppercase currency code (`'USD'`).
     *   - `email`: Customer email address.
     *   - `customer_data`: Customer details array (`id`, `email`, etc.).
     *   - `payment_methods`: Enabled Stripe `payment_method_types` array.
     *   - `metadata`: Flattened key-value metadata array attached to the session.
     *   - `created_at`: ISO 8601 timestamp when the session was created.
     *   - `expires_at`: ISO 8601 timestamp when the session expires.
     *   - `gateway_mode`: Environment mode (`'live'` or `'test'`).
     *
     * @throws \InvalidArgumentException If `$fields` fails input validation, including a Stripe amount that rounds below one cent.
     */
    public function initializePayment(array $fields): array
    {
        $payload = $this->buildRequestFields('stripe', $fields, '');

        // Use merchant reference as idempotency key when provided to prevent duplicate sessions on retry
        $options = [];
        if (!empty($fields['reference'])) {
            $options['idempotency_key'] = (string) $fields['reference'];
        }

        try {
            $stripe   = new \Stripe\StripeClient($this->secretKey);
            $response = $stripe->checkout->sessions->create($payload, $options);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $response = [
                'error' => [
                    'message' => $e->getMessage(),
                    'code'    => $e->getStripeCode(),
                    'type'    => $e->getError()?->type,
                ],
            ];
        }

        return $this->normalizeInitializationResponse('stripe', $response, $fields);
    }

    /**
     * Verify the status and settlement details of a Stripe Checkout Session by its Session ID.
     *
     * Retrieves the session via `$stripe->checkout->sessions->retrieve()` with `payment_intent` expanded
     * and normalizes the response into a standardized 22-key array. Never throws on invalid session IDs
     * or Stripe API errors—instead returns `'status' => false` with the error details in `'message'`
     * and `'last_payment_error'`.
     *
     * **Important:** Require `$result['status'] === true && $result['payment_status'] === 'paid'`
     * before fulfillment. Also ensure the Checkout Session ID belongs to the expected order and reconcile
     * the returned amount/currency with the order's expected charge.
     *
     * @param string $reference Stripe Checkout Session ID (`cs_test_...` or `cs_live_...`) returned at initialization.
     *
     * @return array{
     *     status: bool,
     *     payment_id: string|null,
     *     payment_status: 'paid'|'unpaid'|'no_payment_required'|string|null,
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
     * } Standardized 22-key verification response:
     *   - `status`: `true` if Stripe retrieved the session successfully, `false` on API/lookup error.
     *   - `payment_id`: Underlying Stripe `PaymentIntent` ID (`pi_...`) or `Subscription` ID (`sub_...`).
     *   - `payment_status`: Stripe payment state (`'paid'` indicates confirmed funds collected).
     *   - `payment_methods`: Payment method types configured on the session (e.g., `['card']`).
     *   - `checkout_status`: Lifecycle state of the session (`'open'`, `'complete'`, or `'expired'`).
     *   - `checkout_url`: Hosted checkout URL if session is still `'open'`, otherwise `null`.
     *   - `checkout_code`: Embedded `client_secret` if applicable, otherwise `null`.
     *   - `amount`: Total amount settled by Stripe in `USD` (`float`, converted from cents).
     *   - `amount_in_dollar`: Mirrors `amount` (`float`) since Stripe processes natively in `USD`.
     *   - `currency`: Uppercase 3-letter ISO currency code (`'USD'`).
     *   - `customer_data`: Merged customer object containing `id` (`cus_...`), `email`, `name`, `phone`, and `address`.
     *   - `subscription_data`: Contains `subscription` (`sub_...`) and/or `invoice` (`in_...`) IDs if subscription mode, else `[]`.
     *   - `metadata`: Custom key-value metadata attached during initialization.
     *   - `created_at`: ISO 8601 timestamp when the session was created.
     *   - `expires_at`: ISO 8601 timestamp when the session expires/expired.
     *   - `paid_at`: ISO 8601 timestamp from a subscription invoice's `status_transitions.paid_at`; `null` for one-time payments or when unavailable.
     *   - `transaction_date`: ISO 8601 timestamp of the session record.
     *   - `gateway_mode`: Environment mode (`'live'` or `'test'`).
     *   - `ip_address`: Always `null` (Stripe Checkout Sessions do not expose client IP addresses directly).
     *   - `log`: Always `[]` (Stripe does not expose checkout step logs on Session objects).
     *   - `message`: `'Payment verified'` when paid, current session status summary, or API error message.
     *   - `last_payment_error`: Expanded `PaymentIntent::last_payment_error` array or API error details if failed, else `null`.
     */
    public function verifyPayment(string $reference): array
    {
        if (empty(trim($reference))) {
            return $this->normalizeVerificationResponse('stripe', [
                'error' => ['message' => 'Checkout session ID is required for verification.'],
            ], '');
        }

        try {
            $stripe   = new \Stripe\StripeClient($this->secretKey);
            $response = $stripe->checkout->sessions->retrieve(trim($reference), [
                'expand' => ['payment_intent', 'invoice'],
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $response = [
                'error' => [
                    'message' => $e->getMessage(),
                    'code'    => $e->getStripeCode(),
                    'type'    => $e->getError()?->type,
                ],
            ];
        }

        return $this->normalizeVerificationResponse('stripe', $response, '');
    }
}