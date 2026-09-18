<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Dealer;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class InventoryManageController extends Controller
{
    private function currentAdmin(): Admin
    {
        return Auth::guard('admin')->user();
    }

    /* ==================== PAGE ====================
       Products/dealers are handed to the view up front (same pattern as
       $admins on the leads page) so the search-selects and the Products
       tab both render instantly without an extra AJAX round trip on load.
       total_quantity/is_out_of_stock are appended here since the Products
       tab and the "out of stock" tag in the product picker both need them
       right away. */

    public function inventoryPage()
    {
        $products = Product::with(['images', 'creator:id,name'])
            ->orderBy('name')
            ->get()
            ->each(fn ($p) => $p->append(['total_quantity', 'is_out_of_stock']));

        return view('pages.inventory', [
            'products' => $products,
            'dealers'  => Dealer::orderBy('name')->get(),
            'units'    => ['pcs', 'box', 'kg', 'gram', 'litre', 'ml', 'dozen', 'pack', 'set'],
        ]);
    }

    /* ==================== LISTING (AJAX) ====================
       Returns every stock entry — filtering/search/pagination are handled
       client-side in inventory.js, same pattern as the leads table. */

    public function data()
    {
        $items = InventoryItem::query()
            ->with([
                'product:id,name,sku,model,unit',
                'dealer:id,name,company_name',
                'creator:id,name',
            ])
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'items' => $items]);
    }

    /* ==================== CREATE ==================== */

    public function store(Request $request)
    {
        $validator = $this->itemValidator($request);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $item = InventoryItem::create([
            'product_id' => $request->product_id,
            'dealer_id' => $request->dealer_id,
            'quantity' => $request->quantity,
            'unit' => $request->unit,
            'price' => $request->price,
            'type' => 'in', // manual entries from this page are always dealer stock-ins
            'entry_date' => $request->entry_date,
            'description' => $request->description,
            'created_by' => $this->currentAdmin()->id,
        ]);

        $item->load(['product:id,name,sku,model,unit', 'dealer:id,name,company_name', 'creator:id,name']);

        return response()->json(['success' => true, 'message' => 'Stock entry added.', 'item' => $item]);
    }

    /* ==================== UPDATE ==================== */

    public function update(Request $request, $id)
    {
        $item = InventoryItem::findOrFail($id);

        if ($item->type === 'out') {
            return response()->json([
                'success' => false,
                'message' => 'This entry was generated automatically from a bill — edit the bill instead of the stock entry.',
            ], 422);
        }

        $validator = $this->itemValidator($request);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $item->update([
            'product_id' => $request->product_id,
            'dealer_id' => $request->dealer_id,
            'quantity' => $request->quantity,
            'unit' => $request->unit,
            'price' => $request->price,
            'type' => 'in',
            'entry_date' => $request->entry_date,
            'description' => $request->description,
        ]);

        $item->load(['product:id,name,sku,model,unit', 'dealer:id,name,company_name', 'creator:id,name']);

        return response()->json(['success' => true, 'message' => 'Stock entry updated.', 'item' => $item]);
    }

    private function itemValidator(Request $request)
    {
        return Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'dealer_id' => 'nullable|exists:dealers,id',
            'quantity' => 'required|numeric|min:0.01',
            'unit' => 'required|string|max:20',
            'price' => 'required|numeric|min:0',
            'entry_date' => 'nullable|date',
            'description' => 'nullable|string|max:1000',
        ]);
    }

    /* ==================== DELETE (stock entries) ==================== */

    public function destroy($id)
    {
        $item = InventoryItem::findOrFail($id);

        if ($item->type === 'out') {
            return response()->json([
                'success' => false,
                'message' => 'This entry was generated automatically from a bill — delete or edit the bill instead.',
            ], 422);
        }

        $item->delete();

        return response()->json(['success' => true, 'message' => 'Stock entry deleted.']);
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->input('ids', []);
        $deletableIds = InventoryItem::whereIn('id', $ids)->where('type', 'in')->pluck('id');
        $skipped = count($ids) - $deletableIds->count();

        InventoryItem::whereIn('id', $deletableIds)->delete();

        $message = $deletableIds->count() . ' entrie(s) deleted.';
        if ($skipped > 0) {
            $message .= " {$skipped} bill-generated entrie(s) were skipped — edit those bills instead.";
        }

        return response()->json(['success' => true, 'message' => $message]);
    }

    /* ==================== QUICK-ADD PRODUCT ====================
       Hit by the "+" button next to the product search-select in the
       stock-entry modal, and also reused as the plain "Add product" flow
       from the Products tab (same fields either way) — so a brand new
       product can be created without leaving whichever form triggered it. */

    public function storeProduct(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku',
            'model' => 'nullable|string|max:100',
            'unit' => 'required|string|max:20',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $product = Product::create([
            'name' => $request->name,
            'sku' => $request->sku,
            'model' => $request->model,
            'unit' => $request->unit,
            'description' => $request->description,
            'created_by' => $this->currentAdmin()->id,
        ]);

        return response()->json(['success' => true, 'message' => 'Product added.', 'product' => $product]);
    }

    /* ==================== QUICK-ADD DEALER ====================
       Same idea as storeProduct, but for the "+" next to the dealer
       search-select. */

    public function storeDealer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $dealer = Dealer::create([
            'name' => $request->name,
            'company_name' => $request->company_name,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'created_by' => $this->currentAdmin()->id,
        ]);

        return response()->json(['success' => true, 'message' => 'Dealer added.', 'dealer' => $dealer]);
    }

    /* ==================== PRODUCTS TAB (AJAX) ====================
       Full product list with gallery images + running stock total, for
       the "Products" tab on the inventory page. Kept as its own endpoint
       (separate from data()/inventory entries) since it's a different
       shape entirely — one row per product, not per stock entry. */

    public function productsData()
    {
        $products = Product::with(['images', 'creator:id,name'])
            ->orderByDesc('id')
            ->get()
            ->each(fn ($p) => $p->append(['total_quantity', 'is_out_of_stock']));

        return response()->json(['success' => true, 'products' => $products]);
    }

    /* ==================== PRODUCT DETAIL (AJAX) ====================
       Powers the product detail offcanvas — mirrors what leads.blade's
       "show" endpoint does for the lead detail panel: full product info
       plus every stock entry ever logged against it. */

    public function productShow($id)
    {
        $product = Product::with(['images', 'creator:id,name'])->findOrFail($id);
        $product->append(['total_quantity', 'is_out_of_stock']);

        $entries = InventoryItem::where('product_id', $id)
            ->with(['dealer:id,name,company_name', 'creator:id,name'])
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'product' => $product, 'entries' => $entries]);
    }

    /* ==================== PRODUCT EDIT ==================== */

    public function updateProduct(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product->id)],
            'model' => 'nullable|string|max:100',
            'unit' => 'required|string|max:20',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $product->update($request->only(['name', 'sku', 'model', 'unit', 'description']));
        $product->load(['images', 'creator:id,name']);
        $product->append(['total_quantity', 'is_out_of_stock']);

        return response()->json(['success' => true, 'message' => 'Product updated.', 'product' => $product]);
    }

    /* ==================== PRODUCT DELETE ====================
       Blocked while stock entries still reference the product — deleting
       it out from under those rows would either orphan them or (with the
       FK as currently defined) fail at the database level anyway. Better
       to say so clearly than to surface a raw SQL error. */

    public function destroyProduct($id)
    {
        $product = Product::with('images')->findOrFail($id);

        if ($product->inventoryItems()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This product has stock entries linked to it — delete or reassign those first.',
            ], 422);
        }

        foreach ($product->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        $product->delete();

        return response()->json(['success' => true, 'message' => 'Product deleted.']);
    }

    /* ==================== PRODUCT IMAGES: ADD ====================
       Accepts multiple files in one request (field name images[]) and
       appends them after whatever images the product already has, so
       repeated uploads keep building the same gallery rather than
       replacing it. */

    public function storeProductImages(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'images' => 'required|array|min:1|max:10',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $nextOrder = (int) $product->images()->max('sort_order');
        $created = [];

        foreach ($request->file('images') as $i => $file) {
            $path = $file->store('products', 'public');
            $created[] = $product->images()->create([
                'image_path' => $path,
                'sort_order' => $nextOrder + $i + 1,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => count($created) . ' image(s) uploaded.',
            'images' => $created,
        ]);
    }

    /* ==================== PRODUCT IMAGES: REMOVE ==================== */

    public function destroyProductImage($imageId)
    {
        $image = ProductImage::findOrFail($imageId);

        Storage::disk('public')->delete($image->image_path);
        $image->delete();

        return response()->json(['success' => true, 'message' => 'Image removed.']);
    }
}