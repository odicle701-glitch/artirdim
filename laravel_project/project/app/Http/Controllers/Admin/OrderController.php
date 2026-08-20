<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request)
    {
        $status = $request->get('status');

        $query = Order::with(['auction', 'buyer', 'seller'])->latest();

        if ($status === 'disputed') {
            $query->where('status', 'disputed');
        } elseif ($status) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(20)->withQueryString();

        $counts = [
            'all'      => Order::count(),
            'disputed' => Order::where('status', 'disputed')->count(),
            'active'   => Order::whereIn('status', ['awaiting_payment', 'paid', 'shipped'])->count(),
            'completed'=> Order::where('status', 'completed')->count(),
        ];

        return view('admin.orders.index', compact('orders', 'counts', 'status'));
    }

    public function show(Order $order)
    {
        $order->load(['auction.cover', 'buyer', 'seller', 'events.actor', 'winningBid']);

        return view('admin.orders.show', compact('order'));
    }

    public function resolve(Request $request, Order $order)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:buyer,seller'],
        ]);

        abort_unless($order->status === 'disputed', 422);

        $this->orders->resolveDispute($order, $data['decision'], $request->user()->id);

        return back()->with('success', 'Anlaşmazlık '.($data['decision'] === 'buyer' ? 'alıcı' : 'satıcı').' lehine sonuçlandırıldı.');
    }
}
