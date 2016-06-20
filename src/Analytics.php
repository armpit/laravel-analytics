<?php

namespace Spatie\Analytics;

use Carbon\Carbon;
use DateTime;
use Google_Service_Analytics;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;

class Analytics
{
    use Macroable;

    /** @var \Spatie\Analytics\AnalyticsClient */
    protected $client;

    /** @var string */
    protected $viewId;

    /**
     * @param \Spatie\Analytics\AnalyticsClient $client
     * @param string                            $viewId
     */
    public function __construct(AnalyticsClient $client, string $viewId)
    {
        $this->client = $client;

        $this->viewId = $viewId;
    }

    /**
     * @param string $viewId
     *
     * @return $this
     */
    public function setViewId(string $viewId)
    {
        $this->viewId = $viewId;

        return $this;
    }

    public function fetchVisitorsAndPageViews(DateTime $startDate, DateTime $endDate): Collection
    {
        $response = $this->performQuery($startDate, $endDate, 'ga:users,ga:pageviews', ['dimensions' => 'ga:date']);

        return collect($response['rows'] ?? [])->map(function (array $dateRow) {
            return [
                'date' => Carbon::createFromFormat('Ymd', $dateRow[0]),
                'visitors' => (int) $dateRow[1],
                'pageViews' => (int) $dateRow[2],
            ];
        });
    }

    public function fetchMostVisitedPages(DateTime $startDate, DateTime $endDate, int $maxResults = 20): Collection
    {
        $response = $this->performQuery($startDate, $endDate, 'ga:pageviews',
            ['dimensions' => 'ga:pagePath', 'sort' => '-ga:pageviews', 'max-results' => $maxResults]);

        return collect($response['rows'] ?? [])
            ->map(function (array $pageRow) {
                return [
                    'url' => $pageRow[0],
                    'pageViews' => (int) $pageRow[1],
                ];
            });
    }

    public function fetchTopReferrers(DateTime $startDate, DateTime $endDate, int $maxResults = 20): Collection
    {
        $response = $this->performQuery($startDate, $endDate, 'ga:pageviews',
            ['dimensions' => 'ga:fullReferrer', 'sort' => '-ga:pageviews', 'max-results' => $maxResults]);

        return collect($response['rows'] ?? [])->map(function (array $pageRow) {
            return [
                'url' => $pageRow[0],
                'pageViews' => (int) $pageRow[1],
            ];
        });
    }

    public function fetchTopBrowsers(DateTime $startDate, DateTime $endDate, int $maxResults = 10): Collection
    {
        $response = $this->performQuery($startDate, $endDate, 'ga:sessions',
            ['dimensions' => 'ga:browser', 'sort' => '-ga:sessions']);

        $topBrowsers = collect($response['rows'] ?? [])->map(function (array $browserRow) {
            return [
                'browser' => $browserRow[0],
                'sessions' => (int) $browserRow[1],
            ];
        });

        if ($topBrowsers->count() <= $maxResults) {
            return $topBrowsers;
        }

        return $this->summarizeTopBrowsers($topBrowsers, $maxResults);
    }

    protected function summarizeTopBrowsers(Collection $topBrowsers, int $maxResults): Collection
    {
        return $topBrowsers
            ->take($maxResults - 1)
            ->push([
                'browser' => 'Others',
                'sessions' => $topBrowsers->splice($maxResults - 1)->sum('sessions')
            ]);
    }

    /**
     * Call the query method on the authenticated client.
     *
     * @param DateTime $startDate
     * @param DateTime $endDate
     * @param string   $metrics
     * @param array    $others
     *
     * @return array|null
     */
    public function performQuery(DateTime $startDate, DateTime $endDate, string $metrics, array $others = [])
    {
        return $this->client->performQuery(
            $this->viewId,
            $startDate,
            $endDate,
            $metrics,
            $others
        );
    }

    /**
     * Get the underlying Google_Service_Analytics object. You can use this
     * to basically call anything on the Google Analytics API.
     *
     * @return \Google_Service_Analytics
     */
    public function getAnalyticsService(): Google_Service_Analytics
    {
        return $this->client->getAnalyticsService();
    }
}
