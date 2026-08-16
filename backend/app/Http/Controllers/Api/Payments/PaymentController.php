<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\StorePaymentRequest;
use App\Http\Resources\Payments\PaymentResource;
use App\Models\Customers\Customer;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentMethod;
use App\Models\Sales\Invoice;
use App\Services\Payments\OverpaymentException;
use App\Services\Payments\PaymentService;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints REST de pagos.
 *
 * `store` aplica un pago a una invoice, actualiza el payment_status
 * de la invoice (`pending` → `partial` → `paid`) y crea el registro
 * `InvoicePayment` pivot. La transaccionalidad está delegada al
 * `PaymentService`.
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly StoreContextService $storeContext,
    ) {}

    /**
     * Lista los pagos del cliente autenticado en la tienda actual.
     *
     * Defense-in-depth: aunque `BelongsToStore` filtra por tienda, este
     * endpoint también filtra explícitamente por `customer_id` para evitar
     * exponer pagos de otros clientes dentro de la misma tienda.
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        return PaymentResource::collection(Payment::query()
            ->where(column: 'customer_id', operator: '=', value: $customer->id)
            ->orderByDesc('paid_at')
            ->get())
            ->response();
    }

    /**
     * Aplica un pago a una invoice.
     *
     * Body:
     *   - invoice_id (required)
     *   - payment_method_id (required)
     *   - amount_cents (required, > 0)
     *   - reference (optional)
     *   - notes (optional)
     *
     * Respuesta 201 con `PaymentResource` (incluyendo invoice_payments).
     * Errores:
     *   - 422 validación o sobrepago del saldo pendiente.
     *   - 404 invoice o payment method inexistente.
     *   - 403 invoice no pertenece al cliente.
     */
    public function store(StorePaymentRequest $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $invoice = Invoice::query()->findOrFail(
            $request->integer('invoice_id'),
        );

        abort_if(
            $invoice->customer_id !== $customer->id,
            403,
            'La invoice no pertenece al cliente autenticado.',
        );

        $paymentMethod = PaymentMethod::findOrFail(
            $request->integer('payment_method_id'),
        );

        try {
            $payment = $this->payments->applyPayment(
                customer: $customer,
                invoice: $invoice,
                paymentMethod: $paymentMethod,
                appliedAmountCents: $request->integer('amount_cents'),
                reference: $request->input('reference'),
                notes: $request->input('notes'),
            );
        } catch (OverpaymentException $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                422,
            );
        }

        $payment->load(['invoicePayments.invoice', 'currency', 'paymentMethod']);

        return (new PaymentResource($payment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Devuelve un pago específico (verifica que pertenezca al cliente).
     */
    public function show(Payment $payment): JsonResponse
    {
        $customer = $this->resolveCustomer(request());
        abort_if(
            $payment->customer_id !== $customer->id,
            403,
            'El pago no pertenece al cliente autenticado.',
        );

        return (new PaymentResource(
            $payment->load(['invoicePayments.invoice', 'currency', 'paymentMethod']),
        ))->response();
    }

    /**
     * Update / destroy se exponen como stubs en este MVP: el ledger
     * de pagos es inmutable (regla de auditoría). Se mantienen para
     * mantener apiResource wiring; rechazan con 405.
     */
    public function update(Payment $payment): JsonResponse
    {
        return response()->json(data: ['message' => 'Pagos son inmutables.'], status: 405);
    }

    public function destroy(Payment $payment): JsonResponse
    {
        return response()->json(data: ['message' => 'Pagos son inmutables.'], status: 405);
    }

    private function resolveCustomer(Request $request): Customer
    {
        $user = $request->user();
        abort_if($user === null, 401, 'Autenticación requerida.');

        $customer = Customer::where('user_id', $user->id)->first();
        abort_if($customer === null, 404, 'No existe perfil de cliente para este usuario.');

        return $customer;
    }
}
