<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use App\Services\PaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PaymentRepositoryInterface $paymentRepository
    ) {}

    /**
     * Initiate payment for an order
     */
    public function initiate(Request $request, int $orderId): JsonResponse
    {
        $order = $this->orderRepository->findWithRelations($orderId, ['user', 'payment']);

        if (!$order || $order->user_id !== auth()->id()) {
            return $this->notFound('Order not found');
        }

        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|string|in:bank_transfer,credit_card,e_wallet,qris,convenience_store',
            'bank' => 'nullable|string|in:bca,bni,bri,mandiri,permata',
            'e_wallet_type' => 'nullable|string|in:gopay,ovo,dana,shopeepay',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $payment = $this->paymentService->initiatePayment(
                $order,
                $request->payment_method,
                $request->only(['bank', 'e_wallet_type'])
            );

            return $this->success([
                'payment' => $payment,
                'order' => $order->fresh(['payment']),
            ], 'Payment initiated successfully');

        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * Get payment status
     */
    public function status(int $orderId): JsonResponse
    {
        $order = $this->orderRepository->findWithRelations($orderId, ['user', 'payment']);

        if (!$order || $order->user_id !== auth()->id()) {
            return $this->notFound('Order not found');
        }

        $payment = $order->payment;
        if (!$payment) {
            return $this->notFound('No payment found for this order');
        }

        return $this->success([
            'payment' => $payment,
            'order_status' => $order->status,
        ], 'Payment status retrieved');
    }

    /**
     * Handle payment webhook (called by payment gateway)
     */
    public function webhook(Request $request): JsonResponse
    {
        $signature = $request->header('X-Payment-Signature', '');
        $payload = $request->all();

        Log::info('Payment webhook received', [
            'payload' => $payload,
            'has_signature' => !empty($signature),
        ]);

        try {
            $result = $this->paymentService->handleWebhook($payload, $signature);

            if ($result) {
                return response()->json(['message' => 'Webhook processed'], 200);
            }

            return response()->json(['message' => 'Webhook ignored'], 200);

        } catch (\Throwable $e) {
            Log::error('Webhook processing failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * SIMULATE: Payment success (for testing only)
     */
    public function simulateSuccess(int $orderId): JsonResponse
    {
        if (!config('app.debug')) {
            return $this->error('Simulation not available in production', 403);
        }

        $payment = $this->paymentRepository->findByOrderId($orderId);

        if (!$payment) {
            return $this->notFound('Payment not found');
        }

        try {
            $this->paymentService->simulateSuccess($payment);

            return $this->success(
                $payment->fresh()->order->fresh(['items', 'payment', 'shipping']),
                'Payment success simulated'
            );
        } catch (\Throwable $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * SIMULATE: Payment failure (for testing only)
     */
    public function simulateFailure(Request $request, int $orderId): JsonResponse
    {
        if (!config('app.debug')) {
            return $this->error('Simulation not available in production', 403);
        }

        $payment = $this->paymentRepository->findByOrderId($orderId);

        if (!$payment) {
            return $this->notFound('Payment not found');
        }

        try {
            $this->paymentService->simulateFailure(
                $payment,
                $request->get('reason', 'Payment declined by issuer')
            );

            return $this->success(
                $payment->fresh()->order->fresh(['items', 'payment']),
                'Payment failure simulated'
            );
        } catch (\Throwable $e) {
            return $this->serverError($e->getMessage());
        }
    }
}
