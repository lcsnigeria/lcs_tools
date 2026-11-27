<?php
namespace LCSNG\Tools\Payments;

/**
 * Interface GatewayInterface
 * 
 * Unified Payment Gateway Contract.
 *
 * Defines the standardized interface implemented by all payment gateway adapters
 * (e.g., Paystack, Stripe, PayPal) within the `LCSNG\Tools\Payments` ecosystem.
 *
 * ### Contract Guarantees:
 * - **USD-First Input:** All implementations accept amounts in `USD` (major units) and handle
 *   any gateway-specific currency conversions internally (such as USD to NGN kobo for Paystack).
 * - **Uniform Output Shapes:** Both `initializePayment()` (19 keys) and `verifyPayment()` (22 keys)
 *   return identical associative array structures across all providers so controllers and services
 *   remain completely gateway-agnostic.
 * - **Non-Throwing Gateway Responses:** Provider API errors, network timeouts, and declined payments
 *   are returned cleanly with `'status' => false` and a descriptive `'message'` rather than uncaught
 *   gateway exceptions (input validation errors may still throw `\InvalidArgumentException`).
 *
 * @package LCSNG\Tools\Payments
 * @see     \LCSNG\Tools\Payments\GatewayAbstract
 * @see     \LCSNG\Tools\Payments\LCS_PaymentController
 */
interface GatewayInterface
{
    /**
     * Initialize a new payment session or transaction with the configured payment gateway.
     *
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
     * } $fields Unified payment initialization parameters:
     *   - `email` (required): Customer email address.
     *   - `amount` (required): Positive charge amount in `USD` (major units, e.g., `50.00`).
     *   - `currency` (required): Must be `'USD'`.
     *   - `customer` (optional): Existing gateway customer ID/code (`cus_...` or `CUS_...`).
     *   - `reference` (optional): Unique merchant transaction reference (also acts as Stripe `idempotency_key`).
     *   - `success_url` (optional): Post-checkout redirect URL for Stripe (`success_url` or embedded `return_url`).
     *   - `callback_url` (optional): Post-attempt redirect URL dedicated to Paystack (`callback_url`).
     *   - `cancel_url` (optional): Redirect URL when user cancels checkout.
     *   - `payment_methods` (optional): Allowed payment channels (e.g., `['card', 'bank_transfer', 'bank']`).
     *   - `metadata` (optional): Associative array of custom key-value metadata attached to the payment.
     *   - `plan_id` (optional): Paystack plan code; required for Paystack subscriptions and unused by Stripe.
     *   - `checkout_mode` (optional): UI rendering mode (`'hosted'` default or `'embedded'`).
     *   - `payment_mode` (optional): Billing mode (`'payment'` default or `'subscription'`).
     *   - `subaccount` (optional): Connected account/subaccount ID (`ACCT_...` or `acct_...`) for split payouts.
     *   - `expires_at` (optional): Unix timestamp or date string specifying session expiration.
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
     * } Standardized 19-key initialization response array.
     *
     * @throws \InvalidArgumentException If required input fields are missing or invalid.
     */
    public function initializePayment(array $fields): array;

    /**
     * Verify the payment status and settlement details of an existing transaction or session.
     *
     * **Fulfillment Rule:** Require `$result['status'] === true` and
     * `in_array($result['payment_status'], ['paid', 'success'], true)`. Also verify that the supplied
     * `$reference` belongs to the order and reconcile the verified amount and currency with the expected
     * gateway charge; this method does not compare the result against order expectations.
     *
     * @param string $reference Unique gateway lookup reference returned in `$initResult['reference']`
     *                          (e.g., Stripe Checkout Session ID `cs_test_...` or Paystack transaction reference).
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
     * } Standardized 22-key verification response array.
     * For Paystack, `amount_in_dollar` is recalculated from NGN using the converter's cached/current rate,
     * rather than a rate saved when checkout was initialized. Treat it as an estimate, not a reliable reconciliation value.
     */
    public function verifyPayment(string $reference): array;
}