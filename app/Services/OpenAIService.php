<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class OpenAIService
{
    protected ?string $apiKey;
    protected string $model = 'gpt-4o-mini';
    protected string $embeddingModel = 'text-embedding-3-small';

    /**
     * Dictionary of skills used by the Mock AI parser.
     * You can easily add more skills to this array here in the future.
     */
    public static array $mockSkills = [
        // Web & Backend
        'Laravel', 'PHP', 'MySQL', 'React', 'Vue.js', 'Vue', 'AWS', 'Docker', 'Python', 'Javascript', 'REST API', 'Git', 'HTML', 'CSS', 'Tailwind', 'Typescript', 'Node', 'Java', 'Spring', 'Go',
        // Electronics & Embedded
        'Arduino', 'ESP32', 'STM32', 'Raspberry Pi', 'PCB Design', 'PCB', 'IoT', 'Microcontrollers', 'Embedded Systems', 'Circuits', 'Schematics', 'Circuit Theory', 'Digital Systems', 'Analog Systems'
    ];

    public function __construct()
    {
        $this->apiKey = config('services.openai.key') ?: env('OPENAI_API_KEY');
        if ($this->apiKey === 'your_openai_api_key_here' || empty($this->apiKey)) {
            $this->apiKey = null;
        }

        if ($this->isMockMode()) {
            Log::info("OpenAIService: INITIALIZED IN MOCK MODE (No active OpenAI API key found). Mock heuristics will be used.");
        } else {
            Log::info("OpenAIService: INITIALIZED IN LIVE MODE (OpenAI API key present). Using model '{$this->model}' and embedding model '{$this->embeddingModel}'.");
        }
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

        if ($this->isMockMode()) {
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
                'Authorization' => 'Bearer ' . $this->apiKey,
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
            Log::debug("OpenAIService::analyzeJob: API key missing. Invoking Mock Job Analysis Agent...");
            $result = $this->mockJobAnalysis($description);
            $duration = round(microtime(true) - $startTime, 3);
            Log::info("OpenAIService::analyzeJob: Mock job analysis completed in {$duration}s. Title: '{$result['title']}', Required Skills: " . count($result['required_skills']));
            return $result;
        }

        $prompt = "Analyze this job description and extract requirements. " .
                  "You MUST return a JSON object with the exact keys: " .
                  "'title' (string), 'required_skills' (array of strings), 'preferred_skills' (array of strings), " .
                  "'experience_years' (integer), 'certifications' (array of strings), and 'analysis_summary' (string). " .
                  "Do not wrap the output in markdown code blocks or return anything other than JSON.\n\n" .
                  "Job Description:\n" . $description;

        try {
            Log::debug("OpenAIService::analyzeJob: Sending POST request to OpenAI Chat Completion (model: {$this->model}) for job analysis...");
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
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
                Log::debug("OpenAIService::analyzeJob: OpenAI API response received. Raw content: {$rawContent}");
                
                $data = json_decode($rawContent, true);
                if (is_array($data)) {
                    Log::info("OpenAIService::analyzeJob: Job analysis completed successfully by OpenAI in {$duration}s. Title: '" . ($data['title'] ?? 'N/A') . "', Required skills count: " . count($data['required_skills'] ?? []));
                    return $data;
                }
            }
            $this->handleQuotaExceeded($response->body());
            throw new Exception("Failed to analyze job: " . $response->body());
        } catch (Exception $e) {
            Log::error("OpenAIService::analyzeJob: Job analysis exception: " . $e->getMessage() . " - Falling back to Mock Job Analysis.");
            $this->handleQuotaExceeded($e->getMessage());
            return $this->mockJobAnalysis($description);
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
            Log::debug("OpenAIService::parseResume: API key missing. Invoking Mock Resume Parser Agent...");
            $result = $this->mockResumeParsing($text);
            $duration = round(microtime(true) - $startTime, 3);
            Log::info("OpenAIService::parseResume: Mock resume parser completed in {$duration}s. Name: '{$result['name']}', Email: '{$result['email']}', Exp: {$result['experience_years']} years");
            return $result;
        }

        $prompt = "Parse the following resume text and extract candidate details. " .
                  "You MUST return a JSON object with the exact keys: " .
                  "'name' (string), 'email' (string), 'phone' (string), 'skills' (array of strings), " .
                  "'experience_years' (integer), 'education' (array of strings), and 'summary' (string). " .
                  "Do not wrap the output in markdown code blocks or return anything other than JSON.\n\n" .
                  "Resume:\n" . $text;

        try {
            Log::debug("OpenAIService::parseResume: Sending POST request to OpenAI Chat Completion (model: {$this->model}) for resume parsing...");
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
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
                Log::debug("OpenAIService::parseResume: OpenAI API response received. Raw content: {$rawContent}");

                $data = json_decode($rawContent, true);
                if (is_array($data)) {
                    Log::info("OpenAIService::parseResume: Resume parsing completed successfully by OpenAI in {$duration}s. Name: '" . ($data['name'] ?? 'N/A') . "', Email: '" . ($data['email'] ?? 'N/A') . "'");
                    return $data;
                }
            }
            $this->handleQuotaExceeded($response->body());
            throw new Exception("Failed to parse resume: " . $response->body());
        } catch (Exception $e) {
            Log::error("OpenAIService::parseResume: Resume parsing exception: " . $e->getMessage() . " - Falling back to Mock Resume Parser.");
            $this->handleQuotaExceeded($e->getMessage());
            return $this->mockResumeParsing($text);
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
            Log::debug("OpenAIService::matchAndAnalyze: API key missing. Invoking Mock Candidate Matching Agent...");
            $result = $this->mockMatching($jobAnalysis, $resumeData);
            $duration = round(microtime(true) - $startTime, 3);
            Log::info("OpenAIService::matchAndAnalyze: Mock matching completed in {$duration}s. Total Score: {$result['total_score']}%, Recommendation: '{$result['recommendation']}'");
            return $result;
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
            Log::debug("OpenAIService::matchAndAnalyze: Sending POST request to OpenAI Chat Completion (model: {$this->model}) for candidate matching evaluation...");
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
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
                Log::debug("OpenAIService::matchAndAnalyze: OpenAI API response received. Raw content: {$rawContent}");

                $data = json_decode($rawContent, true);
                if (is_array($data)) {
                    // Calculate total score using formula: Score = 0.6*Skill + 0.3*Exp + 0.1*Edu
                    $data['total_score'] = 0.6 * ($data['skill_match'] ?? 0) + 0.3 * ($data['experience_match'] ?? 0) + 0.1 * ($data['education_match'] ?? 0);
                    Log::info("OpenAIService::matchAndAnalyze: Candidate matching completed successfully by OpenAI in {$duration}s. Total Score: {$data['total_score']}%, Recommendation: '{$data['recommendation']}'");
                    return $data;
                }
            }
            $this->handleQuotaExceeded($response->body());
            throw new Exception("Failed to match candidate: " . $response->body());
        } catch (Exception $e) {
            Log::error("OpenAIService::matchAndAnalyze: Candidate matching exception: " . $e->getMessage() . " - Falling back to Mock Candidate Matching.");
            $this->handleQuotaExceeded($e->getMessage());
            return $this->mockMatching($jobAnalysis, $resumeData);
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
            if (str_contains($descLower, 'electronics') || str_contains($descLower, 'embedded') || str_contains($descLower, 'hardware') || str_contains($descLower, 'circuit') || str_contains($descLower, 'microcontroller') || str_contains($descLower, 'iot')) {
                $required = ['Microcontrollers', 'Circuits', 'Embedded Systems'];
                $preferred = ['Arduino', 'PCB Design', 'ESP32'];
                $certifications = ['Certified Embedded Systems Specialist', 'IoT Developer Certificate'];
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
            if (str_contains($textLower, 'electronics') || str_contains($textLower, 'embedded') || str_contains($textLower, 'hardware') || str_contains($textLower, 'circuit') || str_contains($textLower, 'microcontroller') || str_contains($textLower, 'iot')) {
                $skills = ['Microcontrollers', 'Circuits', 'IoT', 'Embedded Systems'];
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

        $parsedData = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'skills' => $skills,
            'experience_years' => $experience,
            'education' => ['B.S. in Engineering, State University'],
            'summary' => 'Accomplished engineer skilled in ' . implode(', ', $skills) . ' with ' . $experience . ' years of hands-on experience.',
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
