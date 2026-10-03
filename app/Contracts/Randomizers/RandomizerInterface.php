<?php

namespace App\Contracts\Randomizers;

interface RandomizerInterface
{
    /**
     * Generate a random integer within a range.
     */
    public function randomInt(int $min, int $max): int;

    /**
     * Generate a random float between 0 and 1.
     */
    public function randomFloat(): float;

    /**
     * Shuffle an array.
     *
     * @template T
     *
     * @param array<T> $array
     * @return array<T>
     */
    public function shuffle(array $array): array;

    /**
     * Pick a random element from an array.
     *
     * @template T
     *
     * @param array<T> $array
     * @return T
     */
    public function pickRandom(array $array): mixed;

    /**
     * Pick multiple random elements without replacement.
     *
     * @template T
     *
     * @param array<T> $array
     * @param int $count
     * @return array<T>
     */
    public function pickMultiple(array $array, int $count): array;
}
