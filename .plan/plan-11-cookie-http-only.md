# Context

I am running a Laravel 13 backend serving as a REST API for two front-end clients: a React web application and a Flutter mobile application. Currently, the authentication uses Laravel Sanctum, but it strictly returns a plain text API token for all clients.

# Objective

Refactor the authentication flow to implement a Hybrid Authentication system using Laravel Sanctum.

- For the React web app: Use Sanctum's built-in SPA Authentication (HTTP-Only cookies) to mitigate XSS vulnerabilities.
- For the Flutter mobile app: Continue using API Bearer Tokens, as managing HTTP-only cookies in mobile environments is unnecessary and prone to synchronization issues.

# Tasks

1. **Configuration Updates:**
    - Update `config/cors.php` to allow credentials (`supports_credentials => true`) and properly set allowed origins.
    - Update `config/sanctum.php` to define the stateful domains for the React app.
    - Instruct me on what variables to add to my `.env` file.

2. **Middleware & Routing:**
    - Configure the necessary middleware in Laravel 13 (via `bootstrap/app.php` or routing files) to support Sanctum's stateful cookie authentication for the API routes.
    - Ensure the `sanctum/csrf-cookie` route is accessible.

3. **Refactor AuthController:**
    - Modify the `login` method to distinguish between web and mobile requests. Use a custom header (e.g., `X-Client-Type: web` vs `X-Client-Type: mobile`) or check the `Accept` / user-agent headers.
    - **If Web:** Authenticate the user, regenerate the session to prevent session fixation, and return a simple `204 No Content` or user data (the HTTP-Only cookie will be set automatically by Laravel's session). Do NOT return a token in the JSON body.
    - **If Mobile:** Authenticate the user, generate a Sanctum `$user->createToken()`, and return the plain text token in the JSON response.
    - Modify the `logout` method to handle both scenarios (invalidate session for web, revoke current token for mobile).

4. **Client-Side Instructions:**
    - Provide a brief, bulleted checklist of how the React client (using Axios) should handle the login (e.g., hitting the CSRF endpoint first, setting `withCredentials: true`).
    - Provide a brief checklist for the Flutter client (e.g., sending the `X-Client-Type: mobile` header).

5. **Route Protection (Middleware):**
    - Apply the `auth:sanctum` middleware in `routes/api.php` to protect a group of routes (e.g., create a protected `/api/user` endpoint to return the authenticated user's data).
    - Ensure that this single `auth:sanctum` middleware successfully authenticates BOTH the stateful cookie requests (from the React web app) and the Bearer token requests (from the Flutter mobile app) seamlessly without requiring duplicate route definitions.
    - Handle the `AuthenticationException` so that unauthenticated requests return a standard JSON `401 Unauthorized` response instead of redirecting to a login page, specifically since this is an API.

# Rules & Constraints

- Write clean, strongly-typed PHP code (PHP 8.2+).
- Use proper Validation using Laravel Form Requests if necessary, or inline validation.
- Do not break existing database schemas; assume the standard Laravel `users` table.
