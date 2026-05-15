# Context

Act as an expert Laravel 13 developer. Your task is to implement a standardized, global REST API JSON response format across the entire application using the envelope pattern (JSend-inspired).

The target environment is a Laravel 13 application (slim skeleton). You must strictly follow the instructions below without modifying any existing business logic or routes outside the scope of this task. Use PHP 8.2+ syntax (typed properties, return types).

# Standard Response Structure

Every API response must adhere to this JSON structure:

- Success: `{ "success": true, "message": "...", "data": <mixed> }`
- Error: `{ "success": false, "message": "...", "errors": <mixed|null> }`
- Paginated: Includes a `"meta"` object containing `current_page`, `per_page`, `total_items`, and `total_pages`.

# Execution Steps

## Step 1: Create `ApiResponser` Trait

1. Create a new file at `app/Traits/ApiResponser.php`.
2. Define a trait `ApiResponser` with the following protected methods:
    - `successResponse(mixed $data, string $message = 'Success', int $code = 200): JsonResponse`
    - `errorResponse(string $message, mixed $errors = null, int $code = 400): JsonResponse`
    - `paginatedResponse($paginator, string $message = 'Success'): JsonResponse`
3. Implement the methods to return the Standard Response Structure defined above.

## Step 2: Bind Trait to Base Controller

1. Open `app/Http/Controllers/Controller.php`.
2. Import `App\Traits\ApiResponser`.
3. Use the trait inside the abstract `Controller` class.

## Step 3: Implement Global Exception Handler

1. Open `bootstrap/app.php`.
2. Modify the `withExceptions` closure to intercept errors specifically for API requests (`$request->is('api/*') || $request->expectsJson()`).
3. Handle the following exceptions and format them according to the Error Structure:
    - `Illuminate\Validation\ValidationException`: Return 422. Map `$e->errors()` to the `"errors"` key.
    - `Symfony\Component\HttpKernel\Exception\NotFoundHttpException` & `Illuminate\Database\Eloquent\ModelNotFoundException`: Return 404.
    - `Symfony\Component\HttpKernel\Exception\HttpException`: Return the exception's status code and message.
    - Fallback (500 Internal Server Error): Catch `Throwable`. If `config('app.debug')` is true, include `$e->getMessage()`, file, and line in the `"errors"` key. If false, set `"errors"` to null and message to a generic server error message.

# Constraints

- Do not remove any existing middleware or routing configurations in `bootstrap/app.php`.
- Ensure all required classes (`JsonResponse`, `Request`, `Throwable`, etc.) are properly imported at the top of the modified files.
- Run a syntax check on modified files before completing the task.
