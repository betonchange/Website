<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Configuration
$sheetUrl = 'https://docs.google.com/spreadsheets/d/123zFQMukWs8YwSkUMUinicJPW4kEbm-fgLFnv0YdMx8/export?format=csv&gid=0';
$cacheFile = 'quotes_cache.json';
$cacheTime = 300; // 5 minutes in seconds
$maxCacheAge = 3600; // 1 hour maximum age

// Check if we should force refresh (for your "refresh database" button)
$forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === 'true';

// Check cache validity
$useCache = false;
if (!$forceRefresh && file_exists($cacheFile)) {
    $cacheAge = time() - filemtime($cacheFile);
    if ($cacheAge < $cacheTime) {
        $useCache = true;
    }
}

// Return cached data if valid
if ($useCache) {
    $cachedData = file_get_contents($cacheFile);
    if ($cachedData !== false) {
        $data = json_decode($cachedData, true);
        if ($data && isset($data['quotes'])) {
            // Add cache info for debugging
            $data['cached'] = true;
            $data['cache_age'] = time() - filemtime($cacheFile);
            echo json_encode($data);
            exit;
        }
    }
}

// Fetch fresh data from Google Sheets
$context = stream_context_create([
    'http' => [
        'timeout' => 10, // 10 second timeout
        'user_agent' => 'QuotesApp/1.0'
    ]
]);

$csvData = @file_get_contents($sheetUrl, false, $context);

if ($csvData === false) {
    // If fresh fetch fails, try to return stale cache as fallback
    if (file_exists($cacheFile)) {
        $cacheAge = time() - filemtime($cacheFile);
        if ($cacheAge < $maxCacheAge) { // Use stale cache if less than 1 hour old
            $cachedData = file_get_contents($cacheFile);
            if ($cachedData !== false) {
                $data = json_decode($cachedData, true);
                if ($data && isset($data['quotes'])) {
                    $data['cached'] = true;
                    $data['stale'] = true;
                    $data['cache_age'] = $cacheAge;
                    echo json_encode($data);
                    exit;
                }
            }
        }
    }
    
    echo json_encode([
        'error' => 'Failed to fetch data from Google Sheets',
        'quotes' => []
    ]);
    exit;
}

// Parse CSV data
function parseCSV($csvData) {
    $lines = array_map('str_getcsv', explode("\n", trim($csvData)));
    
    if (empty($lines)) {
        return [];
    }
    
    $headers = array_map(function($header) {
        return strtolower(trim($header));
    }, $lines[0]);
    
    $quotes = [];
    
    for ($i = 1; $i < count($lines); $i++) {
        $row = $lines[$i];
        
        // Skip empty rows
        if (empty($row) || (count($row) === 1 && empty(trim($row[0])))) {
            continue;
        }
        
        // Ensure row has enough columns
        while (count($row) < count($headers)) {
            $row[] = '';
        }
        
        $quote = [];
        foreach ($headers as $index => $header) {
            $quote[$header] = isset($row[$index]) ? trim($row[$index]) : '';
        }
        
        // Only include quotes with text
        if (!empty($quote['text'])) {
            $quotes[] = $quote;
        }
    }
    
    return $quotes;
}

try {
    $quotes = parseCSV($csvData);
    
    $response = [
        'quotes' => $quotes,
        'count' => count($quotes),
        'timestamp' => date('c'),
        'cached' => false
    ];
    
    // Cache the successful response
    if (!empty($quotes)) {
        @file_put_contents($cacheFile, json_encode($response));
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // If parsing fails, try to return stale cache
    if (file_exists($cacheFile)) {
        $cacheAge = time() - filemtime($cacheFile);
        if ($cacheAge < $maxCacheAge) {
            $cachedData = file_get_contents($cacheFile);
            if ($cachedData !== false) {
                $data = json_decode($cachedData, true);
                if ($data && isset($data['quotes'])) {
                    $data['cached'] = true;
                    $data['stale'] = true;
                    $data['error'] = 'Parse error, using cached data';
                    echo json_encode($data);
                    exit;
                }
            }
        }
    }
    
    echo json_encode([
        'error' => 'Failed to parse CSV data: ' . $e->getMessage(),
        'quotes' => []
    ]);
}
?>
