<?php

declare(strict_types=1);

namespace BenTools\TypedHttpClient\Tests\Fixtures;

final readonly class Todo
{
    public function __construct(
        public int $userId,
        public int $id,
        public string $title,
        public bool $completed,
    ) {}
}
