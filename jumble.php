<?php

/**
 * Class UnjumbleEngine
 * Indexes a dictionary by sorted-letter signatures for instantaneous O(1) unjumbling.
 */
class UnjumbleEngine {
    private array $signatureMap = [];
    private int $wordCount = 0;

    /**
     * Generates the canonical sorting key for any string.
     * e.g., "baker" -> "abekr"
     */
    private function getSignature(string $word): string {
        $letters = str_split(strtolower($word));
        sort($letters);
        return implode('', $letters);
    }

    /**
     * Consumes raw dictionary words, sanitizes them, and builds the signature map.
     */
    public function loadDictionary(array $words): void {
        foreach ($words as $word) {
            $cleaned = trim(strtolower($word));
            
            // Skip empty items or words containing invalid characters
            if (empty($cleaned) || !ctype_alpha($cleaned)) {
                continue;
            }

            $signature = $this->getSignature($cleaned);

            // Append the real word to its signature bucket (avoiding duplicates)
            if (!isset($this->signatureMap[$signature])) {
                $this->signatureMap[$signature] = [];
            }
            if (!in_array($cleaned, $this->signatureMap[$signature])) {
                $this->signatureMap[$signature][] = $cleaned;
                $this->wordCount++;
            }
        }
    }

    /**
     * Unjumbles a word instantly using the signature map.
     * Returns an array of valid English matches.
     */
    public function unjumble(string $jumbledWord): array {
        $signature = $this->getSignature($jumbledWord);
        return $this->signatureMap[$signature] ?? [];
    }

    public function getWordCount(): int {
        return $this->wordCount;
    }
    
    public function getUniqueSignatureCount(): int {
        return count($this->signatureMap);
    }
}

// --- SYSTEM INITIALIZATION & CLI ARGV ARCHITECTURE ---

// Ensure a jumbled word argument was passed
if (!isset($argv[1])) {
    echo "====================================================\n";
    echo "❌ Error: Missing Argument\n";
    echo "Usage:   php unjumble.php <jumbled_word>\n";
    echo "Example: php unjumble.php tseal\n";
    echo "====================================================\n";
    exit(1);
}

$inputWord = trim($argv[1]);

if (!ctype_alpha($inputWord)) {
    die("❌ Error: The jumbled input string must only contain alphabetical characters.\n");
}

// --- FALLBACK DICTIONARY PIPELINE ---
$englishDictionary = [];
$dictPath = "/usr/share/dict/words";
$remoteUrl = "https://raw.githubusercontent.com/tabatkins/wordle-list/main/words";

echo "Loading dictionary profiles... ";

if (is_file($dictPath)) {
    // Priority 1: Local system dictionary
    $englishDictionary = file($dictPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    echo "Loaded via Local System File.\n";
} else {
    // Priority 2: Fetch remote Wordle list over HTTP stream wrapper
    $context = stream_context_create(["http" => ["timeout" => 3]]);
    $fileContent = @file_get_contents($remoteUrl, false, $context);

    if ($fileContent !== false) {
        $englishDictionary = explode("\n", str_replace("\r", "", $fileContent));
        echo "Loaded via GitHub Stream API.\n";
    } else {
        // Priority 3: Offline code fallback
        $englishDictionary = [
            "apple", "crane", "slate", "stale", "teals", "react", "cater", "crate", 
            "train", "house", "mouse", "plant", "smirk", "shave", "stare", "fresh"
        ];
        echo "Loaded via Script Fallback Array.\n";
    }
}

// --- PROCESS AND MATCH ---
$engine = new UnjumbleEngine();
$engine->loadDictionary($englishDictionary);

$startTime = microtime(true);
$matches = $engine->unjumble($inputWord);
$executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

// --- OUTPUT VIEW DISPLAY ---
echo "====================================================\n";
echo "            ANAGRAM UNJUMBLE ENGINE                 \n";
echo "====================================================\n";
echo "Indexed Database:  " . number_format($engine->getWordCount()) . " total words\n";
echo "Unique Signatures: " . number_format($engine->getUniqueSignatureCount()) . " hash buckets\n";
echo "Jumbled Input:     " . strtoupper($inputWord) . "\n";
echo "Lookup Efficiency: " . number_format($executionTime, 4) . " ms\n";
echo "----------------------------------------------------\n";

if (!empty($matches)) {
    echo "✅ MATCH(ES) FOUND:\n";
    foreach ($matches as $index => $match) {
        echo sprintf("   %d. %s\n", $index + 1, strtoupper($match));
    }
} else {
    echo "❌ No matching English dictionary words could be found.\n";
}
echo "====================================================\n";
