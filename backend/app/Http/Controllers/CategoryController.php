<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\MenuItem;
use App\Services\RestaurantAiChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    // index endpoint that returns a list of all categories, ordered alphabetically by name. Implement caching for this endpoint to improve performance, with a cache duration of 30 seconds. If the cache is available, return the cached data; otherwise, query the database, store the result in the cache, and return it.
    public function index()
    {
        return Cache::remember('categories:index:all', 30, function () {
            return Category::query()
                ->orderBy('name', 'asc')
                ->get()
                ->map(fn (Category $c) => $this->toApi($c))
                ->all();
        });
    }
    // show endpoint that returns the details of a specific category by its ID. If the category does not exist, return a 404 error with an appropriate message.
    public function show(int $id)
    {
        $cat = Category::find($id);

        if (! $cat) {
            return response()->json(['message' => "Category {$id} not found"], 404);
        }

        return $this->toApi($cat);
    }
    // store endpoint that creates a new category with the provided name and description. Validate the input data and return the created category in the response. If validation fails, return a 400 error with details about the validation errors. After creating a new category, invalidate the relevant cache to ensure that subsequent requests to the index endpoint return the updated list of categories.
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:categories,name'],
            'description' => ['nullable', 'string', 'max:200'],
        ]);

        $cat = Category::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        Cache::forget('categories:index:all');
        RestaurantAiChatService::forgetContextCache();

        return response()->json($this->toApi($cat), 201);
    }
    // update endpoint that updates the details of an existing category by its ID. Validate the input data and return the updated category in the response. If the category does not exist, return a 404 error. If validation fails, return a 400 error with details about the validation errors. After updating a category, invalidate the relevant cache to ensure that subsequent requests to the index endpoint return the updated list of categories.
    public function update(Request $request, int $id)
    {
        $cat = Category::find($id);

        if (! $cat) {
            return response()->json(['message' => "Category {$id} not found"], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:50', 'unique:categories,name,'.$id],
            'description' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);
        $originalName = $cat->name;

        $cat->update($data);
        if (array_key_exists('name', $data) && $cat->name !== $originalName) {
            MenuItem::query()
                ->where('category_id', $cat->id)
                ->update(['category' => $cat->name]);
        }

        Cache::forget('categories:index:all');
        Cache::forget('menu:index:all');
        RestaurantAiChatService::forgetContextCache();

        return $this->toApi($cat->fresh());
    }
    // destroy endpoint that deletes a category by its ID. If the category does not exist, return a 404 error. Return a success message in the response if the deletion is successful. After deleting a category, invalidate the relevant cache to ensure that subsequent requests to the index endpoint return the updated list of categories.
    public function destroy(int $id)
    {
        $cat = Category::find($id);

        if (! $cat) {
            return response()->json(['message' => "Category {$id} not found"], 404);
        }

        $cat->delete();

        Cache::forget('categories:index:all');
        Cache::forget('menu:index:all');
        RestaurantAiChatService::forgetContextCache();

        return ['deleted' => true];
    }
    // toApi function that converts a Category model instance to an array suitable for API responses, with the appropriate fields and formatting.
    private function toApi(Category $cat): array
    {
        return [
            'id' => $cat->id,
            'name' => $cat->name,
            'description' => $cat->description,
        ];
    }
}
