<?php
/**
 * URL analyzer with multiple fetch strategies and content processing
 * Handles caching, error handling, and domain-specific rules
 */

namespace Inc;

use Inc\Logger;
use Inc\URLAnalyzer\URLAnalyzerBase;
use Inc\URLAnalyzer\URLAnalyzerException;
use Inc\URLAnalyzer\URLAnalyzerFetch;
use Inc\URLAnalyzer\URLAnalyzerProcess;
use Inc\URLAnalyzer\URLAnalyzerError;
use Inc\URLAnalyzer\URLAnalyzerUtils;

class URLAnalyzer extends URLAnalyzerBase
{
    /** @var URLAnalyzerFetch Content fetcher */
    private $fetch;
    
    /** @var URLAnalyzerProcess Content processor */
    private $process;
    
    /** @var URLAnalyzerError Error handler */
    private $error;
    
    /** @var URLAnalyzerUtils URL utilities */
    private $utils;

    /** Gets URL status info */
    public function checkStatus($url)
    {
        return $this->utils->checkStatus($url);
    }

    /** Sets up analyzer components */
    public function __construct()
    {
        parent::__construct();
        $this->fetch = new URLAnalyzerFetch();
        $this->process = new URLAnalyzerProcess();
        $this->error = new URLAnalyzerError();
        $this->utils = new URLAnalyzerUtils();
    }

    /**
     * Analyzes URL and extracts content
     * Uses cache if available, otherwise fetches and processes
     */
    public function analyze($url)
    {
        // Extract and validate hostname
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            $this->error->throwError(self::ERROR_INVALID_URL, '');
        }

        // Check if domain is in DMCA list FIRST (before any HTTP requests)
        foreach (DMCA_DOMAINS as $dmcaEntry) {
            if (is_array($dmcaEntry) && isset($dmcaEntry['host'])) {
                $dmcaHost = $dmcaEntry['host'];
                if (strpos($url, $dmcaHost) !== false) {
                    Logger::getInstance()->logUrl($url, 'DMCA_DOMAIN');
                    $customMessage = isset($dmcaEntry['message']) ? $dmcaEntry['message'] : '';
                    $this->error->throwError(self::ERROR_DMCA_DOMAIN, $customMessage);
                }
            }
        }

        // Reset activated rules for new analysis
        $this->activatedRules = [];

        // Try to get and process cached content first
        if ($this->cache->exists($url)) {
            $rawContent = $this->cache->get($url);
            // Process the raw content in real-time
            return $this->process->processContent($rawContent, parse_url($url, PHP_URL_HOST), $url);
        }

        // Check if domain is in blocked list
        if (in_array($host, BLOCKED_DOMAINS)) {
            Logger::getInstance()->logUrl($url, 'BLOCKED_DOMAIN');
            $this->error->throwError(self::ERROR_BLOCKED_DOMAIN, '');
        }

        // Check if domain has specific rules by looking for domain-specific configurations
        $hasCustomRules = $this->hasDomainRules($host);
        
        // Check HTTP status and handle any errors only if domain doesn't have custom rules
        if (!$hasCustomRules) {
            $redirectInfo = $this->utils->checkStatus($url);
            if ($redirectInfo['httpCode'] !== 200) {
                Logger::getInstance()->logUrl($url, 'INVALID_STATUS_CODE', "HTTP {$redirectInfo['httpCode']}");
                if ($redirectInfo['httpCode'] === 404) {
                    $this->error->throwError(self::ERROR_NOT_FOUND, '');
                } else {
                    $this->error->throwError(self::ERROR_HTTP_ERROR, (string)$redirectInfo['httpCode']);
                }
            }
        }

        try {
            // Get specific rules for this domain
            $domainRules = $this->getDomainRules($host);
            $fetchStrategy = isset($domainRules['fetchStrategies']) ? $domainRules['fetchStrategies'] : null;

            $hostBase = preg_replace('#^www\.#i', '', strtolower((string) $host));
            if ($hostBase === 'nytimes.com' && $this->nytimesSubscriptionLikePath($url)) {
                $fetchStrategy = 'fetchFromSelenium';
            }

            // Try domain-specific fetch strategy if available
            if ($fetchStrategy) {
                try {
                    $content = null;
                    switch ($fetchStrategy) {
                        case 'fetchContent':
                            $content = $this->fetch->fetchContent($url);
                            break;
                        case 'fetchFromWaybackMachine':
                            $content = $this->fetch->fetchFromWaybackMachine($url);
                            break;
                        case 'fetchFromSelenium':
                            $content = $this->fetch->fetchFromSelenium($url, isset($domainRules['browser']) ? $domainRules['browser'] : 'firefox');
                            break;
                    }

                    if (!empty($content)) {
                        $effectiveFetchStrategy = $fetchStrategy;
                        if (
                            $fetchStrategy === 'fetchFromWaybackMachine'
                            && $hostBase === 'nytimes.com'
                            && $this->nytimesWaybackBodyLooksEmpty($content)
                        ) {
                            try {
                                $browser = isset($domainRules['browser']) ? $domainRules['browser'] : 'firefox';
                                $live = $this->fetch->fetchFromSelenium($url, $browser);
                                if (
                                    !empty($live)
                                    && $this->nytimesVisibleTextLength($live) > $this->nytimesVisibleTextLength($content)
                                ) {
                                    $content = $live;
                                    $effectiveFetchStrategy = 'fetchFromSelenium';
                                }
                            } catch (\Exception $e) {
                                Logger::getInstance()->logUrl($url, 'NYT_SELENIUM_UPGRADE_ERROR', $e->getMessage());
                            }
                        }

                        $this->activatedRules[] = "fetchStrategy: $effectiveFetchStrategy";
                        // Cache the raw HTML content
                        $this->cache->set($url, $content);
                        // Process content in real-time
                        return $this->process->processContent($content, $host, $url);
                    }
                } catch (URLAnalyzerException $e) {
                    Logger::getInstance()->logUrl($url, strtoupper($fetchStrategy) . '_ERROR', $e->getMessage());
                    $fallbackErrors = [
                        self::ERROR_NOT_FOUND,
                        self::ERROR_HTTP_ERROR,
                        self::ERROR_CONTENT_ERROR,
                    ];
                    if (!in_array($e->getErrorType(), $fallbackErrors, true)) {
                        throw $e;
                    }
                    // Wayback and other primary strategies often miss marketing/checkout URLs; try generic chain below.
                } catch (\Exception $e) {
                    Logger::getInstance()->logUrl($url, strtoupper($fetchStrategy) . '_ERROR', $e->getMessage());
                }
            }

            // Try all fetch strategies in order if no domain-specific strategy worked
            $fetchStrategies = [
                ['method' => 'fetchContent', 'args' => [$url]],
                ['method' => 'fetchFromWaybackMachine', 'args' => [$url]],
                ['method' => 'fetchFromSelenium', 'args' => [$url, 'firefox']]
            ];

            if ($fetchStrategy) {
                $fetchStrategies = array_values(array_filter(
                    $fetchStrategies,
                    static function (array $strategy) use ($fetchStrategy) {
                        return $strategy['method'] !== $fetchStrategy;
                    }
                ));
            }

            // Track last error for better error reporting
            $lastError = null;
            foreach ($fetchStrategies as $strategy) {
                try {
                    $content = call_user_func_array([$this->fetch, $strategy['method']], $strategy['args']);
                    if (!empty($content)) {
                        $this->activatedRules[] = "fetchStrategy: {$strategy['method']}";
                        // Cache the raw HTML content
                        $this->cache->set($url, $content);
                        // Process content in real-time
                        return $this->process->processContent($content, $host, $url);
                    }
                } catch (\Exception $e) {
                    $lastError = $e;
                    error_log("{$strategy['method']}_ERROR: " . $e->getMessage());
                    continue;
                }
            }

            Logger::getInstance()->logUrl($url, 'GENERAL_FETCH_ERROR');
            if ($lastError) {
                $message = $lastError->getMessage();
                if (strpos($message, 'DNS') !== false) {
                    $this->error->throwError(self::ERROR_DNS_FAILURE, '');
                } elseif (strpos($message, 'CURL') !== false) {
                    $this->error->throwError(self::ERROR_CONNECTION_ERROR, '');
                } elseif (strpos($message, 'HTTP') !== false) {
                    $this->error->throwError(self::ERROR_HTTP_ERROR, '');
                } elseif (strpos($message, 'not found') !== false) {
                    $this->error->throwError(self::ERROR_NOT_FOUND, '');
                }
            }
            $this->error->throwError(self::ERROR_CONTENT_ERROR, '');
        } catch (URLAnalyzerException $e) {
            throw $e;
        } catch (\Exception $e) {
            $message = $e->getMessage();
            if (strpos($message, 'DNS') !== false) {
                $this->error->throwError(self::ERROR_DNS_FAILURE, '');
            } elseif (strpos($message, 'CURL') !== false) {
                $this->error->throwError(self::ERROR_CONNECTION_ERROR, '');
            } elseif (strpos($message, 'HTTP') !== false) {
                $this->error->throwError(self::ERROR_HTTP_ERROR, '');
            } elseif (strpos($message, 'not found') !== false) {
                $this->error->throwError(self::ERROR_NOT_FOUND, '');
            } else {
                $this->error->throwError(self::ERROR_GENERIC_ERROR, (string)$message);
            }
        }
    }

    /**
     * NYT subscription / checkout style URLs need a live browser; Wayback often returns an empty shell.
     */
    private function nytimesSubscriptionLikePath(string $url): bool
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $pathLower = strtolower($path);

        return str_contains($pathLower, 'subscription')
            || str_contains($pathLower, 'subscribe')
            || str_contains($pathLower, 'checkout')
            || str_contains($pathLower, 'payment')
            || str_contains($pathLower, 'billing')
            || str_contains($pathLower, 'account')
            || str_contains($pathLower, 'login')
            || str_contains($pathLower, 'register');
    }

    private function nytimesVisibleTextLength(string $html): int
    {
        $plain = strip_tags($html);
        $plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = preg_replace('/\s+/u', ' ', $plain);

        return strlen(trim((string) $plain));
    }

    /**
     * Heuristic: Wayback snapshot has lots of markup but almost no body text (SPA shell + footer only).
     */
    private function nytimesWaybackBodyLooksEmpty(string $html): bool
    {
        if (stripos($html, 'web.archive.org') === false) {
            return false;
        }

        $len = $this->nytimesVisibleTextLength($html);

        return strlen($html) > 15000 && $len < 1200;
    }
}
