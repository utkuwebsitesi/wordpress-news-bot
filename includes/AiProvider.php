<?php
declare(strict_types=1);
namespace WordPressNewsBot;

interface AiProvider
{
    /** @return array<string,mixed> */
    public function generate(array $item): array;
    /** @return array{success:bool,model:string,duration_ms:int,request_id:string,http_class:int} */
    public function testConnection(): array;
    public function model(): string;
}
