<?php
class AIService {
    private $apiKey;
    private $baseUrl = 'https://api.openai.com/v1';
    
    public function __construct() {
        $this->apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?? 'default_openai_key';
    }
    
    private function makeRequest($endpoint, $data) {
        $url = $this->baseUrl . $endpoint;
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            error_log("AI API curl error: " . curl_error($ch));
            curl_close($ch);
            return ['error' => 'Network error occurred'];
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("AI API HTTP error: " . $httpCode . " - " . $response);
            return ['error' => 'AI service temporarily unavailable'];
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("AI API JSON decode error: " . json_last_error_msg());
            return ['error' => 'Invalid response from AI service'];
        }
        
        return $decoded;
    }
    
    public function analyzeRequirement($requirement) {
        $prompt = "Analyze the following software requirement and provide insights about clarity, completeness, testability, and potential risks. Also suggest improvements if any:\n\n" . $requirement;
        
        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 500,
            'temperature' => 0.7
        ];
        
        $response = $this->makeRequest('/chat/completions', $data);
        
        if (isset($response['error'])) {
            return $response;
        }
        
        return [
            'analysis' => $response['choices'][0]['message']['content'] ?? 'No analysis available'
        ];
    }
    
    public function generateTestCases($requirement) {
        $prompt = "Based on the following requirement, generate comprehensive test cases including positive, negative, and edge cases. Format as a structured list:\n\n" . $requirement;
        
        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 800,
            'temperature' => 0.7
        ];
        
        $response = $this->makeRequest('/chat/completions', $data);
        
        if (isset($response['error'])) {
            return $response;
        }
        
        return [
            'test_cases' => $response['choices'][0]['message']['content'] ?? 'No test cases generated'
        ];
    }
    
    public function analyzeTestRun($testResults) {
        $prompt = "Analyze the following test execution results and provide insights about patterns, potential issues, and recommendations for improvement:\n\n" . $testResults;
        
        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 600,
            'temperature' => 0.7
        ];
        
        $response = $this->makeRequest('/chat/completions', $data);
        
        if (isset($response['error'])) {
            return $response;
        }
        
        return [
            'insights' => $response['choices'][0]['message']['content'] ?? 'No insights available'
        ];
    }
    
    public function categorizeBug($bugDescription) {
        $prompt = "Categorize the following bug and suggest priority level, severity, and potential root cause. Also suggest steps for reproduction if not clear:\n\n" . $bugDescription;
        
        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 400,
            'temperature' => 0.7
        ];
        
        $response = $this->makeRequest('/chat/completions', $data);
        
        if (isset($response['error'])) {
            return $response;
        }
        
        return [
            'categorization' => $response['choices'][0]['message']['content'] ?? 'No categorization available'
        ];
    }
    
    public function generateDashboardInsights($data) {
        $prompt = "Based on the following test management metrics, provide actionable insights and recommendations:\n\n" . json_encode($data);
        
        $requestData = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 500,
            'temperature' => 0.7
        ];
        
        $response = $this->makeRequest('/chat/completions', $requestData);
        
        if (isset($response['error'])) {
            return $response;
        }
        
        return [
            'insights' => $response['choices'][0]['message']['content'] ?? 'No insights available'
        ];
    }
}

// Global AI service instance
function getAI() {
    static $instance = null;
    if ($instance === null) {
        $instance = new AIService();
    }
    return $instance;
}
?>
