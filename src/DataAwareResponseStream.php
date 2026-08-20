<?php

declare(strict_types=1);

namespace BenTools\TypedHttpClient;

use Generator;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * @template T
 */
final readonly class DataAwareResponseStream implements ResponseStreamInterface
{
    /**
     * @param Generator<DataAwareResponse<T>, ChunkInterface, mixed, mixed> $generator
     */
    public function __construct(
        private Generator $generator,
    ) {}

    /**
     * @return DataAwareResponse<T>
     */
    public function key(): DataAwareResponse
    {
        return $this->generator->key();
    }

    public function current(): ChunkInterface
    {
        return $this->generator->current();
    }

    public function next(): void
    {
        $this->generator->next();
    }

    public function rewind(): void
    {
        $this->generator->rewind();
    }

    public function valid(): bool
    {
        return $this->generator->valid();
    }
}
