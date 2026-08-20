<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = Category::with('parent')
            ->withCount(['children', 'auctions']);

        if ($q = $request->input('q')) {
            $query->where(fn ($qb) =>
                $qb->where('name', 'like', "%{$q}%")
                   ->orWhere('slug', 'like', "%{$q}%")
            );
        }

        if ($request->input('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->input('status') === 'passive') {
            $query->where('is_active', false);
        }

        if ($request->input('type') === 'root') {
            $query->whereNull('parent_id');
        } elseif ($request->input('type') === 'sub') {
            $query->whereNotNull('parent_id');
        }

        $categories = $query->ordered()->paginate(20)->withQueryString();

        $stats = [
            'total'   => Category::count(),
            'active'  => Category::where('is_active', true)->count(),
            'passive' => Category::where('is_active', false)->count(),
            'roots'   => Category::whereNull('parent_id')->count(),
            'subs'    => Category::whereNotNull('parent_id')->count(),
        ];

        return view('admin.categories.index', compact('categories', 'stats'));
    }


    public function create(): View
    {
        $parents = Category::active()->roots()->ordered()->with('children')->get();
        return view('admin.categories.create', compact('parents'));
    }


    public function store(CategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category = Category::create($data);

        return redirect()
            ->route('admin.categories.index')
            ->with('category_success', $category->name. ' kategorisi oluşturuldu.');
    }


    public function show(Category $category): View
    {
        $category->loadCount(['children', 'auctions'])
                 ->load(['parent', 'children' => fn ($q) => $q->withCount('auctions')->ordered()]);

        return view('admin.categories.show', compact('category'));
    }

    public function edit(Category $category): View
    {
        $excludeIds = array_merge([$category->id], $category->allChildrenIds());

        $parents = Category::whereNotIn('id', $excludeIds)
            ->ordered()
            ->get();

        return view('admin.categories.edit', compact('category', 'parents'));
    }


    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category->update($data);

        return redirect()
            ->route('admin.categories.index')
            ->with('category_success', $category->name. ' kategorisi güncellendi.');
    }


    public function destroy(Category $category): \Symfony\Component\HttpFoundation\Response
    {
        foreach ($category->children as $child) {
            if ($child->image) Storage::disk('public')->delete($child->image);
            $child->delete();
        }

        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }

        $name = $category->name;
        $category->delete();

        $msg = $name . ' ve alt kategorileri silindi.';

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['message' => $msg]);
        }

        return redirect()
            ->route('admin.categories.index')
            ->with('category_success', $msg);
    }

    public function toggle(Category $category): RedirectResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        $msg = $category->is_active  ? $category->name. ' aktif edildi.' : $category->name. ' pasife alındı.';

        return back()->with('category_success', $msg);
    }


    public function reorder(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'items'          => ['required', 'array'],
            'items.*.id'     => ['required', 'integer', 'exists:categories,id'],
            'items.*.order'  => ['required', 'integer'],
        ]);

        foreach ($request->input('items') as $item) {
            Category::where('id', $item['id'])->update(['sort_order' => $item['order']]);
        }

        return response()->json(['message' => 'Sıralama güncellendi.']);
    }
}
