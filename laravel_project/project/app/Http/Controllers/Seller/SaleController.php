<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request)
    {
        $orders = Order::where('seller_id', $request->user()->id)
            ->with(['auction.cover', 'buyer'])
            ->latest()
            ->paginate(12);

        return view('seller.sales.index', compact('orders'));
    }

    public function show(Request $request, Order $order)
    {
        abort_unless($order->seller_id === $request->user()->id, 403);

        $order->load(['auction.cover', 'buyer', 'events.actor', 'winningBid']);

        return view('seller.sales.show', compact('order'));
    }

    public function ship(Request $request, Order $order)
    {
        abort_unless($order->seller_id === $request->user()->id, 403);

        if ($order->status !== 'paid') {
            return back()->with('error', 'Kargoya vermek için siparişin ödemesinin alınmış olması gerekir.');
        }

        if (! $order->hasShippingAddress()) {
            return back()->with('error', 'Alıcı henüz teslimat adresini girmedi.');
        }

        $data = $request->validate([
            'carrier'         => ['required', 'string', 'max:80'],
            'tracking_number' => ['required', 'string', 'max:100'],
            'tracking_url'    => ['nullable', 'url', 'max:300'],
        ]);

        $this->orders->markShipped($order, $data['carrier'], $data['tracking_number'], $data['tracking_url'] ?? null);

        return back()->with('success', 'Kargo bilgisi kaydedildi ve alıcı bilgilendirildi.');
    }
}
