<?php

/**
 * Class WordleEngine
 * Handles the logic of picking a word, evaluating guesses, and filtering candidates dynamically.
 */
class WordleEngine {
    private int $size;
    private array $dictionary;
    private string $targetWord = '';

    public function __construct(array $dictionary, int $size) {
        $this->size = $size;
        // Filter dictionary to only $size letter lowercase words
        $this->dictionary = array_values(array_filter(array_map('strtolower', $dictionary), function($word) {
            return strlen($word) === $this->size && ctype_alpha($word);
        }));
        
        if (!empty($this->dictionary)) {
            $this->resetGame();
        }
    }

    public function getDictionaryCount(): int {
        return count($this->dictionary);
    }

    /**
     * Resets the game and picks a random secret word from the matching dictionary.
     */
    public function resetGame(): void {
        if (empty($this->dictionary)) {
            return;
        }
        $this->targetWord = $this->dictionary[array_rand($this->dictionary)];
    }

    /**
     * Set a specific target word for testing purposes.
     */
    public function setTargetWord(string $word): void {
        $this->targetWord = strtolower($word);
        $this->size = strlen($word);
    }

    public function getTargetWord(): string {
        return $this->targetWord;
    }

    /**
     * PART 1: The Evaluator Function
     * Compares the guess against the secret target word.
     */
    public function evaluateGuess(string $guess): array {
        $guess = strtolower($guess);
        $result = array_fill(0, $this->size, 'X');
        $targetCards = str_split($this->targetWord);
        $guessCards = str_split($guess);

        // First pass: Find exact matches (Green)
        for ($i = 0; $i < $this->size; $i++) {
            if ($guessCards[$i] === $targetCards[$i]) {
                $result[$i] = 'G';
                $targetCards[$i] = null; 
                $guessCards[$i] = null;
            }
        }

        // Second pass: Find partial matches (Yellow)
        for ($i = 0; $i < $this->size; $i++) {
            if ($guessCards[$i] === null) continue;

            $index = array_search($guessCards[$i], $targetCards);
            if ($index !== false) {
                $result[$i] = 'Y';
                $targetCards[$index] = null; 
            }
        }

        return $result;
    }

    /**
     * PART 2: Automated Simulator Engine
     */
    public function solve(): array {
        $possibleWords = $this->dictionary;
        $attempts = 0;
        $log = [];

        $regexTemplate = array_fill(0, $this->size, []);
        $exactMatches = array_fill(0, $this->size, null);
        $requiredLetters = []; 
        $forbiddenLetters = []; 

        while (!empty($possibleWords)) {
            $attempts++;
            $guess = $this->pickBestWord($possibleWords, $attempts == 1);
            
            if (count(array_unique(str_split($guess))) === 1) {
                $guess = $possibleWords[0];
            }

            $feedback = $this->evaluateGuess($guess);
            $log[] = ['guess' => $guess, 'feedback' => implode('', $feedback)];

            if ($guess === $this->targetWord) {
                return ['success' => true, 'attempts' => $attempts, 'log' => $log];
            }

            $possibleWords = $this->filterPool($possibleWords, $guess, $feedback, $regexTemplate, $exactMatches, $requiredLetters, $forbiddenLetters);
        }

        return ['success' => false, 'attempts' => $attempts, 'log' => $log];
    }

    /**
     * Helper to filter remaining candidates based on a guess and its structural feedback.
     */
    public function filterPool(array $possibleWords, string $guess, array $feedback, array &$regexTemplate, array &$exactMatches, array &$requiredLetters, array &$forbiddenLetters): array {
        $guessLetters = str_split($guess);
        $currentYellowGreens = [];

        foreach ($guessLetters as $i => $char) {
            if ($feedback[$i] === 'G' || $feedback[$i] === 'Y') {
                $currentYellowGreens[$char] = ($currentYellowGreens[$char] ?? 0) + 1;
            }
        }

        foreach ($guessLetters as $i => $char) {
            if ($feedback[$i] === 'G') {
                $exactMatches[$i] = $char;
            } elseif ($feedback[$i] === 'Y') {
                $regexTemplate[$i][] = $char;
            } elseif ($feedback[$i] === 'X') {
                if (isset($currentYellowGreens[$char])) {
                    $regexTemplate[$i][] = $char; 
                } else {
                    $forbiddenLetters[] = $char;
                }
            }
        }
        
        $requiredLetters = array_merge($requiredLetters, $currentYellowGreens);
        $forbiddenLetters = array_unique($forbiddenLetters);

        // Build the dynamic sizing regex pattern
        $pattern = '/^';
        for ($i = 0; $i < $this->size; $i++) {
            if ($exactMatches[$i] !== null) {
                $pattern .= $exactMatches[$i];
            } else {
                $excludes = array_unique(array_merge($forbiddenLetters, $regexTemplate[$i]));
                if (!empty($excludes)) {
                    $pattern .= '[^' . implode('', $excludes) . ']';
                } else {
                    $pattern .= '[a-z]';
                }
            }
        }
        $pattern .= '$/i';

        return array_values(array_filter($possibleWords, function($word) use ($pattern, $requiredLetters) {
            if (!preg_match($pattern, $word)) {
                return false;
            }
            foreach ($requiredLetters as $char => $count) {
                if (substr_count($word, $char) < $count) {
                    return false;
                }
            }
            return true;
        }));
    }

    /**
     * Pick optimal guessing patterns using frequency arrays
     */
    public function pickBestWord(array $words, bool $isFirstGuess): string {
        if ($isFirstGuess && $this->size === 5) {
            foreach (['slate', 'crane', 'react', 'raise'] as $ideal) {
                if (in_array($ideal, $words)) return $ideal;
            }
        }

        $frequencies = [];
        foreach ($words as $word) {
            foreach (array_unique(str_split($word)) as $char) {
                $frequencies[$char] = ($frequencies[$char] ?? 0) + 1;
            }
        }

        $bestWord = $words[0] ?? '';
        $maxScore = -1;

        foreach ($words as $word) {
            $score = 0;
            $uniqueChars = array_unique(str_split($word));
            foreach ($uniqueChars as $char) {
                $score += $frequencies[$char] ?? 0;
            }
            if ($score > $maxScore) {
                $maxScore = $score;
                $bestWord = $word;
            }
        }

        return $bestWord;
    }
}

// --- SYSTEM INITIALIZATION & CLI ARGV ARCHITECTURE ---

$targetArg = isset($argv[1]) ? trim($argv[1]) : null;
$size = 5; // System standard
$englishDictionary = [];
$mode = "interactive";

if ($targetArg) {
    $mode = "auto";
    $size = strlen($targetArg);
    if (!ctype_alpha($targetArg)) {
        die("Error: Target word parameter must only contain alphabet letters.\n");
    }
    
    // Mode Auto: Read from system dictionary files to accommodate custom sizes
    $dictPath = "/usr/share/dict/words";
    if (is_file($dictPath)) {
        $allWords = file($dictPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $englishDictionary = array_values(array_filter(array_map('strtolower', $allWords), function($word) use ($size) {
            return strlen($word) === $size && ctype_alpha($word);
        }));
    }
} else {
    // Mode Interactive: Attempt fetching via remote source file first
    $remoteUrl = "https://raw.githubusercontent.com/tabatkins/wordle-list/main/words";
    $context = stream_context_create(["http" => ["timeout" => 3]]);
    $fileContent = @file_get_contents($remoteUrl, false, $context);

    if ($fileContent !== false) {
        $allWords = explode("\n", str_replace("\r", "", $fileContent));
        $englishDictionary = array_values(array_filter(array_map('trim', array_map('strtolower', $allWords)), function($word) use ($size) {
            return strlen($word) === $size && ctype_alpha($word);
        }));
    } else {
        // Fallback option: local sample dictionary array
        $englishDictionary = [
            "apple", "crane", "slate", "react", "train", "house", "mouse", "plant",
            "bound", "sound", "pound", "round", "smirk", "shave", "stare", "fresh",
            "world", "robot", "coder", "logic", "array", "match", "regex", "bytes"
        ];
    }
}

$englishDictionary = array_values(array_unique($englishDictionary));

if (empty($englishDictionary)) {
    die("Error: Failed to populate a viable lookup array for target size structural calculations ($size).\n");
}

$engine = new WordleEngine($englishDictionary, $size);

// --- RUNTIME DISPATCHER ---

if ($mode === "auto") {
    // RUN SIMULATION ENGINE AUTOMATICALLY
    $engine->setTargetWord($targetArg);
    
    echo "===========================================\n";
    echo "        AUTOMATED WORD PROCESSING ENGINE   \n";
    echo "===========================================\n";
    echo "Target Size Locked: " . $size . " characters\n";
    echo "Dictionary Pool:    " . $engine->getDictionaryCount() . " valid terms\n\n";

    $result = $engine->solve();

    echo "Target Word: " . strtoupper($engine->getTargetWord()) . "\n";
    echo "Result:      " . ($result['success'] ? "SOLVED! ✅" : "FAILED! ❌") . "\n";
    echo "Attempts:    " . $result['attempts'] . "\n\n";
    echo "Guessing History:\n";
    echo "-------------------------------------------\n";
    echo sprintf("%-5s | %-8s | %-10s\n", "#", "Guess", "Feedback");
    echo "-------------------------------------------\n";
    foreach ($result['log'] as $index => $step) {
        echo sprintf("%-5d | %-8s | %-10s\n", $index + 1, strtoupper($step['guess']), $step['feedback']);
    }
    echo "-------------------------------------------\n";
} else {
    // RUN INTERACTIVE HELPER MODE
    echo "====================================================\n";
    echo "      INTERACTIVE WORDLE SOLVER ASSISTANT           \n";
    echo "====================================================\n";
    echo "Instructions:\n";
    echo "1. Type the suggested word into your game.\n";
    echo "2. Read the game responses and enter them here using:\n";
    echo "   G = Green (Correct slot)\n";
    echo "   Y = Yellow (Valid letter, wrong slot)\n";
    echo "   X = Grey (Incorrect letter)\n";
    echo "Example input format for a 5 letter turn: GXXYX\n";
    echo "====================================================\n\n";

    $possibleWords = $englishDictionary;
    $turn = 0;
    
    $regexTemplate = array_fill(0, $size, []);
    $exactMatches = array_fill(0, $size, null);
    $requiredLetters = []; 
    $forbiddenLetters = []; 

    while (!empty($possibleWords)) {
        $turn++;
        echo "Pool Analysis: " . count($possibleWords) . " words remaining.\n";
        
        $suggestedGuess = $engine->pickBestWord($possibleWords, $turn === 1);
        echo "👉 Recommended Guess for Turn #$turn: " . strtoupper($suggestedGuess) . "\n";
        
        // Get user input loop
        while (true) {
            echo "Enter response string (Length: $size using G/Y/X): ";
            $input = strtoupper(trim(fgets(STDIN)));
            
            if (strlen($input) !== $size) {
                echo "⚠️ Invalid response layout. Must be exactly $size letters long.\n";
                continue;
            }
            if (!preg_match('/^[GYX]{'.$size.'}$/', $input)) {
                echo "⚠️ Input restricted to flags: G, Y, and X.\n";
                continue;
            }
            break;
        }

        // Winning Condition match
        if ($input === str_repeat('G', $size)) {
            echo "\n🎉 Congratulations! We tracked the word down in $turn attempts!\n";
            exit;
        }

        // Apply feedback logic rules
        $feedback = str_split($input);
        $possibleWords = $engine->filterPool($possibleWords, $suggestedGuess, $feedback, $regexTemplate, $exactMatches, $requiredLetters, $forbiddenLetters);
        
        // Remove the guessed word from the future selections
        $possibleWords = array_values(array_diff($possibleWords, [$suggestedGuess]));
        
        echo "----------------------------------------------------\n";
    }

    echo "❌ System Exhausted: No words matching that sequence pattern exist in the active list.\n";
}
