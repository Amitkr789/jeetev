<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('kb_categories')->nullOnDelete();

            // faq     -> question/answer pair
            // policy  -> company policy text (answer holds the full policy)
            // pricing -> pricing rules / plan info
            // product -> linked to a real Product row (catalog) — price/stock
            //            are pulled live from inventory, not duplicated here
            // service -> service offering description
            $table->enum('type', ['faq', 'policy', 'pricing', 'product', 'service'])->default('faq');

            $table->string('title');
            $table->text('question')->nullable();
            $table->longText('answer');

            // Catalog link for type=product — same Product model InventoryItem uses.
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('price_override', 12, 2)->nullable();

            $table->enum('status', ['draft', 'pending_approval', 'published', 'archived'])->default('draft');
            $table->unsignedInteger('version')->default(1);

            // AI-ready structure — gates + ranks what the future chatbot's
            // retrieval step is allowed to pull into its context.
            $table->boolean('ai_ready')->default(true);
            $table->unsignedInteger('priority')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_articles');
    }
};