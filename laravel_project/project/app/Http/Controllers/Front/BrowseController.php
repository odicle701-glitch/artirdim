<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\Category;
use Illuminate\Http\Request;

class BrowseController extends Controller
{
    public function auctions(Request $request)
    {
        $categories = Category::active()->ordered()->withCount('auctions')->get();

        $query = Auction::query()->with(['category', 'cover']);

        if ($request->filled('q')) {
            $query->where('title', 'like', '%' . $request->q . '%');
        }

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($c) => $c->where('slug', $request->category));
        }

        if ($request->status === 'active') {
            $query->where('status', 'active')->where('ends_at', '>', now());
        } elseif ($request->status === 'ended') {
            $query->where(function ($q) {
                $q->where('status', '!=', 'active')->orWhere('ends_at', '<=', now());
            });
        }

        match ($request->sort) {
            'ending' => $query->where('ends_at', '>', now())->orderBy('ends_at'),
            'new'    => $query->latest(),
            'price'  => $query->orderByDesc('current_price'),
            default  => $query->withCount('bids')->orderByDesc('bids_count'),
        };

        $auctions = $query->paginate(12)->withQueryString();

        return view('browse.auctions', compact('auctions', 'categories'));
    }


    public function live()
    {
        $liveAuctions = Auction::query()
            ->with(['category', 'cover'])
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->orderBy('ends_at')
            ->get();

        return view('browse.live', compact('liveAuctions'));
    }

    public function explore()
    {
        $categories = Category::active()->whereNull('parent_id')->ordered()->withCount('auctions')->take(6)->get();

        $featuredAuctions = Auction::query()
            ->with(['category', 'cover'])
            ->where('is_featured', true)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->latest()
            ->take(8)
            ->get();

        $newAuctions = Auction::query()
            ->with(['category', 'cover'])
            ->latest()
            ->take(8)
            ->get();

        return view('browse.explore', compact('categories', 'featuredAuctions', 'newAuctions'));
    }
}
