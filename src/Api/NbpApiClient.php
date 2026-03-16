<?php

declare(strict_types=1);

namespace CurrencyRate\Api;

use RuntimeException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class NbpApiClient
{
    private const BASE_URL = 'https://api.nbp.pl/api/exchangerates/tables/A';
    private const TIMEOUT  = 10;

    private HttpClientInterface $client;

    public function __construct()
    {
        $this->client = HttpClient::create([
            'timeout' => self::TIMEOUT,
            'headers' => ['Accept' => 'application/json'],
        ]);
    }

    /**
     * Fetch rates for a date range
     *
     * @return array<int, array{date: string, rates: array<int, array{code: string, currency: string, mid: float}>}>
     * @throws RuntimeException on API or network error
     */
    public function fetchRatesForRange(string $startDate, string $endDate): array
    {
        $url = sprintf('%s/%s/%s/', self::BASE_URL, $startDate, $endDate);

        return $this->get($url);
    }

    /**
     * Fetch rates for a single date.
     *
     * @return array<int, array{date: string, rates: array}>
     * @throws RuntimeException
     */
    public function fetchRatesForDate(string $date): array
    {
        $url = sprintf('%s/%s/', self::BASE_URL, $date);

        return $this->get($url);
    }

    /**
     * Fetch today's rates (NBP returns last available if today is a holiday).
     *
     * @return array<int, array{date: string, rates: array}>
     * @throws RuntimeException
     */
    public function fetchTodayRates(): array
    {
        return $this->get(self::BASE_URL . '/today/');
    }

    /**
     * @return array<mixed>
     * @throws RuntimeException
     */
    private function get(string $url): array
    {
        try {
            $response = $this->client->request('GET', $url);
            $statusCode = $response->getStatusCode();

            if ($statusCode === 404) {
                // NBP returns 404 for weekends/holidays - not an error
                return [];
            }

            if ($statusCode !== 200) {
                throw new RuntimeException(
                    sprintf('NBP API returned HTTP %d for URL: %s', $statusCode, $url)
                );
            }

            $data = $response->toArray();

            if (!is_array($data)) {
                throw new RuntimeException('NBP API returned unexpected response format.');
            }

            return $data;
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException(
                sprintf('NBP API connection error: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }
}
