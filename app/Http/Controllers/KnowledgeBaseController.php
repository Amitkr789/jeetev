<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\InventoryItem;
use App\Models\KbArticle;
use App\Models\KbArticleVersion;
use App\Models\KbCategory;
use App\Models\KbMedia;
use App\Models\KbTag;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class KnowledgeBaseController extends Controller
{
    /**
     * Fixed title used to find/upsert the single auto-generated "what
     * products do you have" article. Kept as a title match (not a new
     * DB column) so no migration is needed — admins should treat an
     * article with this exact title as system-managed.
     */
    private const CATALOG_ARTICLE_TITLE = 'Our Product Categories';

    private function currentAdmin(): Admin
    {
        return Auth::guard('admin')->user();
    }

    /* ====================================================================
       ADMIN PAGE
       ==================================================================== */

    public function index()
    {
        $products = Product::query()
    ->select('id', 'name', 'sku', 'hsn', 'unit', 'description')
    ->with('images:id,product_id,image_path,sort_order')
    ->get()
    ->map(function ($p) {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'hsn' => $p->hsn,
            'unit' => $p->unit,
            'description' => $p->description,
            'images' => $p->images->map(fn ($img) => $img->url)->values(),
            // netted in-out, same as Product::total_quantity
            'stock' => $p->total_quantity,
            'last_price' => InventoryItem::where('product_id', $p->id)
                ->orderByDesc('entry_date')
                ->value('price'),
        ];
    });

        return view('pages.kb', [
            'categories' => KbCategory::orderBy('order')->orderBy('name')->get(),
            'tags' => KbTag::orderBy('name')->get(['id', 'name']),
            'products' => $products,
            'types' => KbArticle::TYPES,
            'statuses' => KbArticle::STATUSES,
            'canApprove' => $this->currentAdmin()->role === 'super_admin',
        ]);
    }

    public function data()
    {
        $articles = KbArticle::with(['category:id,name', 'tags:id,name', 'product:id,name', 'creator:id,name'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (KbArticle $a) => $this->transform($a));

        return response()->json(['success' => true, 'articles' => $articles]);
    }

    public function show($id)
    {
        $article = KbArticle::with([
    'category', 'tags', 'product.images', 'creator:id,name', 'approver:id,name',
    'versions.editor:id,name', 'media.uploader:id,name',
])->findOrFail($id);

        return response()->json(['success' => true, 'article' => $this->transform($article, true)]);
    }

    /* ====================================================================
       CRUD
       ==================================================================== */

    public function store(Request $request)
    {
        $validator = $this->validateArticle($request);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $article = KbArticle::create([
            'category_id' => $request->category_id,
            'type' => $request->type,
            'title' => $request->title,
            'question' => $request->question,
            'answer' => $request->answer,
            'product_id' => $request->type === 'product' ? $request->product_id : null,
            'price_override' => $request->price_override,
            'status' => 'draft',
            'version' => 1,
            'ai_ready' => $request->boolean('ai_ready', true),
            'priority' => $request->input('priority', 0),
            'created_by' => $this->currentAdmin()->id,
            'detailed_description' => $request->detailed_description,

        ]);

        $this->syncTags($article, $request->input('tags', []));
        $this->recordVersion($article, 1);

        $article->load(['category', 'tags', 'product', 'creator:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Article created as draft.',
            'article' => $this->transform($article),
        ]);
    }

    public function update(Request $request, $id)
    {
        $article = KbArticle::findOrFail($id);

        $validator = $this->validateArticle($request);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $contentChanged = $article->title !== $request->title
            || $article->question !== $request->question
            || $article->answer !== $request->answer;

        $article->fill([
            'category_id' => $request->category_id,
            'type' => $request->type,
            'title' => $request->title,
            'question' => $request->question,
            'answer' => $request->answer,
            'product_id' => $request->type === 'product' ? $request->product_id : null,
            'price_override' => $request->price_override,
            'ai_ready' => $request->boolean('ai_ready', true),
            'priority' => $request->input('priority', 0),
            'detailed_description' => $request->detailed_description,

        ]);

        // Content edits on live/retired articles send them back through
        // approval — published text should never change silently under
        // an admin without a fresh sign-off, and the AI should never be
        // fed a mid-edit answer.
        if ($contentChanged) {
            $article->version += 1;

            if ($article->status === 'published') {
                $article->status = 'pending_approval';
                $article->approved_by = null;
                $article->approved_at = null;
            } elseif ($article->status === 'archived') {
                $article->status = 'draft';
            }
        }

        $article->save();

        $this->syncTags($article, $request->input('tags', []));

        if ($contentChanged) {
            $this->recordVersion($article, $article->version);
        }

        $article->load(['category', 'tags', 'product', 'creator:id,name', 'approver:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Article updated.',
            'article' => $this->transform($article),
        ]);
    }

    public function destroy($id)
    {
        $article = KbArticle::with('media')->findOrFail($id);

        foreach ($article->media as $media) {
            Storage::disk('public')->delete($media->file_path);
        }

        $article->delete();

        return response()->json(['success' => true, 'message' => 'Article deleted.']);
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->input('ids', []);
        $articles = KbArticle::with('media')->whereIn('id', $ids)->get();

        foreach ($articles as $article) {
            foreach ($article->media as $media) {
                Storage::disk('public')->delete($media->file_path);
            }
        }

        $count = $articles->count();
        KbArticle::whereIn('id', $ids)->delete();

        return response()->json(['success' => true, 'message' => "{$count} deleted."]);
    }

    /* ====================================================================
       APPROVAL WORKFLOW
       draft -> pending_approval -> published -> archived
       (reject sends pending_approval back to draft)
       ==================================================================== */

    public function submit($id)
    {
        $article = KbArticle::findOrFail($id);

        if ($article->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Only draft articles can be submitted for approval.'], 422);
        }

        $article->status = 'pending_approval';
        $article->save();

        return response()->json([
            'success' => true,
            'message' => 'Submitted for approval.',
            'article' => $this->transform($article->fresh(['category', 'tags', 'product'])),
        ]);
    }

    public function approve($id)
    {
        $admin = $this->currentAdmin();
        if ($admin->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Only a super admin can approve articles.'], 403);
        }

        $article = KbArticle::findOrFail($id);
        if ($article->status !== 'pending_approval') {
            return response()->json(['success' => false, 'message' => 'Only articles pending approval can be published.'], 422);
        }

        $article->status = 'published';
        $article->approved_by = $admin->id;
        $article->approved_at = now();
        $article->published_at = $article->published_at ?? now();
        $article->save();

        return response()->json([
            'success' => true,
            'message' => 'Article published.',
            'article' => $this->transform($article->fresh(['category', 'tags', 'product', 'approver:id,name'])),
        ]);
    }

    public function reject($id)
    {
        $admin = $this->currentAdmin();
        if ($admin->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Only a super admin can reject articles.'], 403);
        }

        $article = KbArticle::findOrFail($id);
        if ($article->status !== 'pending_approval') {
            return response()->json(['success' => false, 'message' => 'Only articles pending approval can be rejected.'], 422);
        }

        $article->status = 'draft';
        $article->save();

        return response()->json([
            'success' => true,
            'message' => 'Sent back to draft.',
            'article' => $this->transform($article->fresh(['category', 'tags', 'product'])),
        ]);
    }

    public function archive($id)
    {
        $article = KbArticle::findOrFail($id);
        $article->status = 'archived';
        $article->save();

        return response()->json([
            'success' => true,
            'message' => 'Article archived.',
            'article' => $this->transform($article->fresh(['category', 'tags', 'product'])),
        ]);
    }

    /* ====================================================================
       VERSION HISTORY
       ==================================================================== */

    public function restoreVersion(Request $request, $id)
    {
        $article = KbArticle::findOrFail($id);
        $version = KbArticleVersion::where('article_id', $article->id)
            ->findOrFail($request->input('version_id'));

        $article->title = $version->title;
        $article->question = $version->question;
        $article->answer = $version->answer;
        $article->version += 1;

        if ($article->status === 'published') {
            $article->status = 'pending_approval';
            $article->approved_by = null;
            $article->approved_at = null;
        } elseif ($article->status === 'archived') {
            $article->status = 'draft';
        }

        $article->save();
        $this->recordVersion($article, $article->version);

        return response()->json([
            'success' => true,
            'message' => "Restored to version {$version->version_number}.",
            'article' => $this->transform($article->fresh(['category', 'tags', 'product', 'versions.editor:id,name'])->load('versions'), true),
        ]);
    }

    /* ====================================================================
       MEDIA (PDF / Image uploads)
       ==================================================================== */

    public function uploadMedia(Request $request, $id)
{
    $article = KbArticle::findOrFail($id);

    $validator = Validator::make($request->all(), [
        'file' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
        'purpose' => 'nullable|in:general,catalog',
    ]);
    if ($validator->fails()) {
        return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
    }

    $file = $request->file('file');
    $type = strtolower($file->getClientOriginalExtension()) === 'pdf' ? 'pdf' : 'image';
    $path = $file->store('kb-media', 'public');

    $media = KbMedia::create([
        'article_id' => $article->id,
        'type' => $type,
        'purpose' => $request->input('purpose', 'general'),
        'file_name' => $file->getClientOriginalName(),
        'file_path' => $path,
        'mime_type' => $file->getClientMimeType(),
        'file_size' => $file->getSize(),
        'uploaded_by' => $this->currentAdmin()->id,
    ]);

    return response()->json(['success' => true, 'message' => 'File uploaded.', 'media' => $media]);
}
    public function destroyMedia($mediaId)
    {
        $media = KbMedia::findOrFail($mediaId);
        Storage::disk('public')->delete($media->file_path);
        $media->delete();

        return response()->json(['success' => true, 'message' => 'File removed.']);
    }

    /* ====================================================================
       AUTO-GENERATED PRODUCT CATALOG OVERVIEW
       Answers broad questions like "what products do you have?" using a
       single pinned FAQ article rebuilt from the live Product table on
       demand — so the AI never has to guess/combine a category list out
       of many separate product articles, and admins don't have to
       hand-maintain it every time a product is added or removed.
       ==================================================================== */

    public function regenerateCatalogOverview()
    {
        $admin = $this->currentAdmin();

        $products = Product::orderBy('name')->get(['id', 'name', 'unit']);

        if ($products->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No products found in the catalog yet — add products first.',
            ], 422);
        }

        $lines = $products->map(function ($p) {
            return '- ' . $p->name . ($p->unit ? " ({$p->unit})" : '');
        })->implode("\n");

        $answer = "Hum ye products offer karte hain:\n\n{$lines}\n\n"
            . 'Kisi specific product ke baare mein poochne ke liye uska naam bataiye — warranty, price ya stock, sab bata denge.';

        $article = KbArticle::firstOrNew([
            'title' => self::CATALOG_ARTICLE_TITLE,
            'type' => 'faq',
        ]);
        $isNew = ! $article->exists;

        // System-generated and admin-triggered, so it's published
        // immediately rather than going through draft -> approval —
        // there's no free-text content here for a human to sign off on,
        // just a mechanical listing of real product names.
        $article->fill([
            'type' => 'faq',
            'question' => 'What products do you have? / Aapke paas kaun kaun se products hain?',
            'answer' => $answer,
            'status' => 'published',
            'ai_ready' => true,
            'priority' => 100,
            'version' => $isNew ? 1 : $article->version + 1,
            'created_by' => $article->created_by ?? $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'published_at' => $article->published_at ?? now(),
        ]);
        $article->save();

        $this->recordVersion($article, $article->version);

        $article->load(['category', 'tags', 'product', 'creator:id,name', 'approver:id,name']);

        return response()->json([
            'success' => true,
            'message' => $isNew
                ? 'Product catalog overview created & published.'
                : "Product catalog overview refreshed with {$products->count()} products.",
            'article' => $this->transform($article),
        ]);
    }

    /* ====================================================================
       CATEGORIES
       ==================================================================== */

    public function storeCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:kb_categories,id',
            'icon' => 'nullable|string|max:50',
            'status' => 'nullable|in:active,inactive',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $category = KbCategory::create([
            'name' => $request->name,
            'parent_id' => $request->parent_id,
            'icon' => $request->icon,
            'status' => $request->input('status', 'active'),
        ]);

        return response()->json(['success' => true, 'message' => 'Category added.', 'category' => $category]);
    }

    public function updateCategory(Request $request, $id)
    {
        $category = KbCategory::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:kb_categories,id',
            'icon' => 'nullable|string|max:50',
            'status' => 'nullable|in:active,inactive',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $category->update([
            'name' => $request->name,
            'parent_id' => $request->parent_id,
            'icon' => $request->icon,
            'status' => $request->input('status', $category->status),
        ]);

        return response()->json(['success' => true, 'message' => 'Category updated.', 'category' => $category]);
    }

    public function destroyCategory($id)
    {
        KbCategory::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Category deleted.']);
    }

    /* ====================================================================
       Internals
       ==================================================================== */

    private function validateArticle(Request $request)
    {
        return Validator::make($request->all(), [
            'category_id' => 'nullable|exists:kb_categories,id',
            'type' => 'required|in:' . implode(',', KbArticle::TYPES),
            'title' => 'required|string|max:255',
            'question' => 'nullable|string|max:500',
            'answer' => 'required|string',
            'product_id' => 'nullable|required_if:type,product|exists:products,id',
            'price_override' => 'nullable|numeric|min:0',
            'ai_ready' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'detailed_description' => 'nullable|string',

        ]);
    }

    private function syncTags(KbArticle $article, array $tagNames): void
    {
        $ids = collect($tagNames)
            ->filter(fn ($n) => trim((string) $n) !== '')
            ->map(fn ($n) => KbTag::findOrCreateByName($n)->id)
            ->unique()
            ->values();

        $article->tags()->sync($ids);
    }

    private function recordVersion(KbArticle $article, int $versionNumber): void
    {
        KbArticleVersion::create([
            'article_id' => $article->id,
            'version_number' => $versionNumber,
            'title' => $article->title,
            'question' => $article->question,
            'answer' => $article->answer,
            'status_at_time' => $article->status,
            'edited_by' => $this->currentAdmin()->id,
            'created_at' => now(),
        ]);
    }

    private function transform(KbArticle $article, bool $detailed = false): array
    {
        $data = [
            'id' => $article->id,
            'category_id' => $article->category_id,
            'category_name' => $article->category->name ?? null,
            'type' => $article->type,
            'title' => $article->title,
            'question' => $article->question,
            'answer' => $article->answer,
            'product_id' => $article->product_id,
            'product_name' => $article->product->name ?? null,
            'live_price' => $article->live_price,
            'live_stock' => $article->live_stock,
            'price_override' => $article->price_override,
            'status' => $article->status,
            'status_label' => $article->status_label,
            'version' => $article->version,
            'ai_ready' => $article->ai_ready,
            'priority' => $article->priority,
            'tags' => $article->tags->pluck('name'),
            'creator_name' => $article->creator->name ?? null,
            'approver_name' => $article->approver->name ?? null,
            'approved_at' => optional($article->approved_at)->toDateTimeString(),
            'published_at' => optional($article->published_at)->toDateTimeString(),
            'created_at' => optional($article->created_at)->toDateTimeString(),
            'updated_at' => optional($article->updated_at)->toDateTimeString(),
            'detailed_description' => $article->detailed_description,

        ];
    if ($article->type === 'product' && $article->product) {
        $data['product_description'] = $article->product->description;
        $data['product_images'] = $article->product->images->map(fn ($img) => $img->url)->values();
    }
        if ($detailed) {
            $data['versions'] = $article->versions->map(fn ($v) => [
                'id' => $v->id,
                'version_number' => $v->version_number,
                'title' => $v->title,
                'question' => $v->question,
                'answer' => $v->answer,
                'status_at_time' => $v->status_at_time,
                'edited_by' => $v->editor->name ?? null,
                'created_at' => optional($v->created_at)->toDateTimeString(),
            ]);
            $data['media'] = $article->media->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type,
                'file_name' => $m->file_name,
                'url' => $m->url,
                'file_size' => $m->file_size,
            ]);
           $data['media'] = $article->media->map(fn ($m) => [
            'id' => $m->id,
            'type' => $m->type,
            'purpose' => $m->purpose,     // NEW
            'file_name' => $m->file_name,
            'url' => $m->url,
            'file_size' => $m->file_size,
        ]);
        $data['ai_context'] = $article->toAiContext();
        }

        return $data;
    }
}
