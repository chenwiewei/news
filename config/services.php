<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'llm' => [
        'default' => env('LLM_DEFAULT_PROVIDER', 'doubao'),

        'doubao' => [
            'api_key' => env('DOUBAO_API_KEY'),
            'endpoint' => env('DOUBAO_API_ENDPOINT', 'https://ark.cn-beijing.volces.com/api/v3'),
            'model' => env('DOUBAO_MODEL', 'doubao-lite-4k'),
        ],

        'baidu' => [
            'api_key' => env('BAIDU_API_KEY'),
            'secret_key' => env('BAIDU_SECRET_KEY'),
            'endpoint' => env('BAIDU_API_ENDPOINT', 'https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop/chat'),
            'model' => env('BAIDU_MODEL', 'ernie_speed'),
        ],

        'aliyun' => [
            'api_key' => env('ALIYUN_API_KEY'),
            'endpoint' => env('ALIYUN_API_ENDPOINT', 'https://dashscope.aliyuncs.com/api/v1'),
            'model' => env('ALIYUN_MODEL', 'qwen-turbo'),
        ],

        'zhipu' => [
            'api_key' => env('ZHIPU_API_KEY'),
            'endpoint' => env('ZHIPU_API_ENDPOINT', 'https://open.bigmodel.cn/api/paas/v4'),
            'model' => env('ZHIPU_MODEL', 'glm-4-flash'),
        ],

        'kimi' => [
            'api_key' => env('KIMI_API_KEY'),
            'endpoint' => env('KIMI_API_ENDPOINT', 'https://api.moonshot.cn/v1'),
            'model' => env('KIMI_MODEL', 'moonshot-v1-8k'),
        ],
    ],

];
