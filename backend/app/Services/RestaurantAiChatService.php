<?php

namespace App\Services;

use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RestaurantAiChatService
{
    private const OUT_OF_SCOPE_MESSAGE = 'I can only help with Cedars of Lebanon restaurant information (menu, ingredients, allergens, dietary options, hours, and location).';

    private const MISSING_INFO_MESSAGE = 'I don’t have verified information for that yet.';

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function reply(string $message, array $history = []): array
    {
        $cleanMessage = $this->sanitizeText($message, 1000);

        if ($cleanMessage === '') {
            return ['reply' => self::OUT_OF_SCOPE_MESSAGE];
        }

        $context = $this->buildContext();
        if (! $this->isLikelyInScope($cleanMessage, $context) || $this->containsOutOfScopeTopic($cleanMessage)) {
            return ['reply' => self::OUT_OF_SCOPE_MESSAGE];
        }

        $apiKey = (string) config('services.llm.api_key', '');
        if ($apiKey === '') {
            throw new \RuntimeException('LLM_API_KEY is missing');
        }

        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $this->runtimeSystemPrompt()];
        $messages[] = ['role' => 'system', 'content' => "Verified restaurant context (JSON):\n".$this->jsonEncode($context)];
        $messages[] = ['role' => 'system', 'content' => 'If user asks for data not present in context, respond exactly: “'.self::MISSING_INFO_MESSAGE.'”'];

        foreach ($this->sanitizeHistory($history) as $pastMessage) {
            $messages[] = $pastMessage;
        }

        $messages[] = ['role' => 'user', 'content' => $cleanMessage];

        $response = Http::timeout((int) config('services.llm.timeout_seconds', 20))
            ->withToken($apiKey)
            ->acceptJson()
            ->post(rtrim((string) config('services.llm.base_url', 'https://api.openai.com/v1'), '/').'/chat/completions', [
                'model' => (string) config('services.llm.model', 'gpt-4o-mini'),
                'temperature' => 0.1,
                'max_tokens' => 350,
                'messages' => $messages,
            ]);

        if (! $response->ok()) {
            throw new \RuntimeException('LLM API request failed with status '.$response->status());
        }

        $rawReply = (string) data_get($response->json(), 'choices.0.message.content', '');
        $reply = $this->sanitizeText($rawReply, 1200);

        if ($reply === '') {
            return ['reply' => self::MISSING_INFO_MESSAGE];
        }

        if (! $this->isOutputAllowed($reply)) {
            return ['reply' => self::OUT_OF_SCOPE_MESSAGE];
        }

        return ['reply' => $reply];
    }

    private function runtimeSystemPrompt(): string
    {
        return <<<PROMPT
You are the official Cedars of Lebanon restaurant assistant.
You must answer ONLY with Cedars of Lebanon restaurant information provided in your context.

Allowed topics:
- menu items, categories, prices
- ingredients and allergens
- dietary suitability (vegan/vegetarian/etc.)
- opening hours, location, contact
- dish recommendations based on user preferences

Rules:
1) Do not answer questions outside restaurant scope.
2) If user asks out-of-scope, reply:
   “I can only help with Cedars of Lebanon restaurant information (menu, ingredients, allergens, dietary options, hours, and location).”
3) Do not invent facts. If data is missing, say:
   “I don’t have verified information for that yet.”
4) Recommend only dishes that exist in provided data.
5) Keep answers concise, helpful, and polite.
PROMPT;
    }

    private function buildContext(): array
    {
        $menuItems = MenuItem::query()
            ->with('categoryRef')
            ->orderBy('name')
            ->get()
            ->map(function (MenuItem $item) {
                return [
                    'name' => $item->name,
                    'category' => $item->categoryRef?->name ?? $item->category,
                    'price' => (float) $item->price,
                    'description' => $item->description ?: null,
                    'is_available' => (bool) $item->is_available,
                    'ingredients' => $this->cleanList($item->ingredients),
                    'allergens' => $this->cleanList($item->allergens),
                    'dietary_tags' => $this->cleanList($item->dietary_tags),
                ];
            })
            ->all();

        $categories = Category::query()
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return [
            'restaurant' => [
                'name' => config('restaurant.name', 'Cedars of Lebanon'),
                'location' => config('restaurant.location', []),
                'contact' => config('restaurant.contact', []),
                'hours' => config('restaurant.hours', []),
            ],
            'categories' => $categories,
            'menu_items' => $menuItems,
            'recommendation_candidates' => $this->buildRecommendationCandidates($menuItems),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $menuItems
     * @return array<string, array<int, string>>
     */
    private function buildRecommendationCandidates(array $menuItems): array
    {
        $candidateMap = [
            'vegetarian' => [],
            'vegan' => [],
            'spicy' => [],
            'high_protein' => [],
            'no_nuts' => [],
        ];

        foreach ($menuItems as $item) {
            $name = (string) ($item['name'] ?? '');
            $description = Str::lower((string) ($item['description'] ?? ''));
            $dietaryTags = array_map('strtolower', $item['dietary_tags'] ?? []);
            $ingredients = array_map('strtolower', $item['ingredients'] ?? []);
            $allergens = array_map('strtolower', $item['allergens'] ?? []);

            if (in_array('vegetarian', $dietaryTags, true) || in_array('vegetarian-friendly', $dietaryTags, true)) {
                $candidateMap['vegetarian'][] = $name;
            }
            if (in_array('vegan', $dietaryTags, true) || in_array('vegan-friendly', $dietaryTags, true)) {
                $candidateMap['vegan'][] = $name;
            }
            if (in_array('spicy', $dietaryTags, true) || str_contains($description, 'spicy')) {
                $candidateMap['spicy'][] = $name;
            }
            if (
                in_array('high protein', $dietaryTags, true)
                || in_array('high-protein', $dietaryTags, true)
                || $this->containsAny($ingredients, ['chicken', 'lamb', 'beef', 'kafta'])
            ) {
                $candidateMap['high_protein'][] = $name;
            }
            if (! $this->containsAny($allergens, ['nuts', 'tree nuts', 'peanut', 'peanuts'])) {
                $candidateMap['no_nuts'][] = $name;
            }
        }

        foreach ($candidateMap as $key => $values) {
            $candidateMap[$key] = array_values(array_unique(array_filter($values)));
        }

        return $candidateMap;
    }

    /**
     * @param  array<int, mixed>  $history
     * @return array<int, array{role: string, content: string}>
     */
    private function sanitizeHistory(array $history): array
    {
        $safe = [];

        foreach (array_slice($history, -10) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $role = (string) ($entry['role'] ?? '');
            $content = $this->sanitizeText((string) ($entry['content'] ?? ''), 1000);

            if (! in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            if ($content === '') {
                continue;
            }

            $safe[] = ['role' => $role, 'content' => $content];
        }

        return $safe;
    }

    private function isLikelyInScope(string $message, array $context): bool
    {
        $normalized = Str::lower($message);
        $restaurantScopeKeywords = [
            'menu',
            'dish',
            'food',
            'meal',
            'ingredient',
            'allergen',
            'allergy',
            'vegan',
            'vegetarian',
            'halal',
            'gluten',
            'nut',
            'hours',
            'open',
            'close',
            'location',
            'address',
            'contact',
            'phone',
            'recommend',
            'suggest',
            'spicy',
            'protein',
            'cedars',
            'lebanon',
        ];

        foreach ($restaurantScopeKeywords as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        foreach ($context['menu_items'] as $menuItem) {
            $name = Str::lower((string) ($menuItem['name'] ?? ''));
            if ($name !== '' && str_contains($normalized, $name)) {
                return true;
            }
        }

        return preg_match('/^\s*(hi|hello|hey|good (morning|afternoon|evening))[\s!.,?]*$/i', $message) === 1;
    }

    private function isOutputAllowed(string $reply): bool
    {
        if ($reply === self::OUT_OF_SCOPE_MESSAGE) {
            return true;
        }
        if ($reply === self::MISSING_INFO_MESSAGE) {
            return true;
        }

        $suspicious = [
            'as an ai',
            'weather',
            'stock',
            'bitcoin',
            'politics',
            'president',
            'world cup',
            'news',
        ];
        $normalized = Str::lower($reply);
        foreach ($suspicious as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return false;
            }
        }

        return true;
    }

    private function containsOutOfScopeTopic(string $message): bool
    {
        $normalized = Str::lower($message);
        $blockedTopics = [
            'weather',
            'forecast',
            'politics',
            'president',
            'election',
            'stock market',
            'bitcoin',
            'crypto',
            'world cup',
            'nba',
            'nfl',
            'movie',
            'homework',
            'programming',
            'code',
            'news',
        ];

        foreach ($blockedTopics as $topic) {
            if (str_contains($normalized, $topic)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  mixed  $values
     * @return array<int, string>
     */
    private function cleanList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $clean = [];
        foreach ($values as $value) {
            if (! is_scalar($value)) {
                continue;
            }
            $item = trim((string) $value);
            if ($item === '') {
                continue;
            }
            $clean[] = Str::limit($item, 120, '');
        }

        return array_values(array_unique($clean));
    }

    /**
     * @param  array<int, string>  $haystack
     * @param  array<int, string>  $needles
     */
    private function containsAny(array $haystack, array $needles): bool
    {
        $haystackText = ' '.Str::lower(implode(' ', $haystack)).' ';
        foreach ($needles as $needle) {
            if (str_contains($haystackText, ' '.Str::lower($needle).' ')) {
                return true;
            }
        }

        return false;
    }

    private function sanitizeText(string $value, int $maxLength): string
    {
        $clean = strip_tags($value);
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? '';
        $clean = trim($clean);

        return Str::limit($clean, $maxLength, '');
    }

    private function jsonEncode(array $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
