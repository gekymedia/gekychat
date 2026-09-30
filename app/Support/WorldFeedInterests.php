<?php

namespace App\Support;

/**
 * Canonical World Feed interest catalog for first-visit onboarding.
 * Interest ids are stored on the user; tags/keywords drive ranking boosts.
 */
class WorldFeedInterests
{
    public const MIN_REQUIRED = 3;

    /**
     * @return list<array{id: string, label: string, icon: string, tags: list<string>}>
     */
    public static function catalog(): array
    {
        return [
            ['id' => 'comedy', 'label' => 'Comedy', 'icon' => 'emoji-laughing', 'tags' => ['comedy', 'funny', 'humor', 'skit']],
            ['id' => 'music', 'label' => 'Music', 'icon' => 'music-note-beamed', 'tags' => ['music', 'afrobeats', 'hiplife', 'song']],
            ['id' => 'dance', 'label' => 'Dance', 'icon' => 'activity', 'tags' => ['dance', 'choreography', 'azonto']],
            ['id' => 'sports', 'label' => 'Sports', 'icon' => 'trophy', 'tags' => ['sports', 'football', 'soccer', 'fitness']],
            ['id' => 'food', 'label' => 'Food', 'icon' => 'cup-hot', 'tags' => ['food', 'cooking', 'recipe', 'streetfood']],
            ['id' => 'beauty', 'label' => 'Beauty & Style', 'icon' => 'stars', 'tags' => ['beauty', 'fashion', 'style', 'makeup']],
            ['id' => 'gaming', 'label' => 'Gaming', 'icon' => 'controller', 'tags' => ['gaming', 'game', 'esports']],
            ['id' => 'education', 'label' => 'Education', 'icon' => 'book', 'tags' => ['education', 'learning', 'tips', 'howto']],
            ['id' => 'travel', 'label' => 'Travel', 'icon' => 'airplane', 'tags' => ['travel', 'tour', 'adventure']],
            ['id' => 'tech', 'label' => 'Tech', 'icon' => 'cpu', 'tags' => ['tech', 'gadgets', 'phones', 'coding']],
            ['id' => 'news', 'label' => 'News', 'icon' => 'newspaper', 'tags' => ['news', 'current affairs', 'politics']],
            ['id' => 'faith', 'label' => 'Faith', 'icon' => 'heart', 'tags' => ['faith', 'gospel', 'church', 'inspiration']],
            ['id' => 'business', 'label' => 'Business', 'icon' => 'briefcase', 'tags' => ['business', 'entrepreneur', 'money']],
            ['id' => 'lifestyle', 'label' => 'Lifestyle', 'icon' => 'house-heart', 'tags' => ['lifestyle', 'vlog', 'daily']],
            ['id' => 'diy', 'label' => 'DIY', 'icon' => 'tools', 'tags' => ['diy', 'crafts', 'homemade']],
            ['id' => 'auto', 'label' => 'Auto', 'icon' => 'car-front', 'tags' => ['auto', 'cars', 'motors']],
        ];
    }

    /**
     * @return list<string>
     */
    public static function validIds(): array
    {
        return array_column(self::catalog(), 'id');
    }

    /**
     * Expand selected interest ids into ranking tags/keywords.
     *
     * @param  list<string>  $interestIds
     * @return list<string>
     */
    public static function tagsForInterests(array $interestIds): array
    {
        $tags = [];
        $wanted = array_fill_keys($interestIds, true);
        foreach (self::catalog() as $item) {
            if (!isset($wanted[$item['id']])) {
                continue;
            }
            $tags[] = $item['id'];
            foreach ($item['tags'] as $tag) {
                $tags[] = strtolower($tag);
            }
        }

        return array_values(array_unique($tags));
    }

    /**
     * Public payload for clients (no internal keyword lists required).
     *
     * @return list<array{id: string, label: string, icon: string}>
     */
    public static function clientCatalog(): array
    {
        return array_map(static function (array $item) {
            return [
                'id' => $item['id'],
                'label' => $item['label'],
                'icon' => $item['icon'],
            ];
        }, self::catalog());
    }
}
