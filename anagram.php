<?php

/**
 * Class AnagramEngine
 * Indexes a dictionary by sorted signatures and provides both exact and sub-anagram lookups.
 */
class AnagramEngine {
    private array $signatureMap = [];
    private array $rawDictionary = [];
    private int $wordCount = 0;

    /**
     * Helper to sort letters of a string alphabetically.
     */
    private function getSignature(string $word): string {
        $letters = str_split(strtolower($word));
        sort($letters);
        return implode('', $letters);
    }

    /**
     * Indexes the dictionary for both fast exact lookups and filtered sub-lookups.
     */
    public function loadDictionary(array $words): void {
        foreach ($words as $word) {
            $cleaned = trim(strtolower($word));
            // Skip empty entries, words with non-alphabet characters, or single letters (except 'a' and 'i')
            if (empty($cleaned) || !ctype_alpha($cleaned)) {
                continue;
            }
            if (strlen($cleaned) === 1 && $cleaned !== 'a' && $cleaned !== 'i') {
                continue;
            }

            // Save to raw dictionary for sub-anagram processing later
            $this->rawDictionary[] = $cleaned;

            // Save to signature map for instant exact anagram lookups
            $signature = $this->getSignature($cleaned);
            if (!isset($this->signatureMap[$signature])) {
                $this->signatureMap[$signature] = [];
            }
            if (!in_array($cleaned, $this->signatureMap[$signature])) {
                $this->signatureMap[$signature][] = $cleaned;
                $this->wordCount++;
            }
        }
        // De-duplicate the raw list to keep sub-anagram checks snappy
        $this->rawDictionary = array_unique($this->rawDictionary);
    }

    /**
     * Feature 1: Exact Anagrams - O(1) instantaneous lookup
     */
    public function getExactAnagrams(string $input): array {
        $signature = $this->getSignature($input);
        $matches = $this->signatureMap[$signature] ?? [];
        
        // Remove the exact input word from the anagram results if it's in there
        return array_values(array_diff($matches, [strtolower($input)]));
    }

    /**
     * Feature 2: Sub-Anagrams - Finds words you can make using a subset of the letters
     */
    public function getSubAnagrams(string $input): array {
        $input = strtolower($input);
        $inputChars = count_chars($input, 1); // Counts occurrences of each ASCII character
        $subAnagrams = [];

        foreach ($this->rawDictionary as $word) {
            // Optimization: Skip words that are longer than our input or match the exact input
            if (strlen($word) > strlen($input) || $word === $input) {
                continue;
            }

            $wordChars = count_chars($word, 1);
            $isValid = true;

            // Check if the input has enough of each letter to form this dictionary word
            foreach ($wordChars as $ascii => $count) {
                if (!isset($inputChars[$ascii]) || $inputChars[$ascii] < $count) {
                    $isValid = false;
                    break;
                }
            }

            if ($isValid) {
                $subAnagrams[] = $word;
            }
        }

        // Sort results by length descending, then alphabetically
        usort($subAnagrams, function($a, $b) {
            if (strlen($a) === strlen($b)) {
                return strcmp($a, $b);
            }
            return strlen($b) <=> strlen($a);
        });

        return $subAnagrams;
    }

    public function getWordCount(): int {
        return $this->wordCount;
    }
}

// --- CLI ENTRY POINT & DICTIONARY PIPELINE ---

if (!isset($argv[1])) {
    echo "====================================================\n";
    echo "❌ Error: Missing Argument\n";
    echo "Usage:   php anagram.php <letters>\n";
    echo "Example: php anagram.php slate\n";
    echo "====================================================\n";
    exit(1);
}

$inputWord = trim($argv[1]);

if (!ctype_alpha($inputWord)) {
    die("❌ Error: Please provide alphabetical characters only.\n");
}

// --- UNIVERSAL FULL-LENGTH DICTIONARY PIPELINE ---
$englishDictionary = [];
$remoteUrl = "https://raw.githubusercontent.com/dwyl/english-words/master/words_alpha.txt";
$dictPath = "/usr/share/dict/words";

echo "Loading comprehensive multi-length dictionary pool... ";

if (is_file($dictPath)) {
    // Local system file (usually contains ~100k+ words of all lengths)
    $englishDictionary = file($dictPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    echo "Done (Loaded via Local System).\n";
} else {
    // Download a massive raw master alpha dictionary (over 370k words) over HTTP
    $context = stream_context_create(["http" => ["timeout" => 15]]);
    $fileContent = @file_get_contents($remoteUrl, false, $context);

    if ($fileContent !== false) {
        $englishDictionary = explode("\n", str_replace("\r", "", $fileContent));
        echo "Done (Loaded via GitHub Alpha Master List).\n";
    } else {
        die("❌ Error: Could not fetch the multi-length dictionary over the network.\n");
    }
}

// --- ENGINE EXECUTION ---

$engine = new AnagramEngine();
$engine->loadDictionary($englishDictionary);

// Run exact lookup algorithm
$startTimeExact = microtime(true);
$exactMatches = $engine->getExactAnagrams($inputWord);
$timeExact = (microtime(true) - $startTimeExact) * 1000;

// Run subset breakdown algorithm
$startTimeSub = microtime(true);
$subMatches = $engine->getSubAnagrams($inputWord);
$timeSub = (microtime(true) - $startTimeSub) * 1000;

// --- OUTPUT PRESENTATION LAYER ---
echo "====================================================\n";
echo "              POWER ANAGRAM SOLVER                  \n";
echo "====================================================\n";
echo "Target Letters:    " . strtoupper($inputWord) . "\n";
echo "Database Size:     " . number_format($engine->getWordCount()) . " total terms loaded\n";
echo "----------------------------------------------------\n";

echo "🎯 EXACT ANAGRAMS (" . count($exactMatches) . " found in " . number_format($timeExact, 3) . "ms):\n";
if (!empty($exactMatches)) {
    echo "   -> " . implode(', ', array_map('strtoupper', $exactMatches)) . "\n";
} else {
    echo "   -> None found.\n";
}

echo "\n🧩 SUB-ANAGRAM WORDS (Hidden words sorted by length, " . count($subMatches) . " found in " . number_format($timeSub, 3) . "ms):\n";
if (!empty($subMatches)) {
    $currentLength = 0;
    foreach ($subMatches as $match) {
        if (strlen($match) !== $currentLength) {
            $currentLength = strlen($match);
            echo "\n   [$currentLength Letters]: ";
        }
        echo strtoupper($match) . " ";
    }
    echo "\n";
} else {
    echo "   -> None found.\n";
}
echo "====================================================\n";
