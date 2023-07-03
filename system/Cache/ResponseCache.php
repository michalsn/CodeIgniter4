<?php

/**
 * This file is part of CodeIgniter 4 framework.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace CodeIgniter\Cache;

use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Cache as CacheConfig;
use Exception;

/**
 * Web Page Caching
 */
class ResponseCache
{
    /**
     * Whether to take the URL query string into consideration when generating
     * output cache files. Valid options are:
     *
     *    false      = Disabled
     *    true       = Enabled, take all query parameters into account.
     *                 Please be aware that this may result in numerous cache
     *                 files generated for the same page over and over again.
     *    array('q') = Enabled, but only take into account the specified list
     *                 of query parameters.
     *
     * @var bool|string[]
     */
    protected $cacheQueryString = false;

    protected CacheInterface $cache;

    public function __construct(CacheConfig $config, CacheInterface $cache)
    {
        $this->cacheQueryString = $config->cacheQueryString;
        $this->cache            = $cache;
    }

    /**
     * Generates the cache key to use from the current request.
     *
     * @param CLIRequest|IncomingRequest $request
     *
     * @internal for testing purposes only
     */
    public function generateCacheKey($request): string
    {
        if ($request instanceof CLIRequest) {
            return md5($request->getPath());
        }

        $uri = clone $request->getUri();

        $query = $this->cacheQueryString
            ? $uri->getQuery(is_array($this->cacheQueryString) ? ['only' => $this->cacheQueryString] : [])
            : '';

        return md5($uri->setFragment('')->setQuery($query));
    }

    /**
     * Caches the full response from the current request.
     *
     * @param CLIRequest|IncomingRequest $request
     *
     * @params int $ttl time to live in seconds.
     */
    public function make($request, ResponseInterface $response, int $ttl): bool
    {
        $headers = [];

        foreach ($response->headers() as $header) {
            $headers[$header->getName()] = $header->getValueLine();
        }

        return $this->cache->save(
            $this->generateCacheKey($request),
            serialize(['headers' => $headers, 'output' => $response->getBody()]),
            $ttl
        );
    }

    /**
     * Gets the cached response for the request.
     *
     * @param CLIRequest|IncomingRequest $request
     */
    public function getCachedResponse($request, ResponseInterface $response): ?ResponseInterface
    {
        if ($cachedResponse = $this->cache->get($this->generateCacheKey($request))) {
            $cachedResponse = unserialize($cachedResponse);

            if (
                ! is_array($cachedResponse)
                || ! isset($cachedResponse['output'])
                || ! isset($cachedResponse['headers'])
            ) {
                throw new Exception('Error unserializing page cache');
            }

            $headers = $cachedResponse['headers'];
            $output  = $cachedResponse['output'];

            // Clear all default headers
            foreach (array_keys($response->headers()) as $key) {
                $response->removeHeader($key);
            }

            // Set cached headers
            foreach ($headers as $name => $value) {
                $response->setHeader($name, $value);
            }

            $response->setBody($output);

            return $response;
        }

        return null;
    }
}
