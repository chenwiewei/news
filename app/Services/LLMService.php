<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class LLMService
{
    private string $provider;
    private array $config;

    public function __construct(string $provider = null)
    {
        $this->provider = $provider ?? config('services.llm.default');
        $this->config = config("services.llm.{$this->provider}");
    }

    public static function make(string $provider = null): self
    {
        return new self($provider);
    }

    public function chat(string $message, array $options = []): string
    {
        try {
            return match ($this->provider) {
                'doubao' => $this->chatDoubao($message, $options),
                'baidu' => $this->chatBaidu($message, $options),
                'aliyun' => $this->chatAliyun($message, $options),
                'zhipu' => $this->chatZhipu($message, $options),
                //'kimi' => $this->chatKimi($message, $options),
                default => throw new Exception("Unsupported provider: {$this->provider}"),
            };
        } catch (Exception $e) {
            Log::error("LLM API Error [{$this->provider}]: " . $e->getMessage());
            throw $e;
        }
    }

    private function chatDoubao(string $message, array $options = []): string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->config['api_key']}",
            'Content-Type' => 'application/json',
        ])->post("{$this->config['endpoint']}/chat/completions", [
            'model' => $this->config['model'],
            'messages' => [
                ['role' => 'user', 'content' => $message],
            ],
            //'temperature' => $options['temperature'] ?? 0.7,
            //'max_tokens' => $options['max_tokens'] ?? 2000,
        ]);

       if ($response->failed()) {
            throw new Exception("Doubao API error: " . $response->body());
        }

        return $response->json('choices.0.message.content');
    }

    private function chatBaidu(string $message, array $options = []): string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->config['api_key']}",
            'Content-Type' => 'application/json',
        ])->post("{$this->config['endpoint']}/chat/completions", [
            'model' => $this->config['model'],
            'messages' => [
                ['role' => 'user', 'content' => $message],
            ],
            //'temperature' => $options['temperature'] ?? 0.7,
            //'max_tokens' => $options['max_tokens'] ?? 2000,
        ]);

        if ($response->failed()) {
            throw new Exception("Baidu API error: " . $response->body());
        }

        return $response->json('choices.0.message.content');
        /*$accessToken = $this->getBaiduAccessToken();

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post("{$this->config['endpoint']}/{$this->config['model']}?access_token={$accessToken}", [
            'messages' => [
                ['role' => 'user', 'content' => $message],
            ],
            'temperature' => $options['temperature'] ?? 0.7,
            'max_output_tokens' => $options['max_tokens'] ?? 2000,
        ]);

        if ($response->failed()) {
            throw new Exception("Baidu API error: " . $response->body());
        }

        return $response->json('result');*/
    }

    private function getBaiduAccessToken(): string
    {
        $cacheKey = 'baidu_access_token';
        $cached = cache()->get($cacheKey);

        if ($cached) {
            return $cached;
        }

        $response = Http::post("https://aip.baidubce.com/oauth/2.0/token", [
            'grant_type' => 'client_credentials',
            'client_id' => $this->config['api_key'],
            'client_secret' => $this->config['secret_key'],
        ]);

        if ($response->failed()) {
            throw new Exception("Failed to get Baidu access token");
        }

        $token = $response->json('access_token');
        $expiresIn = $response->json('expires_in', 2592000) - 3600;

        cache()->put($cacheKey, $token, $expiresIn);

        return $token;
    }

    private function chatAliyun(string $message, array $options = []): string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->config['api_key']}",
            'Content-Type' => 'application/json',
        ])->post("{$this->config['endpoint']}", [
            'model' => $this->config['model'],
            'messages' => [
                ['role' => 'user', 'content' => $message],
            ],
            //'parameters' => [
            //    'temperature' => $options['temperature'] ?? 0.7,
            //    'max_tokens' => $options['max_tokens'] ?? 2000,
            //],
        ]);
        //dd($response);
        if ($response->failed()) {
            throw new Exception("Aliyun API error: " . $response->body());
        }
        return $response->json('choices.0.message.content');
        //return $response->json('output.text');
    }

    private function chatZhipu(string $message, array $options = []): string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->config['api_key']}",
            'Content-Type' => 'application/json',
        ])->post("{$this->config['endpoint']}/chat/completions", [
            'model' => $this->config['model'],
            'messages' => [
                ['role' => 'user', 'content' => $message],
            ],
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 2000,
        ]);

        if ($response->failed()) {
            throw new Exception("Zhipu API error: " . $response->body());
        }

        return $response->json('choices.0.message.content');
    }

    private function chatKimi(string $message, array $options = []): string
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->config['api_key']}",
            'Content-Type' => 'application/json',
        ])->post("{$this->config['endpoint']}/chat/completions", [
            'model' => $this->config['model'],
            'messages' => [
                ['role' => 'user', 'content' => $message],
            ],
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 2000,
        ]);

        if ($response->failed()) {
            throw new Exception("Kimi API error: " . $response->body());
        }

        return $response->json('choices.0.message.content');
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;
        $this->config = config("services.llm.{$this->provider}");
        return $this;
    }
}
