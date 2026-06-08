<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class OpenAIService
{
    protected ?string $provider = 'openai';
    protected ?string $apiKey;
    protected string $baseUrl = 'https://api.openai.com/v1';
    protected string $model = 'gpt-4o-mini';
    protected string $embeddingModel = 'text-embedding-3-small';

    /**
     * Dictionary of skills used by the Mock AI parser.
     * You can easily add more skills to this array here in the future.
     */
    public static array $mockSkills = [
        // Web & Backend Software
        'Laravel', 'PHP', 'MySQL', 'React', 'Vue.js', 'Vue', 'AWS', 'Docker', 'Python', 'Javascript', 'REST API', 'Git', 'HTML', 'CSS', 'Tailwind', 'Typescript', 'Node', 'Java', 'Spring', 'Go', 'Ruby', 'Rails', 'C#', '.NET', 'ASP.NET', 'PostgreSQL', 'MongoDB', 'Redis', 'GraphQL',
        
        // Systems, DevOps & Cloud
        'Kubernetes', 'Terraform', 'CI/CD', 'Jenkins', 'Ansible', 'Linux', 'Bash', 'Azure', 'GCP', 'Nginx', 'Apache', 'Prometheus', 'Grafana',
        
        // Data Science & AI/ML
        'Machine Learning', 'Data Science', 'Deep Learning', 'PyTorch', 'TensorFlow', 'Pandas', 'NumPy', 'Scikit-learn', 'NLP', 'Computer Vision', 'R', 'SQL', 'Hadoop', 'Spark',
        
        // Electronics, Hardware & Electrical
        'Arduino', 'ESP32', 'STM32', 'Raspberry Pi', 'PCB Design', 'PCB', 'IoT', 'Microcontrollers', 'Embedded Systems', 'Circuits', 'Schematics', 'Circuit Theory', 'Digital Systems', 'Analog Systems', 'Firmware', 'VHDL', 'Verilog', 'FPGA', 'Altium', 'Cadence', 'Oscilloscope', 'MATLAB', 'LabVIEW', 'PLC', 'SCADA', 'Power Electronics', 'Control Systems', 'RF Design',
        
        // Mechanical, CAD & Engineering
        'SolidWorks', 'AutoCAD', 'CAD Design', 'Finite Element Analysis', 'FEA', 'Thermodynamics', 'Mechanical Design', 'CNC', 'Robotics', 'Fluid Dynamics',
        
        // Business, Project Management & Quality
        'Agile', 'Scrum', 'Project Management', 'Product Management', 'Jira', 'Confluence', 'Communication', 'QA Testing', 'Selenium', 'Manual Testing', 'SDLC'
    ];

    public function __construct()
    {
        $openaiKey = config('services.openai.key');
        if ($openaiKey === 'your_openai_api_key_here' || empty($openaiKey)) {
            $openaiKey = null;
        }

        $grokKey = config('services.grok.key');
        if ($grokKey === 'your_grok_api_key_here' || empty($grokKey)) {
            $grokKey = null;
        }

        $configuredProvider = config('services.llm_provider', 'openai');

        if ($configuredProvider === 'grok' || ($grokKey && !$openaiKey)) {
            $this->provider = 'grok';
            $this->apiKey = $grokKey;
            $this->baseUrl = config('services.grok.base_url') ?: 'https://api.x.ai/v1';
            $this->model = config('services.grok.model') ?: 'grok-beta';
        } else {
            $this->provider = 'openai';
            $this->apiKey = $openaiKey;
            $this->baseUrl = 'https://api.openai.com/v1';
            $this->model = 'gpt-4o-mini';
        }

        if ($this->isMockMode()) {
            Log::info("OpenAIService: INITIALIZED IN MOCK MODE (No active API key found for provider '{$this->provider}'). Mock heuristics will be used.");
        } else {
            Log::info("OpenAIService: INITIALIZED IN LIVE MODE (using provider '{$this->provider}'). Base URL: {$this->baseUrl}, Model: {$this->model}.");
        }
    }

    /**
     * Determine the active provider.
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Determine if we are running in Mock mode.
     */
    public function isMockMode(): bool
    {
        return is_null($this->apiKey);
    }

    /**
     * Generate vector embedding for a given text.
     */
    public function generateEmbedding(string $text): array
    {
        $charLength = strlen($text);
        Log::info("OpenAIService::generateEmbedding: Starting embedding generation for text (length: {$charLength} chars)");
        
        $startTime = microtime(true);

        $openaiKey = config('services.openai.key');
        if (empty($openaiKey) || $openaiKey === 'your_openai_api_key_here') {
            $openaiKey = null;
        }

        if (is_null($openaiKey)) {
            Log::debug("OpenAIService::generateEmbedding: Running in MOCK MODE. Generating deterministic mock embedding vector...");
            $words = str_word_count(strtolower($text), 1);
            $vector = array_fill(0, 1536, 0.0);
            
            $seed = count($words);
            foreach ($words as $word) {
                $seed += crc32($word);
            }
            srand($seed);

            for ($i = 0; $i < 1536; $i++) {
                $vector[$i] = (rand(0, 1000) / 1000.0) - 0.5;
            }
            
            $norm = sqrt(array_sum(array_map(fn($val) => $val * $val, $vector)));
            if ($norm > 0) {
                $vector = array_map(fn($val) => $val / $norm, $vector);
            }
            
            $duration = round(microtime(true) - $startTime, 3);
            $vectorPreview = implode(', ', array_slice($vector, 0, 5));
            Log::info("OpenAIService::generateEmbedding: Mock embedding vector generated successfully in {$duration}s. Dimensions: 1536. Preview: [{$vectorPreview}, ...]");
            return $vector;
        }

        try {
            $truncatedText = mb_substr($text, 0, 30000);
            Log::debug("OpenAIService::generateEmbedding: Sending POST request to OpenAI embeddings endpoint (model: {$this->embeddingModel}). Truncated size: " . strlen($truncatedText) . " chars.");
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $openaiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/embeddings', [
                'model' => $this->embeddingModel,
                'input' => $truncatedText,
            ]);

            $duration = round(microtime(true) - $startTime, 3);

            if ($response->successful()) {
                $this->clearQuotaExceeded();
                $embedding = $response->json('data.0.embedding');
                $vectorPreview = implode(', ', array_slice($embedding, 0, 5));
                Log::info("OpenAIService::generateEmbedding: Embeddings successfully received from OpenAI in {$duration}s. Dimensions: " . count($embedding) . ". Preview: [{$vectorPreview}, ...]");
                return $embedding;
            }

            Log::error('OpenAIService::generateEmbedding: OpenAI Embedding Request Failed', [
                'status' => $response->status(), 
                'body' => $response->body(),
                'duration' => "{$duration}s"
            ]);
            $this->handleQuotaExceeded($response->body());
            throw new Exception("OpenAI API returned status " . $response->status());
        } catch (Exception $e) {
            Log::error("OpenAIService::generateEmbedding: Embedding exception: " . $e->getMessage() . " - Falling back to default mock vector.");
            $this->handleQuotaExceeded($e->getMessage());
            return array_fill(0, 1536, 0.01);
        }
    }

    /**
     * Job Analysis Agent: Extract requirements from job description.
     */
    public function analyzeJob(string $description): array
    {
        Log::info("OpenAIService::analyzeJob: Initiating job description analysis (description length: " . strlen($description) . " chars)");
        
        $startTime = microtime(true);

        if ($this->isMockMode()) {
            throw new Exception("API key is not configured for provider {$this->provider}. Mock mode is disabled.");
        }

        $prompt = "Analyze this job description and extract requirements. " .
                  "You MUST return a JSON object with the exact keys: " .
                  "'title' (string), 'required_skills' (array of strings), 'preferred_skills' (array of strings), " .
                  "'experience_years' (integer), 'certifications' (array of strings), and 'analysis_summary' (string). " .
                  "Do not wrap the output in markdown code blocks or return anything other than JSON.\n\n" .
                  "Job Description:\n" . $description;

        try {
            Log::debug("OpenAIService::analyzeJob: Sending POST request to {$this->provider} Chat Completion (model: {$this->model}) for job analysis...");
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert technical recruiter.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object']
            ]);

            $duration = round(microtime(true) - $startTime, 3);

            if ($response->successful()) {
                $this->clearQuotaExceeded();
                $rawContent = $response->json('choices.0.message.content');
                Log::debug("OpenAIService::analyzeJob: {$this->provider} API response received. Raw content: {$rawContent}");
                
                $data = json_decode($rawContent, true);
                if (is_array($data)) {
                    Log::info("OpenAIService::analyzeJob: Job analysis completed successfully by {$this->provider} in {$duration}s. Title: '" . ($data['title'] ?? 'N/A') . "', Required skills count: " . count($data['required_skills'] ?? []));
                    return $data;
                }
            }
            $this->handleQuotaExceeded($response->body());
            throw new Exception("Failed to analyze job: " . $response->body());
        } catch (Exception $e) {
            Log::error("OpenAIService::analyzeJob: Job analysis exception: " . $e->getMessage());
            $this->handleQuotaExceeded($e->getMessage());
            throw $e;
        }
    }

    /**
     * Resume Parsing Agent: Extract candidate data.
     */
    public function parseResume(string $text): array
    {
        Log::info("OpenAIService::parseResume: Initiating resume parsing (text length: " . strlen($text) . " chars)");
        
        $startTime = microtime(true);

        if ($this->isMockMode()) {
            throw new Exception("API key is not configured for provider {$this->provider}. Mock mode is disabled.");
        }

        $prompt = "Parse the following resume text and extract candidate details. " .
                  "You MUST return a JSON object with the exact keys: " .
                  "'name' (string), 'email' (string), 'phone' (string), 'skills' (array of strings), " .
                  "'experience_years' (integer), 'education' (array of strings), 'summary' (string), " .
                  "'expected_salary' (string), 'notice_period' (string), 'current_company' (string), " .
                  "'remote_preference' (string), 'visa_status' (string), and " .
                  "'work_experience' (array of objects, where each object has keys: 'company' (string), 'role' (string), 'duration' (string), and 'description' (string)). " .
                  "If any field like expected_salary, notice_period, current_company, remote_preference, or visa_status is not found in the resume, set its value to 'Not specified'. " .
                  "Do not wrap the output in markdown code blocks or return anything other than JSON.\n\n" .
                  "Resume:\n" . $text;

        try {
            Log::debug("OpenAIService::parseResume: Sending POST request to {$this->provider} Chat Completion (model: {$this->model}) for resume parsing...");
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert resume parsing tool.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object']
            ]);

            $duration = round(microtime(true) - $startTime, 3);

            if ($response->successful()) {
                $this->clearQuotaExceeded();
                $rawContent = $response->json('choices.0.message.content');
                Log::debug("OpenAIService::parseResume: {$this->provider} API response received. Raw content: {$rawContent}");

                $data = json_decode($rawContent, true);
                if (is_array($data)) {
                    Log::info("OpenAIService::parseResume: Resume parsing completed successfully by {$this->provider} in {$duration}s. Name: '" . ($data['name'] ?? 'N/A') . "', Email: '" . ($data['email'] ?? 'N/A') . "'");
                    return $data;
                }
            }
            $this->handleQuotaExceeded($response->body());
            throw new Exception("Failed to parse resume: " . $response->body());
        } catch (Exception $e) {
            Log::error("OpenAIService::parseResume: Resume parsing exception: " . $e->getMessage());
            $this->handleQuotaExceeded($e->getMessage());
            throw $e;
        }
    }

    /**
     * Matching & Analysis Agent: Score candidate & generate summaries, strengths, concerns, and interview questions.
     */
    public function matchAndAnalyze(array $jobAnalysis, array $resumeData): array
    {
        Log::info("OpenAIService::matchAndAnalyze: Initiating profile matching against job requirements.");
        Log::debug("OpenAIService::matchAndAnalyze: Job Title: '" . ($jobAnalysis['title'] ?? 'N/A') . "', Required skills count: " . count($jobAnalysis['required_skills'] ?? []) . ", Candidate Name: '" . ($resumeData['name'] ?? 'N/A') . "', Candidate skills count: " . count($resumeData['skills'] ?? []));
        
        $startTime = microtime(true);

        if ($this->isMockMode()) {
            throw new Exception("API key is not configured for provider {$this->provider}. Mock mode is disabled.");
        }

        $prompt = "Compare a candidate's resume data against the job requirements and calculate match scores.\n\n" .
                  "Job Requirements:\n" . json_encode($jobAnalysis, JSON_PRETTY_PRINT) . "\n\n" .
                  "Candidate Resume Data:\n" . json_encode($resumeData, JSON_PRETTY_PRINT) . "\n\n" .
                  "Calculate the following scores on a scale of 0 to 100:\n" .
                  "1. skill_match: Assess how candidate's skills cover required and preferred skills.\n" .
                  "2. experience_match: Compare experience years to the job target.\n" .
                  "3. education_match: Compare degree level to typical requirements.\n\n" .
                  "Generate a recruitment summary (summary), strengths (array of strings), concerns (array of strings), " .
                  "and a recommendation (string e.g. 'Strong Hire', 'Good', 'Moderate', 'Low Match').\n" .
                  "Also generate 3 tailored interview questions targeting gaps or skills.\n\n" .
                  "You MUST return a JSON object with the exact keys:\n" .
                  "{\n" .
                  "  \"skill_match\": float,\n" .
                  "  \"experience_match\": float,\n" .
                  "  \"education_match\": float,\n" .
                  "  \"recommendation\": \"string\",\n" .
                  "  \"summary\": \"string\",\n" .
                  "  \"strengths\": [\"string\"],\n" .
                  "  \"concerns\": [\"string\"],\n" .
                  "  \"interview_questions\": [{\"topic\": \"string\", \"question\": \"string\", \"expected_answer_keys\": \"string\"}]\n" .
                  "}\n" .
                  "Do not wrap output in markdown code blocks.";

        try {
            Log::debug("OpenAIService::matchAndAnalyze: Sending POST request to {$this->provider} Chat Completion (model: {$this->model}) for candidate matching evaluation...");
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a candidate matching agent.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object']
            ]);

            $duration = round(microtime(true) - $startTime, 3);

            if ($response->successful()) {
                $this->clearQuotaExceeded();
                $rawContent = $response->json('choices.0.message.content');
                Log::debug("OpenAIService::matchAndAnalyze: {$this->provider} API response received. Raw content: {$rawContent}");

                $data = json_decode($rawContent, true);
                if (is_array($data)) {
                    // Calculate total score using formula: Score = 0.6*Skill + 0.3*Exp + 0.1*Edu
                    $data['total_score'] = 0.6 * ($data['skill_match'] ?? 0) + 0.3 * ($data['experience_match'] ?? 0) + 0.1 * ($data['education_match'] ?? 0);
                    Log::info("OpenAIService::matchAndAnalyze: Candidate matching completed successfully by {$this->provider} in {$duration}s. Total Score: {$data['total_score']}%, Recommendation: '{$data['recommendation']}'");
                    return $data;
                }
            }
            $this->handleQuotaExceeded($response->body());
            throw new Exception("Failed to match candidate: " . $response->body());
        } catch (Exception $e) {
            Log::error("OpenAIService::matchAndAnalyze: Candidate matching exception: " . $e->getMessage());
            $this->handleQuotaExceeded($e->getMessage());
            throw $e;
        }
    }

    /* =========================================================================
     * MOCK AI FALLBACK METHODS
     * ========================================================================= */

    protected function mockJobAnalysis(string $description): array
    {
        Log::debug("OpenAIService::mockJobAnalysis: Running mock heuristics on description.");
        $lines = explode("\n", $description);
        $title = 'Software Engineer';
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (strlen($trimmed) > 5 && strlen($trimmed) < 50 && !str_contains($trimmed, ':') && !str_contains(strtolower($trimmed), 'description')) {
                $title = $trimmed;
                Log::debug("OpenAIService::mockJobAnalysis: Inferred Job Title: '{$title}'");
                break;
            }
        }

        // Use central static mock skills dictionary
        $allSkills = self::$mockSkills;
        
        $required = [];
        $preferred = [];

        foreach ($allSkills as $skill) {
            if (preg_match('/\b' . preg_quote($skill, '/') . '\b/i', $description)) {
                if (count($required) < 4) {
                    $required[] = $skill;
                } else {
                    $preferred[] = $skill;
                }
            }
        }

        Log::debug("OpenAIService::mockJobAnalysis: Keyword match results. Required: [" . implode(', ', $required) . "], Preferred: [" . implode(', ', $preferred) . "]");

        // Context-aware defaults if no specific keywords matched
        if (empty($required)) {
            $descLower = strtolower($description);
            if (str_contains($descLower, 'electronics') || str_contains($descLower, 'embedded') || str_contains($descLower, 'hardware') || str_contains($descLower, 'circuit') || str_contains($descLower, 'microcontroller') || str_contains($descLower, 'iot') || str_contains($descLower, 'electrical')) {
                $required = ['Microcontrollers', 'Circuits', 'Embedded Systems'];
                $preferred = ['Arduino', 'PCB Design', 'ESP32'];
                $certifications = ['Certified Embedded Systems Specialist', 'IoT Developer Certificate'];
            } elseif (str_contains($descLower, 'solidworks') || str_contains($descLower, 'cad') || str_contains($descLower, 'mechanical') || str_contains($descLower, 'thermo') || str_contains($descLower, 'fluid')) {
                $required = ['SolidWorks', 'CAD Design', 'Mechanical Design'];
                $preferred = ['AutoCAD', 'Robotics'];
                $certifications = ['Certified SolidWorks Associate (CSWA)', 'Autodesk Certified Professional'];
            } elseif (str_contains($descLower, 'data') || str_contains($descLower, 'machine learning') || str_contains($descLower, 'deep learning') || str_contains($descLower, 'ai') || str_contains($descLower, 'pytorch') || str_contains($descLower, 'tensorflow')) {
                $required = ['Python', 'Machine Learning', 'Data Science'];
                $preferred = ['PyTorch', 'TensorFlow', 'SQL'];
                $certifications = ['Google Professional Data Engineer', 'AWS Certified Machine Learning'];
            } elseif (str_contains($descLower, 'devops') || str_contains($descLower, 'kubernetes') || str_contains($descLower, 'cloud') || str_contains($descLower, 'terraform') || str_contains($descLower, 'aws')) {
                $required = ['AWS', 'Docker', 'Kubernetes'];
                $preferred = ['Terraform', 'CI/CD', 'Linux'];
                $certifications = ['AWS Certified Solutions Architect', 'Certified Kubernetes Administrator (CKA)'];
            } elseif (str_contains($descLower, 'project') || str_contains($descLower, 'product') || str_contains($descLower, 'manager') || str_contains($descLower, 'agile') || str_contains($descLower, 'scrum')) {
                $required = ['Project Management', 'Agile', 'Scrum'];
                $preferred = ['Communication', 'Jira'];
                $certifications = ['Project Management Professional (PMP)', 'Certified ScrumMaster (CSM)'];
            } else {
                $required = ['PHP', 'Laravel', 'MySQL'];
                $preferred = ['AWS', 'Vue.js'];
                $certifications = ['AWS Certified Developer', 'Laravel Certification'];
            }
            Log::debug("OpenAIService::mockJobAnalysis: No key skills matched description. Applied context-aware default skills. Required: [" . implode(', ', $required) . "].");
        } else {
            // Contextual certifications based on matching skills
            $certifications = [];
            $hasElectronics = false;
            foreach ($required as $s) {
                if (in_array($s, ['Arduino', 'ESP32', 'STM32', 'Raspberry Pi', 'PCB Design', 'PCB', 'IoT', 'Microcontrollers', 'Embedded Systems', 'Circuits', 'Schematics'])) {
                    $hasElectronics = true;
                    break;
                }
            }
            
            if ($hasElectronics) {
                $certifications = ['Certified Embedded Systems Engineer', 'IoT Developer Certificate'];
            } else {
                $certifications = ['AWS Certified Developer', 'Laravel Certification'];
            }
        }

        // Extract experience years
        $experience = 0;
        if (preg_match('/([0-9]+)\+?\s*years?/i', $description, $matches)) {
            $experience = (int)$matches[1];
            Log::debug("OpenAIService::mockJobAnalysis: Inferred required experience years: {$experience}");
        }

        $analysis = [
            'title' => $title,
            'required_skills' => $required,
            'preferred_skills' => $preferred,
            'experience_years' => $experience,
            'certifications' => $certifications,
            'analysis_summary' => 'Analyzed role for a ' . $title . ' targeting candidates with skillsets in ' . implode(', ', $required) . '.',
        ];
        
        Log::debug("OpenAIService::mockJobAnalysis: Final mock payload generated: " . json_encode($analysis));
        return $analysis;
    }

    protected function mockResumeParsing(string $text): array
    {
        Log::debug("OpenAIService::mockResumeParsing: Running mock heuristics on resume text.");
        // Try regex to pull out details
        $email = null;
        $phone = null;
        $name = null;

        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches)) {
            $email = $matches[0];
            Log::debug("OpenAIService::mockResumeParsing: Regex extracted email: '{$email}'");
        } else {
            $email = 'john.doe@example.com';
        }

        if (preg_match('/(?:\+?[0-9\s\-()]{7,})/', $text, $matches)) {
            $phone = trim($matches[0]);
            Log::debug("OpenAIService::mockResumeParsing: Regex extracted phone: '{$phone}'");
        } else {
            $phone = '+1 (555) 123-4567';
        }

        // Extract name
        $lines = array_filter(array_map('trim', explode("\n", $text)));
        if (!empty($lines)) {
            foreach ($lines as $line) {
                if (strlen($line) > 3 && strlen($line) < 25 && !str_contains($line, '@') && !str_contains($line, 'http')) {
                    $name = $line;
                    Log::debug("OpenAIService::mockResumeParsing: Inferred candidate name from text lines: '{$name}'");
                    break;
                }
            }
        }
        if (!$name) {
            $name = 'Jane Doe';
        }

        $lowerName = strtolower($name);
        $lowerText = strtolower($text);

        // Find candidate skills in text
        $allSkills = self::$mockSkills;
        
        $skills = [];
        foreach ($allSkills as $skill) {
            if (preg_match('/\b' . preg_quote($skill, '/') . '\b/i', $text)) {
                $skills[] = $skill;
            }
        }

        Log::debug("OpenAIService::mockResumeParsing: Matched vocabulary skills: [" . implode(', ', $skills) . "]");
        
        if (empty($skills)) {
            $textLower = strtolower($text);
            if (str_contains($textLower, 'electronics') || str_contains($textLower, 'embedded') || str_contains($textLower, 'hardware') || str_contains($textLower, 'circuit') || str_contains($textLower, 'microcontroller') || str_contains($textLower, 'iot') || str_contains($textLower, 'electrical')) {
                $skills = ['Microcontrollers', 'Circuits', 'IoT', 'Embedded Systems'];
            } elseif (str_contains($textLower, 'solidworks') || str_contains($textLower, 'cad') || str_contains($textLower, 'mechanical') || str_contains($textLower, 'design')) {
                $skills = ['SolidWorks', 'CAD Design', 'Mechanical Design', 'Robotics'];
            } elseif (str_contains($textLower, 'data') || str_contains($textLower, 'machine learning') || str_contains($textLower, 'deep learning') || str_contains($textLower, 'ai') || str_contains($textLower, 'python')) {
                $skills = ['Python', 'Data Science', 'Machine Learning', 'SQL'];
            } elseif (str_contains($textLower, 'devops') || str_contains($textLower, 'kubernetes') || str_contains($textLower, 'cloud') || str_contains($textLower, 'terraform')) {
                $skills = ['AWS', 'Docker', 'Kubernetes', 'Linux'];
            } elseif (str_contains($textLower, 'project') || str_contains($textLower, 'product') || str_contains($textLower, 'manager') || str_contains($textLower, 'agile') || str_contains($textLower, 'scrum')) {
                $skills = ['Project Management', 'Agile', 'Scrum', 'Communication'];
            } else {
                $skills = ['PHP', 'Laravel', 'REST API', 'Git'];
            }
            Log::debug("OpenAIService::mockResumeParsing: No vocabulary skills matched. Contextual fallback applied: [" . implode(', ', $skills) . "]");
        }

        // Experience
        $experience = 3;
        if (preg_match('/([0-9]+)\+?\s*years?\s*experience/i', $text, $matches)) {
            $experience = (int)$matches[1];
            Log::debug("OpenAIService::mockResumeParsing: Inferred experience years from text: {$experience}");
        }

        $expectedSalary = 'Not specified';
        $noticePeriod = 'Not specified';
        $currentCompany = 'Not specified';
        $remotePreference = 'Not specified';
        $visaStatus = 'Not specified';

        $workExperience = [];
        $education = ['B.S. in Engineering, State University'];

        $parsedData = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'skills' => $skills,
            'experience_years' => $experience,
            'education' => $education,
            'summary' => 'Accomplished engineer skilled in ' . implode(', ', $skills) . ' with ' . $experience . ' years of hands-on experience.',
            'expected_salary' => $expectedSalary,
            'notice_period' => $noticePeriod,
            'current_company' => $currentCompany,
            'remote_preference' => $remotePreference,
            'visa_status' => $visaStatus,
            'work_experience' => $workExperience,
        ];

        Log::debug("OpenAIService::mockResumeParsing: Final mock parsed payload: " . json_encode($parsedData));
        return $parsedData;
    }

    protected function mockMatching(array $jobAnalysis, array $resumeData): array
    {
        Log::debug("OpenAIService::mockMatching: Running mock matching heuristics.");
        $reqSkills = $jobAnalysis['required_skills'] ?? [];
        $prefSkills = $jobAnalysis['preferred_skills'] ?? [];
        $candSkills = $resumeData['skills'] ?? [];

        // Calculate skill match
        $matched = array_intersect(array_map('strtolower', $reqSkills), array_map('strtolower', $candSkills));
        $skillScore = count($reqSkills) > 0 ? (count($matched) / count($reqSkills)) * 100 : 80;
        Log::debug("OpenAIService::mockMatching: Skill match count: " . count($matched) . " out of " . count($reqSkills) . ". Initial skill score: {$skillScore}%");
        
        // Add extra credit for preferred skills
        if (!empty($prefSkills)) {
            $matchedPref = array_intersect(array_map('strtolower', $prefSkills), array_map('strtolower', $candSkills));
            $extraCredit = count($prefSkills) > 0 ? (count($matchedPref) / count($prefSkills)) * 10 : 0;
            $skillScore += $extraCredit;
            Log::debug("OpenAIService::mockMatching: Preferred skill match count: " . count($matchedPref) . " out of " . count($prefSkills) . ". Extra credit added: +{$extraCredit}%. Skill score after extra credit: {$skillScore}%");
        }
        $skillScore = min(100, $skillScore);

        // Experience match
        $reqExp = $jobAnalysis['experience_years'] ?? 0;
        $candExp = $resumeData['experience_years'] ?? 0;

        if ($candExp >= $reqExp) {
            $expScore = 100;
        } else if ($candExp > 0) {
            $expScore = ($candExp / $reqExp) * 100;
        } else {
            $expScore = 40;
        }
        Log::debug("OpenAIService::mockMatching: Experience check - Required: {$reqExp}, Candidate: {$candExp}. Experience Score: {$expScore}%");

        // Education match
        $eduScore = 85.0;

        $totalScore = 0.6 * $skillScore + 0.3 * $expScore + 0.1 * $eduScore;
        Log::debug("OpenAIService::mockMatching: Formula scores: [Skills (60%): {$skillScore}%, Experience (30%): {$expScore}%, Education (10%): {$eduScore}%] -> Total Score: {$totalScore}%");

        if ($totalScore >= 85) {
            $recommendation = 'Strong Hire';
        } else if ($totalScore >= 70) {
            $recommendation = 'Good';
        } else if ($totalScore >= 50) {
            $recommendation = 'Moderate';
        } else {
            $recommendation = 'Low Match';
        }
        Log::debug("OpenAIService::mockMatching: Grade recommendation: '{$recommendation}'");

        $strengths = [];
        $concerns = [];

        if ($candExp >= $reqExp) {
            $strengths[] = "Meets or exceeds experience requirements ($candExp years).";
        } else {
            $concerns[] = "Lacks experience requirements (has $candExp, requires $reqExp years).";
        }

        $missingReq = array_diff(array_map('strtolower', $reqSkills), array_map('strtolower', $candSkills));
        if (empty($missingReq)) {
            $strengths[] = "Has all key required skills: " . implode(', ', $reqSkills);
        } else {
            $concerns[] = "Missing some required skills: " . implode(', ', array_map('ucfirst', $missingReq));
        }

        if (count($matched) > 0) {
            $strengths[] = "Strong fit with core technologies like " . implode(', ', array_slice($matched, 0, 3));
        }

        $questions = [];
        foreach ($reqSkills as $skill) {
            if (in_array(strtolower($skill), array_map('strtolower', $candSkills))) {
                $questions[] = [
                    'topic' => $skill,
                    'question' => "In your resume, you listed experience with $skill. Can you talk about a complex project where you designed the architecture using $skill?",
                    'expected_answer_keys' => "Understanding of design patterns, performance scaling, and lifecycle management related to $skill."
                ];
            } else {
                $questions[] = [
                    'topic' => $skill,
                    'question' => "The job description mentions $skill. How would you quickly adapt to this stack if hired?",
                    'expected_answer_keys' => "Transferrable experience, quick learning methodologies, and concrete examples of fast onboarding."
                ];
            }
            if (count($questions) >= 3) break;
        }

        $matchResult = [
            'skill_match' => $skillScore,
            'experience_match' => $expScore,
            'education_match' => $eduScore,
            'total_score' => $totalScore,
            'recommendation' => $recommendation,
            'summary' => "The candidate displays a " . strtolower($recommendation) . " fit for the " . ($jobAnalysis['title'] ?? 'role') . ". They possess " . $candExp . " years of professional experience and match key skills like " . implode(', ', array_slice($candSkills, 0, 3)) . ".",
            'strengths' => $strengths,
            'concerns' => $concerns,
            'interview_questions' => $questions
        ];

        Log::debug("OpenAIService::mockMatching: Mock evaluation details generated. Questions generated count: " . count($questions));
        return $matchResult;
    }

    /**
     * Interview Evaluation Agent: Generate scores and recommendations from interview notes.
     */
    public function evaluateInterviewNotes(string $jobTitle, string $candidateName, string $notes): array
    {
        Log::info("OpenAIService::evaluateInterviewNotes: Analyzing notes for candidate: {$candidateName}, Job: {$jobTitle}");

        $startTime = microtime(true);

        if ($this->isMockMode()) {
            throw new Exception("API key is not configured for provider {$this->provider}. Mock mode is disabled.");
        }

        $prompt = "You are an expert technical interviewer evaluating notes from a recent candidate interview.\n\n" .
                  "Job Title: {$jobTitle}\n" .
                  "Candidate: {$candidateName}\n" .
                  "Recruiter's Interview Notes:\n\"{$notes}\"\n\n" .
                  "Please analyze the notes and score the candidate (on a scale of 0 to 100) on the following areas:\n" .
                  "1. technical_score: Technical proficiency, problem-solving, and domain knowledge mentioned.\n" .
                  "2. communication_score: Clarity, articulation, and responsiveness.\n" .
                  "3. leadership_score: Collaborative skills, ownership, and cultural alignment.\n\n" .
                  "Also provide a recommendation (one of: 'Strong Hire', 'Hire', 'Maybe', 'No Hire') and an evaluation summary.\n\n" .
                  "You MUST return a JSON object with the exact keys:\n" .
                  "{\n" .
                  "  \"technical_score\": float,\n" .
                  "  \"communication_score\": float,\n" .
                  "  \"leadership_score\": float,\n" .
                  "  \"recommendation\": \"string\",\n" .
                  "  \"summary\": \"string\"\n" .
                  "}\n" .
                  "Do not wrap output in markdown code blocks.";

        try {
            Log::debug("OpenAIService::evaluateInterviewNotes: Sending POST request to {$this->provider} Chat Completion (model: {$this->model})...");
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert technical interviewer.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object']
            ]);

            $duration = round(microtime(true) - $startTime, 3);

            if ($response->successful()) {
                $this->clearQuotaExceeded();
                $rawContent = $response->json('choices.0.message.content');
                Log::debug("OpenAIService::evaluateInterviewNotes: Response received: {$rawContent}");
                
                $data = json_decode($rawContent, true);
                if (is_array($data)) {
                    Log::info("OpenAIService::evaluateInterviewNotes: Completed successfully in {$duration}s.");
                    return $data;
                }
            }
            $this->handleQuotaExceeded($response->body());
            throw new Exception("Failed to evaluate notes: " . $response->body());
        } catch (Exception $e) {
            Log::error("OpenAIService::evaluateInterviewNotes: Exception: " . $e->getMessage());
            $this->handleQuotaExceeded($e->getMessage());
            throw $e;
        }
    }

    /**
     * Offer Recommendation Agent: Suggest salary and benefits.
     */
    public function generateOfferRecommendation(array $candidateDetails, array $jobDetails): array
    {
        Log::info("OpenAIService::generateOfferRecommendation: Generating offer suggestions for {$candidateDetails['name']}");

        $startTime = microtime(true);

        if ($this->isMockMode()) {
            throw new Exception("API key is not configured for provider {$this->provider}. Mock mode is disabled.");
        }

        $prompt = "You are an expert HR compensation advisor recommending an employment offer for a candidate.\n\n" .
                  "Candidate Details:\n" . json_encode($candidateDetails, JSON_PRETTY_PRINT) . "\n\n" .
                  "Job details:\n" . json_encode($jobDetails, JSON_PRETTY_PRINT) . "\n\n" .
                  "Based on the candidate's score, experience years, expected salary, and job requirements, generate a suggested salary range (in LPA or appropriate currency e.g. ₹18-20 LPA), a professional compensation justification, and recommended benefits.\n\n" .
                  "You MUST return a JSON object with the exact keys:\n" .
                  "{\n" .
                  "  \"suggested_salary\": \"string\",\n" .
                  "  \"justification\": \"string\",\n" .
                  "  \"benefits\": [\"string\"]\n" .
                  "}\n" .
                  "Do not wrap output in markdown code blocks.";

        try {
            Log::debug("OpenAIService::generateOfferRecommendation: Sending POST request to {$this->provider} Chat Completion...");
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert compensation advisor.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object']
            ]);

            $duration = round(microtime(true) - $startTime, 3);

            if ($response->successful()) {
                $this->clearQuotaExceeded();
                $rawContent = $response->json('choices.0.message.content');
                Log::debug("OpenAIService::generateOfferRecommendation: Response: {$rawContent}");
                
                $data = json_decode($rawContent, true);
                if (is_array($data)) {
                    Log::info("OpenAIService::generateOfferRecommendation: Completed successfully in {$duration}s.");
                    return $data;
                }
            }
            $this->handleQuotaExceeded($response->body());
            throw new Exception("Failed to generate offer: " . $response->body());
        } catch (Exception $e) {
            Log::error("OpenAIService::generateOfferRecommendation: Exception: " . $e->getMessage());
            $this->handleQuotaExceeded($e->getMessage());
            throw $e;
        }
    }

    /**
     * Recruiter Copilot Agent: Parse natural language queries and map them to filter candidates or direct answers.
     */
    public function queryCopilot(string $query, array $candidates): array
    {
        Log::info("OpenAIService::queryCopilot: Processing query: '{$query}' against " . count($candidates) . " candidates.");

        $startTime = microtime(true);

        if ($this->isMockMode()) {
            throw new Exception("API key is not configured for provider {$this->provider}. Mock mode is disabled.");
        }

        $prompt = "You are an AI Recruitment Copilot. You are asked a query about a candidate pool.\n\n" .
                  "Query: \"{$query}\"\n\n" .
                  "Candidate Pool Data:\n" . json_encode($candidates, JSON_PRETTY_PRINT) . "\n\n" .
                  "Review the query and candidate list. Filter the candidates that match the recruiter's command (e.g. skills, experience, expected salary, notice period). " .
                  "Generate a helpful natural language summary response answering their question in markdown, and return the list of matched candidate IDs.\n\n" .
                  "You MUST return a JSON object with the exact keys:\n" .
                  "{\n" .
                  "  \"answer\": \"string in markdown format\",\n" .
                  "  \"matched_candidate_ids\": [integer]\n" .
                  "}\n" .
                  "Do not wrap output in markdown code blocks.";

        try {
            Log::debug("OpenAIService::queryCopilot: Sending POST request to {$this->provider} Chat Completion...");
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an AI Recruitment Copilot.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object']
            ]);

            $duration = round(microtime(true) - $startTime, 3);

            if ($response->successful()) {
                $this->clearQuotaExceeded();
                $rawContent = $response->json('choices.0.message.content');
                Log::debug("OpenAIService::queryCopilot: Response: {$rawContent}");
                
                $data = json_decode($rawContent, true);
                if (is_array($data)) {
                    Log::info("OpenAIService::queryCopilot: Completed successfully in {$duration}s.");
                    return $data;
                }
            }
            $this->handleQuotaExceeded($response->body());
            throw new Exception("Failed to query copilot: " . $response->body());
        } catch (Exception $e) {
            Log::error("OpenAIService::queryCopilot: Exception: " . $e->getMessage());
            $this->handleQuotaExceeded($e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle OpenAI API quota exceeded error.
     */
    protected function handleQuotaExceeded(string $responseBody): void
    {
        if (str_contains(strtolower($responseBody), 'insufficient_quota')) {
            Log::warning("OpenAIService: OpenAI API returns insufficient_quota. Setting cache flag 'openai_quota_exceeded' to notify the user.");
            \Illuminate\Support\Facades\Cache::put('openai_quota_exceeded', true, 86400); // 24 hours
        }
    }

    /**
     * Clear OpenAI API quota exceeded error flag when a request succeeds.
     */
    protected function clearQuotaExceeded(): void
    {
        if (\Illuminate\Support\Facades\Cache::has('openai_quota_exceeded')) {
            Log::info("OpenAIService: OpenAI API request succeeded. Clearing cache flag 'openai_quota_exceeded'.");
            \Illuminate\Support\Facades\Cache::forget('openai_quota_exceeded');
        }
    }
}
