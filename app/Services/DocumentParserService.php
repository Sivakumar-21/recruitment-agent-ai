<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

class DocumentParserService
{
    /**
     * Parse text from a document based on its extension.
     */
     public function parse(string $filePath): string
     {
         if (!file_exists($filePath)) {
             Log::error("DocumentParserService: File not found at '{$filePath}'");
             throw new Exception("File not found: {$filePath}");
         }

         $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
         $fileSize = filesize($filePath);
         Log::info("DocumentParserService: Initiating parsing for [file: " . basename($filePath) . ", extension: .{$extension}, size: {$fileSize} bytes]");

         $startTime = microtime(true);
         
         $text = match ($extension) {
             'pdf' => $this->parsePdf($filePath),
             'docx' => $this->parseDocx($filePath),
             default => throw new Exception("Unsupported file type: .{$extension}"),
         };

         $duration = round(microtime(true) - $startTime, 3);
         $charCount = strlen($text);
         $preview = mb_substr(trim(preg_replace('/\s+/', ' ', $text)), 0, 100);

         Log::info("DocumentParserService: Parsing completed in {$duration}s. Extracted {$charCount} characters. Preview: \"{$preview}...\"");

         return $text;
     }

    /**
     * Parse text from a PDF file.
     */
    protected function parsePdf(string $filePath): string
    {
        try {
            Log::debug("DocumentParserService: PDF path: '{$filePath}'. Instantiating Smalot PDF Parser...");
            $parser = new PdfParser();
            
            $memoryBefore = memory_get_usage();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
            $memoryUsed = round((memory_get_usage() - $memoryBefore) / 1024 / 1024, 2);
            
            Log::debug("DocumentParserService: Smalot PDF Parser successfully extracted text. Approximate memory change: {$memoryUsed} MB");
            return $text;
        } catch (Exception $e) {
            Log::error("DocumentParserService: Failed parsing PDF file: " . $e->getMessage(), [
                'file' => $filePath,
                'exception' => $e
            ]);
            throw new Exception("Failed parsing PDF file: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Parse text from a DOCX file.
     */
    protected function parseDocx(string $filePath): string
    {
        try {
            Log::debug("DocumentParserService: DOCX path: '{$filePath}'. Instantiating ZipArchive reader...");
            $zip = new ZipArchive();
            
            if ($zip->open($filePath) === true) {
                Log::debug("DocumentParserService: Successfully opened DOCX zip archive. Checking word/document.xml...");
                if (($index = $zip->locateName('word/document.xml')) !== false) {
                    Log::debug("DocumentParserService: word/document.xml found at index {$index}. Extracting contents...");
                    $data = $zip->getFromIndex($index);
                    $zip->close();
                    
                    // Replace paragraph, run break, tab, and br tags with newlines/spaces to maintain layout
                    $data = str_replace(
                         ['</w:p>', '</w:r>', '<w:tab/>', '<w:br/>'], 
                         ["\n", ' ', ' ', "\n"], 
                         $data
                    );
                    
                    $text = trim(html_entity_decode(strip_tags($data)));
                    Log::debug("DocumentParserService: XML tags stripped. Word document text successfully extracted.");
                    return $text;
                }
                
                $zip->close();
                Log::error("DocumentParserService: XML extraction failed. word/document.xml not found inside DOCX zip archive: '{$filePath}'");
                throw new Exception("Could not locate word/document.xml inside zip archive.");
            }
            
            Log::error("DocumentParserService: Could not open ZIP archive at '{$filePath}'. Error code: " . $zip->open($filePath));
            throw new Exception("Could not open ZIP archive.");
        } catch (Exception $e) {
            Log::error("DocumentParserService: Failed parsing DOCX file: " . $e->getMessage(), [
                'file' => $filePath,
                'exception' => $e
            ]);
            throw new Exception("Failed parsing DOCX file: " . $e->getMessage(), 0, $e);
        }
    }
}
