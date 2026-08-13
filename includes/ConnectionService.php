<?php
declare(strict_types=1);
namespace WordPressNewsBot;

final class ConnectionService
{
    public function __construct(private readonly SecretStorage $storage, private readonly mixed $providerFactory = null) {}
    public function saveAndTest(string $apiKey, string $model): array
    {
        $provider=$this->provider($apiKey,$model); $result=$provider->testConnection(); $this->storage->store($apiKey); return $result;
    }
    public function retest(string $model): array { return $this->provider(Credentials::openAiKey($this->storage),$model)->testConnection(); }
    public function delete(): void { $this->storage->delete(); }
    private function provider(string $key,string $model): AiProvider { return $this->providerFactory?($this->providerFactory)($key,$model):new OpenAiProvider($key,$model); }
}
