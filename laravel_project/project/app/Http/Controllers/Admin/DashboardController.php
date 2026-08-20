<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function index()
    {
        $revenue     = (float) Order::where('status', 'completed')->sum('amount');
        $escrowHeld  = (float) Order::where('escrow_status', 'held')->sum('amount');
        $commission  = (float) Order::where('status', 'completed')->sum('commission_amount');

        $stats = [
            'users'          => User::count(),
            'sellers'        => User::role('seller')->count(),
            'auctions'       => Auction::count(),
            'active'         => Auction::where('status', 'active')->count(),
            'bids'           => Bid::count(),
            'orders'         => Order::count(),
            'completed'      => Order::where('status', 'completed')->count(),
            'disputes'       => Order::where('status', 'disputed')->count(),
            'pending'        => User::where('is_verified', false)->count(),
            'revenue'        => $revenue,
            'escrow_held'    => $escrowHeld,
            'commission'     => $commission,
            'new_users_week' => User::where('created_at', '>=', now()->startOfWeek())->count(),
            'auctions_week'  => Auction::where('created_at', '>=', now()->startOfWeek())->count(),
            'bids_today'     => Bid::where('created_at', '>=', now()->startOfDay())->count(),
        ];

        // Son 14 gün: sipariş adedi + günlük ciro (tamamlanan)
        $start = now()->subDays(13)->startOfDay();
        $ordersDaily  = Order::where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) d, COUNT(*) c')->groupBy('d')->pluck('c', 'd');
        $revenueDaily = Order::where('status', 'completed')->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) d, SUM(amount) s')->groupBy('d')->pluck('s', 'd');

        $chart = [];
        for ($i = 0; $i < 14; $i++) {
            $day = (clone $start)->addDays($i);
            $key = $day->format('Y-m-d');
            $chart[] = [
                'label'   => $day->format('d.m'),
                'orders'  => (int) ($ordersDaily[$key] ?? 0),
                'revenue' => (float) ($revenueDaily[$key] ?? 0),
            ];
        }

        // Sipariş durum dağılımı
        $statusCounts = Order::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');
        $orderStatuses = collect(Order::STATUSES)->map(function ($meta, $key) use ($statusCounts) {
            return [
                'label' => $meta['label'],
                'color' => $meta['color'],
                'count' => (int) ($statusCounts[$key] ?? 0),
            ];
        })->filter(fn ($s) => $s['count'] > 0)->values();

        // En iyi satıcılar (tamamlanan satış cirosuna göre)
        $topSellers = Order::where('status', 'completed')
            ->selectRaw('seller_id, COUNT(*) sales, SUM(amount) total')
            ->groupBy('seller_id')->orderByDesc('total')->take(5)->get()
            ->map(function ($row) {
                $u = User::find($row->seller_id);
                return (object) ['name' => $u?->name ?? 'Bilinmiyor', 'avatar' => $u?->profile_img, 'sales' => (int) $row->sales, 'total' => (float) $row->total];
            });

        // Son siparişler
        $recentOrders = Order::with(['buyer', 'seller', 'auction'])->latest()->take(6)->get();

        // Son aktiviteler
        $activities = collect();
        Bid::with(['user', 'auction'])->latest()->take(6)->get()->each(fn ($bid) => $activities->push((object) [
            'icon' => 'bi-hammer', 'color' => '#3b82f6',
            'title' => ($bid->user?->name ?? 'Kullanıcı') . ' teklif verdi: ' . Str::limit($bid->auction?->title ?? 'ilan', 26) . ' — ' . number_format((float) $bid->amount, 0, ',', '.') . ' ₺',
            'created_at' => $bid->created_at,
        ]));
        Auction::latest()->take(4)->get()->each(fn ($a) => $activities->push((object) [
            'icon' => 'bi-tag', 'color' => '#10b981',
            'title' => 'Yeni ilan: ' . Str::limit($a->title, 30), 'created_at' => $a->created_at,
        ]));
        User::latest()->take(4)->get()->each(fn ($u) => $activities->push((object) [
            'icon' => 'bi-person-plus', 'color' => '#8b5cf6',
            'title' => 'Yeni üye: ' . $u->name, 'created_at' => $u->created_at,
        ]));
        $activities = $activities->sortByDesc('created_at')->take(8)->values();

        return view('admin.dashboard', compact('stats', 'chart', 'orderStatuses', 'topSellers', 'recentOrders', 'activities'));
    }
}
