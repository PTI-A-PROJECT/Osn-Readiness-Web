<?php

namespace App\Randomizers;

use App\Contracts\Randomizers\RandomizerInterface;

/**
 * SeededRandomizer - Deterministic randomizer for testing.
 */
class SeededRandomizer implements RandomizerInterface
{
    private int $seed;

    private int $state;

    public function __construct(int $seed = 42)
    {
        $this->seed = $seed;
        $this->state = $seed;
    }

    public function randomInt(int $min, int $max): int
    {
        $this->state = (($this->state * 1103515245) + 12345) & 0x7fffffff;

        return $min + (int) (($this->state / 0x7fffffff) * ($max - $min + 1));
    }

    public function randomFloat(): float
    {
        $this->state = (($this->state * 1103515245) + 12345) & 0x7fffffff;

        return $this->state / 0x7fffffff;
    }

    /**
     * @template T
     *
     * @param array<T> $array
     * @return array<T>
     */
    public function shuffle(array $array): array
    {
        $result = $array;
        $n = count($result);

        for ($i = $n - 1; $i > 0; $i--) {
            $j = $this->randomInt(0, $i);
            [$result[$i], $result[$j]] = [$result[$j], $result[$i]];
        }

        return $result;
    }

    /**
     * @template T
     *
     * @param array<T> $array
     * @return T
     */
    public function pickRandom(array $array): mixed
    {
        if (empty($array)) {
            throw new \InvalidArgumentException('Cannot pick from empty array');
        }

        $index = $this->randomInt(0, count($array) - 1);

        return $array[$index];
    }

    /**
     * @template T
     *
     * @param array<T> $array
     * @return array<T>
     */
    public function pickMultiple(array $array, int $count): array
    {
        if ($count < 0 || $count > count($array)) {
            throw new \InvalidArgumentException('Invalid count for pickMultiple');
        }

        $shuffled = $this->shuffle($array);

        return array_slice($shuffled, 0, $count);
    }

    public function getSeed(): int
    {
        return $this->seed;
    }

    public function reset(): void
    {
        $this->state = $this->seed;
    }
}
