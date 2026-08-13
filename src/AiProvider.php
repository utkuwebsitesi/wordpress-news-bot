<?php
declare(strict_types=1);
namespace Neyelazim\NewsBot;

interface AiProvider
{
    /** @return array<string,mixed> */
    public function generate(array $item): array;
    public function testConnection(): void;
    public function model(): string;
}
