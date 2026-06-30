<?php

namespace App\Actions\ParentChild;

use App\Services\ParentChildContextService;
use Illuminate\Http\Request;

class ClearActiveChild
{
    public function __construct(private readonly ParentChildContextService $childContext)
    {
    }

    public function handle(Request $request): void
    {
        $this->childContext->clearActiveChild($request);
    }
}
