<?php
// ============================================================================
// Restaurant AI Chat Service
// ============================================================================
// Hey! This is the "brain" of the Cedars of Lebanon AI assistant. 🤖
// It decides how to reply to every message the user sends in the chat widget.
//
// How it works (step by step):
// 1. The user's message arrives from the frontend (global.js).
// 2. This class first checks for simple, common questions (menu, hours, greetings)
//    and replies INSTANTLY without calling an external AI — this makes it super fast!
// 3. If the question is more complex, it sends the message to an LLM (Groq AI)
//    with a friendly "personality prompt" and restaurant data as context.
// 4. The reply is cleaned, checked for safety, and sent back to the user.
//
// The "personality" and "smartness" come from:
//   - runtimeSystemPrompt()  → the friendly tone and behavior rules
//   - handleDirectIntents()  → fast answers for common questions
//   - buildContext()         → live menu data so the AI never makes up dishes!
// ============================================================================

namespace App\Services;

use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RestaurantAiChatService
{

    private const MISSING_INFO_MESSAGE = 'I don’t have verified information for that yet.';

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    /**
     * Main entry point: receives the user's message and returns the AI's reply.
     *
     * Flow:
     *   1. Sanitize the input (strip dangerous content, limit length).
     *   2. Try to answer with "direct intents" — fast, hard-coded replies for
     *      common questions like "What are your hours?" or "Hello".
     *   3. If no direct intent matched, build a prompt with restaurant data
     *      and send it to the external LLM (Groq / Llama).
     *   4. Return the final reply to the controller.
     *
     * @param  array<int, array{role: string, content: string}>  $history  Previous messages in this session
     */
    public function reply(string $message, array $history = []): array
    {
        $cleanMessage = $this->sanitizeText($message, 1000);

        if ($cleanMessage === '') {
            return ['reply' => "Hey there! I'm here to help with our Lebanese menu, ingredients, allergens, dietary options, hours, and location. What would you like to know? 😊"];
        }

        // Build a fresh snapshot of the restaurant menu, hours, and location from the database.
        $context = $this->buildContext();

        // Try to answer instantly without calling the expensive AI API.
        $directReply = $this->handleDirectIntents($cleanMessage, $context);
        if ($directReply !== null) {
            return ['reply' => $directReply];
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

        $baseUrl = rtrim((string) config('services.llm.base_url', 'https://api.groq.com/openai/v1'), '/');
        $model = (string) config('services.llm.model', 'llama-3.3-70b-versatile');

        $payload = [
            'model' => $model,
            'temperature' => 0.1,
            'messages' => $messages,
        ];
        if (str_contains(Str::lower($baseUrl), 'groq.com')) {
            $payload['max_completion_tokens'] = 350;
        } else {
            $payload['max_tokens'] = 350;
        }

        $response = Http::timeout((int) config('services.llm.timeout_seconds', 20))
            ->withToken($apiKey)
            ->acceptJson()
            ->post($baseUrl.'/chat/completions', $payload);

        if (! $response->ok()) {
            $responseJson = $response->json();
            $upstreamMessage = trim((string) data_get($responseJson, 'error.message', ''));
            if ($upstreamMessage === '') {
                $upstreamMessage = trim((string) $response->body());
            }

            throw new \RuntimeException(
                'LLM API request failed with status '.$response->status()
                .($upstreamMessage !== '' ? ': '.$upstreamMessage : '')
            );
        }

        $rawReply = (string) data_get($response->json(), 'choices.0.message.content', '');
        $reply = $this->sanitizeText($rawReply, 1200);

        if ($reply === '') {
            return ['reply' => self::MISSING_INFO_MESSAGE];
        }

        if (! $this->isOutputAllowed($reply)) {
            return ['reply' => "I'm here to help with our Lebanese menu, ingredients, allergens, dietary options, hours, and location. What would you like to know? 🍽️"];
        }

        return ['reply' => $reply];
    }

    /**
     * The "personality prompt" sent to the LLM.
     * This tells the AI how to behave: friendly tone, emoji use, conversation style,
     * and the rules it must follow (only real menu data, no invented facts, etc.).
     * Changing this text changes how the AI feels to users!
     */
    private function runtimeSystemPrompt(): string
    {
        return <<<PROMPT
You are a smart, friendly, and conversational AI assistant for Cedars of Lebanon, a Lebanese restaurant.

Your primary role is to help users with:
- Lebanese food and drinks
- Menu recommendations
- Ingredients and meal details
- Dietary preferences
- Restaurant services
- Meal suggestions
- Customer support related to the restaurant

Behavior Guidelines:
- Speak naturally like a modern human assistant.
- Be friendly, warm, and engaging.
- Respond naturally to greetings, casual messages, reactions, jokes, confirmations, emotions, and small talk.
- Avoid robotic or repetitive responses.
- Never repeatedly say phrases like "I only answer restaurant questions."

Conversation Handling:
- If the user asks about food, drinks, meals, ingredients, or restaurant-related topics, provide detailed and helpful answers.
- If the user asks a general or unrelated question, briefly answer in a friendly way, then smoothly redirect the conversation toward the restaurant or food experience when appropriate.
- If the question is completely unrelated, inappropriate, dangerous, illegal, or outside your expertise, politely explain that your expertise is focused on the Lebanese restaurant experience.

Response Style:
- Keep responses concise but helpful.
- Maintain smooth conversational flow.
- Use light emojis naturally when appropriate.
- Sound intelligent, welcoming, and professional.
- Make the user feel assisted, not restricted.

Smart Restaurant Behavior:
- Suggest dishes when relevant.
- Recommend meals based on preferences like spicy, vegetarian, grilled, light, or budget-friendly.
- Encourage exploration of Lebanese cuisine naturally.
- Remember previous user preferences during the conversation when possible.

Rules:
1) Do not invent facts. If data is missing, say: "I don't have verified information for that yet."
2) Recommend only dishes that exist in provided data.
3) Keep answers concise, helpful, and polite.
4) When the user asks for "the menu" or "what do you have", list dishes by category with their prices. Use bullet points.
PROMPT;
    }

    /**
     * "Direct intents" = fast, local replies for super-common questions.
     * Instead of waiting for the LLM, we answer immediately with hard-coded logic.
     * This saves API tokens, speeds up replies, and guarantees correct formatting.
     *
     * Covered topics:
     *   - Full menu listing, categories, hours, location
     *   - Price / ingredients / allergens / availability of a specific dish
     *   - Dish recommendations based on dietary preferences
     *   - Identity, purpose, capabilities
     *   - Small talk (hello, thanks, goodbye, "I'm hungry", etc.)
     */
    private function handleDirectIntents(string $message, array $context): ?string
    {
        $low = Str::lower($message);

        // 1. Menu Logic (Highest Priority)
        // Handles full menu and categories strictly to avoid LLM token usage and ensure formatting.
        if (preg_match('/\b(menu|what do you have|what do you serve)\b/', $low)) {
            // Sub-check for Categories
            if (preg_match('/\b(categories|types of food)\b/', $low)) {
                $cats = $context['categories'] ?? [];
                return 'We serve: '.implode(', ', $cats).'.';
            }
            // Full Menu List
            $lines = ["Here is our menu:"];
            foreach ($context['menu_items'] ?? [] as $item) {
                if (! ($item['is_available'] ?? true)) {
                    continue;
                }
                $lines[] = '• '.$item['name'].' – '.$item['category'].' – $'.number_format($item['price'] ?? 0, 2);
                if (! empty($item['description'])) {
                    $lines[] = '  '.$item['description'];
                }
            }
            return implode("\n", $lines);
        }

        // 2. Hours Logic
        // Strict checks to avoid matching "What are you" or "Where are you".
        if (preg_match('/\b(hours|when do you open|when do you close|opening times|open today|what are your hours|when are you open)\b/', $low)) {
            $hours = $context['restaurant']['hours'] ?? [];
            if (! empty($hours)) {
                $lines = ['Our hours:'];
                foreach ($hours as $slot) {
                    $days = $slot['days'] ?? '';
                    $open = $slot['open'] ?? '';
                    $close = $slot['close'] ?? '';
                    if ($days && $open && $close) {
                        $lines[] = "• $days: $open – $close";
                    }
                }
                return implode("\n", $lines);
            }
        }

        // 3. Location Logic
        // Handles address queries directly.
        if (preg_match('/\b(location|address|where are you|how to get there|where is the restaurant|where are you located)\b/', $low)) {
            $loc = $context['restaurant']['location'] ?? [];
            if (! empty($loc)) {
                return 'We are located at: '.implode(', ', $loc).'.';
            }
        }

        // 4. Menu-Item Details Logic
        $menuItemReply = $this->handleMenuItemDetailsIntent($message, $context);
        if ($menuItemReply !== null) {
            return $menuItemReply;
        }

        // 5. Recommendation Logic
        $recommendationReply = $this->handleRecommendationIntent($message, $context);
        if ($recommendationReply !== null) {
            return $recommendationReply;
        }

        // 6. Identity Logic
        // Answers "Who are you" specifically.
        if (preg_match('/\b(who are you|what are you|your name|introduce yourself)\b/', $low)) {
            return "I'm your Cedars of Lebanon assistant 🌲 I can help you discover dishes, check ingredients and allergens, recommend meals based on your preferences, and answer questions about our hours and location. What would you like to know?";
        }

        // 7. Purpose Logic
        // Answers "Why do you exist".
        if (preg_match('/\b(meaningful|meaning|purpose|role|function|why are you here|why do you exist|who created you|who made you)\b/', $low)) {
            return "I'm here to make your Cedars of Lebanon experience amazing 🍽️ I can help you find dishes, check ingredients and allergens, recommend meals based on your preferences, and answer questions about our hours and location.";
        }
        // 8. Capabilities Logic
        if (preg_match('/\b(what can you do|how can you help|what do you help with|what can you help with)\b/', $low)) {
            return "I can help with our menu, prices, ingredients, allergens, dietary options, dish recommendations, opening hours, and location. Tell me what you'd like to know! 😊";
        }

        // 9. Social Small-Talk Logic
        $smallTalkReply = $this->smallTalkReply($message);
        if ($smallTalkReply !== null) {
            return $smallTalkReply;
        }

        // All other queries (Recommendations, "Tell me about X", etc.) fall through to the LLM.
        // This ensures intelligent handling of complex queries while keeping simple data lookups fast.
        return null;
    }

    private function handleMenuItemDetailsIntent(string $message, array $context): ?string
    {
        $low = Str::lower($message);
        $item = $this->findMenuItemFromMessage($message, $context['menu_items'] ?? []);
        if ($item === null) {
            return null;
        }

        $itemName = (string) ($item['name'] ?? 'This dish');
        $hasPriceIntent = preg_match('/\b(price|cost|how much)\b/', $low) === 1;
        $hasIngredientIntent = preg_match('/\b(ingredient|ingredients|what is in|what s in|made of|contain|contains)\b/', $low) === 1;
        $hasAllergenIntent = preg_match('/\b(allergen|allergens|allergy|allergies|nut|nuts|peanut|gluten|dairy|sesame|soy|egg|shellfish)\b/', $low) === 1;
        $hasAvailabilityIntent = preg_match('/\b(available|availability|sold out|in stock)\b/', $low) === 1;
        $hasAboutIntent = preg_match('/\b(tell me about|what is|what s|describe)\b/', $low) === 1;

        if ($hasPriceIntent) {
            return $itemName.' is $'.number_format((float) ($item['price'] ?? 0), 2).'.';
        }

        if ($hasIngredientIntent) {
            $ingredients = $item['ingredients'] ?? [];
            if (! empty($ingredients)) {
                return 'Ingredients in '.$itemName.': '.implode(', ', $ingredients).'.';
            }

            return 'I don’t have verified ingredient details for '.$itemName.' yet.';
        }

        if ($hasAllergenIntent) {
            $allergens = $item['allergens'] ?? [];
            if (! empty($allergens)) {
                return 'Allergens in '.$itemName.': '.implode(', ', $allergens).'.';
            }

            return 'I don’t have verified allergen details for '.$itemName.' yet.';
        }

        if ($hasAvailabilityIntent) {
            $isAvailable = (bool) ($item['is_available'] ?? true);
            if ($isAvailable) {
                return $itemName.' is currently available.';
            }

            return $itemName.' is currently unavailable.';
        }

        if ($hasAboutIntent) {
            $segments = [];
            $description = trim((string) ($item['description'] ?? ''));
            if ($description !== '') {
                $segments[] = $description;
            }
            $segments[] = 'Price: $'.number_format((float) ($item['price'] ?? 0), 2).'.';

            $dietaryTags = $item['dietary_tags'] ?? [];
            if (! empty($dietaryTags)) {
                $segments[] = 'Dietary tags: '.implode(', ', $dietaryTags).'.';
            }

            return $itemName.': '.implode(' ', $segments);
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $menuItems
     * @return array<string, mixed>|null
     */
    private function findMenuItemFromMessage(string $message, array $menuItems): ?array
    {
        $normalizedMessage = $this->normalizeIntentText($message);
        if ($normalizedMessage === '' || empty($menuItems)) {
            return null;
        }

        $bestMatch = null;
        $bestScore = -1;

        foreach ($menuItems as $menuItem) {
            if (! is_array($menuItem)) {
                continue;
            }

            $name = trim((string) ($menuItem['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $normalizedName = $this->normalizeIntentText($name);
            if ($normalizedName === '') {
                continue;
            }

            if ($this->containsPhrase($normalizedMessage, $normalizedName)) {
                $score = 1000 + strlen($normalizedName);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = $menuItem;
                }
                continue;
            }

            $nameTokens = array_values(array_filter(
                explode(' ', $normalizedName),
                static fn (string $token): bool => strlen($token) >= 3
            ));
            if (empty($nameTokens)) {
                continue;
            }

            $matchedTokenCount = 0;
            foreach ($nameTokens as $token) {
                if ($this->containsPhrase($normalizedMessage, $token)) {
                    $matchedTokenCount++;
                }
            }

            if ($matchedTokenCount === 0) {
                continue;
            }

            $allTokensMatched = $matchedTokenCount === count($nameTokens);
            $isStrongPartialMatch = $allTokensMatched || $matchedTokenCount >= 2;
            if (! $isStrongPartialMatch) {
                continue;
            }

            $score = ($allTokensMatched ? 500 : 100) + $matchedTokenCount + strlen($normalizedName);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $menuItem;
            }
        }

        return $bestMatch;
    }

    private function containsPhrase(string $haystack, string $phrase): bool
    {
        $escaped = preg_quote($phrase, '/');
        $pattern = '/\b'.str_replace('\ ', '\s+', $escaped).'\b/u';

        return preg_match($pattern, $haystack) === 1;
    }
    private function handleRecommendationIntent(string $message, array $context): ?string
    {
        $low = Str::lower($message);
        $isRecommendationPrompt = preg_match('/\b(recommend|suggest|recommendation|what should i (eat|try)|what do you recommend|best dish|any idea)\b/', $low) === 1;
        if (! $isRecommendationPrompt) {
            return null;
        }

        $preferenceMap = [
            'vegan' => ['vegan'],
            'vegetarian' => ['vegetarian', 'veggie'],
            'spicy' => ['spicy', 'hot'],
            'high_protein' => ['high protein', 'high-protein', 'protein', 'gym'],
            'no_nuts' => ['no nuts', 'nut free', 'nut-free', 'without nuts', 'peanut allergy', 'nut allergy', 'allergic to nuts'],
        ];

        $selectedPreferenceKeys = [];
        foreach ($preferenceMap as $key => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($low, $needle)) {
                    $selectedPreferenceKeys[] = $key;
                    break;
                }
            }
        }
        $selectedPreferenceKeys = array_values(array_unique($selectedPreferenceKeys));

        $availableItemsByName = [];
        foreach ($context['menu_items'] ?? [] as $menuItem) {
            if ((bool) ($menuItem['is_available'] ?? true)) {
                $availableItemsByName[(string) ($menuItem['name'] ?? '')] = true;
            }
        }

        $candidateMap = $context['recommendation_candidates'] ?? [];
        if (! empty($selectedPreferenceKeys)) {
            $candidateLists = [];
            foreach ($selectedPreferenceKeys as $preferenceKey) {
                $values = array_values(array_filter($candidateMap[$preferenceKey] ?? []));
                $values = array_values(array_filter($values, fn (string $name): bool => isset($availableItemsByName[$name])));
                if (! empty($values)) {
                    $candidateLists[] = $values;
                }
            }

            if (empty($candidateLists)) {
                return self::MISSING_INFO_MESSAGE;
            }

            $matching = array_shift($candidateLists);
            foreach ($candidateLists as $list) {
                $matching = array_values(array_intersect($matching, $list));
            }

            if (empty($matching)) {
                $matching = array_values(array_unique(array_merge(...$candidateLists)));
            }

            $suggestions = array_slice($matching, 0, 4);
            if (empty($suggestions)) {
                return self::MISSING_INFO_MESSAGE;
            }

            return 'Great choice. Based on your preferences, I recommend: '.implode(', ', $suggestions).'.';
        }

        $availableNames = array_keys($availableItemsByName);
        if (empty($availableNames)) {
            return self::MISSING_INFO_MESSAGE;
        }

        return 'Sure! A few popular options to try are: '.implode(', ', array_slice($availableNames, 0, 4)).'.';
    }

    /**
     * Friendly canned replies for casual social messages.
     * Makes the bot feel human and welcoming instead of robotic.
     *
     * Detected intents: greeting, thanks, goodbye, how_are_you, acknowledgement, hungry.
     */
    private function smallTalkReply(string $message): ?string
    {
        $intent = $this->detectSmallTalkIntent($message);

        return match ($intent) {
            'greeting' => "Hello 👋 Welcome! I'm here to help you explore our Lebanese menu and find meals you'll love.",
            'thanks' => "You're very welcome 😊 I'm always here if you'd like help choosing a meal or exploring Lebanese flavors.",
            'goodbye' => "You're always welcome. See you soon at Cedars of Lebanon!",
            'how_are_you' => "I'm doing great—thanks for asking! I'm ready to help with menu choices, allergens, dietary options, hours, or location.",
            'acknowledgement' => "Perfect 😄 Let me know if you'd like recommendations or details about any dishes.",
            'hungry' => "😄 Then you're in the perfect place! Would you like something grilled, spicy, light, or vegetarian from our Lebanese menu?",
            default => null,
        };
    }

    private function detectSmallTalkIntent(string $message): ?string
    {
        $normalized = $this->normalizeIntentText($message);
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^(h+i+|he+y+|hello+|good morning|good afternoon|good evening|yo+|salam+|marhaba+)$/u', $normalized) === 1) {
            return 'greeting';
        }

        if (preg_match('/^(thanks|thank you|thank you so much|thanks a lot|thx|ty|appreciate it|much appreciated)$/u', $normalized) === 1) {
            return 'thanks';
        }

        if (preg_match('/^(bye|goodbye|see you|see ya|later|talk to you later)$/u', $normalized) === 1) {
            return 'goodbye';
        }

        if (preg_match('/^(how are you|how are you doing|how is it going|how s it going)$/u', $normalized) === 1) {
            return 'how_are_you';
        }

        if (preg_match('/^(ok|okay|cool|great|awesome|nice|perfect)$/u', $normalized) === 1) {
            return 'acknowledgement';
        }

        if (preg_match('/^(i am hungry|i\'m hungry|hungry|starving)$/u', $normalized) === 1) {
            return 'hungry';
        }

        return null;
    }

    private function normalizeIntentText(string $message): string
    {
        $normalized = Str::lower($message);
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? '';
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? '';

        return trim($normalized);
    }

    /**
     * Pull live data from the database to give the AI accurate, up-to-date context.
     * This includes every menu item (name, price, ingredients, allergens, dietary tags),
     * plus restaurant hours, location, and pre-built recommendation lists.
     *
     * We send this context to the LLM so it never "hallucinates" fake dishes or prices.
     */
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

        foreach (array_slice($history, -6) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $role = (string) ($entry['role'] ?? '');
            $content = $this->sanitizeText((string) ($entry['content'] ?? ''), 500);

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


    private function isOutputAllowed(string $reply): bool
    {
        if ($reply === self::MISSING_INFO_MESSAGE) {
            return true;
        }

        $suspicious = [
            'as an ai',
            'as an artificial intelligence',
            'i am an ai',
        ];
        $normalized = Str::lower($reply);
        foreach ($suspicious as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return false;
            }
        }

        return true;
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
