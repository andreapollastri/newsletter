<?php

namespace App\Http\Controllers;

use L5Swagger\Http\Controllers\SwaggerController as BaseSwaggerController;

/**
 * Fixes OpenAPI spec URL generation: the package passes the JSON filename as a route
 * parameter, but the `docs` route has no placeholders, so Laravel appends a bogus
 * query string (`?api-docs.json`) and Swagger UI requests a broken URL.
 *
 * @see BaseSwaggerController::generateDocumentationFileURL()
 */
class L5SwaggerController extends BaseSwaggerController
{
    /**
     * The spec filename is read from config in {@see BaseSwaggerController::docs()}; the URL
     * for the {@code docs} route must not include the filename as a query parameter.
     */
    protected function generateDocumentationFileURL(string $documentation, array $config): string
    {
        $useAbsolutePath = config('l5-swagger.documentations.'.$documentation.'.paths.use_absolute_path', true);

        return route('l5-swagger.'.$documentation.'.docs', [], $useAbsolutePath);
    }
}
