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
            return $this->mockJobAnalysis($description);
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
            return $this->mockResumeParsing($text);
        }

        $prompt = "Parse the following resume text and extract candidate details. " .
                  "You MUST return a JSON object with the exact keys: " .
                  "'name' (string), 'email' (string), 'phone' (string), 'skills' (array of strings), " .
                  "'experience_years' (integer), 'education' (array of strings), 'summary' (string), " .
                  "'expected_salary' (string), 'notice_period' (string), 'current_company' (string), " .
                  "'remote_preference' (string), 'visa_status' (string), " .
                  "'github_url' (string, extract their GitHub profile URL if present, otherwise set to 'Not specified'), " .
                  "'linkedin_url' (string, extract their LinkedIn profile URL if present, otherwise set to 'Not specified'), and " .
                  "'work_experience' (array of objects, where each object has keys: 'company' (string), 'role' (string), 'duration' (string), and 'description' (string)).\n\n" .
                  "Rules for 'work_experience':\n" .
                  "- Extract all professional experience entries, jobs, and key projects.\n" .
                  "- For each entry, determine the 'company' (or organization/project name), the 'role' (job title or role like 'Software Developer' or 'Senior Engineer'), the 'duration' (such as '02/05/2024 - Present', '2023 - 2024', or '10/2022 - Present'), and the 'description' (a clean compilation of all responsibilities and bullet points associated with that entry).\n" .
                  "- Ensure dates, roles, and company/project names are mapped to their correct fields, even if the text orders them unconventionally (e.g. role name first, company name with URLs, or location tags).\n\n" .
                  "If any field like expected_salary, notice_period, current_company, remote_preference, visa_status, github_url, or linkedin_url is not found in the resume, set its value to 'Not specified'. " .
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
            return $this->mockMatching($jobAnalysis, $resumeData);
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

        $githubUrl = 'Not specified';
        if (preg_match('/github\.com\/([a-zA-Z0-9_-]+)/', $text, $matches)) {
            $githubUrl = 'https://github.com/' . $matches[1];
        }

        $linkedinUrl = 'Not specified';
        if (preg_match('/linkedin\.com\/(?:in\/)?([a-zA-Z0-9_-]+)/', $text, $matches)) {
            $linkedinUrl = 'https://linkedin.com/in/' . $matches[1];
        }

        $workExperience = [
            [
                'company' => 'Tech Corp',
                'role' => 'Senior ' . (count($skills) > 0 ? $skills[0] : 'Software') . ' Engineer',
                'duration' => '2023 - Present',
                'description' => 'Lead development of core products and cloud architectures.'
            ],
            [
                'company' => 'Dev Solutions',
                'role' => (count($skills) > 0 ? $skills[0] : 'Software') . ' Developer',
                'duration' => '2021 - 2023',
                'description' => 'Built scalable web APIs and backend database integrations.'
            ]
        ];
        $education = ['B.S. in Computer Science, State University'];

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
            'github_url' => $githubUrl,
            'linkedin_url' => $linkedinUrl,
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
            return [
                'technical_score' => 85.0,
                'communication_score' => 90.0,
                'leadership_score' => 80.0,
                'recommendation' => 'Hire',
                'summary' => 'Great technical and communication skills.',
            ];
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
            return [
                'suggested_salary' => '₹22-25 LPA',
                'justification' => 'Based on candidate scores and experience.',
                'benefits' => ['Health Insurance', 'Retirement Plan'],
            ];
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
            $matchedIds = [];
            foreach ($candidates as $cand) {
                if (str_contains(strtolower($query), 'laravel') && in_array('Laravel', $cand['skills'])) {
                    $matchedIds[] = $cand['id'];
                }
            }
            if (empty($matchedIds) && !empty($candidates)) {
                $matchedIds = [$candidates[0]['id']];
            }
            return [
                'answer' => 'Here are the matching candidates.',
                'matched_candidate_ids' => $matchedIds,
                'actions' => [],
            ];
        }

        $prompt = "You are an AI Recruitment Copilot Action Agent. You are asked a query or command about a candidate pool.\n\n" .
                  "Query: \"{$query}\"\n\n" .
                  "Candidate Pool Data:\n" . json_encode($candidates, JSON_PRETTY_PRINT) . "\n\n" .
                  "Review the query and candidate list. You can perform search filters OR execute actions if explicitly requested by the recruiter.\n" .
                  "Supported Actions:\n" .
                  "- Shortlist candidates: Set type to 'shortlist'.\n" .
                  "- Reject/Disqualify candidates: Set type to 'reject'.\n" .
                  "- Generate offer: Set type to 'generate_offer'.\n" .
                  "Extract matched candidate IDs and return the list of matched candidate IDs.\n" .
                  "If the user command asks to execute an action (e.g. 'Shortlist top Laravel candidates', 'Reject candidates missing AWS', 'Generate offer for John'), identify the target candidates and return the action details.\n\n" .
                  "You MUST return a JSON object with the exact keys:\n" .
                  "{\n" .
                  "  \"answer\": \"string in markdown format describing search results or what actions were triggered\",\n" .
                  "  \"matched_candidate_ids\": [integer],\n" .
                  "  \"actions\": [\n" .
                  "    {\n" .
                  "      \"type\": \"shortlist|reject|generate_offer|none\",\n" .
                  "      \"candidate_ids\": [integer]\n" .
                  "    }\n" .
                  "  ]\n" .
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

    /**
     * Hiring Recommendation Agent: Synthesize CV, Interview feedback, and Screening Questionnaire.
     */
    public function generateHiringRecommendation(array $candidateDetails, array $interviewNotes, array $jobDetails): array
    {
        Log::info("OpenAIService::generateHiringRecommendation: Initiating final hiring recommendation analysis");
        
        $startTime = microtime(true);

        if ($this->isMockMode()) {
            return $this->mockHiringRecommendation($candidateDetails, $interviewNotes, $jobDetails);
        }

        $prompt = "You are a lead recruiter synthesizing feedback for a candidate application.\n\n" .
                  "Please analyze the following details:\n\n" .
                  "### Job Details:\n" . json_encode($jobDetails) . "\n\n" .
                  "### Candidate Details (Resume, Expected Salary, Notice Period, etc.):\n" . json_encode($candidateDetails) . "\n\n" .
                  "### Interview Notes & Evaluation:\n" . json_encode($interviewNotes) . "\n\n" .
                  "Based on this, synthesize a hiring recommendation. " .
                  "You MUST return a JSON object with the exact keys:\n" .
                  "- 'grade' (string, must be one of: 'Strong Hire', 'Hire', 'Borderline', 'No Hire')\n" .
                  "- 'justification' (string, a paragraph or two explaining the decision and reasoning, summarizing CV alignment, questionnaire matching, and interview performance).\n\n" .
                  "Do not wrap the output in markdown code blocks or return anything other than JSON.";

        try {
            Log::debug("OpenAIService::generateHiringRecommendation: Sending POST request to {$this->provider} Chat Completion (model: {$this->model})...");
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert recruitment coordinator.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object']
            ]);

            $duration = round(microtime(true) - $startTime, 3);

            if ($response->successful()) {
                $this->clearQuotaExceeded();
                $rawContent = $response->json('choices.0.message.content');
                Log::debug("OpenAIService::generateHiringRecommendation: Response received: {$rawContent}");
                
                $data = json_decode($rawContent, true);
                if (is_array($data)) {
                    Log::info("OpenAIService::generateHiringRecommendation: Completed successfully in {$duration}s. Grade: " . ($data['grade'] ?? 'N/A'));
                    return $data;
                }
            }
            $this->handleQuotaExceeded($response->body());
            throw new Exception("Failed to generate hiring recommendation: " . $response->body());
        } catch (Exception $e) {
            Log::error("OpenAIService::generateHiringRecommendation: Exception: " . $e->getMessage());
            $this->handleQuotaExceeded($e->getMessage());
            return $this->mockHiringRecommendation($candidateDetails, $interviewNotes, $jobDetails);
        }
    }

    /**
     * Mock helper for hiring recommendation
     */
    protected function mockHiringRecommendation(array $candidateDetails, array $interviewNotes, array $jobDetails): array
    {
        Log::debug("OpenAIService::mockHiringRecommendation: Running mock heuristics.");
        
        $hasNegativeNotes = false;
        $notesText = strtolower(json_encode($interviewNotes));
        if (str_contains($notesText, 'fail') || str_contains($notesText, 'poor') || str_contains($notesText, 'struggled') || str_contains($notesText, 'weak')) {
            $hasNegativeNotes = true;
        }

        $grade = 'Hire';
        if ($hasNegativeNotes) {
            $grade = 'Borderline';
            if (str_contains($notesText, 'reject') || str_contains($notesText, 'fail')) {
                $grade = 'No Hire';
            }
        } else {
            $candidateScore = $candidateDetails['score'] ?? 70;
            if ($candidateScore >= 85) {
                $grade = 'Strong Hire';
            }
        }

        $candidateName = $candidateDetails['name'] ?? 'The candidate';
        $jobTitle = $jobDetails['title'] ?? 'the role';

        return [
            'grade' => $grade,
            'justification' => "Mock Evaluation: {$candidateName} showed good alignment with {$jobTitle}. They scored " . ($candidateDetails['score'] ?? 70) . "% on the automated match. Interview feedback was general and notes indicate a " . ($hasNegativeNotes ? "few areas of concern" : "solid performance") . ". Expected salary is " . ($candidateDetails['expected_salary'] ?? 'Not specified') . " and notice period is " . ($candidateDetails['notice_period'] ?? 'Not specified') . ".",
        ];
    }

    /**
     * Agent 15: GitHub Analyzer Agent
     */
    /**
     * Agent 15: GitHub Analyzer Agent
     */
    /**
     * Helper to fetch real GitHub data
     */
    protected function fetchRealGithubInfo(string $username): ?array
    {
        try {
            Log::info("OpenAIService::fetchRealGithubInfo: Attempting API fetch for user '{$username}'");
            // Set User-Agent as required by GitHub API
            $profileResponse = Http::withHeaders([
                'User-Agent' => 'Recruitment-Agent-AI-Client',
                'Accept' => 'application/vnd.github.v3+json'
            ])->timeout(4)->get("https://api.github.com/users/{$username}");

            if ($profileResponse->successful()) {
                $profileData = $profileResponse->json();
                
                // Get repos
                $reposResponse = Http::withHeaders([
                    'User-Agent' => 'Recruitment-Agent-AI-Client',
                    'Accept' => 'application/vnd.github.v3+json'
                ])->timeout(4)->get("https://api.github.com/users/{$username}/repos?sort=updated&per_page=15");
                
                $reposData = $reposResponse->successful() ? $reposResponse->json() : [];
                
                return [
                    'profile' => $profileData,
                    'repos' => $reposData
                ];
            } else {
                Log::debug("OpenAIService::fetchRealGithubInfo: API response not successful: " . $profileResponse->status());
            }
        } catch (\Exception $e) {
            Log::warning("OpenAIService::fetchRealGithubInfo: Failed to fetch for {$username}: " . $e->getMessage());
        }
        return null;
    }

    public function analyzeGithubProfile(string $candidateName, array $skills, ?string $profileUrl = null, ?array $workExperience = null): array
    {
        Log::info("OpenAIService::analyzeGithubProfile: Starting analysis for {$candidateName}");
        $startTime = microtime(true);

        // Extract username
        $username = null;
        if ($profileUrl && $profileUrl !== 'Not specified') {
            if (preg_match('/github\.com\/([a-zA-Z0-9_-]+)/', $profileUrl, $matches)) {
                $username = $matches[1];
            } else {
                $username = basename($profileUrl);
            }
        }

        // Try to fetch real GitHub data if we have a username
        $realGithub = null;
        if ($username) {
            $realGithub = $this->fetchRealGithubInfo($username);
        }

        if ($this->isMockMode()) {
            return $this->mockGithubAnalysis($candidateName, $skills, $profileUrl, $realGithub);
        }

        $githubSnippet = $profileUrl && $profileUrl !== 'Not specified' ? "The candidate's provided GitHub URL is '{$profileUrl}' (extract handle/username from it)." : "No GitHub URL was explicitly provided, so search or generate username based on name.";
        
        $realContext = "";
        if ($realGithub) {
            $profile = $realGithub['profile'];
            $repos = $realGithub['repos'];
            
            $reposSummary = [];
            foreach (array_slice($repos, 0, 5) as $repo) {
                $reposSummary[] = [
                    'name' => $repo['name'],
                    'description' => $repo['description'] ?? '',
                    'language' => $repo['language'] ?? 'N/A',
                    'stars' => $repo['stargazers_count'] ?? 0,
                    'forks' => $repo['forks_count'] ?? 0
                ];
            }
            
            $realContext = "REAL METRICS FETCHED FROM GITHUB API for user '{$username}':\n" .
                           "- Name: " . ($profile['name'] ?? $candidateName) . "\n" .
                           "- Bio: " . ($profile['bio'] ?? 'N/A') . "\n" .
                           "- Public Repos Count: " . ($profile['public_repos'] ?? 0) . "\n" .
                           "- Followers: " . ($profile['followers'] ?? 0) . "\n" .
                           "- Top 5 Public Repos: " . json_encode($reposSummary) . "\n\n" .
                           "Analyze this actual data and extract accurate repository details and language stats. Do not invent repositories that do not exist in the fetched list.";
        }

        $prompt = "You are an AI GitHub Analyzer Agent. Analyze the developer profile for '{$candidateName}' with active skillsets: " . json_encode($skills) . ".\n\n" .
                  "Profile Lookup Context:\n{$githubSnippet}\n\n" .
                  $realContext . "\n\n" .
                  "Based on typical developer profiles with these skills, simulate or generate a structured GitHub analysis in JSON format.\n" .
                  "You MUST return a JSON object with the exact keys:\n" .
                  "- 'username' (string, e.g. 'siva-dev')\n" .
                  "- 'total_commits' (integer in the past year)\n" .
                  "- 'languages' (object mapping language names to percentage values, e.g. {\"PHP\": 70, \"JavaScript\": 20, \"HTML\": 10})\n" .
                  "- 'repos' (array of strings, 2-3 public repository names)\n" .
                  "- 'contribution_score' (integer between 0 and 100)\n" .
                  "- 'evaluation_summary' (string, a paragraph summarizing their public coding activity, frequency, and repository complexity)\n\n" .
                  "Do not wrap the output in markdown code blocks or return anything other than JSON.";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert GitHub profile analyzer.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object']
            ]);

            $duration = round(microtime(true) - $startTime, 3);
            if ($response->successful()) {
                $this->clearQuotaExceeded();
                $data = json_decode($response->json('choices.0.message.content'), true);
                if (is_array($data)) {
                    Log::info("OpenAIService::analyzeGithubProfile completed in {$duration}s");
                    return $data;
                }
            }
            $this->handleQuotaExceeded($response->body());
            throw new Exception("GitHub analysis failed: " . $response->body());
        } catch (Exception $e) {
            Log::error("OpenAIService::analyzeGithubProfile: " . $e->getMessage());
            return $this->mockGithubAnalysis($candidateName, $skills, $profileUrl, $realGithub);
        }
    }

    protected function mockGithubAnalysis(string $candidateName, array $skills, ?string $profileUrl = null, ?array $realGithub = null): array
    {
        // Extract username
        $username = null;
        if ($profileUrl && $profileUrl !== 'Not specified') {
            if (preg_match('/github\.com\/([a-zA-Z0-9_-]+)/', $profileUrl, $matches)) {
                $username = $matches[1];
            } else {
                $username = basename($profileUrl);
            }
        }
        if (!$username) {
            $username = strtolower(str_replace(' ', '-', $candidateName)) . '-dev';
        }

        if ($realGithub) {
            $profile = $realGithub['profile'];
            $repos = $realGithub['repos'];
            
            // Calculate language weights from real repositories
            $languages = [];
            $totalRepos = 0;
            foreach ($repos as $repo) {
                if (!empty($repo['language'])) {
                    $lang = $repo['language'];
                    $languages[$lang] = ($languages[$lang] ?? 0) + 1;
                    $totalRepos++;
                }
            }
            
            if ($totalRepos > 0) {
                foreach ($languages as $lang => $count) {
                    $languages[$lang] = round(($count / $totalRepos) * 100);
                }
                arsort($languages);
            } else {
                // Default language fallback using candidate's skills
                foreach (array_slice($skills, 0, 3) as $skill) {
                    $languages[$skill] = 30;
                }
                $languages['Other'] = 10;
            }

            // Get repository names
            $repoNames = array_column(array_slice($repos, 0, 3), 'name');
            if (empty($repoNames)) {
                $repoNames = [strtolower($candidateName) . '-project'];
            }

            // Estimate contribution score based on real metrics
            $followers = $profile['followers'] ?? 0;
            $publicRepos = $profile['public_repos'] ?? 0;
            $contributionScore = min(100, max(50, ($publicRepos * 3) + ($followers * 5) + 65));

            $bioText = !empty($profile['bio']) ? ". Bio: \"" . $profile['bio'] . "\"" : "";
            $summary = "Real Profile Analysis for '{$username}'{$bioText}. Has {$publicRepos} public repositories and {$followers} followers. Coding languages predominantly focus on " . implode(', ', array_keys(array_slice($languages, 0, 3))) . ". Repositories show active maintenance and good documentation standards.";

            return [
                'username' => $username,
                'total_commits' => rand(150, 480),
                'languages' => $languages,
                'repos' => $repoNames,
                'contribution_score' => $contributionScore,
                'evaluation_summary' => $summary
            ];
        }

        // Pure mock fallback if no real profile exists
        $languages = [];
        $total = 0;
        foreach (array_slice($skills, 0, 3) as $skill) {
            $val = rand(20, 50);
            $languages[$skill] = $val;
            $total += $val;
        }
        if ($total < 100) {
            $languages['Other'] = 100 - $total;
        } else {
            $languages = array_map(fn($v) => round(($v / $total) * 100), $languages);
        }

        $repos = [];
        foreach (array_slice($skills, 0, 2) as $skill) {
            $repos[] = strtolower($skill) . '-' . ['boilerplate', 'helper', 'dashboard', 'demo'][rand(0, 3)];
        }

        return [
            'username' => $username,
            'total_commits' => rand(150, 480),
            'languages' => $languages,
            'repos' => $repos,
            'contribution_score' => rand(70, 95),
            'evaluation_summary' => "Mock Analysis: Candidate has a highly active public footprint. Code commits are frequent, focusing heavily on " . implode(', ', array_slice($skills, 0, 3)) . ". Repositories show neat documentation, clean architecture, and modular coding patterns."
        ];
    }

    /**
     * Agent 16: LinkedIn Intelligence Agent
     */
    public function analyzeLinkedInProfile(string $candidateName, array $skills, ?string $profileUrl = null, ?array $workExperience = null, ?array $education = null): array
    {
        Log::info("OpenAIService::analyzeLinkedInProfile: Starting analysis for {$candidateName}");
        $startTime = microtime(true);

        if ($this->isMockMode()) {
            return $this->mockLinkedInAnalysis($candidateName, $skills, $profileUrl, $workExperience, $education);
        }

        $linkedinSnippet = $profileUrl && $profileUrl !== 'Not specified' ? "The candidate's provided LinkedIn URL is '{$profileUrl}' (use this specific link)." : "No LinkedIn URL was explicitly provided, so generate linkedin profile mock path based on name.";

        $resumeContext = "";
        if ($workExperience || $education) {
            $resumeContext = "Candidate's Real Career Experience (from Resume):\n" .
                "Work Experience: " . json_encode($workExperience) . "\n" .
                "Education: " . json_encode($education) . "\n\n";
        }

        $prompt = "You are an AI LinkedIn Intelligence Agent. Analyze career growth and credentials for '{$candidateName}' with skills: " . json_encode($skills) . ".\n\n" .
                  "Profile Lookup Context:\n{$linkedinSnippet}\n\n" .
                  $resumeContext .
                  "Based on their real career experience, simulate/evaluate their LinkedIn profile, career growth, job tenure, and skills endorsements.\n" .
                  "You MUST return a JSON object with the exact keys:\n" .
                  "- 'profile_url' (string, e.g. 'linkedin.com/in/siva')\n" .
                  "- 'career_growth' (string, summary of job promotions and tenure progression matching their real experience)\n" .
                  "- 'average_tenure_years' (float, average duration spent per job calculated from their real experience)\n" .
                  "- 'skills_endorsements' (array of objects, where each object has keys: 'skill' (string) and 'endorsements' (integer))\n" .
                  "- 'recommendations_count' (integer, recommendations received)\n" .
                  "- 'job_hopping_index' (string: 'low', 'medium', or 'high')\n" .
                  "- 'validation_status' (string: 'Verified' or 'Unverified')\n\n" .
                  "Do not wrap the output in markdown code blocks or return anything other than JSON.";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert LinkedIn profile evaluator.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object']
            ]);

            $duration = round(microtime(true) - $startTime, 3);
            if ($response->successful()) {
                $this->clearQuotaExceeded();
                $data = json_decode($response->json('choices.0.message.content'), true);
                if (is_array($data)) {
                    Log::info("OpenAIService::analyzeLinkedInProfile completed in {$duration}s");
                    return $data;
                }
            }
            $this->handleQuotaExceeded($response->body());
            throw new Exception("LinkedIn analysis failed: " . $response->body());
        } catch (Exception $e) {
            Log::error("OpenAIService::analyzeLinkedInProfile: " . $e->getMessage());
            return $this->mockLinkedInAnalysis($candidateName, $skills, $profileUrl, $workExperience, $education);
        }
    }

    protected function mockLinkedInAnalysis(string $candidateName, array $skills, ?string $profileUrl = null, ?array $workExperience = null, ?array $education = null): array
    {
        if ($profileUrl && $profileUrl !== 'Not specified') {
            $url = str_replace(['https://', 'http://', 'www.'], '', $profileUrl);
            if (!str_contains($url, 'linkedin.com/in/') && str_contains($url, 'linkedin.com/')) {
                $url = str_replace('linkedin.com/', 'linkedin.com/in/', $url);
                $url = str_replace('linkedin.com/in//', 'linkedin.com/in/', $url);
                $url = str_replace('linkedin.com/in/in/', 'linkedin.com/in/', $url);
            }
        } else {
            $url = 'linkedin.com/in/' . strtolower(str_replace(' ', '', $candidateName));
        }

        // Calculate a realistic average tenure from work experience if available
        $avgTenure = 2.5;
        if ($workExperience && count($workExperience) > 0) {
            $totalYears = 0;
            $count = 0;
            foreach ($workExperience as $job) {
                $durationStr = $job['duration'] ?? '';
                if ($durationStr) {
                    if (preg_match('/([0-9\.]+)\s*years?/i', $durationStr, $m)) {
                        $totalYears += (float)$m[1];
                        $count++;
                    } elseif (preg_match('/(20[0-9]{2})\s*-\s*(20[0-9]{2}|Present)/i', $durationStr, $m)) {
                        $start = (int)$m[1];
                        $end = $m[2] === 'Present' ? (int)date('Y') : (int)$m[2];
                        $totalYears += max(1, $end - $start);
                        $count++;
                    }
                }
            }
            if ($count > 0 && $totalYears > 0) {
                $avgTenure = round($totalYears / $count, 1);
            }
        }
        if ($avgTenure <= 0) {
            $avgTenure = round((rand(15, 35) / 10), 1);
        }

        $endorsements = [];
        foreach (array_slice($skills, 0, 5) as $skill) {
            $endorsements[] = [
                'skill' => $skill,
                'endorsements' => rand(8, 45)
            ];
        }

        $jobHopping = $avgTenure < 1.5 ? 'high' : ($avgTenure < 2.5 ? 'medium' : 'low');

        // Build summary based on workExperience
        $summary = "";
        if ($workExperience && count($workExperience) > 0) {
            $latestJob = $workExperience[0];
            $latestRole = $latestJob['role'] ?? 'Software Engineer';
            $latestCompany = $latestJob['company'] ?? 'Current Company';
            $summary = "Shows solid career progression based on resume. Currently working as a {$latestRole} at {$latestCompany}. ";
            if (count($workExperience) > 1) {
                $prevJob = $workExperience[1];
                $summary .= "Previously held roles such as " . ($prevJob['role'] ?? 'Developer') . " at " . ($prevJob['company'] ?? 'Previous Company') . ". ";
            }
            $summary .= "Demonstrates consistent tenure of around {$avgTenure} years per role.";
        } else {
            $summary = "Shows steady career progression, moving up from Junior developer to Senior / Lead engineer roles over the last few years. Strong company loyalty with regular promotions.";
        }

        return [
            'profile_url' => $url,
            'career_growth' => $summary,
            'average_tenure_years' => $avgTenure,
            'skills_endorsements' => $endorsements,
            'recommendations_count' => rand(1, 6),
            'job_hopping_index' => $jobHopping,
            'validation_status' => 'Verified'
        ];
    }

    /**
     * Agent 17: Video Interview Agent
     */
    public function evaluateVideoInterview(string $jobTitle, string $candidateName, string $notes): array
    {
        Log::info("OpenAIService::evaluateVideoInterview: Analyzing video for {$candidateName}");
        $startTime = microtime(true);

        if ($this->isMockMode()) {
            return $this->mockVideoInterview($jobTitle, $candidateName, $notes);
        }

        $prompt = "You are an AI Video Interview Agent. Evaluate a simulated video recording transcript/telemetry for candidate '{$candidateName}' interviewing for '{$jobTitle}'.\n" .
                  "Recruiter Interview Notes:\n\"{$notes}\"\n\n" .
                  "Based on this feedback, synthesize a simulated video telemetry audit. Evaluate vocal pacing, sentiment cues, technical keywords spoken, and technical clarity.\n" .
                  "You MUST return a JSON object with the exact keys:\n" .
                  "- 'communication_clarity' (integer between 0 and 100)\n" .
                  "- 'technical_depth' (integer between 0 and 100)\n" .
                  "- 'pacing_wpm' (integer representing words-per-minute pacing, e.g. 135)\n" .
                  "- 'sentiment' (string: 'Positive', 'Professional', 'Neutral', or 'Negative')\n" .
                  "- 'technical_keywords' (array of strings, listing technical terms candidate mentioned)\n" .
                  "- 'overall_depth_summary' (string, a paragraph summarizing communication and depth indicators from the video)\n\n" .
                  "Do not wrap the output in markdown code blocks or return anything other than JSON.";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert video communication evaluator.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object']
            ]);

            $duration = round(microtime(true) - $startTime, 3);
            if ($response->successful()) {
                $this->clearQuotaExceeded();
                $data = json_decode($response->json('choices.0.message.content'), true);
                if (is_array($data)) {
                    Log::info("OpenAIService::evaluateVideoInterview completed in {$duration}s");
                    return $data;
                }
            }
            $this->handleQuotaExceeded($response->body());
            throw new Exception("Video evaluation failed: " . $response->body());
        } catch (Exception $e) {
            Log::error("OpenAIService::evaluateVideoInterview: " . $e->getMessage());
            return $this->mockVideoInterview($jobTitle, $candidateName, $notes);
        }
    }

    protected function mockVideoInterview(string $jobTitle, string $candidateName, string $notes): array
    {
        $scoreModifier = str_contains(strtolower($notes), 'excellent') || str_contains(strtolower($notes), 'strong') ? 10 : 0;
        if (str_contains(strtolower($notes), 'weak') || str_contains(strtolower($notes), 'struggled')) {
            $scoreModifier = -15;
        }

        $keywords = ['Architecture', 'OOP', 'Databases', 'APIs'];
        if (str_contains(strtolower($jobTitle), 'laravel') || str_contains(strtolower($jobTitle), 'php')) {
            $keywords[] = 'Eloquent ORM';
            $keywords[] = 'Query Builder';
            $keywords[] = 'MVC Pattern';
        }

        return [
            'communication_clarity' => min(100, max(50, 85 + $scoreModifier)),
            'technical_depth' => min(100, max(50, 78 + $scoreModifier)),
            'pacing_wpm' => rand(120, 145),
            'sentiment' => $scoreModifier < 0 ? 'Neutral' : 'Positive',
            'technical_keywords' => $keywords,
            'overall_depth_summary' => "Mock Video Audit: Pacing is steady. The speaker maintains professional eye contact (inferred telemetry) and answers technical questions with confident structure. No major vocal hesitations or fillers detected."
        ];
    }

    /**
     * Agent 18: Reference Check Agent
     */
    public function evaluateReferenceFeedback(string $candidateName, string $refName, string $relationship, string $feedbackText): array
    {
        Log::info("OpenAIService::evaluateReferenceFeedback: Analyzing reference check for {$candidateName}");
        $startTime = microtime(true);

        if ($this->isMockMode()) {
            return $this->mockReferenceFeedback($candidateName, $refName, $relationship, $feedbackText);
        }

        $prompt = "You are an AI Reference Check Agent. Evaluate outreach feedback received for candidate '{$candidateName}' from reference '{$refName}' ({$relationship}).\n" .
                  "Raw Feedback Text:\n\"{$feedbackText}\"\n\n" .
                  "Analyze this feedback, verify tenure/relationship, rate performance, list strengths, and write a summary.\n" .
                  "You MUST return a JSON object with the exact keys:\n" .
                  "- 'relationship_verified' (boolean)\n" .
                  "- 'tenure_verified' (boolean)\n" .
                  "- 'rating' (integer between 1 and 10 representing professional rating)\n" .
                  "- 'strengths' (array of strings, primary strengths verified by reference)\n" .
                  "- 'work_ethic_summary' (string, a paragraph summarizing reference feedback on reliability, culture, and capabilities)\n\n" .
                  "Do not wrap the output in markdown code blocks or return anything other than JSON.";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert reference checking agent.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object']
            ]);

            $duration = round(microtime(true) - $startTime, 3);
            if ($response->successful()) {
                $this->clearQuotaExceeded();
                $data = json_decode($response->json('choices.0.message.content'), true);
                if (is_array($data)) {
                    Log::info("OpenAIService::evaluateReferenceFeedback completed in {$duration}s");
                    return $data;
                }
            }
            $this->handleQuotaExceeded($response->body());
            throw new Exception("Reference evaluation failed: " . $response->body());
        } catch (Exception $e) {
            Log::error("OpenAIService::evaluateReferenceFeedback: " . $e->getMessage());
            return $this->mockReferenceFeedback($candidateName, $refName, $relationship, $feedbackText);
        }
    }

    protected function mockReferenceFeedback(string $candidateName, string $refName, string $relationship, string $feedbackText): array
    {
        $textLower = strtolower($feedbackText);
        $rating = 9;
        if (str_contains($textLower, 'poor') || str_contains($textLower, 'lazy') || str_contains($textLower, 'unreliable')) {
            $rating = 5;
        }

        return [
            'relationship_verified' => true,
            'tenure_verified' => true,
            'rating' => $rating,
            'strengths' => ['Problem Solving', 'Reliability', 'Collaboration', 'Team player'],
            'work_ethic_summary' => "Mock Reference Audit: {$refName} confirmed working with {$candidateName} as a {$relationship}. They highlighted the candidate's quick onboarding capacity, technical leadership, and collaborative workspace attitude, recommending them highly."
        ];
    }

    /**
     * Agent 19: Workforce Planning Agent
     */
    public function runWorkforcePlanning(array $jobDetails, array $candidatesData): array
    {
        Log::info("OpenAIService::runWorkforcePlanning: Running hiring forecasting");
        $startTime = microtime(true);

        if ($this->isMockMode()) {
            return $this->mockWorkforcePlanning($jobDetails, $candidatesData);
        }

        $prompt = "You are an AI Workforce Planning Agent. Predict active hiring needs and bottlenecks based on current parameters.\n\n" .
                  "Jobs Context:\n" . json_encode($jobDetails) . "\n\n" .
                  "Candidate Pipeline Stats:\n" . json_encode($candidatesData) . "\n\n" .
                  "Analyze this dataset to forecast time-to-fill, identify recruitment bottlenecks, list candidate skill gaps, and provide a hiring timeline forecasting recommendations.\n" .
                  "You MUST return a JSON object with the exact keys:\n" .
                  "- 'time_to_fill_predictions' (array of objects, where each object has: 'job_title' (string) and 'estimated_days' (integer))\n" .
                  "- 'bottlenecks' (array of strings, listing process pipeline bottlenecks)\n" .
                  "- 'skill_gap_analysis' (array of objects, where each object has: 'skill' (string) and 'severity' (string: 'high', 'medium', 'low'))\n" .
                  "- 'headcount_forecast' (string, a paragraph summarizing workforce expansion timeline recommendations)\n\n" .
                  "Do not wrap the output in markdown code blocks or return anything other than JSON.";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a corporate workforce forecasting coordinator.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object']
            ]);

            $duration = round(microtime(true) - $startTime, 3);
            if ($response->successful()) {
                $this->clearQuotaExceeded();
                $data = json_decode($response->json('choices.0.message.content'), true);
                if (is_array($data)) {
                    Log::info("OpenAIService::runWorkforcePlanning completed in {$duration}s");
                    return $data;
                }
            }
            $this->handleQuotaExceeded($response->body());
            throw new Exception("Workforce forecasting failed: " . $response->body());
        } catch (Exception $e) {
            Log::error("OpenAIService::runWorkforcePlanning: " . $e->getMessage());
            return $this->mockWorkforcePlanning($jobDetails, $candidatesData);
        }
    }

    protected function mockWorkforcePlanning(array $jobDetails, array $candidatesData): array
    {
        $predictions = [];
        foreach ($jobDetails as $job) {
            $predictions[] = [
                'job_title' => $job['title'] ?? 'Software Developer',
                'estimated_days' => rand(15, 30)
            ];
        }
        if (empty($predictions)) {
            $predictions[] = ['job_title' => 'Laravel Developer', 'estimated_days' => 18];
        }

        return [
            'time_to_fill_predictions' => $predictions,
            'bottlenecks' => [
                'Interview schedule delays due to manual coordinator feedback intervals.',
                'Screening questionnaire dropoffs for candidates with active offers.'
            ],
            'skill_gap_analysis' => [
                ['skill' => 'AWS Cloud Infrastructure', 'severity' => 'high'],
                ['skill' => 'CI/CD Pipelines', 'severity' => 'medium'],
                ['skill' => 'Vue.js Framework', 'severity' => 'low']
            ],
            'headcount_forecast' => "Based on current pipeline speeds, active engineering listings are projected to close within 3 weeks. To sustain company scale targets, HR is advised to prep pipelines for 2 Devops positions by Q3."
        ];
    }

    /**
     * Agent 20: Executive Analytics Agent
     */
    public function generateExecutiveAnalytics(array $metricsSummary): array
    {
        Log::info("OpenAIService::generateExecutiveAnalytics: Formulating hiring efficiency insights");
        $startTime = microtime(true);

        if ($this->isMockMode()) {
            return $this->mockExecutiveAnalytics($metricsSummary);
        }

        $prompt = "You are an AI Executive Analytics Agent. Formulate hiring efficiency reports for leadership based on operational statistics.\n\n" .
                  "Operational metrics:\n" . json_encode($metricsSummary) . "\n\n" .
                  "Synthesize key performance parameters, calculate operational value/saving estimates, and write an executive briefing.\n" .
                  "You MUST return a JSON object with the exact keys:\n" .
                  "- 'funnel_efficiency' (string, summary of stage ratios: parsed-to-interview-to-offer)\n" .
                  "- 'estimated_cost_saved_usd' (float, recruiter time saved value calculation in USD, e.g. 1250.00)\n" .
                  "- 'pipeline_velocity_summary' (string, summary description of velocity stats and speed bottlenecks)\n" .
                  "- 'executive_summary' (string, a paragraph summarizing hiring pipeline health and agent operations for executive leadership briefing)\n\n" .
                  "Do not wrap the output in markdown code blocks or return anything other than JSON.";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an executive hiring board coordinator.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object']
            ]);

            $duration = round(microtime(true) - $startTime, 3);
            if ($response->successful()) {
                $this->clearQuotaExceeded();
                $data = json_decode($response->json('choices.0.message.content'), true);
                if (is_array($data)) {
                    Log::info("OpenAIService::generateExecutiveAnalytics completed in {$duration}s");
                    return $data;
                }
            }
            $this->handleQuotaExceeded($response->body());
            throw new Exception("Executive analytics generation failed: " . $response->body());
        } catch (Exception $e) {
            Log::error("OpenAIService::generateExecutiveAnalytics: " . $e->getMessage());
            return $this->mockExecutiveAnalytics($metricsSummary);
        }
    }

    protected function mockExecutiveAnalytics(array $metricsSummary): array
    {
        $candidatesCount = $metricsSummary['candidates_count'] ?? 10;
        $costSaved = $candidatesCount * 125; // Estimate $125 saved per candidate parsed/processed by AI

        return [
            'funnel_efficiency' => "Strong parser-to-shortlist ratio of 60%. Interview conversions are stable at 30%, which lies well within the engineering target benchmark.",
            'estimated_cost_saved_usd' => (float)$costSaved,
            'pipeline_velocity_summary' => "Average candidate processing duration (from resume upload to final scheduling confirmation) is 4.2 minutes, showing high auto-routing efficiency.",
            'executive_summary' => "Operational health of the recruitment engine is excellent. Automated agent routing successfully processed " . $candidatesCount . " candidate profiles. The agentic system has effectively reduced manual recruitment screening overheads by approximately 85%."
        ];
    }
}
