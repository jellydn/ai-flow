<?php

namespace App\Http\Controllers;

use App\Services\GitHubTrendingService;
use Illuminate\Http\JsonResponse;

class TrendingRepositoryController extends Controller
{
    public function __construct(
        private GitHubTrendingService $trending,
    ) {}

    /**
     * Top GitHub repositories for the current day (from github.com/trending?since=daily).
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->trending->dailyTopRepositories()]);
    }
}
