<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $table = 'categories';
    protected $primaryKey = 'category_id';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'parent_category_id',
        'category_code',
        'image_url',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'tenant_id');
    }

    public function parentCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_category_id', 'category_id');
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_category_id', 'category_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id', 'category_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeParent($query)
    {
        return $query->whereNull('parent_category_id');
    }

    public function scopeChildren($query)
    {
        return $query->whereNotNull('parent_category_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isParent(): bool
    {
        return $this->parent_category_id === null;
    }

    public function isChild(): bool
    {
        return $this->parent_category_id !== null;
    }

    public function hasChildren(): bool
    {
        return $this->subcategories()->exists();
    }

    public function hasProducts(): bool
    {
        return $this->products()->exists();
    }

    public function getDepth(): int
    {
        $depth = 0;
        $category = $this;
        
        while ($category->parent_category_id !== null) {
            $depth++;
            $category = $category->parentCategory;
            
            // Prevent infinite loop
            if ($depth > 10) {
                break;
            }
        }
        
        return $depth;
    }

    public function getPath(): array
    {
        $path = [];
        $category = $this;
        
        while ($category !== null) {
            array_unshift($path, [
                'id' => $category->category_id,
                'name' => $category->name,
                'code' => $category->category_code
            ]);
            
            $category = $category->parentCategory;
            
            // Prevent infinite loop
            if (count($path) > 10) {
                break;
            }
        }
        
        return $path;
    }

    public function getPathString(string $separator = ' > '): string
    {
        $path = $this->getPath();
        return implode($separator, array_column($path, 'name'));
    }

    public function getAllChildren(): \Illuminate\Database\Eloquent\Collection
    {
        $children = collect();
        
        foreach ($this->subcategories as $subcategory) {
            $children->push($subcategory);
            $children = $children->merge($subcategory->getAllChildren());
        }
        
        return $children;
    }

    public function getAllProductsIncludingChildren(): \Illuminate\Database\Eloquent\Collection
    {
        $products = $this->products;
        
        foreach ($this->getAllChildren() as $child) {
            $products = $products->merge($child->products);
        }
        
        return $products;
    }

    public function getProductCount(): int
    {
        return $this->products()->count();
    }

    public function getTotalProductCount(): int
    {
        return $this->getAllProductsIncludingChildren()->count();
    }

    public function getActiveProductCount(): int
    {
        return $this->products()->where('status', 'active')->count();
    }

    public function canBeDeleted(): bool
    {
        return !$this->hasProducts() && !$this->hasChildren();
    }

    public function activate(): void
    {
        $this->is_active = true;
        $this->save();
    }

    public function deactivate(): void
    {
        $this->is_active = false;
        $this->save();
    }

    // Get root categories (parents only)
    public static function getRootCategories($tenantId): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('tenant_id', $tenantId)
            ->whereNull('parent_category_id')
            ->active()
            ->ordered()
            ->get();
    }

    // Get category hierarchy as nested array
    public static function getHierarchy($tenantId): \Illuminate\Support\Collection
    {
        $categories = static::where('tenant_id', $tenantId)
            ->active()
            ->ordered()
            ->get();
            
        return static::buildHierarchy($categories);
    }

    private static function buildHierarchy(\Illuminate\Database\Eloquent\Collection $categories, $parentId = null): \Illuminate\Support\Collection
    {
        return $categories
            ->where('parent_category_id', $parentId)
            ->map(function ($category) use ($categories) {
                $category->children = static::buildHierarchy($categories, $category->category_id);
                return $category;
            });
    }
}
