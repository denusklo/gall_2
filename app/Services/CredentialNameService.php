<?php

namespace App\Services;

use App\Models\StorageCredential;
use Illuminate\Support\Facades\DB;

/**
 * Service to generate unique random names for storage credentials.
 * Names are generated using 2 random words separated by a hyphen.
 * Examples: crimson-wolf, sapphire-dragon, velvet-phoenix
 */
class CredentialNameService
{
    /**
     * List of adjectives for name generation.
     */
    private const ADJECTIVES = [
        'crimson', 'azure', 'emerald', 'golden', 'silver', 'velvet',
        'obsidian', 'cobalt', 'amber', 'violet', 'scarlet', 'indigo',
        'jade', 'ruby', 'sapphire', 'topaz', 'pearl', 'onyx',
        'platinum', 'bronze', 'copper', 'titanium', 'carbon', 'shadow',
        'frost', 'thunder', 'blaze', 'storm', 'mist', 'dawn',
        'dusk', 'midnight', 'solar', 'lunar', 'stellar', 'cosmic',
        'arctic', 'tropical', 'desert', 'ocean', 'mountain', 'forest',
        'crystal', 'prism', 'spectrum', 'chroma', 'radiant', 'luminous',
        'whisper', 'echo', 'sonic', 'rhythm', 'tempo', 'melody',
        'swift', 'noble', 'fierce', 'gentle', 'wild', 'calm',
        'brave', 'wise', 'proud', 'humble', 'bold', 'keen',
    ];

    /**
     * List of nouns for name generation.
     */
    private const NOUNS = [
        'wolf', 'dragon', 'phoenix', 'tiger', 'eagle', 'lion',
        'bear', 'hawk', 'fox', 'panther', 'leopard', 'cobra',
        'falcon', 'owl', 'shark', 'whale', 'dolphin', 'raven',
        'spider', 'scorpion', 'beetle', 'butterfly', 'moth', 'wasp',
        'castle', 'fortress', 'temple', 'palace', 'tower', 'keep',
        'mountain', 'valley', 'canyon', 'forest', 'desert', 'ocean',
        'river', 'lake', 'waterfall', 'volcano', 'glacier', 'cave',
        'thunder', 'lightning', 'rainbow', 'aurora', 'comet', 'star',
        'planet', 'galaxy', 'nebula', 'void', 'horizon', 'zenith',
        'warrior', 'knight', 'paladin', 'ranger', 'mage', 'monk',
        'rogue', 'bard', 'druid', 'sorcerer', 'wizard', 'alchemist',
    ];

    /**
     * Maximum number of attempts to generate a unique name.
     */
    private const MAX_ATTEMPTS = 100;

    /**
     * Generate a unique random name for a storage credential.
     *
     * @return string A unique name in format "adjective-noun"
     * @throws \RuntimeException If unable to generate unique name
     */
    public function generate(): string
    {
        $attempts = 0;

        while ($attempts < self::MAX_ATTEMPTS) {
            $name = $this->generateRandomName();

            // Check if name is unique
            if (!$this->nameExists($name)) {
                return $name;
            }

            $attempts++;
        }

        throw new \RuntimeException(
            'Unable to generate unique credential name after ' . self::MAX_ATTEMPTS . ' attempts'
        );
    }

    /**
     * Generate a random name without checking uniqueness.
     *
     * @return string
     */
    public function generateRandomName(): string
    {
        $adjective = $this->getRandomAdjective();
        $noun = $this->getRandomNoun();

        return strtolower($adjective . '-' . $noun);
    }

    /**
     * Check if a name already exists in the database.
     *
     * @param string $name
     * @return bool
     */
    protected function nameExists(string $name): bool
    {
        return StorageCredential::where('name', $name)->exists();
    }

    /**
     * Get a random adjective from the list.
     *
     * @return string
     */
    protected function getRandomAdjective(): string
    {
        return self::ADJECTIVES[array_rand(self::ADJECTIVES)];
    }

    /**
     * Get a random noun from the list.
     *
     * @return string
     */
    protected function getRandomNoun(): string
    {
        return self::NOUNS[array_rand(self::NOUNS)];
    }

    /**
     * Generate a random name with a numeric suffix if needed.
     * Useful when most simple names are taken.
     *
     * @param int $suffix Numeric suffix to append
     * @return string
     */
    public function generateWithSuffix(int $suffix = null): string
    {
        $name = $this->generateRandomName();

        if ($suffix !== null) {
            return $name . '-' . $suffix;
        }

        return $name;
    }
}
