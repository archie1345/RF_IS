<?php

namespace App\Actions\ParentChild;

use App\Models\Athlete;
use App\Services\ParentChildContextService;
use Illuminate\Http\Request;

class SwitchActiveChild
{
    public function __construct(private readonly ParentChildContextService $childContext)
    {
    }

    public function handle(Request $request, string $athleteId): Athlete
    {
        return $this->childContext->setActiveChild($request, $athleteId);
    }
}
