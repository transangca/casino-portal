<?php
namespace App\Services;

use App\Core\Database;

/**
 * Trung tâm AI — gộp toàn bộ model AI về 1 trang quản lý + 1 API thống nhất.
 *
 * Hỗ trợ OpenAI-compatible API (OpenAI, DeepSeek, Gemini-OpenAI-compat,
 * Groq, Together, Ollama, LM Studio…). Admin chỉ cần thêm base URL + key.
 */
class AIService
{
    /**
     * Danh sách model mặc định khi cài mới.
     */
    public const PRESET_MODELS = [
        ['provider' => 'openai',    'model' => 'gpt-4o-mini',        'label' => 'GPT-4o mini',            'context_k' => 128, 'price_in' => 0.00015, 'price_out' => 0.0006],
        ['provider' => 'openai',    'model' => 'gpt-4o',             'label' => 'GPT-4o',                 'context_k' => 128, 'price_in' => 0.0025,  'price_out' => 0.01],
        ['provider' => 'openai',    'model' => 'gpt-4.1',            'label' => 'GPT-4.1',                'context_k' => 128, 'price_in' => 0.002,   'price_out' => 0.008],
        ['provider' => 'deepseek',  'model' => 'deepseek-chat',      'label' => 'DeepSeek V3',            'context_k' => 64,  'price_in' => 0.00027, 'price_out' => 0.0011],
        ['provider' => 'gemini',    'model' => 'gemini-2.0-flash',   'label' => 'Gemini 2.0 Flash',       'context_k' => 1000, 'price_in' => 0.0001, 'price_out' => 0.0004],
        ['provider' => 'groq',      'model' => 'llama-3.3-70b-versatile', 'label' => 'Llama 3.3 70B (Groq)', 'context_k' => 128, 'price_in' => 0.00059, 'price_out' => 0.00079],
        ['provider' => 'together',  'model' => 'meta-llama/Llama-3.3-70B-Instruct-Turbo', 'label' => 'Llama 3.3 70B (Together)', 'context_k' => 128, 'price_in' => 0.00088, 'price_out' => 0.00088],
        ['provider' => 'ollama',    'model' => 'llama3.1',           'label' => 'Ollama Llama 3.1 (local)', 'context_k' => 32, 'price_in' => 0, 'price_out' => 0],
        ['provider' => 'lmstudio',  'model' => 'local-model',         'label' => 'LM Studio (local)',      'context_k' => 32, 'price_in' => 0, 'price_out' => 0],
    ];

    /**
     * Base URL mặc định theo provider (tương thích OpenAI API).
     */
    public const BASE_URLS = [
        'openai'    => 'https://api.openai.com/v1',
        'deepseek'  => 'https://api.deepseek.com/v1',
        'gemini'    => 'https://generativelanguage.googleapis.com/v1beta/openai',
        'groq'      => 'https://api.groq.com/openai/v1',
        'together'  => 'https://api.together.xyz/v1',
        'ollama'    => 'http://127.0.0.1:11434/v1',
        'lmstudio'  => 'http://127.0.0.1:1234/v1',
    ];

    /**
     * Cài sẵn danh sách model (chạy 1 lần khi triển khai).
     */
    public static function seedModels(): int
    {
        $n = 0;
        foreach (self::PRESET_MODELS as $m) {
            $exists = Database::fetchColumn(
                'SELECT id FROM ai_models WHERE provider = ? AND model = ?',
                [$m['provider'], $m['model']]
            );
            if ($exists) {
                continue;
            }
            Database::insert('ai_models', [
                'provider'     => $m['provider'],
                'model'        => $m['model'],
                'label'        => $m['label'],
                'endpoint'     => self::BASE_URLS[$m['provider']] ?? null,
                'api_key_ref'  => $m['provider'] . '_api_key',
                'context_k'    => $m['context_k'],
                'price_in'     => $m['price_in'],
                'price_out'    => $m['price_out'],
                'capabilities' => json_encode(['chat', 'vision', 'tools'], JSON_UNESCAPED_UNICODE),
                'is_active'    => 1,
                'sort_order'   => $n++,
            ]);
        }
        return $n;
    }

    public static function models(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM ai_models';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order, provider, model';
        return Database::fetchAll($sql);
    }

    public static function findModel(int $id): ?array
    {
        return Database::fetch('SELECT * FROM ai_models WHERE id = ?', [$id]);
    }

    public static function agents(): array
    {
        return Database::fetchAll(
            'SELECT a.*, m.label AS model_label, m.provider
             FROM ai_agents a LEFT JOIN ai_models m ON m.id = a.model_id
             ORDER BY a.id DESC'
        );
    }

    /**
     * Lấy API key của 1 provider từ settings.
     */
    public static function apiKey(string $provider): string
    {
        return (string)setting($provider . '_api_key', '');
    }

    /**
     * Gọi 1 model, trả về nội dung text.
     */
    public static function chat(array $model, array $messages, array $opts = []): array
    {
        $provider = (string)$model['provider'];
        $key      = self::apiKey($provider);

        $base = rtrim((string)($model['endpoint'] ?: (self::BASE_URLS[$provider] ?? '')), '/');
        if ($base === '') {
            return ['ok' => false, 'message' => 'Chưa cấu hình endpoint cho provider ' . $provider];
        }
        if ($key === '' && !str_contains($base, '127.0.0.1') && !str_contains($base, 'localhost')) {
            return ['ok' => false, 'message' => 'Chưa cấu hình API key cho ' . $provider . '. Vào Cấu hình để thêm.'];
        }

        $payload = [
            'model'    => $model['model'],
            'messages' => $messages,
            'stream'   => false,
        ];
        if (!empty($opts['temperature'])) {
            $payload['temperature'] = (float)$opts['temperature'];
        }
        if (!empty($opts['max_tokens'])) {
            $payload['max_tokens'] = (int)$opts['max_tokens'];
        }

        $headers = [];
        if ($key !== '') {
            $headers[] = 'Authorization: Bearer ' . $key;
        }

        $raw = HttpClient::postJson($base . '/chat/completions', $payload, $headers, ['timeout' => 90]);
        if ($raw === null) {
            return ['ok' => false, 'message' => 'Không kết nối được tới ' . $provider . '.'];
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            return ['ok' => false, 'message' => 'Phản hồi không hợp lệ từ ' . $provider . '.'];
        }
        if (isset($json['error'])) {
            return ['ok' => false, 'message' => (string)($json['error']['message'] ?? 'Lỗi API')];
        }

        $text = $json['choices'][0]['message']['content'] ?? '';
        $usage = $json['usage'] ?? [];

        return [
            'ok'     => true,
            'text'   => (string)$text,
            'usage'  => $usage,
            'model'  => $model['model'],
            'raw'    => $json,
        ];
    }

    /**
     * Ước tính chi phí 1 lượt gọi.
     */
    public static function estimateCost(array $model, array $usage): float
    {
        $in  = (int)($usage['prompt_tokens'] ?? 0);
        $out = (int)($usage['completion_tokens'] ?? 0);
        return round($in * (float)$model['price_in'] + $out * (float)$model['price_out'], 6);
    }
}
