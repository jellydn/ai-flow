<?php

namespace App\Http\Controllers;

use App\Support\AiProviderRegistry;
use Illuminate\Http\JsonResponse;

class ProviderController extends Controller
{
    public function __construct(
        private AiProviderRegistry $registry,
    ) {}

    /**
     * Return the list of available AI providers with their display names and models.
     */
    public function index(): JsonResponse
    {
        return response()->json($this->registry->list());
    }
}
