<?php

namespace App\Http\Controllers;

use App\Models\Category;

use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MenuController extends Controller
{
    // index endpoint that returns a paginated list of menu items, with optional query parameters for pagination (page and perPage). If pagination parameters are not provided, return all menu items.
    public function index(Request $request)
    {
        if ($request->query('perPage') !== null) {
            $data = $request->validate([
                'perPage' => ['required', 'integer', 'min:1', 'max:200'],
                'page' => ['sometimes', 'integer', 'min:1'],
            ]);

            return MenuItem::query()
                ->with('categoryRef')
                ->orderBy('category', 'asc')
                ->orderBy('name', 'asc')
                ->paginate($data['perPage'])
                ->through(fn (MenuItem $item) => $this->toApi($item));
        }

        return Cache::remember('menu:index:all', 30, function () {
            return MenuItem::query()
                ->with('categoryRef')
                ->orderBy('category', 'asc')
                ->orderBy('name', 'asc')
                ->get()
                ->map(fn (MenuItem $item) => $this->toApi($item))
                ->all();
        });
    }
    // show endpoint that returns the details of a single menu item by its ID. If the item does not exist, return a 404 error.
    public function show(int $id)
    {
        $item = MenuItem::query()
            ->with('categoryRef')
            ->find($id);

        if (! $item) {
            return response()->json(['message' => "Menu item with id {$id} not found"], 404);
        }

        return $this->toApi($item);
    }
    // store endpoint that creates a new menu item with the provided details. Validate the input data and return the created item in the response. If validation fails, return a 400 error with details about the validation errors.
    public function store(Request $request)
    {
        $request->merge([
            'ingredients' => $this->normalizeListInput($request->input('ingredients')),
            'allergens' => $this->normalizeListInput($request->input('allergens')),
            'dietaryTags' => $this->normalizeListInput($request->input('dietaryTags')),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:menu_items,name'],
            'description' => ['nullable', 'string'],
            'imageUrl' => ['nullable', 'string'],
            'price' => ['required', 'numeric'],
            'category' => ['required', 'string', 'max:255'],
            'ingredients' => ['nullable', 'array'],
            'ingredients.*' => ['string', 'max:120'],
            'allergens' => ['nullable', 'array'],
            'allergens.*' => ['string', 'max:120'],
            'dietaryTags' => ['nullable', 'array'],
            'dietaryTags.*' => ['string', 'max:120'],
            'isAvailable' => ['sometimes', 'boolean'],
        ]);
        $categoryName = trim($data['category']);
        $category = Category::firstOrCreate(
            ['name' => $categoryName],
            ['description' => null],
        );

        $payload = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'ingredients' => $data['ingredients'] ?? null,
            'allergens' => $data['allergens'] ?? null,
            'dietary_tags' => $data['dietaryTags'] ?? null,
            'image_url' => $data['imageUrl'] ?? null,
            'price' => $data['price'],
            'category' => $categoryName,
            'is_available' => array_key_exists('isAvailable', $data) ? (bool) $data['isAvailable'] : true,
        ];
        if (Schema::hasColumn('menu_items', 'category_id')) {
            $payload['category_id'] = $category->id;
        }
        $item = MenuItem::create($payload)->load('categoryRef');

        Cache::forget('menu:index:all');

        return response()->json($this->toApi($item), 201);
    }
    // update endpoint that updates the details of an existing menu item by its ID. Validate the input data and return the updated item in the response. If the item does not exist, return a 404 error. If validation fails, return a 400 error with details about the validation errors.
    public function update(Request $request, int $id)
    {
        $item = MenuItem::find($id);

        if (! $item) {
            return response()->json(['message' => "Menu item with id {$id} not found"], 404);
        }
        $request->merge([
            'ingredients' => $this->normalizeListInput($request->input('ingredients')),
            'allergens' => $this->normalizeListInput($request->input('allergens')),
            'dietaryTags' => $this->normalizeListInput($request->input('dietaryTags')),
        ]);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:menu_items,name,'.$id],
            'description' => ['sometimes', 'nullable', 'string'],
            'imageUrl' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'numeric'],
            'category' => ['sometimes', 'string', 'max:255'],
            'ingredients' => ['sometimes', 'nullable', 'array'],
            'ingredients.*' => ['string', 'max:120'],
            'allergens' => ['sometimes', 'nullable', 'array'],
            'allergens.*' => ['string', 'max:120'],
            'dietaryTags' => ['sometimes', 'nullable', 'array'],
            'dietaryTags.*' => ['string', 'max:120'],
            'isAvailable' => ['sometimes', 'boolean'],
        ]);

        $updates = [];

        foreach (['name', 'description', 'price'] as $k) {
            if (array_key_exists($k, $data)) {
                $updates[$k] = $data[$k];
            }
        }
        if (array_key_exists('ingredients', $data)) {
            $updates['ingredients'] = $data['ingredients'];
        }
        if (array_key_exists('allergens', $data)) {
            $updates['allergens'] = $data['allergens'];
        }
        if (array_key_exists('dietaryTags', $data)) {
            $updates['dietary_tags'] = $data['dietaryTags'];
        }
        if (array_key_exists('category', $data)) {
            $categoryName = trim($data['category']);
            $category = Category::firstOrCreate(
                ['name' => $categoryName],
                ['description' => null],
            );
            $updates['category'] = $categoryName;
            if (Schema::hasColumn('menu_items', 'category_id')) {
                $updates['category_id'] = $category->id;
            }
        }

        if (array_key_exists('imageUrl', $data)) {
            $updates['image_url'] = $data['imageUrl'];
        }

        if (array_key_exists('isAvailable', $data)) {
            $updates['is_available'] = (bool) $data['isAvailable'];
        }

        $item->update($updates);

        Cache::forget('menu:index:all');

        return $this->toApi($item->fresh(['categoryRef']));
    }
    // destroy endpoint that deletes a menu item by its ID. If the item does not exist, return a 404 error. Return a success message in the response if the deletion is successful.
    public function destroy(int $id)
    {
        $item = MenuItem::find($id);

        if (! $item) {
            return response()->json(['message' => "Menu item with id {$id} not found"], 404);
        }

        $item->delete();

        Cache::forget('menu:index:all');

        return ['deleted' => true];
    }
    // toApi function that converts a MenuItem model instance to an array suitable for API responses, with the appropriate fields and formatting.
    private function toApi(MenuItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'ingredients' => $this->normalizeArrayForApi($item->ingredients),
            'allergens' => $this->normalizeArrayForApi($item->allergens),
            'dietaryTags' => $this->normalizeArrayForApi($item->dietary_tags),
            'price' => $item->price,
            'category' => $item->categoryRef?->name ?? $item->category,
            'imageUrl' => $item->image_url,
            'isAvailable' => (bool) $item->is_available,
            'createdAt' => $item->created_at?->toISOString(),
            'updatedAt' => $item->updated_at?->toISOString(),
        ];
    }
    // normalizeListInput helper function that takes a string or an array input and normalizes it into an array of unique, trimmed strings, splitting by commas or newlines if the input is a string. This is useful for handling the ingredients, allergens, and dietary tags fields in a flexible way.
    private function normalizeListInput(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $parts = preg_split('/[,\n]/', $value) ?: [];
            return $this->normalizeArrayForApi($parts);
        }

        if (is_array($value)) {
            return $this->normalizeArrayForApi($value);
        }

        return null;
    }
    // normalizeArrayForApi helper function that takes an array input and normalizes it into an array of unique, trimmed strings, filtering out any non-scalar values and empty strings. This is used to ensure consistent formatting for the ingredients, allergens, and dietary tags fields in API responses.
    private function normalizeArrayForApi(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (! is_scalar($item)) {
                continue;
            }
            $clean = trim((string) $item);
            if ($clean === '') {
                continue;
            }
            $items[] = Str::limit($clean, 120, '');
        }

        return array_values(array_unique($items));
    }
}
