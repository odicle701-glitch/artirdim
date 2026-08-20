<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request)
    {
        $orders = Order::where('buyer_id', $request->user()->id)
            ->with(['auction.cover', 'seller'])
            ->latest()
            ->paginate(12);

        return view('buyer.orders.index', compact('orders'));
    }

    public function show(Request $request, Order $order)
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);

        $order->load(['auction.cover', 'seller', 'events.actor', 'winningBid']);

        return view('buyer.orders.show', compact('order'));
    }

    public function pay(Request $request, Order $order)
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);

        if ($order->status !== 'awaiting_payment') {
            return back()->with('error', 'Bu sipariş için ödeme yapılamaz.');
        }

        if (! $this->orders->tryHoldEscrow($order)) {
            return redirect()
                ->route('general.balance.create')
                ->with('error', 'Bakiyeniz yetersiz. Lütfen bakiye yükleyip tekrar deneyin.');
        }

        return back()->with('success', 'Ödeme alındı ve güvenli şekilde emanete alındı.');
    }

    public function address(Request $request, Order $order)
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);

        $data = $request->validate([
            'recipient_name'   => ['required', 'string', 'max:120'],
            'recipient_phone'  => ['required', 'string', 'max:30'],
            'address_line'     => ['required', 'string', 'max:500'],
            'address_city'     => ['required', 'string', 'max:80'],
            'address_district' => ['nullable', 'string', 'max:80'],
            'address_zip'      => ['nullable', 'string', 'max:20'],
        ]);

        $this->orders->setShippingAddress($order, $data);

        return back()->with('success', 'Teslimat adresi kaydedildi.');
    }

    public function confirm(Request $request, Order $order)
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);
        abort_unless(in_array($order->status, ['shipped', 'delivered'], true), 422);

        $this->orders->confirmDelivered($order);

        return back()->with('success', 'Teslimatı onayladınız. Ödeme satıcıya aktarıldı.');
    }

    public function dispute(Request $request, Order $order)
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);
        abort_unless(in_array($order->status, ['paid', 'shipped', 'delivered'], true), 422);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $this->orders->openDispute($order, $data['reason']);

        return back()->with('success', 'Anlaşmazlık talebiniz alındı. Ekibimiz en kısa sürede inceleyecek.');
    }
}
