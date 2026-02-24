<?php
/**
 * EL Core AI Client
 * 
 * Shared AI integration layer. All modules use this for AI features
 * instead of implementing their own API calls.
 * 
 * Supports Anthropic (Claude) and OpenAI.
 * Handles API key management, error handling, and usage tracking.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EL_AI_Client {

    private EL_Settings $settings;

    public function __construct( EL_Settings $settings ) {
        $this->settings = $settings;
    }

    /**
     * Send a completion request
     * 
     * @param array $args {
     *     @type string $system     System prompt
     *     @type string $prompt     User message
     *     @type string $model      Model override (optional, uses settings default)
     *     @type int    $max_tokens Token limit (optional, uses settings default)
     * }
     * @return array ['success' => bool, 'content' => string, 'usage' => array, 'error' => string]
     */
    public function complete( array $args ): array {
        $provider = $this->settings->get( 'ai', 'provider', 'anthropic' );
        $api_key  = $this->settings->get( 'ai', 'api_key', '' );

        if ( empty( $api_key ) ) {
            return [
                'success' => false,
                'content' => '',
                'error'   => 'No API key configured. Go to EL Core → Settings to add your API key.',
            ];
        }

        if ( $provider === 'anthropic' ) {
            return $this->call_anthropic( $args, $api_key );
        } elseif ( $provider === 'openai' ) {
            return $this->call_openai( $args, $api_key );
        }

        return [
            'success' => false,
            'content' => '',
            'error'   => "Unknown AI provider: {$provider}",
        ];
    }

    /**
     * Call Anthropic Claude API
     */
    private function call_anthropic( array $args, string $api_key ): array {
        $model      = $args['model'] ?? $this->settings->get( 'ai', 'model', 'claude-sonnet-4-5-20250929' );
        $max_tokens = $args['max_tokens'] ?? $this->settings->get( 'ai', 'max_tokens', 1024 );

        $body = [
            'model'      => $model,
            'max_tokens' => (int) $max_tokens,
            'messages'   => [
                [ 'role' => 'user', 'content' => $args['prompt'] ],
            ],
        ];

        if ( ! empty( $args['system'] ) ) {
            $body['system'] = $args['system'];
        }

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'timeout' => 60,
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
            ],
            'body' => wp_json_encode( $body ),
        ]);

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'content' => '',
                'error'   => $response->get_error_message(),
            ];
        }

        $status = wp_remote_retrieve_response_code( $response );
        $data   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status !== 200 ) {
            return [
                'success' => false,
                'content' => '',
                'error'   => $data['error']['message'] ?? "API error (HTTP {$status})",
            ];
        }

        $content = $data['content'][0]['text'] ?? '';
        $usage   = $data['usage'] ?? [];

        // Log usage for cost tracking
        $this->log_usage( 'anthropic', $model, $usage );

        return [
            'success' => true,
            'content' => $content,
            'usage'   => $usage,
            'error'   => '',
        ];
    }

    /**
     * Call OpenAI API
     */
    private function call_openai( array $args, string $api_key ): array {
        $model      = $args['model'] ?? 'gpt-4o';
        $max_tokens = $args['max_tokens'] ?? $this->settings->get( 'ai', 'max_tokens', 1024 );

        $messages = [];
        if ( ! empty( $args['system'] ) ) {
            $messages[] = [ 'role' => 'system', 'content' => $args['system'] ];
        }
        $messages[] = [ 'role' => 'user', 'content' => $args['prompt'] ];

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 60,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer {$api_key}",
            ],
            'body' => wp_json_encode( [
                'model'      => $model,
                'max_tokens' => (int) $max_tokens,
                'messages'   => $messages,
            ]),
        ]);

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'content' => '',
                'error'   => $response->get_error_message(),
            ];
        }

        $status = wp_remote_retrieve_response_code( $response );
        $data   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status !== 200 ) {
            return [
                'success' => false,
                'content' => '',
                'error'   => $data['error']['message'] ?? "API error (HTTP {$status})",
            ];
        }

        $content = $data['choices'][0]['message']['content'] ?? '';
        $usage   = $data['usage'] ?? [];

        $this->log_usage( 'openai', $model, $usage );

        return [
            'success' => true,
            'content' => $content,
            'usage'   => $usage,
            'error'   => '',
        ];
    }

    /**
     * Log API usage for cost tracking
     */
    private function log_usage( string $provider, string $model, array $usage ): void {
        $log = get_option( 'el_core_ai_usage', [] );

        $today = date( 'Y-m-d' );
        if ( ! isset( $log[ $today ] ) ) {
            $log[ $today ] = [];
        }

        $log[ $today ][] = [
            'provider'      => $provider,
            'model'         => $model,
            'input_tokens'  => $usage['input_tokens'] ?? $usage['prompt_tokens'] ?? 0,
            'output_tokens' => $usage['output_tokens'] ?? $usage['completion_tokens'] ?? 0,
            'timestamp'     => current_time( 'mysql' ),
        ];

        // Keep only last 30 days
        $cutoff = date( 'Y-m-d', strtotime( '-30 days' ) );
        $log = array_filter( $log, fn( $key ) => $key >= $cutoff, ARRAY_FILTER_USE_KEY );

        update_option( 'el_core_ai_usage', $log );
    }

    /**
     * Send a completion request with an image (vision).
     *
     * @param array $args {
     *     @type string $system        System prompt
     *     @type string $prompt        User text message
     *     @type string $image_base64  Base64-encoded image data
     *     @type string $image_mime    MIME type (e.g. 'image/jpeg')
     *     @type string $model         Model override (optional)
     *     @type int    $max_tokens    Token limit (optional)
     * }
     * @return array ['success' => bool, 'content' => string, 'error' => string]
     */
    public function complete_with_image( array $args ): array {
        $provider = $this->settings->get( 'ai', 'provider', 'anthropic' );
        $api_key  = $this->settings->get( 'ai', 'api_key', '' );

        if ( empty( $api_key ) ) {
            return [
                'success' => false,
                'content' => '',
                'error'   => 'No API key configured. Go to EL Core → Settings to add your API key.',
            ];
        }

        if ( $provider === 'anthropic' ) {
            return $this->call_anthropic_vision( $args, $api_key );
        }

        return [
            'success' => false,
            'content' => '',
            'error'   => "Vision analysis is only supported with Anthropic (Claude). Current provider: {$provider}",
        ];
    }

    /**
     * Call Anthropic Claude vision API with a base64 image.
     */
    private function call_anthropic_vision( array $args, string $api_key ): array {
        $model      = $args['model']      ?? 'claude-opus-4-5';
        $max_tokens = $args['max_tokens'] ?? max( 1024, (int) $this->settings->get( 'ai', 'max_tokens', 1024 ) );
        $mime       = $args['image_mime'] ?? 'image/jpeg';

        $user_content = [
            [
                'type'   => 'image',
                'source' => [
                    'type'       => 'base64',
                    'media_type' => $mime,
                    'data'       => $args['image_base64'],
                ],
            ],
        ];

        if ( ! empty( $args['prompt'] ) ) {
            $user_content[] = [ 'type' => 'text', 'text' => $args['prompt'] ];
        }

        $body = [
            'model'      => $model,
            'max_tokens' => (int) $max_tokens,
            'messages'   => [
                [ 'role' => 'user', 'content' => $user_content ],
            ],
        ];

        if ( ! empty( $args['system'] ) ) {
            $body['system'] = $args['system'];
        }

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'timeout' => 90,
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
            ],
            'body' => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'content' => '',
                'error'   => $response->get_error_message(),
            ];
        }

        $status = wp_remote_retrieve_response_code( $response );
        $data   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $status !== 200 ) {
            return [
                'success' => false,
                'content' => '',
                'error'   => $data['error']['message'] ?? "API error (HTTP {$status})",
            ];
        }

        $content = $data['content'][0]['text'] ?? '';
        $usage   = $data['usage'] ?? [];

        $this->log_usage( 'anthropic', $model, $usage );

        return [
            'success' => true,
            'content' => $content,
            'usage'   => $usage,
            'error'   => '',
        ];
    }

    /**
     * Check if AI is configured and ready
     */
    public function is_configured(): bool {
        return ! empty( $this->settings->get( 'ai', 'api_key', '' ) );
    }
}
